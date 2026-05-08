<?php

declare(strict_types=1);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__, 2));
}

final class QualityGatesRunner
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $results = [];
    /**
     * @var list<string>
     */
    private array $includeIds = [];
    /**
     * @var list<string>
     */
    private array $excludeIds = [];
    private bool $failFast = false;
    private string $exitMode = 'boolean';
    private bool $listOnly = false;
    private bool $helpOnly = false;

    public static function fromArgv(array $argv): self
    {
        $runner = new self();
        $runner->parseArgs($argv);
        return $runner;
    }

    public function run(): int
    {
        if ($this->helpOnly) {
            $this->printHelp();
            return 0;
        }

        if ($this->listOnly) {
            $this->printCheckCatalog();
            return 0;
        }

        $checks = $this->filteredChecks();
        if ($checks === []) {
            fwrite(STDERR, '[ERROR] Nenhum quality gate selecionado. Use --list para ver os ids disponiveis.' . PHP_EOL);
            return 64;
        }

        $startedAt = microtime(true);

        foreach ($checks as $check) {
            $this->runCheck($check);
            if ($this->failFast && (int) ($this->results[array_key_last($this->results)]['exit_code'] ?? 1) !== 0) {
                break;
            }
        }

        $this->printSummary($startedAt);

        return $this->resolveExitCode();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function checks(): array
    {
        return [
            [
                'id' => 'mvcl',
                'bit' => 1,
                'label' => 'MVCL Audit',
                'command' => [PHP_BINARY, $this->path('tools/architecture/run-mvcl-audit.php')],
            ],
            [
                'id' => 'composition',
                'bit' => 2,
                'label' => 'Service Composition Audit',
                'command' => [PHP_BINARY, $this->path('tools/architecture/run-service-composition-audit.php')],
            ],
            [
                'id' => 'security_suite',
                'bit' => 4,
                'label' => 'Security Suite',
                'command' => [PHP_BINARY, $this->path('tests/security/run-security-suite.php')],
            ],
            [
                'id' => 'operational_security',
                'bit' => 8,
                'label' => 'Operational Security Audit',
                'command' => [PHP_BINARY, $this->path('tools/security/run-operational-audit.php')],
            ],
            [
                'id' => 'critical_flows',
                'bit' => 16,
                'label' => 'Critical Flow Suite',
                'command' => [PHP_BINARY, $this->path('tests/critical/run-critical-flow-suite.php')],
            ],
            [
                'id' => 'mvcl_maturity',
                'bit' => 32,
                'label' => 'MVCL Maturity Budget Audit',
                'command' => [PHP_BINARY, $this->path('tools/architecture/run-mvcl-maturity-budget-audit.php')],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $check
     */
    private function runCheck(array $check): void
    {
        $id = (string) ($check['id'] ?? 'unknown');
        $bit = (int) ($check['bit'] ?? 0);
        $label = (string) ($check['label'] ?? $id);
        $command = (array) ($check['command'] ?? []);

        echo PHP_EOL . '>>> Running [' . $id . '] ' . $label . PHP_EOL;
        $startedAt = microtime(true);
        $exitCode = $this->executeCommand($command);
        $durationSeconds = round(microtime(true) - $startedAt, 2);

        $status = $exitCode === 0 ? 'PASS' : 'FAIL';
        echo '<<< Result [' . $id . '] status=' . $status
            . ' exit=' . $exitCode
            . ' duration_s=' . $durationSeconds
            . PHP_EOL;

        $this->results[] = [
            'id' => $id,
            'bit' => $bit,
            'label' => $label,
            'exit_code' => $exitCode,
            'duration_seconds' => $durationSeconds,
        ];
    }

    /**
     * @param array<int, mixed> $command
     */
    private function executeCommand(array $command): int
    {
        $parts = [];
        foreach ($command as $part) {
            $value = trim((string) $part);
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        if ($parts === []) {
            return 1;
        }

        $commandString = implode(' ', array_map('escapeshellarg', $parts));
        $previousCwd = getcwd();
        if ($previousCwd === false) {
            $previousCwd = DIR_ROOT;
        }

        @chdir(DIR_ROOT);
        $exitCode = 1;
        passthru($commandString, $exitCode);
        @chdir($previousCwd);

        return is_int($exitCode) ? $exitCode : 1;
    }

    private function printSummary(float $startedAt): void
    {
        $passes = 0;
        $failures = 0;
        $failureMask = 0;
        foreach ($this->results as $result) {
            if ((int) ($result['exit_code'] ?? 1) === 0) {
                $passes++;
            } else {
                $failures++;
                $failureMask |= (int) ($result['bit'] ?? 0);
            }
        }

        $durationSeconds = round(microtime(true) - $startedAt, 2);

        echo PHP_EOL;
        echo '--- Quality Gates Summary ---' . PHP_EOL;
        echo 'Checks: ' . count($this->results) . PHP_EOL;
        echo 'Passes: ' . $passes . PHP_EOL;
        echo 'Failures: ' . $failures . PHP_EOL;
        echo 'FailFast: ' . ($this->failFast ? 'true' : 'false') . PHP_EOL;
        echo 'ExitMode: ' . $this->exitMode . PHP_EOL;
        echo 'FailureMask: ' . $failureMask . PHP_EOL;
        echo 'Duration_s: ' . $durationSeconds . PHP_EOL;

        foreach ($this->results as $result) {
            $id = (string) ($result['id'] ?? 'unknown');
            $bit = (int) ($result['bit'] ?? 0);
            $label = (string) ($result['label'] ?? $id);
            $exitCode = (int) ($result['exit_code'] ?? 1);
            $status = $exitCode === 0 ? 'PASS' : 'FAIL';
            $duration = (float) ($result['duration_seconds'] ?? 0.0);
            echo '- ' . $id . ' | bit=' . $bit . ' | ' . $label . ' | ' . $status
                . ' | exit=' . $exitCode
                . ' | duration_s=' . $duration
                . PHP_EOL;
        }

        echo 'Status: ' . ($failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
        echo 'ExitCode: ' . $this->resolveExitCode() . PHP_EOL;
    }

    private function resolveExitCode(): int
    {
        $failureMask = 0;
        foreach ($this->results as $result) {
            if ((int) ($result['exit_code'] ?? 1) === 0) {
                continue;
            }
            $failureMask |= (int) ($result['bit'] ?? 0);
        }

        if ($failureMask === 0) {
            return 0;
        }

        return $this->exitMode === 'bitmap' ? $failureMask : 1;
    }

    private function parseArgs(array $argv): void
    {
        foreach ($argv as $index => $arg) {
            if ($index === 0) {
                continue;
            }

            $value = strtolower(trim((string) $arg));
            if ($value === '') {
                continue;
            }

            if ($value === '--fail-fast') {
                $this->failFast = true;
                continue;
            }

            if ($value === '--list') {
                $this->listOnly = true;
                continue;
            }

            if ($value === '--help' || $value === '-h') {
                $this->helpOnly = true;
                continue;
            }

            if (str_starts_with($value, '--exit-mode=')) {
                $mode = trim(substr($value, strlen('--exit-mode=')));
                if (in_array($mode, ['boolean', 'bitmap'], true)) {
                    $this->exitMode = $mode;
                }
                continue;
            }

            if (str_starts_with($value, '--only=')) {
                $ids = trim(substr($value, strlen('--only=')));
                $this->includeIds = $this->normalizeIdList($ids);
                continue;
            }

            if (str_starts_with($value, '--skip=')) {
                $ids = trim(substr($value, strlen('--skip=')));
                $this->excludeIds = $this->normalizeIdList($ids);
                continue;
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filteredChecks(): array
    {
        $checks = $this->checks();
        $filtered = [];

        foreach ($checks as $check) {
            $id = strtolower(trim((string) ($check['id'] ?? '')));
            if ($id === '') {
                continue;
            }

            if ($this->includeIds !== [] && !in_array($id, $this->includeIds, true)) {
                continue;
            }

            if (in_array($id, $this->excludeIds, true)) {
                continue;
            }

            $filtered[] = $check;
        }

        return $filtered;
    }

    /**
     * @return list<string>
     */
    private function normalizeIdList(string $csv): array
    {
        $parts = explode(',', strtolower($csv));
        $ids = [];

        foreach ($parts as $part) {
            $id = trim($part);
            if ($id === '') {
                continue;
            }
            $ids[$id] = true;
        }

        return array_keys($ids);
    }

    private function printCheckCatalog(): void
    {
        echo 'Available quality gates:' . PHP_EOL;
        foreach ($this->checks() as $check) {
            echo '- ' . (string) $check['id']
                . ' | bit=' . (int) $check['bit']
                . ' | ' . (string) $check['label']
                . PHP_EOL;
        }
    }

    private function printHelp(): void
    {
        echo 'Usage: php tools/quality/run-quality-gates.php [options]' . PHP_EOL;
        echo PHP_EOL;
        echo 'Options:' . PHP_EOL;
        echo '  --fail-fast                Interrompe na primeira falha.' . PHP_EOL;
        echo '  --exit-mode=boolean|bitmap Define codigo de saida (padrao: boolean).' . PHP_EOL;
        echo '  --only=id1,id2             Executa apenas os gates informados.' . PHP_EOL;
        echo '  --skip=id1,id2             Ignora os gates informados.' . PHP_EOL;
        echo '  --list                     Lista os gates disponiveis.' . PHP_EOL;
        echo '  --help, -h                 Exibe esta ajuda.' . PHP_EOL;
        echo PHP_EOL;
        echo 'Exemplo:' . PHP_EOL;
        echo '  php tools/quality/run-quality-gates.php --fail-fast --exit-mode=bitmap' . PHP_EOL;
    }

    private function path(string $relative): string
    {
        return DIR_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}

$runner = QualityGatesRunner::fromArgv($argv ?? []);
exit($runner->run());
