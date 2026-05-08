<?php

declare(strict_types=1);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__, 2));
}

final class MvclAudit
{
    private int $passes = 0;
    private array $warnings = [];
    private array $failures = [];

    /**
     * @var array<string, array<string>>
     */
    private array $areaModels = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $renderTemplates = [];

    public function run(): int
    {
        $this->checkCoreEngine();
        $this->checkAreas();
        $this->checkControllerModelBindings();
        $this->checkControllerRenderTemplates();
        $this->checkLayeringHeuristics();
        $this->printSummary();

        return empty($this->failures) ? 0 : 1;
    }

    private function checkCoreEngine(): void
    {
        $requiredCoreFiles = [
            'system/Engine/Application.php',
            'system/Engine/Router.php',
            'system/Engine/Loader.php',
            'system/Engine/Controller.php',
            'system/Engine/Model.php',
            'system/Engine/View.php',
            'system/Engine/Request.php',
            'system/Engine/Response.php',
        ];

        $missing = [];
        foreach ($requiredCoreFiles as $relative) {
            if (!is_file($this->path($relative))) {
                $missing[] = $relative;
            }
        }

        if ($missing !== []) {
            $this->fail('Arquivos centrais de engine ausentes.', implode(', ', $missing));
            return;
        }

        $this->pass('Nucleo MVCL (Engine) presente.');
    }

    private function checkAreas(): void
    {
        $areas = ['admin' => 'Admin', 'client' => 'Client', 'install' => 'Install'];

        foreach ($areas as $areaDir => $areaNamespace) {
            $requiredDirs = ['Controller', 'Model', 'View', 'Language'];
            $missingDirs = [];

            foreach ($requiredDirs as $dir) {
                if (!is_dir($this->path($areaDir . '/' . $dir))) {
                    $missingDirs[] = $areaDir . '/' . $dir;
                }
            }

            if ($missingDirs !== []) {
                $this->fail('Area sem diretorios obrigatorios.', implode(', ', $missingDirs));
                continue;
            }

            $this->pass('Area `' . $areaDir . '` possui estrutura base de diretorios.');

            $controllerFiles = $this->phpFiles($this->path($areaDir . '/Controller'));
            if ($controllerFiles === []) {
                $this->warn('Area sem controllers.', $areaDir . '/Controller');
            }

            foreach ($controllerFiles as $file) {
                $content = $this->read($file);
                if (!$this->hasNamespacePrefix($content, $areaNamespace . '\\Controller')) {
                    $this->fail('Namespace de controller divergente.', $this->relative($file));
                    continue;
                }

                if ($this->className($content) === null && $this->declaresTrait($content)) {
                    continue;
                }

                if (!$this->containsClassSuffix($content, 'Controller')) {
                    $this->warn('Controller sem sufixo esperado `Controller`.', $this->relative($file));
                }
            }

            $modelFiles = $this->phpFiles($this->path($areaDir . '/Model'));
            if ($modelFiles === []) {
                $this->warn('Area sem models.', $areaDir . '/Model');
            }

            $knownModels = [];
            foreach ($modelFiles as $file) {
                $content = $this->read($file);
                if (!$this->hasNamespace($content, $areaNamespace . '\\Model')) {
                    $this->fail('Namespace de model divergente.', $this->relative($file));
                    continue;
                }

                $class = $this->className($content);
                if ($class !== null && str_ends_with($class, 'Model')) {
                    $knownModels[] = strtolower(substr($class, 0, -5));
                }
            }

            $this->areaModels[$areaDir] = array_values(array_unique($knownModels));
        }
    }

    private function checkControllerModelBindings(): void
    {
        $areas = ['admin', 'client', 'install'];
        $issues = [];

        foreach ($areas as $area) {
            $controllerFiles = $this->phpFiles($this->path($area . '/Controller'));
            foreach ($controllerFiles as $file) {
                $content = $this->read($file);

                if (preg_match_all('/->model\(\s*[\'"]([a-zA-Z0-9_\-]+)[\'"](?:\s*,\s*[\'"]([a-zA-Z0-9_\-]+)[\'"])?\s*\)/', $content, $matches, PREG_SET_ORDER) !== 1) {
                    continue;
                }

                foreach ($matches as $match) {
                    $modelSlug = strtolower(trim((string) ($match[1] ?? '')));
                    $targetArea = strtolower(trim((string) ($match[2] ?? $area)));
                    if ($modelSlug === '' || $targetArea === '') {
                        continue;
                    }

                    $known = $this->areaModels[$targetArea] ?? [];
                    if (!in_array(str_replace('-', '', $modelSlug), array_map(static fn (string $value): string => str_replace('-', '', $value), $known), true)) {
                        $issues[] = $this->relative($file) . ' -> loader->model(\'' . $modelSlug . '\', \'' . $targetArea . '\')';
                    }
                }
            }
        }

        if ($issues === []) {
            $this->pass('Bindings de controller para models validos por area.');
            return;
        }

        $this->fail('Controllers referenciam models nao encontrados.', implode(' | ', $issues));
    }

    private function checkControllerRenderTemplates(): void
    {
        $areas = ['admin', 'client', 'install'];
        $issues = [];

        foreach ($areas as $area) {
            $controllerFiles = $this->phpFiles($this->path($area . '/Controller'));
            foreach ($controllerFiles as $file) {
                $content = $this->read($file);
                if (preg_match_all('/->render\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches) !== 1) {
                    continue;
                }

                foreach ($matches[1] as $template) {
                    $template = trim((string) $template);
                    if ($template === '') {
                        continue;
                    }

                    $viewPath = $this->path($area . '/View/' . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php');
                    if (!is_file($viewPath)) {
                        $issues[] = $this->relative($file) . ' -> ' . $area . '/View/' . $template . '.php';
                    }
                }
            }
        }

        if ($issues === []) {
            $this->pass('Templates renderizados por controllers existem nas views.');
            return;
        }

        $this->fail('Controllers renderizam templates ausentes.', implode(' | ', $issues));
    }

    private function checkLayeringHeuristics(): void
    {
        $this->checkRawSqlInControllers();
        $this->checkFatControllers();
        $this->checkModelPurity();
        $this->checkLibrarySize();
    }

    private function checkRawSqlInControllers(): void
    {
        $areas = ['admin', 'client', 'install'];
        $issues = [];

        foreach ($areas as $area) {
            foreach ($this->phpFiles($this->path($area . '/Controller')) as $file) {
                $content = $this->read($file);
                if (!$this->isControllerClassContent($content)) {
                    continue;
                }
                $hasDbDirect = preg_match('/\$this->db->(fetch|fetchAll|query|execute|insert|update|delete)\s*\(/', $content) === 1;
                $hasSqlKeyword = preg_match('/\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bCREATE TABLE\b|\bALTER TABLE\b/i', $content) === 1;
                if ($hasDbDirect && $hasSqlKeyword) {
                    $issues[] = $this->relative($file);
                }
            }
        }

        if ($issues === []) {
            $this->pass('Controllers sem SQL direto relevante (heuristica).');
            return;
        }

        $this->warn('Controllers com SQL direto identificado (reduz aderencia MVCL estrita).', implode(', ', $issues));
    }

    private function checkFatControllers(): void
    {
        $areas = ['admin', 'client', 'install'];
        $issues = [];

        foreach ($areas as $area) {
            foreach ($this->phpFiles($this->path($area . '/Controller')) as $file) {
                $content = $this->read($file);
                if (!$this->isControllerClassContent($content)) {
                    continue;
                }
                $lineCount = $this->lineCount($file);
                if ($lineCount >= 500) {
                    $issues[] = $this->relative($file) . ' (' . $lineCount . ' linhas)';
                }
            }
        }

        if ($issues === []) {
            $this->pass('Controllers dentro de limite de complexidade por tamanho (heuristica).');
            return;
        }

        $this->warn('Controllers muito extensos (candidatos a decomposicao).', implode(', ', $issues));
    }

    private function checkModelPurity(): void
    {
        $folders = ['admin/Model', 'client/Model', 'install/Model'];
        $issues = [];

        foreach ($folders as $folder) {
            foreach ($this->phpFiles($this->path($folder)) as $file) {
                $content = $this->read($file);
                $hasViewCall = preg_match('/->render\(|->setOutput\(|->redirect\(|header\s*\(/', $content) === 1;
                if ($hasViewCall) {
                    $issues[] = $this->relative($file);
                }
            }
        }

        if ($issues === []) {
            $this->pass('Models sem chamadas de apresentacao/response (render/redirect/header).');
            return;
        }

        $this->fail('Models com acoplamento de apresentacao/resposta.', implode(', ', $issues));
    }

    private function checkLibrarySize(): void
    {
        $issues = [];
        foreach ($this->phpFiles($this->path('system/Library')) as $file) {
            $content = $this->read($file);
            if ($this->declaresTrait($content) && $this->className($content) === null) {
                continue;
            }

            $lineCount = $this->lineCount($file);
            if ($lineCount >= 1200) {
                $issues[] = $this->relative($file) . ' (' . $lineCount . ' linhas)';
            }
        }

        if ($issues === []) {
            $this->pass('Bibliotecas dentro de limite de tamanho critico (heuristica).');
            return;
        }

        $this->warn('Bibliotecas muito extensas (risco de baixo coesao).', implode(', ', $issues));
    }

    private function hasNamespace(string $content, string $namespace): bool
    {
        return preg_match('/^namespace\s+' . preg_quote($namespace, '/') . '\s*;/m', $content) === 1;
    }

    private function hasNamespacePrefix(string $content, string $namespacePrefix): bool
    {
        return preg_match('/^namespace\s+' . preg_quote($namespacePrefix, '/') . '(?:\\\\[A-Za-z0-9_\\\\]+)?\s*;/m', $content) === 1;
    }

    private function declaresTrait(string $content): bool
    {
        return preg_match('/\btrait\s+[A-Za-z0-9_]+/', $content) === 1;
    }

    private function isControllerClassContent(string $content): bool
    {
        $class = $this->className($content);
        return is_string($class) && str_ends_with($class, 'Controller');
    }

    private function containsClassSuffix(string $content, string $suffix): bool
    {
        if (preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $match) !== 1) {
            return false;
        }

        return str_ends_with((string) $match[1], $suffix);
    }

    private function className(string $content): ?string
    {
        if (preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $match) !== 1) {
            return null;
        }

        return (string) $match[1];
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if (strtolower((string) $fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        sort($files);

        return $files;
    }

    private function lineCount(string $file): int
    {
        $lines = @file($file);
        return is_array($lines) ? count($lines) : 0;
    }

    private function read(string $file): string
    {
        $content = @file_get_contents($file);
        return is_string($content) ? $content : '';
    }

    private function path(string $relative): string
    {
        return DIR_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function relative(string $file): string
    {
        $normalizedRoot = str_replace('\\', '/', DIR_ROOT);
        $normalizedFile = str_replace('\\', '/', $file);
        if (str_starts_with($normalizedFile, $normalizedRoot . '/')) {
            return substr($normalizedFile, strlen($normalizedRoot) + 1);
        }

        return $normalizedFile;
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
        echo '--- MVCL Audit Summary ---' . PHP_EOL;
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
}

$audit = new MvclAudit();
exit($audit->run());
