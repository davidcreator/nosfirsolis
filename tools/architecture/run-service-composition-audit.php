<?php

declare(strict_types=1);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__, 2));
}

final class ServiceCompositionAudit
{
    private int $passes = 0;
    private array $warnings = [];
    private array $failures = [];

    public function run(): int
    {
        $this->checkCompositionContracts();
        $this->checkAggregatorSizeBudgets();
        $this->checkTraitSizeBudgets();
        $this->checkControllerInheritanceByArea();
        $this->checkModelInheritanceByArea();
        $this->checkRegistryServiceInstantiationInControllers();
        $this->checkServiceInstantiationInControllers();
        $this->checkModelInstantiationInControllers();
        $this->checkCrossAreaModelLoaderUsageInControllers();
        $this->checkDirectDatabaseAccessInControllers();
        $this->checkServerSuperglobalInControllers();
        $this->checkServerSuperglobalInModels();
        $this->checkServerSuperglobalInLibraries();
        $this->checkInputSuperglobalsInCoreLayers();
        $this->checkEnvironmentPrimitiveUsageInCoreLayers();
        $this->checkTemporalPrimitiveUsageInControllers();
        $this->checkStrtotimeUsageInControllers();
        $this->checkDateUsageInControllers();
        $this->checkDateTimeImmutableUsageInControllers();
        $this->checkDateTimeImmutableUsageInModelsAndLibraries();
        $this->checkDateUsageInModelsAndLibraries();
        $this->checkTimeAndMicrotimeUsageInModelsAndLibraries();
        $this->checkStrtotimeUsageInModelsAndLibraries();
        $this->checkTemporalPrimitiveUsageInEngine();
        $this->checkDirectOutputPrimitivesInControllersAndModels();
        $this->checkControllerDependencyOutsideControllerLayer();
        $this->checkCrossAreaNamespaceDependencies();
        $this->checkSystemLibraryAreaIsolation();
        $this->checkNetworkPrimitiveUsageOutsideLibrary();
        $this->checkFilesystemMutationInControllers();
        $this->checkFilesystemMutationInModelsOutsideInstaller();
        $this->checkRuntimeDdlOutsideMigrations();
        $this->checkRawDriverInstantiationOutsideInstaller();
        $this->printSummary();

        return empty($this->failures) ? 0 : 1;
    }

    private function checkCompositionContracts(): void
    {
        foreach ($this->contracts() as $contract) {
            $ownerFile = (string) $contract['owner_file'];
            $ownerName = (string) $contract['owner_name'];
            $ownerKind = (string) $contract['owner_kind'];
            $ownerNamespace = (string) $contract['owner_namespace'];
            $traits = (array) $contract['traits'];

            $ownerPath = $this->path($ownerFile);
            if (!is_file($ownerPath)) {
                $this->fail('Arquivo de contrato estrutural ausente.', $ownerFile);
                continue;
            }

            $ownerContent = $this->read($ownerPath);
            if (!$this->hasNamespace($ownerContent, $ownerNamespace)) {
                $this->fail('Namespace do agregador divergente.', $ownerFile . ' expected=' . $ownerNamespace);
                continue;
            }

            if (!$this->declaresSymbol($ownerContent, $ownerKind, $ownerName)) {
                $this->fail('Agregador sem declaracao esperada.', $ownerFile . ' expected=' . $ownerKind . ' ' . $ownerName);
                continue;
            }

            $missingUses = [];
            foreach ($traits as $trait) {
                $traitName = (string) ($trait['name'] ?? '');
                if ($traitName === '') {
                    continue;
                }

                if (!$this->usesTrait($ownerContent, $traitName)) {
                    $missingUses[] = $traitName;
                }
            }

            if ($missingUses !== []) {
                $this->fail(
                    'Agregador sem uso completo de traits esperados.',
                    $ownerFile . ' missing=' . implode(',', $missingUses)
                );
                continue;
            }

            foreach ($traits as $trait) {
                $traitFile = (string) ($trait['file'] ?? '');
                $traitName = (string) ($trait['name'] ?? '');
                $traitNamespace = (string) ($trait['namespace'] ?? '');
                if ($traitFile === '' || $traitName === '' || $traitNamespace === '') {
                    continue;
                }

                $traitPath = $this->path($traitFile);
                if (!is_file($traitPath)) {
                    $this->fail('Trait contratada ausente.', $traitFile);
                    continue;
                }

                $traitContent = $this->read($traitPath);
                if (!$this->hasNamespace($traitContent, $traitNamespace)) {
                    $this->fail('Namespace de trait divergente.', $traitFile . ' expected=' . $traitNamespace);
                    continue;
                }

                if (!$this->declaresSymbol($traitContent, 'trait', $traitName)) {
                    $this->fail('Arquivo de trait sem declaracao esperada.', $traitFile . ' expected=trait ' . $traitName);
                }
            }

            $this->pass('Contrato estrutural valido: ' . $ownerFile);
        }
    }

    private function checkAggregatorSizeBudgets(): void
    {
        $warnBudget = 320;
        $criticalBudget = 520;

        $warnings = [];
        $failures = [];

        foreach ($this->contracts() as $contract) {
            $ownerFile = (string) $contract['owner_file'];
            $ownerPath = $this->path($ownerFile);
            if (!is_file($ownerPath)) {
                continue;
            }

            $lines = $this->lineCount($ownerPath);
            if ($lines >= $criticalBudget) {
                $failures[] = $ownerFile . ' (' . $lines . ' lines)';
                continue;
            }

            if ($lines >= $warnBudget) {
                $warnings[] = $ownerFile . ' (' . $lines . ' lines)';
            }
        }

        if ($failures !== []) {
            $this->fail('Agregadores acima do limite critico de tamanho.', implode(', ', $failures));
            return;
        }

        if ($warnings !== []) {
            $this->warn('Agregadores acima do limite de alerta de tamanho.', implode(', ', $warnings));
            return;
        }

        $this->pass('Agregadores dentro do limite de tamanho alvo.');
    }

    private function checkTraitSizeBudgets(): void
    {
        $warnBudget = 420;
        $criticalBudget = 700;

        $warnings = [];
        $failures = [];
        $seen = [];

        foreach ($this->contracts() as $contract) {
            foreach ((array) $contract['traits'] as $trait) {
                $traitFile = (string) ($trait['file'] ?? '');
                if ($traitFile === '' || isset($seen[$traitFile])) {
                    continue;
                }
                $seen[$traitFile] = true;

                $traitPath = $this->path($traitFile);
                if (!is_file($traitPath)) {
                    continue;
                }

                $lines = $this->lineCount($traitPath);
                if ($lines >= $criticalBudget) {
                    $failures[] = $traitFile . ' (' . $lines . ' lines)';
                    continue;
                }

                if ($lines >= $warnBudget) {
                    $warnings[] = $traitFile . ' (' . $lines . ' lines)';
                }
            }
        }

        if ($failures !== []) {
            $this->fail('Traits acima do limite critico de tamanho.', implode(', ', $failures));
            return;
        }

        if ($warnings !== []) {
            $this->warn('Traits acima do limite de alerta de tamanho.', implode(', ', $warnings));
            return;
        }

        $this->pass('Traits contratuais dentro do limite de tamanho alvo.');
    }

    private function checkControllerInheritanceByArea(): void
    {
        $issues = [];

        $adminControllerDir = $this->path('admin/Controller');
        foreach ($this->phpFiles([$adminControllerDir]) as $file) {
            $content = $this->read($file);
            if ($this->declaresTrait($content) && !$this->declaresClass($content)) {
                continue;
            }

            [$className, $parentName] = $this->classAndParent($content);
            if ($className === null) {
                continue;
            }

            if ($className === 'BaseController') {
                if ($parentName !== 'Controller') {
                    $issues[] = $this->relative($file) . ' expected=Controller actual=' . ($parentName ?? '(none)');
                }
                continue;
            }

            if ($parentName !== 'BaseController') {
                $issues[] = $this->relative($file) . ' expected=BaseController actual=' . ($parentName ?? '(none)');
            }
        }

        $clientControllerDir = $this->path('client/Controller');
        foreach ($this->phpFiles([$clientControllerDir]) as $file) {
            $content = $this->read($file);
            if ($this->declaresTrait($content) && !$this->declaresClass($content)) {
                continue;
            }

            [$className, $parentName] = $this->classAndParent($content);
            if ($className === null) {
                continue;
            }

            if ($className === 'BaseController') {
                if ($parentName !== 'Controller') {
                    $issues[] = $this->relative($file) . ' expected=Controller actual=' . ($parentName ?? '(none)');
                }
                continue;
            }

            if ($parentName !== 'BaseController') {
                $issues[] = $this->relative($file) . ' expected=BaseController actual=' . ($parentName ?? '(none)');
            }
        }

        $installControllerDir = $this->path('install/Controller');
        foreach ($this->phpFiles([$installControllerDir]) as $file) {
            $content = $this->read($file);
            if ($this->declaresTrait($content) && !$this->declaresClass($content)) {
                continue;
            }

            [, $parentName] = $this->classAndParent($content);
            if ($parentName === null) {
                $issues[] = $this->relative($file) . ' expected=Controller actual=(none)';
                continue;
            }

            if ($parentName !== 'Controller' && $parentName !== 'BaseController') {
                $issues[] = $this->relative($file) . ' expected=Controller|BaseController actual=' . $parentName;
            }
        }

        if ($issues === []) {
            $this->pass('Heranca de controllers por area aderente (admin/client via BaseController; install via Controller).');
            return;
        }

        $this->fail(
            'Heranca de controllers fora do padrao arquitetural por area.',
            implode(' | ', $issues)
        );
    }

    private function checkModelInheritanceByArea(): void
    {
        $issues = [];
        $areas = ['admin', 'client', 'install'];

        foreach ($areas as $area) {
            $modelDir = $this->path($area . '/Model');
            foreach ($this->phpFiles([$modelDir]) as $file) {
                $content = $this->read($file);
                if ($this->declaresTrait($content) && !$this->declaresClass($content)) {
                    continue;
                }

                [$className, $parentName] = $this->classAndParent($content);
                if ($className === null || !str_ends_with($className, 'Model')) {
                    continue;
                }

                if ($className === 'AbstractCrudModel') {
                    if ($parentName !== 'Model') {
                        $issues[] = $this->relative($file) . ' expected=Model actual=' . ($parentName ?? '(none)');
                    }
                    continue;
                }

                if ($area === 'admin') {
                    if ($parentName !== 'Model' && $parentName !== 'AbstractCrudModel') {
                        $issues[] = $this->relative($file) . ' expected=Model|AbstractCrudModel actual=' . ($parentName ?? '(none)');
                    }
                    continue;
                }

                if ($parentName !== 'Model') {
                    $issues[] = $this->relative($file) . ' expected=Model actual=' . ($parentName ?? '(none)');
                }
            }
        }

        if ($issues === []) {
            $this->pass('Heranca de models por area aderente (Model/AbstractCrudModel conforme dominio).');
            return;
        }

        $this->fail(
            'Heranca de models fora do padrao arquitetural por area.',
            implode(' | ', $issues)
        );
    }

    private function checkRegistryServiceInstantiationInControllers(): void
    {
        $allowed = [
            'admin/Controller/BaseController.php' => true,
            'client/Controller/BaseController.php' => true,
        ];

        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $issues = [];
        $pattern = '/new\s+[A-Za-z0-9_\\\\]+Service\s*\(\s*\$this->registry\s*\)/';

        foreach ($this->phpFiles($directories) as $file) {
            $relative = $this->relative($file);
            if (isset($allowed[$relative])) {
                continue;
            }

            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem instanciacao direta de services com Registry fora do BaseController.');
            return;
        }

        $this->fail(
            'Instanciacao direta de services com Registry identificada fora do BaseController.',
            implode(' | ', $issues)
        );
    }

    private function checkServiceInstantiationInControllers(): void
    {
        $allowed = [
            'admin/Controller/BaseController.php' => true,
            'client/Controller/BaseController.php' => true,
        ];

        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $issues = [];
        $pattern = '/new\s+[A-Za-z0-9_\\\\]+Service\s*\(/';

        foreach ($this->phpFiles($directories) as $file) {
            $relative = $this->relative($file);
            if (isset($allowed[$relative])) {
                continue;
            }

            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem instanciacao direta de services fora do BaseController.');
            return;
        }

        $this->fail(
            'Instanciacao direta de services identificada fora do BaseController.',
            implode(' | ', $issues)
        );
    }

    private function checkModelInstantiationInControllers(): void
    {
        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $issues = [];
        $pattern = '/new\s+[A-Za-z0-9_\\\\]+Model\s*\(/';

        foreach ($this->phpFiles($directories) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem instanciacao direta de models (uso via Loader/Registry).');
            return;
        }

        $this->fail(
            'Instanciacao direta de models detectada em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkCrossAreaModelLoaderUsageInControllers(): void
    {
        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $issues = [];
        $pattern = '/->model\(\s*[\'"][a-zA-Z0-9_\-]+[\'"]\s*,\s*[\'"](admin|client|install)[\'"]\s*\)/i';

        foreach ($this->phpFiles($directories) as $file) {
            $relative = $this->relative($file);
            $currentArea = $this->controllerAreaFromPath($relative);
            if ($currentArea === null) {
                continue;
            }

            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line, $matches) !== 1) {
                    continue;
                }

                $targetArea = strtolower(trim((string) ($matches[1] ?? '')));
                if ($targetArea === '' || $targetArea === $currentArea) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1)
                    . ' current=' . $currentArea
                    . ' target=' . $targetArea;
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem carga cross-area de models via Loader.');
            return;
        }

        $this->fail(
            'Carga cross-area de model detectada em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkDirectDatabaseAccessInControllers(): void
    {
        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $issues = [];
        $pattern = '/\$this->db->/';

        foreach ($this->phpFiles($directories) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem acesso direto ao Database (uso via Model/Service).');
            return;
        }

        $this->fail(
            'Acesso direto ao Database detectado em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkServerSuperglobalInControllers(): void
    {
        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $issues = [];
        $pattern = '/\$_SERVER\b/';

        foreach ($this->phpFiles($directories) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem acesso direto a $_SERVER (uso via Request/Controller base).');
            return;
        }

        $this->fail(
            'Acesso direto a $_SERVER detectado em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkServerSuperglobalInModels(): void
    {
        $directories = [
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
        ];

        $issues = [];
        $pattern = '/\$_SERVER\b/';

        foreach ($this->phpFiles($directories) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Models sem acesso direto a $_SERVER (uso via Request/abstracoes).');
            return;
        }

        $this->fail(
            'Acesso direto a $_SERVER detectado em models.',
            implode(' | ', $issues)
        );
    }

    private function checkServerSuperglobalInLibraries(): void
    {
        $directories = [$this->path('system/Library')];
        $issues = [];
        $pattern = '/\$_SERVER\b/';

        foreach ($this->phpFiles($directories) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Libraries sem acesso direto a $_SERVER (uso via Request/abstracoes).');
            return;
        }

        $this->fail(
            'Acesso direto a $_SERVER detectado em libraries.',
            implode(' | ', $issues)
        );
    }

    private function checkInputSuperglobalsInCoreLayers(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        // $_SERVER is intentionally excluded because host/scheme/IP context may be infrastructure-dependent.
        $pattern = '/\$_(?:GET|POST|REQUEST|COOKIE|FILES|SESSION)\b/';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Camadas core sem acesso direto a superglobais de entrada sensiveis.');
            return;
        }

        $this->fail(
            'Acesso direto a superglobais de entrada sensiveis detectado em camadas core.',
            implode(' | ', $issues)
        );
    }

    private function checkEnvironmentPrimitiveUsageInCoreLayers(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        $patterns = [
            '/\$_ENV\b/',
            '/\bgetenv\s*\(/i',
            '/\bputenv\s*\(/i',
        ];

        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                $lineValue = (string) $line;
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $lineValue) !== 1) {
                        continue;
                    }

                    $issues[] = $this->relative($file) . ':' . ($index + 1);
                    break;
                }
            }
        }

        if ($issues === []) {
            $this->pass('Camadas core sem acesso direto a primitivas de ambiente (ENV/getenv/putenv).');
            return;
        }

        $this->fail(
            'Acesso direto a primitivas de ambiente detectado em camadas core.',
            implode(' | ', $issues)
        );
    }

    private function checkTemporalPrimitiveUsageInControllers(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $pattern = '/(?<!->)\b(?:time|microtime)\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem uso direto de time()/microtime() (uso via abstracao base).');
            return;
        }

        $this->fail(
            'Uso direto de time()/microtime() detectado em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkStrtotimeUsageInControllers(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $pattern = '/(?<!->)\bstrtotime\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem uso direto de strtotime() (uso via abstracao base).');
            return;
        }

        $this->fail(
            'Uso direto de strtotime() detectado em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkDateUsageInControllers(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $pattern = '/(?<!->)\bdate\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem uso direto de date() (uso via abstracao base).');
            return;
        }

        $this->fail(
            'Uso direto de date() detectado em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkDateTimeImmutableUsageInControllers(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $pattern = '/\bDateTimeImmutable\b/';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem uso direto de DateTimeImmutable (uso via abstracoes base).');
            return;
        }

        $this->fail(
            'Uso direto de DateTimeImmutable detectado em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkDateTimeImmutableUsageInModelsAndLibraries(): void
    {
        $scanDirs = [
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        $allowedFiles = [];

        $pattern = '/\bDateTimeImmutable\b/';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $relative = $this->relative($file);
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                if (isset($allowedFiles[$relative])) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Model/Library sem uso direto nao autorizado de DateTimeImmutable (allowlist temporal controlada).');
            return;
        }

        $this->fail(
            'Uso de DateTimeImmutable fora da allowlist temporal em model/library.',
            implode(' | ', $issues)
        );
    }

    private function checkDateUsageInModelsAndLibraries(): void
    {
        $scanDirs = [
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        $allowedFiles = [];

        $pattern = '/(?<!->)\bdate\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $relative = $this->relative($file);
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                if (isset($allowedFiles[$relative])) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Model/Library sem uso direto nao autorizado de date() (allowlist temporal controlada).');
            return;
        }

        $this->fail(
            'Uso de date() fora da allowlist temporal em model/library.',
            implode(' | ', $issues)
        );
    }

    private function checkTimeAndMicrotimeUsageInModelsAndLibraries(): void
    {
        $scanDirs = [
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        $allowedFiles = [];
        $pattern = '/(?<!->)\b(?:time|microtime)\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $relative = $this->relative($file);
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                if (isset($allowedFiles[$relative])) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Model/Library sem uso direto nao autorizado de time()/microtime() (allowlist temporal controlada).');
            return;
        }

        $this->fail(
            'Uso de time()/microtime() fora da allowlist temporal em model/library.',
            implode(' | ', $issues)
        );
    }

    private function checkStrtotimeUsageInModelsAndLibraries(): void
    {
        $scanDirs = [
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        $allowedFiles = [];

        $pattern = '/(?<!->)\bstrtotime\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $relative = $this->relative($file);
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                if (isset($allowedFiles[$relative])) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Model/Library sem uso direto nao autorizado de strtotime (allowlist temporal controlada).');
            return;
        }

        $this->fail(
            'Uso de strtotime fora da allowlist temporal em model/library.',
            implode(' | ', $issues)
        );
    }

    private function checkTemporalPrimitiveUsageInEngine(): void
    {
        $scanDirs = [
            $this->path('system/Engine'),
        ];

        $allowedFiles = [
            'system/Engine/TemporalClockTrait.php' => true,
        ];

        $patterns = [
            '/\bDateTimeImmutable\b/',
            '/(?<!->)\bdate\s*\(/i',
            '/(?<!->)\bstrtotime\s*\(/i',
            '/(?<!->)\b(?:time|microtime)\s*\(/i',
        ];
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $relative = $this->relative($file);
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                $lineValue = (string) $line;
                $matched = false;
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $lineValue) === 1) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    continue;
                }

                if (isset($allowedFiles[$relative])) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Engine sem uso temporal direto nao autorizado (date/strtotime/time/DateTimeImmutable).');
            return;
        }

        $this->fail(
            'Uso temporal direto detectado no Engine fora da allowlist.',
            implode(' | ', $issues)
        );
    }

    private function checkDirectOutputPrimitivesInControllersAndModels(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
        ];

        $pattern = '/\b(?:echo|print|var_dump|die|exit|header|setcookie)\s*\(/';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers e models sem saida direta fora do Response/View.');
            return;
        }

        $this->fail(
            'Saida direta detectada em controller/model fora do Response/View.',
            implode(' | ', $issues)
        );
    }

    private function checkControllerDependencyOutsideControllerLayer(): void
    {
        $scanDirs = [
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        $patterns = [
            '/\buse\s+(Admin|Client|Install)\\\\Controller\\\\/i',
            '/\bnew\s+\\\\?(Admin|Client|Install)\\\\Controller\\\\/i',
            '/\b(Admin|Client|Install)\\\\Controller\\\\[A-Za-z0-9_\\\\]+\s*::/i',
            '/\bextends\s+\\\\?(Admin|Client|Install)\\\\Controller\\\\/i',
        ];

        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                $lineValue = (string) $line;
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $lineValue) !== 1) {
                        continue;
                    }

                    $issues[] = $this->relative($file) . ':' . ($index + 1);
                    break;
                }
            }
        }

        if ($issues === []) {
            $this->pass('Model/Library sem dependencia de namespace de Controller.');
            return;
        }

        $this->fail(
            'Dependencia de Controller detectada fora da camada de controller.',
            implode(' | ', $issues)
        );
    }

    private function checkCrossAreaNamespaceDependencies(): void
    {
        $areas = [
            'admin' => ['namespace' => 'Admin', 'dirs' => ['admin/Controller', 'admin/Model']],
            'client' => ['namespace' => 'Client', 'dirs' => ['client/Controller', 'client/Model']],
            'install' => ['namespace' => 'Install', 'dirs' => ['install/Controller', 'install/Model']],
        ];

        $issues = [];
        $patterns = [
            '/\buse\s+(Admin|Client|Install)\\\\/i',
            '/\bnew\s+\\\\?(Admin|Client|Install)\\\\/i',
            '/\b(Admin|Client|Install)\\\\[A-Za-z0-9_\\\\]+\s*::/i',
        ];

        foreach ($areas as $areaKey => $meta) {
            $expectedNamespace = strtolower((string) ($meta['namespace'] ?? ''));
            $directories = array_map(fn (string $dir): string => $this->path($dir), (array) ($meta['dirs'] ?? []));

            foreach ($this->phpFiles($directories) as $file) {
                $lines = @file($file, FILE_IGNORE_NEW_LINES);
                if (!is_array($lines)) {
                    continue;
                }

                foreach ($lines as $index => $line) {
                    $lineValue = (string) $line;
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $lineValue, $match) !== 1) {
                            continue;
                        }

                        $usedNamespace = strtolower((string) ($match[1] ?? ''));
                        if ($usedNamespace === '' || $usedNamespace === $expectedNamespace) {
                            continue;
                        }

                        $issues[] = $this->relative($file) . ':' . ($index + 1)
                            . ' uses=' . $usedNamespace
                            . ' expected=' . $areaKey;
                    }
                }
            }
        }

        if ($issues === []) {
            $this->pass('Fronteiras de namespace por area preservadas (Admin/Client/Install).');
            return;
        }

        $this->fail(
            'Dependencia cruzada de namespace entre areas detectada.',
            implode(' | ', $issues)
        );
    }

    private function checkSystemLibraryAreaIsolation(): void
    {
        $libraryDir = $this->path('system/Library');
        $issues = [];
        $patterns = [
            '/\buse\s+(Admin|Client|Install)\\\\/i',
            '/\bnew\s+\\\\?(Admin|Client|Install)\\\\/i',
            '/\b(Admin|Client|Install)\\\\[A-Za-z0-9_\\\\]+\s*::/i',
        ];

        foreach ($this->phpFiles([$libraryDir]) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                $lineValue = (string) $line;
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $lineValue, $match) !== 1) {
                        continue;
                    }

                    $issues[] = $this->relative($file) . ':' . ($index + 1)
                        . ' area_namespace=' . strtolower((string) ($match[1] ?? 'unknown'));
                }
            }
        }

        if ($issues === []) {
            $this->pass('System/Library isolado de namespaces de area (Admin/Client/Install).');
            return;
        }

        $this->fail(
            'System/Library com dependencia direta de namespaces de area.',
            implode(' | ', $issues)
        );
    }

    private function checkNetworkPrimitiveUsageOutsideLibrary(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
        ];

        $pattern = '/\bcurl_(?:init|exec|setopt|setopt_array|close|getinfo|errno|error)\b/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers/Models sem chamadas de rede diretas (curl_*), restritas a Library.');
            return;
        }

        $this->fail(
            'Chamadas de rede diretas detectadas fora da camada Library.',
            implode(' | ', $issues)
        );
    }

    private function checkFilesystemMutationInControllers(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        $pattern = '/\b(?:file_put_contents|fopen|fwrite|unlink|rename|copy|mkdir|rmdir|touch|chmod|move_uploaded_file)\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem mutacao direta de filesystem (delegacao para Library/Model especializado).');
            return;
        }

        $this->fail(
            'Mutacao direta de filesystem detectada em controllers.',
            implode(' | ', $issues)
        );
    }

    private function checkFilesystemMutationInModelsOutsideInstaller(): void
    {
        $scanDirs = [
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
        ];

        $allowedFiles = [
            'install/Model/InstallerModel.php' => true,
        ];

        $pattern = '/\b(?:file_put_contents|fopen|fwrite|unlink|rename|copy|mkdir|rmdir|touch|chmod|move_uploaded_file|ftruncate)\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $relative = $this->relative($file);
            if (isset($allowedFiles[$relative])) {
                continue;
            }

            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Models sem mutacao direta de filesystem fora do InstallerModel.');
            return;
        }

        $this->fail(
            'Mutacao direta de filesystem detectada em models fora do InstallerModel.',
            implode(' | ', $issues)
        );
    }

    private function checkRuntimeDdlOutsideMigrations(): void
    {
        $scanDirs = [
            $this->path('system/Library'),
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
        ];

        $pattern = '/\bCREATE\s+TABLE\b|\bALTER\s+TABLE\b|\bDROP\s+TABLE\b/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $this->relative($file) . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Sem DDL runtime em controllers/models/libraries.');
            return;
        }

        $this->fail('DDL runtime detectado fora de fluxo de migracao.', implode(' | ', $issues));
    }

    private function checkRawDriverInstantiationOutsideInstaller(): void
    {
        $scanDirs = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        $allowedFiles = [
            'install/Model/InstallerModel.php' => true,
            'system/Library/Database.php' => true,
        ];

        $pattern = '/\bnew\s+PDO\s*\(|\bnew\s+mysqli\s*\(|\bmysqli_connect\s*\(/i';
        $issues = [];

        foreach ($this->phpFiles($scanDirs) as $file) {
            $relative = $this->relative($file);
            if (isset($allowedFiles[$relative])) {
                continue;
            }

            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, (string) $line) !== 1) {
                    continue;
                }

                $issues[] = $relative . ':' . ($index + 1);
            }
        }

        if ($issues === []) {
            $this->pass('Sem instanciacao direta de drivers de banco fora do InstallerModel.');
            return;
        }

        $this->fail(
            'Instanciacao direta de driver de banco detectada fora do InstallerModel.',
            implode(' | ', $issues)
        );
    }

    private function hasNamespace(string $content, string $namespace): bool
    {
        return preg_match('/^namespace\s+' . preg_quote($namespace, '/') . '\s*;/m', $content) === 1;
    }

    private function controllerAreaFromPath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', strtolower(trim($relativePath)));
        if (str_starts_with($relativePath, 'admin/controller/')) {
            return 'admin';
        }

        if (str_starts_with($relativePath, 'client/controller/')) {
            return 'client';
        }

        if (str_starts_with($relativePath, 'install/controller/')) {
            return 'install';
        }

        return null;
    }

    private function declaresTrait(string $content): bool
    {
        return preg_match('/\btrait\s+[A-Za-z0-9_]+/', $content) === 1;
    }

    private function declaresClass(string $content): bool
    {
        return preg_match('/\bclass\s+[A-Za-z0-9_]+/', $content) === 1;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function classAndParent(string $content): array
    {
        if (preg_match('/\bclass\s+([A-Za-z0-9_]+)\s+extends\s+([A-Za-z0-9_\\\\]+)/', $content, $matches) === 1) {
            return [$matches[1] ?? null, $matches[2] ?? null];
        }

        if (preg_match('/\bclass\s+([A-Za-z0-9_]+)/', $content, $matches) === 1) {
            return [$matches[1] ?? null, null];
        }

        return [null, null];
    }

    private function declaresSymbol(string $content, string $kind, string $name): bool
    {
        $kind = strtolower(trim($kind));
        if (!in_array($kind, ['class', 'trait'], true)) {
            return false;
        }

        return preg_match('/\b' . preg_quote($kind, '/') . '\s+' . preg_quote($name, '/') . '\b/', $content) === 1;
    }

    private function usesTrait(string $content, string $traitName): bool
    {
        return preg_match('/\buse\s+' . preg_quote($traitName, '/') . '\s*;/', $content) === 1;
    }

    private function path(string $relative): string
    {
        return DIR_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function relative(string $absolute): string
    {
        $root = str_replace('\\', '/', DIR_ROOT);
        $file = str_replace('\\', '/', $absolute);
        if (str_starts_with($file, $root . '/')) {
            return substr($file, strlen($root) + 1);
        }

        return $file;
    }

    private function read(string $file): string
    {
        $content = @file_get_contents($file);
        return is_string($content) ? $content : '';
    }

    private function lineCount(string $file): int
    {
        $lines = @file($file);
        return is_array($lines) ? count($lines) : 0;
    }

    /**
     * @param list<string> $directories
     * @return list<string>
     */
    private function phpFiles(array $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $entry) {
                if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
                    continue;
                }

                if (strtolower((string) $entry->getExtension()) !== 'php') {
                    continue;
                }

                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function pass(string $message): void
    {
        $this->passes++;
        echo '[PASS] ' . $message . PHP_EOL;
    }

    private function warn(string $message, ?string $details = null): void
    {
        $this->warnings[] = [$message, $details];
        echo '[WARN] ' . $message;
        if (is_string($details) && $details !== '') {
            echo ' | ' . $details;
        }
        echo PHP_EOL;
    }

    private function fail(string $message, ?string $details = null): void
    {
        $this->failures[] = [$message, $details];
        echo '[FAIL] ' . $message;
        if (is_string($details) && $details !== '') {
            echo ' | ' . $details;
        }
        echo PHP_EOL;
    }

    private function printSummary(): void
    {
        echo PHP_EOL;
        echo '--- Service Composition Audit Summary ---' . PHP_EOL;
        echo 'Passes: ' . $this->passes . PHP_EOL;
        echo 'Warnings: ' . count($this->warnings) . PHP_EOL;
        echo 'Failures: ' . count($this->failures) . PHP_EOL;

        if (!empty($this->failures)) {
            echo 'Status: FAIL' . PHP_EOL;
            return;
        }

        if (!empty($this->warnings)) {
            echo 'Status: PASS_WITH_WARNINGS' . PHP_EOL;
            return;
        }

        echo 'Status: PASS' . PHP_EOL;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contracts(): array
    {
        return [
            [
                'owner_file' => 'system/Library/SubscriptionService.php',
                'owner_name' => 'SubscriptionService',
                'owner_kind' => 'class',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'SubscriptionServiceOperationsTrait', 'file' => 'system/Library/SubscriptionServiceOperationsTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServiceContextTrait', 'file' => 'system/Library/SubscriptionServiceContextTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServiceBillingInternalsTrait', 'file' => 'system/Library/SubscriptionServiceBillingInternalsTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServicePlanPersistenceTrait', 'file' => 'system/Library/SubscriptionServicePlanPersistenceTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/SubscriptionServiceOperationsTrait.php',
                'owner_name' => 'SubscriptionServiceOperationsTrait',
                'owner_kind' => 'trait',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'SubscriptionServiceSchemaAndPlansTrait', 'file' => 'system/Library/SubscriptionServiceSchemaAndPlansTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServiceBillingOperationsTrait', 'file' => 'system/Library/SubscriptionServiceBillingOperationsTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServiceEntitlementAndCheckoutTrait', 'file' => 'system/Library/SubscriptionServiceEntitlementAndCheckoutTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/SubscriptionServiceBillingOperationsTrait.php',
                'owner_name' => 'SubscriptionServiceBillingOperationsTrait',
                'owner_kind' => 'trait',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'SubscriptionServiceBillingPromotionsAndAnnouncementsTrait', 'file' => 'system/Library/SubscriptionServiceBillingPromotionsAndAnnouncementsTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServiceBillingSettingsTrait', 'file' => 'system/Library/SubscriptionServiceBillingSettingsTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServicePaymentValidationTrait', 'file' => 'system/Library/SubscriptionServicePaymentValidationTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/SubscriptionServiceEntitlementAndCheckoutTrait.php',
                'owner_name' => 'SubscriptionServiceEntitlementAndCheckoutTrait',
                'owner_kind' => 'trait',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'SubscriptionServiceEntitlementsTrait', 'file' => 'system/Library/SubscriptionServiceEntitlementsTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServiceCheckoutLifecycleTrait', 'file' => 'system/Library/SubscriptionServiceCheckoutLifecycleTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SubscriptionServiceAdminOverridesTrait', 'file' => 'system/Library/SubscriptionServiceAdminOverridesTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/SocialPublishingService.php',
                'owner_name' => 'SocialPublishingService',
                'owner_kind' => 'class',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'SocialPublishingSchemaTrait', 'file' => 'system/Library/SocialPublishingSchemaTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SocialPublishingQueueTrait', 'file' => 'system/Library/SocialPublishingQueueTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SocialPublishingDeliveryTrait', 'file' => 'system/Library/SocialPublishingDeliveryTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/AutomationService.php',
                'owner_name' => 'AutomationService',
                'owner_kind' => 'class',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'AutomationServiceSchemaAndValidationTrait', 'file' => 'system/Library/AutomationServiceSchemaAndValidationTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'AutomationServiceWebhookCrudTrait', 'file' => 'system/Library/AutomationServiceWebhookCrudTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'AutomationServiceDispatchTrait', 'file' => 'system/Library/AutomationServiceDispatchTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/FeatureFlagService.php',
                'owner_name' => 'FeatureFlagService',
                'owner_kind' => 'class',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'FeatureFlagSchemaTrait', 'file' => 'system/Library/FeatureFlagSchemaTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'FeatureFlagCrudTrait', 'file' => 'system/Library/FeatureFlagCrudTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'FeatureFlagResolutionTrait', 'file' => 'system/Library/FeatureFlagResolutionTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/CampaignTrackingService.php',
                'owner_name' => 'CampaignTrackingService',
                'owner_kind' => 'class',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'CampaignTrackingSchemaTrait', 'file' => 'system/Library/CampaignTrackingSchemaTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'CampaignTrackingOperationsTrait', 'file' => 'system/Library/CampaignTrackingOperationsTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/CampaignTrackingOperationsTrait.php',
                'owner_name' => 'CampaignTrackingOperationsTrait',
                'owner_kind' => 'trait',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'CampaignTrackingLinkCrudTrait', 'file' => 'system/Library/CampaignTrackingLinkCrudTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'CampaignTrackingUrlHelpersTrait', 'file' => 'system/Library/CampaignTrackingUrlHelpersTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/JobMonitorService.php',
                'owner_name' => 'JobMonitorService',
                'owner_kind' => 'class',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'JobMonitorSchemaTrait', 'file' => 'system/Library/JobMonitorSchemaTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'JobMonitorOperationsTrait', 'file' => 'system/Library/JobMonitorOperationsTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'system/Library/SecurityService.php',
                'owner_name' => 'SecurityService',
                'owner_kind' => 'class',
                'owner_namespace' => 'System\\Library',
                'traits' => [
                    ['name' => 'SecurityRuntimeTrait', 'file' => 'system/Library/SecurityRuntimeTrait.php', 'namespace' => 'System\\Library'],
                    ['name' => 'SecurityAuthAuditTrait', 'file' => 'system/Library/SecurityAuthAuditTrait.php', 'namespace' => 'System\\Library'],
                ],
            ],
            [
                'owner_file' => 'client/Model/PlannerModel.php',
                'owner_name' => 'PlannerModel',
                'owner_kind' => 'class',
                'owner_namespace' => 'Client\\Model',
                'traits' => [
                    ['name' => 'PlannerModelPlanLifecycleTrait', 'file' => 'client/Model/PlannerModelPlanLifecycleTrait.php', 'namespace' => 'Client\\Model'],
                    ['name' => 'PlannerModelStatusAutomationTrait', 'file' => 'client/Model/PlannerModelStatusAutomationTrait.php', 'namespace' => 'Client\\Model'],
                    ['name' => 'PlannerModelCalendarTrait', 'file' => 'client/Model/PlannerModelCalendarTrait.php', 'namespace' => 'Client\\Model'],
                ],
            ],
            [
                'owner_file' => 'client/Model/SocialModel.php',
                'owner_name' => 'SocialModel',
                'owner_kind' => 'class',
                'owner_namespace' => 'Client\\Model',
                'traits' => [
                    ['name' => 'SocialModelConnectionsTrait', 'file' => 'client/Model/SocialModelConnectionsTrait.php', 'namespace' => 'Client\\Model'],
                    ['name' => 'SocialModelDraftsAndPresetsTrait', 'file' => 'client/Model/SocialModelDraftsAndPresetsTrait.php', 'namespace' => 'Client\\Model'],
                    ['name' => 'SocialModelSchemaTrait', 'file' => 'client/Model/SocialModelSchemaTrait.php', 'namespace' => 'Client\\Model'],
                ],
            ],
            [
                'owner_file' => 'client/Model/CalendarModel.php',
                'owner_name' => 'CalendarModel',
                'owner_kind' => 'class',
                'owner_namespace' => 'Client\\Model',
                'traits' => [
                    ['name' => 'CalendarModelEventsTrait', 'file' => 'client/Model/CalendarModelEventsTrait.php', 'namespace' => 'Client\\Model'],
                    ['name' => 'CalendarModelBaseEventsTrait', 'file' => 'client/Model/CalendarModelBaseEventsTrait.php', 'namespace' => 'Client\\Model'],
                ],
            ],
            [
                'owner_file' => 'admin/Controller/UsersController.php',
                'owner_name' => 'UsersController',
                'owner_kind' => 'class',
                'owner_namespace' => 'Admin\\Controller',
                'traits' => [
                    ['name' => 'UsersControllerFiltersTrait', 'file' => 'admin/Controller/Concerns/UsersControllerFiltersTrait.php', 'namespace' => 'Admin\\Controller\\Concerns'],
                    ['name' => 'UsersControllerSubscriptionTrait', 'file' => 'admin/Controller/Concerns/UsersControllerSubscriptionTrait.php', 'namespace' => 'Admin\\Controller\\Concerns'],
                ],
            ],
            [
                'owner_file' => 'client/Controller/AuthController.php',
                'owner_name' => 'AuthController',
                'owner_kind' => 'class',
                'owner_namespace' => 'Client\\Controller',
                'traits' => [
                    ['name' => 'AuthPasswordResetFlowTrait', 'file' => 'client/Controller/Concerns/AuthPasswordResetFlowTrait.php', 'namespace' => 'Client\\Controller\\Concerns'],
                ],
            ],
            [
                'owner_file' => 'client/Controller/SocialController.php',
                'owner_name' => 'SocialController',
                'owner_kind' => 'class',
                'owner_namespace' => 'Client\\Controller',
                'traits' => [
                    ['name' => 'SocialConnectionFlowTrait', 'file' => 'client/Controller/Concerns/SocialConnectionFlowTrait.php', 'namespace' => 'Client\\Controller\\Concerns'],
                    ['name' => 'SocialContentActionsTrait', 'file' => 'client/Controller/Concerns/SocialContentActionsTrait.php', 'namespace' => 'Client\\Controller\\Concerns'],
                    ['name' => 'SocialPublishingActionsTrait', 'file' => 'client/Controller/Concerns/SocialPublishingActionsTrait.php', 'namespace' => 'Client\\Controller\\Concerns'],
                ],
            ],
            [
                'owner_file' => 'client/Controller/Concerns/SocialConnectionFlowTrait.php',
                'owner_name' => 'SocialConnectionFlowTrait',
                'owner_kind' => 'trait',
                'owner_namespace' => 'Client\\Controller\\Concerns',
                'traits' => [
                    ['name' => 'SocialConnectionOAuthFlowTrait', 'file' => 'client/Controller/Concerns/SocialConnectionOAuthFlowTrait.php', 'namespace' => 'Client\\Controller\\Concerns'],
                    ['name' => 'SocialConnectionManualFlowTrait', 'file' => 'client/Controller/Concerns/SocialConnectionManualFlowTrait.php', 'namespace' => 'Client\\Controller\\Concerns'],
                    ['name' => 'SocialConnectionSupportTrait', 'file' => 'client/Controller/Concerns/SocialConnectionSupportTrait.php', 'namespace' => 'Client\\Controller\\Concerns'],
                ],
            ],
        ];
    }
}

$audit = new ServiceCompositionAudit();
exit($audit->run());
