<?php

declare(strict_types=1);

final class SecuritySuite
{
    private int $passes = 0;
    private array $failures = [];
    private array $warnings = [];

    public function __construct(private readonly string $root)
    {
    }

    public function run(): int
    {
        $this->testRouterUsesPublicMethodsOnly();
        $this->testNoMutatingGetLinksInViews();
        $this->testMutatingControllerActionsRequireCsrf();
        $this->testPostFormsIncludeCsrfField();
        $this->testTokenCipherUsesAuthenticatedEncryption();
        $this->testProductionConfigWarnings();
        $this->testRawArrayEchoHeuristic();

        $this->printReport();

        return empty($this->failures) ? 0 : 1;
    }

    private function testRouterUsesPublicMethodsOnly(): void
    {
        $file = $this->root . '/system/Engine/Router.php';
        $content = $this->readFile($file);

        $hasReflection = str_contains($content, 'ReflectionMethod');
        $hasPublicCheck = str_contains($content, 'isPublic()');

        if ($hasReflection && $hasPublicCheck) {
            $this->pass('Router bloqueia invocacao de metodos nao publicos.');
            return;
        }

        $this->fail('Router sem validacao de metodo publico para rotas dinamicas.', $file);
    }

    private function testNoMutatingGetLinksInViews(): void
    {
        $issues = [];
        $viewDirs = [
            $this->root . '/admin/View',
            $this->root . '/client/View',
        ];

        $patterns = [
            '/<a[^>]+route_url\(\'[^\']*\/delete\//i' => 'delete_via_get',
            '/<a[^>]+route_url\(\'auth\/logout\'/i' => 'logout_via_get',
            '/<a[^>]+route_url\(\'calendar\/deleteExtraEvent\//i' => 'delete_extra_event_via_get',
        ];

        foreach ($this->phpFiles($viewDirs) as $file) {
            $content = $this->readFile($file);
            foreach ($patterns as $pattern => $tag) {
                if (preg_match($pattern, $content) === 1) {
                    $issues[] = $tag . ' -> ' . $this->relative($file);
                }
            }
        }

        if (empty($issues)) {
            $this->pass('Views nao usam links GET para acoes mutaveis (delete/logout sensiveis).');
            return;
        }

        $this->fail('Encontradas acoes mutaveis via GET em views.', implode(' | ', $issues));
    }

    private function testMutatingControllerActionsRequireCsrf(): void
    {
        $controllerDirs = [
            $this->root . '/admin/Controller',
            $this->root . '/client/Controller',
            $this->root . '/install/Controller',
        ];

        $issues = [];
        foreach ($this->phpFiles($controllerDirs) as $file) {
            $code = $this->readFile($file);
            $methods = $this->extractPublicMethods($code);

            foreach ($methods as $methodName => $body) {
                if (!$this->isMutatingAction($methodName)) {
                    continue;
                }

                $hasGuard = str_contains($body, 'requirePostAndCsrf(')
                    || str_contains($body, 'ensurePostWithCsrf(')
                    || str_contains($body, 'verify_csrf(');

                if (!$hasGuard) {
                    $issues[] = $this->relative($file) . '::' . $methodName;
                }
            }
        }

        if (empty($issues)) {
            $this->pass('Acoes mutaveis de controller possuem protecao CSRF/metodo POST.');
            return;
        }

        $this->fail('Acoes mutaveis sem guard de CSRF/metodo POST.', implode(' | ', $issues));
    }

    private function testPostFormsIncludeCsrfField(): void
    {
        $viewDirs = [
            $this->root . '/admin/View',
            $this->root . '/client/View',
            $this->root . '/install/View',
        ];

        $issues = [];
        $pattern = '/<form\b[^>]*method\s*=\s*(["\'])post\1[^>]*>(.*?)<\/form>/is';

        foreach ($this->phpFiles($viewDirs) as $file) {
            $content = $this->readFile($file);
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER) !== 1 && empty($matches)) {
                continue;
            }

            foreach ($matches as $match) {
                $formBlock = (string) ($match[0] ?? '');
                if (!str_contains($formBlock, 'csrf_field()')) {
                    $issues[] = $this->relative($file);
                }
            }
        }

        if (empty($issues)) {
            $this->pass('Todos os formularios POST encontrados incluem csrf_field().');
            return;
        }

        $this->fail('Formularios POST sem csrf_field().', implode(' | ', array_unique($issues)));
    }

    private function testTokenCipherUsesAuthenticatedEncryption(): void
    {
        require_once $this->root . '/system/Library/TokenCipher.php';

        $cipher = new \System\Library\TokenCipher([
            'token_cipher_key' => 'suite-key-123',
        ], [
            'session_name' => 'suite-session',
        ]);

        $plain = 'secret-value';
        $encrypted = $cipher->encrypt($plain);
        if (!is_string($encrypted) || $encrypted === '') {
            $this->fail('TokenCipher nao retornou payload valido ao criptografar.', 'encrypt');
            return;
        }

        if (!str_starts_with($encrypted, 'v2:')) {
            $this->fail('TokenCipher nao esta emitindo payload autenticado v2.', 'format');
            return;
        }

        $decrypted = $cipher->decrypt($encrypted);
        if ($decrypted !== $plain) {
            $this->fail('TokenCipher falhou no roundtrip de criptografia.', 'roundtrip');
            return;
        }

        $tampered = substr($encrypted, 0, -1) . ($encrypted[-1] === 'A' ? 'B' : 'A');
        if ($cipher->decrypt($tampered) !== null) {
            $this->fail('TokenCipher aceitou payload adulterado.', 'tamper');
            return;
        }

        $legacyKey = hash('sha256', 'suite-key-123', true);
        $legacyIv = random_bytes(16);
        $legacyCipher = openssl_encrypt($plain, 'AES-256-CBC', $legacyKey, OPENSSL_RAW_DATA, $legacyIv);
        if (is_string($legacyCipher)) {
            $legacyPayload = base64_encode($legacyIv . $legacyCipher);
            if ($cipher->decrypt($legacyPayload) !== $plain) {
                $this->fail('TokenCipher perdeu compatibilidade com payload legado.', 'legacy');
                return;
            }
        }

        $oldCipher = new \System\Library\TokenCipher([
            'token_cipher_key' => 'suite-key-123',
        ], [
            'session_name' => 'suite-session',
        ]);
        $oldEncrypted = $oldCipher->encrypt($plain);
        if (!is_string($oldEncrypted) || $oldEncrypted === '') {
            $this->fail('TokenCipher nao gerou payload v2 de chave antiga para teste de rotacao.', 'rotation_setup');
            return;
        }

        $rotatedCipher = new \System\Library\TokenCipher([
            'token_cipher_key' => 'suite-key-new',
            'token_cipher_key_previous' => ['suite-key-123'],
        ], [
            'session_name' => 'suite-session',
        ]);
        if ($rotatedCipher->decrypt($oldEncrypted) !== $plain) {
            $this->fail('TokenCipher falhou no decrypt com token_cipher_key_previous durante rotacao.', 'rotation_previous');
            return;
        }

        $this->pass('TokenCipher usa payload autenticado (v2), compatibilidade legada e rotacao com chave anterior.');
    }

    private function testProductionConfigWarnings(): void
    {
        $config = require $this->root . '/config.php';
        $security = (array) ($config['security'] ?? []);

        $tokenKey = trim((string) ($security['token_cipher_key'] ?? ''));
        if ($tokenKey === '') {
            $this->warn('security.token_cipher_key esta vazio em config.php (defina segredo forte antes de producao).');
        } else {
            $this->pass('security.token_cipher_key definido.');
        }

        $trustedProxies = (array) ($security['trusted_proxies'] ?? []);
        if (empty($trustedProxies)) {
            $this->warn('security.trusted_proxies vazio. Se usar proxy reverso/CDN, configure para manter IP real confiavel.');
        } else {
            $this->pass('security.trusted_proxies configurado.');
        }

        $allowPrivate = (bool) (($security['automation']['allow_private_webhook_endpoints'] ?? false) === true);
        if ($allowPrivate) {
            $this->warn('security.automation.allow_private_webhook_endpoints=true aumenta risco de SSRF em ambiente produtivo.');
        } else {
            $this->pass('Bloqueio de endpoints privados para webhooks habilitado por padrao.');
        }
    }

    private function testRawArrayEchoHeuristic(): void
    {
        $allowed = [
            'client/View/calendar/annual.php:29',
            'client/View/calendar/index.php:114',
            'client/View/calendar/index.php:167',
            'client/View/calendar/monthly.php:37',
        ];
        $allowedMap = array_fill_keys($allowed, true);

        $issues = [];
        foreach ($this->phpFiles([$this->root . '/admin/View', $this->root . '/client/View']) as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                $lineNumber = $index + 1;
                if (preg_match('/<\?=\s*\$[a-zA-Z_][a-zA-Z0-9_]*\[[^\]]+\]/', (string) $line) !== 1) {
                    continue;
                }

                $key = $this->relative($file) . ':' . $lineNumber;
                if (!isset($allowedMap[$key])) {
                    $issues[] = $key;
                }
            }
        }

        if (empty($issues)) {
            $this->pass('Heuristica de sanitizacao: sem eco bruto suspeito de arrays em views.');
            return;
        }

        $this->fail('Heuristica encontrou eco bruto suspeito em views.', implode(' | ', $issues));
    }

    private function extractPublicMethods(string $code): array
    {
        $methods = [];
        $pattern = '/public function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*:\s*[^{]+\{/m';
        if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE) !== 1 && empty($matches[0])) {
            return $methods;
        }

        foreach ($matches[0] as $index => $match) {
            $signature = (string) ($match[0] ?? '');
            $offset = (int) ($match[1] ?? 0);
            $name = (string) ($matches[1][$index][0] ?? '');
            if ($name === '') {
                continue;
            }

            $bracePos = strpos($code, '{', $offset + strlen($signature) - 1);
            if ($bracePos === false) {
                continue;
            }

            $endPos = $this->findMatchingBrace($code, $bracePos);
            if ($endPos === null) {
                continue;
            }

            $methods[$name] = substr($code, $bracePos + 1, $endPos - $bracePos - 1);
        }

        return $methods;
    }

    private function findMatchingBrace(string $code, int $startPos): ?int
    {
        $length = strlen($code);
        $depth = 0;
        $inString = false;
        $stringDelimiter = '';

        for ($i = $startPos; $i < $length; $i++) {
            $char = $code[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }

                if ($char === $stringDelimiter) {
                    $inString = false;
                    $stringDelimiter = '';
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $stringDelimiter = $char;
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function isMutatingAction(string $name): bool
    {
        $readOnly = [
            'index', 'show', 'create', 'edit',
            'annual', 'monthly', 'period',
            'callback', 'connect', 'redirect',
            'login',
        ];
        if (in_array($name, $readOnly, true)) {
            return false;
        }

        return preg_match('/^(store|update|delete|save|archive|disconnect|queue|publish|process|run|install|authenticate|logout|bulk|test)/i', $name) === 1;
    }

    private function phpFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $entry) {
                if (!$entry instanceof SplFileInfo) {
                    continue;
                }

                if (strtolower($entry->getExtension()) !== 'php') {
                    continue;
                }

                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function readFile(string $file): string
    {
        $content = file_get_contents($file);
        if (!is_string($content)) {
            throw new RuntimeException('Nao foi possivel ler arquivo: ' . $file);
        }

        return $content;
    }

    private function relative(string $file): string
    {
        $normalizedRoot = str_replace('\\', '/', $this->root);
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

    private function fail(string $message, string $details = ''): void
    {
        $this->failures[] = ['message' => $message, 'details' => $details];
        echo '[FAIL] ' . $message;
        if ($details !== '') {
            echo ' | ' . $details;
        }
        echo PHP_EOL;
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
        echo '[WARN] ' . $message . PHP_EOL;
    }

    private function printReport(): void
    {
        echo PHP_EOL;
        echo '--- Security Suite Summary ---' . PHP_EOL;
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

$root = dirname(__DIR__, 2);
$suite = new SecuritySuite($root);
exit($suite->run());
