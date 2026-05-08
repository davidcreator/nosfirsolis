<?php

declare(strict_types=1);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__, 2));
}

final class MvclMaturityBudgetAudit
{
    private int $passes = 0;
    /**
     * @var array<int, array{message: string, details: string}>
     */
    private array $warnings = [];
    /**
     * @var array<int, array{message: string, details: string}>
     */
    private array $failures = [];

    public function run(): int
    {
        $this->checkControllerLineBudgets();
        $this->checkControllerPublicMethodBudgets();
        $this->checkControllerServiceAccessorDensity();
        $this->checkModelAndLibraryLineBudgets();
        $this->printSummary();

        return $this->failures === [] ? 0 : 1;
    }

    private function checkControllerLineBudgets(): void
    {
        $warnBudget = 450;
        $criticalBudget = 560;
        $warnings = [];
        $failures = [];

        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        foreach ($this->phpFiles($directories) as $file) {
            $lines = $this->lineCount($file);
            $relative = $this->relative($file);

            if ($lines >= $criticalBudget) {
                $failures[] = $relative . ' (' . $lines . ' lines)';
                continue;
            }

            if ($lines >= $warnBudget) {
                $warnings[] = $relative . ' (' . $lines . ' lines)';
            }
        }

        if ($failures !== []) {
            $this->fail(
                'Controllers acima do limite critico de tamanho.',
                implode(' | ', $failures)
            );
            return;
        }

        if ($warnings !== []) {
            $this->warn(
                'Controllers acima do limite de alerta de tamanho.',
                implode(' | ', $warnings)
            );
            return;
        }

        $this->pass('Controllers dentro do orcamento de tamanho.');
    }

    private function checkControllerPublicMethodBudgets(): void
    {
        $warnBudget = 18;
        $criticalBudget = 30;
        $warnings = [];
        $failures = [];

        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        foreach ($this->phpFiles($directories) as $file) {
            $content = $this->read($file);
            $publicMethods = preg_match_all('/\bpublic function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/', $content);
            $count = is_int($publicMethods) ? $publicMethods : 0;
            $relative = $this->relative($file);

            if ($count >= $criticalBudget) {
                $failures[] = $relative . ' (public_methods=' . $count . ')';
                continue;
            }

            if ($count >= $warnBudget) {
                $warnings[] = $relative . ' (public_methods=' . $count . ')';
            }
        }

        if ($failures !== []) {
            $this->fail(
                'Controllers acima do limite critico de metodos publicos.',
                implode(' | ', $failures)
            );
            return;
        }

        if ($warnings !== []) {
            $this->warn(
                'Controllers acima do limite de alerta de metodos publicos.',
                implode(' | ', $warnings)
            );
            return;
        }

        $this->pass('Controllers dentro do orcamento de metodos publicos.');
    }

    private function checkControllerServiceAccessorDensity(): void
    {
        $warnBudget = 13;
        $criticalBudget = 18;
        $warnings = [];
        $failures = [];
        $pattern = '/->\w+Service\s*\(/';

        $directories = [
            $this->path('admin/Controller'),
            $this->path('client/Controller'),
            $this->path('install/Controller'),
        ];

        foreach ($this->phpFiles($directories) as $file) {
            $content = $this->read($file);
            $count = preg_match_all($pattern, $content);
            $matches = is_int($count) ? $count : 0;
            $relative = $this->relative($file);

            if ($matches >= $criticalBudget) {
                $failures[] = $relative . ' (service_accessor_calls=' . $matches . ')';
                continue;
            }

            if ($matches >= $warnBudget) {
                $warnings[] = $relative . ' (service_accessor_calls=' . $matches . ')';
            }
        }

        if ($failures !== []) {
            $this->fail(
                'Controllers acima do limite critico de densidade de service accessors.',
                implode(' | ', $failures)
            );
            return;
        }

        if ($warnings !== []) {
            $this->warn(
                'Controllers acima do limite de alerta de densidade de service accessors.',
                implode(' | ', $warnings)
            );
            return;
        }

        $this->pass('Controllers dentro do orcamento de densidade de service accessors.');
    }

    private function checkModelAndLibraryLineBudgets(): void
    {
        $classWarnBudget = 500;
        $classCriticalBudget = 800;
        $traitWarnBudget = 430;
        $traitCriticalBudget = 700;
        $classBudgetOverrides = [
            'install/Model/InstallerModel.php' => ['warn' => 650, 'critical' => 900],
        ];

        $classWarnings = [];
        $classFailures = [];
        $traitWarnings = [];
        $traitFailures = [];

        $directories = [
            $this->path('admin/Model'),
            $this->path('client/Model'),
            $this->path('install/Model'),
            $this->path('system/Library'),
        ];

        foreach ($this->phpFiles($directories) as $file) {
            $content = $this->read($file);
            $isTraitOnly = preg_match('/\btrait\s+[A-Za-z_][A-Za-z0-9_]*/', $content) === 1
                && preg_match('/\bclass\s+[A-Za-z_][A-Za-z0-9_]*/', $content) !== 1;

            $lines = $this->lineCount($file);
            $relative = $this->relative($file);

            if ($isTraitOnly) {
                if ($lines >= $traitCriticalBudget) {
                    $traitFailures[] = $relative . ' (' . $lines . ' lines)';
                } elseif ($lines >= $traitWarnBudget) {
                    $traitWarnings[] = $relative . ' (' . $lines . ' lines)';
                }
                continue;
            }

            $effectiveClassWarn = $classWarnBudget;
            $effectiveClassCritical = $classCriticalBudget;
            if (isset($classBudgetOverrides[$relative])) {
                $override = (array) $classBudgetOverrides[$relative];
                $effectiveClassWarn = (int) ($override['warn'] ?? $classWarnBudget);
                $effectiveClassCritical = (int) ($override['critical'] ?? $classCriticalBudget);
            }

            if ($lines >= $effectiveClassCritical) {
                $classFailures[] = $relative . ' (' . $lines . ' lines)';
            } elseif ($lines >= $effectiveClassWarn) {
                $classWarnings[] = $relative . ' (' . $lines . ' lines)';
            }
        }

        if ($classFailures !== []) {
            $this->fail(
                'Models/Libraries acima do limite critico de tamanho para classes.',
                implode(' | ', $classFailures)
            );
            return;
        }

        if ($traitFailures !== []) {
            $this->fail(
                'Models/Libraries acima do limite critico de tamanho para traits.',
                implode(' | ', $traitFailures)
            );
            return;
        }

        if ($classWarnings !== []) {
            $this->warn(
                'Models/Libraries acima do limite de alerta de tamanho para classes.',
                implode(' | ', $classWarnings)
            );
            return;
        }

        if ($traitWarnings !== []) {
            $this->warn(
                'Models/Libraries acima do limite de alerta de tamanho para traits.',
                implode(' | ', $traitWarnings)
            );
            return;
        }

        $this->pass('Models/Libraries dentro do orcamento de tamanho por classe/trait.');
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

    private function relative(string $absolute): string
    {
        $root = str_replace('\\', '/', DIR_ROOT);
        $file = str_replace('\\', '/', $absolute);
        if (str_starts_with($file, $root . '/')) {
            return substr($file, strlen($root) + 1);
        }

        return $file;
    }

    private function pass(string $message): void
    {
        $this->passes++;
        echo '[PASS] ' . $message . PHP_EOL;
    }

    private function warn(string $message, string $details): void
    {
        $this->warnings[] = ['message' => $message, 'details' => $details];
        echo '[WARN] ' . $message;
        if ($details !== '') {
            echo ' | ' . $details;
        }
        echo PHP_EOL;
    }

    private function fail(string $message, string $details): void
    {
        $this->failures[] = ['message' => $message, 'details' => $details];
        echo '[FAIL] ' . $message;
        if ($details !== '') {
            echo ' | ' . $details;
        }
        echo PHP_EOL;
    }

    private function printSummary(): void
    {
        echo PHP_EOL;
        echo '--- MVCL Maturity Budget Audit Summary ---' . PHP_EOL;
        echo 'Passes: ' . $this->passes . PHP_EOL;
        echo 'Warnings: ' . count($this->warnings) . PHP_EOL;
        echo 'Failures: ' . count($this->failures) . PHP_EOL;

        if ($this->failures !== []) {
            echo 'Status: FAIL' . PHP_EOL;
            return;
        }

        if ($this->warnings !== []) {
            echo 'Status: PASS_WITH_WARNINGS' . PHP_EOL;
            return;
        }

        echo 'Status: PASS' . PHP_EOL;
    }
}

$audit = new MvclMaturityBudgetAudit();
exit($audit->run());
