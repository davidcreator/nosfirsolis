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
        $this->testRedirectSanitization();
        $this->testNoMutatingGetLinksInViews();
        $this->testMutatingControllerActionsRequireCsrf();
        $this->testPostFormsIncludeCsrfField();
        $this->testTokenCipherUsesAuthenticatedEncryption();
        $this->testAuthControllerAvoidsRuntimePasswordResetDdl();
        $this->testAuthLibraryRuntimeMutationGuard();
        $this->testRuntimeSchemaMutationGuards();
        $this->testProductionConfigWarnings();
        $this->testSecurityHeadersBaseline();
        $this->testLandingHostGuardCoverage();
        $this->testLandingSecurityHeadersCoverage();
        $this->testLandingProxyAwareHttpsCoverage();
        $this->testSessionSecureCookieProxyAwareness();
        $this->testApplicationEnvironmentOverrides();
        $this->testSensitiveStorageFilesAreNotVersioned();
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

    private function testRedirectSanitization(): void
    {
        $file = $this->root . '/system/Engine/Response.php';
        $content = $this->readFile($file);

        $hasNormalizer = str_contains($content, 'normalizeRedirectUrl');
        $hasCrLfStrip = str_contains($content, "preg_replace('/[\\r\\n]+/', '', \$url)");
        $hasProtocolRelativeBlock = str_contains($content, "str_starts_with(\$url, '//')");
        $hasSchemeValidation = str_contains($content, 'parse_url($url, PHP_URL_SCHEME)');

        if ($hasNormalizer && $hasCrLfStrip && $hasProtocolRelativeBlock && $hasSchemeValidation) {
            $this->pass('Response::redirect aplica sanitizacao contra CRLF e protocolos inseguros.');
            return;
        }

        $this->fail('Response::redirect sem sanitizacao completa de URL de redirecionamento.', $this->relative($file));
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
        $app = (array) ($config['app'] ?? []);
        $security = (array) ($config['security'] ?? []);
        $environment = strtolower(trim((string) ($app['environment'] ?? 'production')));
        $isProduction = in_array($environment, ['production', 'prod', 'live'], true);

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

        $hostGuardCompatibilityMode = (bool) ($security['host_guard_compatibility_mode'] ?? false);
        if ($isProduction && $hostGuardCompatibilityMode) {
            $this->warn('security.host_guard_compatibility_mode=true em producao amplia risco de host bypass legado.');
        } else {
            $this->pass('HostGuard compatibility mode seguro para ambiente atual.');
        }

        $allowedHosts = array_values(array_filter(array_map(
            static fn ($host): string => strtolower(trim((string) $host)),
            (array) ($security['allowed_hosts'] ?? [])
        ), static fn (string $host): bool => $host !== ''));

        if (!$isProduction) {
            $this->pass('Validacao de allowed_hosts para producao nao se aplica ao ambiente atual.');
            return;
        }

        if ($allowedHosts === []) {
            $this->warn('security.allowed_hosts vazio em producao. Defina dominios oficiais via ALLOWED_HOSTS.');
            return;
        }

        $localDefaults = ['localhost', '127.0.0.1', '::1'];
        $nonLocalHosts = array_values(array_diff($allowedHosts, $localDefaults));
        if ($nonLocalHosts === []) {
            $this->warn('security.allowed_hosts em producao contem apenas hosts locais.');
            return;
        }

        $this->pass('security.allowed_hosts contem hosts nao locais para producao.');
    }

    private function testAuthControllerAvoidsRuntimePasswordResetDdl(): void
    {
        $file = $this->root . '/client/Controller/AuthController.php';
        $content = $this->readFile($file);

        if (str_contains($content, 'CREATE TABLE IF NOT EXISTS password_resets')) {
            $this->fail(
                'AuthController ainda tenta criar schema de password_resets em runtime.',
                $this->relative($file)
            );
            return;
        }

        $this->pass('AuthController nao cria schema de password_resets em runtime.');
    }

    private function testAuthLibraryRuntimeMutationGuard(): void
    {
        $file = $this->root . '/system/Library/Auth.php';
        $content = $this->readFile($file);

        $hasRuntimeAlter = str_contains($content, 'ALTER TABLE users ADD COLUMN language_code');
        if (!$hasRuntimeAlter) {
            $this->pass('Auth library sem alteracao de schema runtime para users.language_code.');
            return;
        }

        $hasGuardMethod = str_contains($content, 'runtimeSchemaMutationsAllowed');
        $hasConfigFlag = str_contains($content, 'security.runtime_schema_mutations');
        if ($hasGuardMethod && $hasConfigFlag) {
            $this->pass('Auth library protege ALTER TABLE runtime por security.runtime_schema_mutations.');
            return;
        }

        $this->fail(
            'Auth library altera schema runtime sem guard por configuracao de seguranca.',
            $this->relative($file)
        );
    }

    private function testRuntimeSchemaMutationGuards(): void
    {
        $targets = [
            $this->root . '/system/Library/SubscriptionService.php',
            $this->root . '/system/Library/SocialPublishingService.php',
            $this->root . '/system/Library/ObservabilityService.php',
            $this->root . '/system/Library/JobMonitorService.php',
            $this->root . '/system/Library/FeatureFlagService.php',
            $this->root . '/system/Library/CampaignTrackingService.php',
            $this->root . '/system/Library/AutomationService.php',
            $this->root . '/client/Model/SocialModel.php',
            $this->root . '/client/Model/PlannerModel.php',
            $this->root . '/admin/Model/UserGroupsModel.php',
        ];

        $issues = [];
        foreach ($targets as $file) {
            $content = $this->readFile($file);
            $hasRuntimeDdl = str_contains($content, 'CREATE TABLE IF NOT EXISTS')
                || str_contains($content, 'ALTER TABLE');
            if (!$hasRuntimeDdl) {
                continue;
            }

            $hasGuardMethod = str_contains($content, 'runtimeSchemaMutationsAllowed');
            $hasConfigFlag = str_contains($content, 'security.runtime_schema_mutations');
            if (!$hasGuardMethod || !$hasConfigFlag) {
                $issues[] = $this->relative($file);
            }
        }

        if (empty($issues)) {
            $this->pass('Componentes com DDL runtime possuem guard por security.runtime_schema_mutations.');
            return;
        }

        $this->fail(
            'Componentes com DDL runtime sem guard por security.runtime_schema_mutations.',
            implode(' | ', $issues)
        );
    }

    private function testSecurityHeadersBaseline(): void
    {
        $config = require $this->root . '/config.php';
        $security = (array) ($config['security'] ?? []);
        $headers = (array) ($security['headers'] ?? []);
        $auth = (array) ($security['auth'] ?? []);
        $app = (array) ($config['app'] ?? []);
        $environment = strtolower(trim((string) ($app['environment'] ?? 'production')));
        $isProduction = in_array($environment, ['production', 'prod', 'live'], true);

        if (!array_key_exists('enabled', $headers)) {
            $this->fail('security.headers.enabled ausente em config.php.', 'security.headers');
            return;
        }

        $csp = trim((string) ($headers['content_security_policy'] ?? ''));
        if ($csp === '') {
            $this->warn('security.headers.content_security_policy vazio. Defina CSP para reduzir superficie de XSS.');
        } else {
            $this->pass('CSP configurada em security.headers.content_security_policy.');
            if (str_contains(strtolower($csp), "'unsafe-eval'")) {
                $this->warn("CSP inclui 'unsafe-eval'. Mantenha apenas se houver dependencia tecnica comprovada.");
            }
        }

        $hsts = (array) ($headers['hsts'] ?? []);
        $hstsEnabled = (bool) ($hsts['enabled'] ?? false);
        if ($isProduction && !$hstsEnabled) {
            $this->warn('HSTS desabilitado em producao. Recomenda-se habilitar quando HTTPS estiver estavel.');
        } else {
            $this->pass('Politica HSTS coerente com o ambiente atual.');
        }

        if (!array_key_exists('fail_open_on_security_error', $auth)) {
            $this->fail('security.auth.fail_open_on_security_error ausente em config.php.', 'security.auth');
            return;
        }

        $failOpen = (bool) $auth['fail_open_on_security_error'];
        if ($isProduction && $failOpen) {
            $this->warn('security.auth.fail_open_on_security_error=true em producao prioriza disponibilidade sobre seguranca.');
            return;
        }

        $runtimeSchemaMutations = (bool) ($security['runtime_schema_mutations'] ?? false);
        if ($isProduction && $runtimeSchemaMutations) {
            $this->warn('security.runtime_schema_mutations=true em producao aumenta risco operacional e de seguranca.');
            return;
        }

        $this->pass('Politica fail-open/fail-closed de autenticacao configurada por ambiente.');
    }

    private function testLandingHostGuardCoverage(): void
    {
        $file = $this->root . '/index.php';
        $content = $this->readFile($file);

        $usesAllowedCheck = str_contains($content, 'HostGuard::isAllowedRequestHost');
        $usesEffectiveHost = str_contains($content, 'HostGuard::effectiveHost');

        if ($usesAllowedCheck && $usesEffectiveHost) {
            $this->pass('Landing principal aplica HostGuard para validacao e resolucao de host efetivo.');
            return;
        }

        $this->fail('Landing principal nao cobre HostGuard de forma consistente.', $this->relative($file));
    }

    private function testLandingSecurityHeadersCoverage(): void
    {
        $file = $this->root . '/index.php';
        $content = $this->readFile($file);

        $checks = [
            'X-Content-Type-Options',
            'Content-Security-Policy',
            'Strict-Transport-Security',
            "securityConfig['headers']",
        ];

        foreach ($checks as $needle) {
            if (!str_contains($content, $needle)) {
                $this->fail(
                    'Landing principal sem cobertura completa de headers de seguranca.',
                    $this->relative($file) . ' missing=' . $needle
                );
                return;
            }
        }

        $this->pass('Landing principal aplica headers de seguranca configuraveis.');
    }

    private function testLandingProxyAwareHttpsCoverage(): void
    {
        $file = $this->root . '/index.php';
        $content = $this->readFile($file);

        $checks = [
            str_contains($content, 'nosfir_request_is_https'),
            str_contains($content, "trusted_proxies"),
        ];

        if (!in_array(false, $checks, true)) {
            $this->pass('Landing principal considera HTTPS em proxy confiavel para scheme/HSTS.');
            return;
        }

        $this->fail(
            'Landing principal sem cobertura completa de HTTPS via proxy confiavel.',
            $this->relative($file)
        );
    }

    private function testSessionSecureCookieProxyAwareness(): void
    {
        $sessionFile = $this->root . '/system/Engine/Session.php';
        $sessionContent = $this->readFile($sessionFile);
        $applicationFile = $this->root . '/system/Engine/Application.php';
        $applicationContent = $this->readFile($applicationFile);

        $checks = [
            str_contains($sessionContent, 'requestIsHttps('),
            str_contains($sessionContent, 'nosfir_request_is_https'),
            str_contains($sessionContent, 'HTTP_X_FORWARDED_PROTO'),
            str_contains($sessionContent, 'trusted_proxies'),
            str_contains($applicationContent, 'nosfir_request_is_https'),
            str_contains($applicationContent, "'trusted_proxies' => (array) \$config->get('security.trusted_proxies', [])"),
        ];

        if (!in_array(false, $checks, true)) {
            $this->pass('Sessao considera HTTPS em proxy confiavel para cookie Secure.');
            return;
        }

        $this->fail(
            'Sessao sem cobertura completa para cookie Secure atras de proxy confiavel.',
            $this->relative($sessionFile) . ' | ' . $this->relative($applicationFile)
        );
    }

    private function testApplicationEnvironmentOverrides(): void
    {
        $file = $this->root . '/system/Engine/Application.php';
        $content = $this->readFile($file);

        $checks = [
            str_contains($content, 'applyEnvironmentOverrides'),
            str_contains($content, "'DB_HOST'"),
            str_contains($content, "'DB_PORT'"),
            str_contains($content, "'DB_DATABASE'"),
            str_contains($content, "'DB_USERNAME'"),
            str_contains($content, "'DB_PASSWORD'"),
        ];

        if (!in_array(false, $checks, true)) {
            $this->pass('Application suporta overrides por variaveis de ambiente para configuracao de banco.');
            return;
        }

        $this->fail(
            'Application sem cobertura completa de overrides DB_* por ambiente.',
            $this->relative($file)
        );
    }

    private function testSensitiveStorageFilesAreNotVersioned(): void
    {
        if (!is_dir($this->root . '/.git')) {
            $this->warn('Repositorio Git nao encontrado para validar versionamento de arquivos sensiveis.');
            return;
        }

        $paths = [
            'system/Storage/config.php',
            'system/Storage/config-local.php',
            'system/Storage/config copy.php',
            'system/Storage/sessions/sess_*',
        ];

        $command = 'git -C ' . escapeshellarg($this->root) . ' ls-files -- '
            . implode(' ', array_map('escapeshellarg', $paths));

        $output = [];
        $exitCode = 0;
        @exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->warn('Nao foi possivel validar arquivos sensiveis versionados via git ls-files.');
            return;
        }

        $tracked = array_values(array_filter(array_map(
            static fn ($line): string => trim((string) $line),
            $output
        ), static fn (string $line): bool => $line !== ''));

        if ($tracked === []) {
            $this->pass('Arquivos sensiveis de storage nao estao versionados no Git.');
            return;
        }

        $this->fail('Arquivos sensiveis de storage ainda estao versionados no Git.', implode(' | ', $tracked));
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
