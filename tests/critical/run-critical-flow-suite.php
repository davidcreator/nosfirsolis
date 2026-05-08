<?php

declare(strict_types=1);

final class CriticalFlowSuite
{
    private int $passes = 0;
    /**
     * @var array<int, array{message: string, details: string}>
     */
    private array $failures = [];
    /**
     * @var list<string>
     */
    private array $warnings = [];
    private mixed $runtimeDb = null;
    private bool $runtimeDbInitialized = false;

    public function __construct(private readonly string $root)
    {
    }

    public function run(): int
    {
        $this->testAuthPasswordResetContract();
        $this->testBillingCheckoutAndManualValidationContract();
        $this->testSocialPublishingPipelineContract();
        $this->testCalendarMutationGuardsContract();
        $this->testRuntimeCriticalDatabaseContracts();

        $this->printSummary();
        return $this->failures === [] ? 0 : 1;
    }

    private function testAuthPasswordResetContract(): void
    {
        $candidateFiles = [
            $this->root . '/client/Controller/Concerns/AuthPasswordResetFlowTrait.php',
            $this->root . '/client/Controller/Concerns/AuthPasswordResetRequestTrait.php',
            $this->root . '/client/Controller/Concerns/AuthPasswordResetTokenTrait.php',
            $this->root . '/client/Controller/Concerns/AuthEmailRecoveryFlowTrait.php',
        ];

        $filesUsed = [];
        $contentParts = [];
        foreach ($candidateFiles as $file) {
            if (!is_file($file)) {
                continue;
            }

            $filesUsed[] = $this->relative($file);
            $contentParts[] = $this->readFile($file);
        }

        $content = implode("\n\n", $contentParts);
        if ($content === '') {
            $this->fail(
                'Fluxo de reset de senha fora do contrato critico esperado.',
                'Nao foi possivel localizar arquivos do contrato de reset em client/Controller/Concerns.'
            );
            return;
        }

        $issues = [];

        $csrfGuards = preg_match_all('/!\$this->request->isPost\(\)\s*\|\|\s*!verify_csrf\(/', $content);
        if (!is_int($csrfGuards) || $csrfGuards < 2) {
            $issues[] = 'sendPasswordReset/updatePassword sem guard POST+CSRF consistente';
        }

        if (!str_contains($content, 'bin2hex(random_bytes(32))')) {
            $issues[] = 'token de reset nao usa random_bytes(32)+bin2hex';
        }

        if (!str_contains($content, "hash('sha256', \$token)")) {
            $issues[] = 'token de reset nao e persistido via hash sha256';
        }

        if (!str_contains($content, "preg_match('/^[a-f0-9]{64}$/', \$token) === 1")) {
            $issues[] = 'validacao de formato do token de reset ausente';
        }

        if (!str_contains($content, 'password_hash($password, PASSWORD_DEFAULT)')) {
            $issues[] = 'atualizacao de senha sem password_hash(PASSWORD_DEFAULT)';
        }

        if (!str_contains($content, 'passwordResetTableExists()')) {
            $issues[] = 'guard de existencia da tabela password_resets ausente';
        }

        if (!str_contains($content, "security.runtime_schema_mutations")) {
            $issues[] = 'guard de runtime_schema_mutations ausente no fluxo de storage de reset';
        }

        if (str_contains($content, 'CREATE TABLE IF NOT EXISTS password_resets')) {
            $issues[] = 'fluxo ainda tenta criar password_resets em runtime';
        }

        if ($issues === []) {
            $this->pass('Fluxo critico de reset de senha com contratos de seguranca e armazenamento aderentes.');
            return;
        }

        $this->fail(
            'Fluxo de reset de senha fora do contrato critico esperado.',
            implode(', ', $filesUsed) . ' | ' . implode(' | ', $issues)
        );
    }

    private function testBillingCheckoutAndManualValidationContract(): void
    {
        $checkoutFile = $this->root . '/system/Library/SubscriptionServiceCheckoutLifecycleTrait.php';
        $validationFile = $this->root . '/system/Library/SubscriptionServicePaymentValidationTrait.php';
        $checkout = $this->readFile($checkoutFile);
        $validation = $this->readFile($validationFile);
        $issues = [];

        if (!str_contains($checkout, 'manualPaymentValidationEnabled()')) {
            $issues[] = 'checkout sem branch de validacao manual de pagamento';
        }

        if (!str_contains($checkout, "'payment_validation_requested'")) {
            $issues[] = 'checkout sem evento payment_validation_requested';
        }

        $pendingTransactions = preg_match_all("/insertTransaction\s*\([^;]*'pending'/s", $checkout);
        if (!is_int($pendingTransactions) || $pendingTransactions < 1) {
            $issues[] = 'checkout sem insertTransaction pendente para validacao manual';
        }

        $paidTransactions = preg_match_all("/insertTransaction\s*\([^;]*'paid'/s", $checkout);
        if (!is_int($paidTransactions) || $paidTransactions < 2) {
            $issues[] = 'checkout sem trilha completa de insertTransaction pago';
        }

        if (!str_contains($checkout, 'markInvoicePaid($invoiceId, $paymentMethod)')) {
            $issues[] = 'checkout sem consolidacao markInvoicePaid';
        }

        if (!str_contains($checkout, "activatePlan(\$userId, \$planId, 'active')")) {
            $issues[] = 'checkout sem ativacao de plano apos pagamento';
        }

        if (!str_contains($validation, 'public function approvePaymentTransaction(')) {
            $issues[] = 'servico de validacao sem endpoint de aprovacao manual';
        }

        if (!str_contains($validation, 'public function rejectPaymentTransaction(')) {
            $issues[] = 'servico de validacao sem endpoint de rejeicao manual';
        }

        if (!str_contains($validation, "'validated_at'")) {
            $issues[] = 'aprovacao manual sem carimbo validated_at';
        }

        if (!str_contains($validation, "'rejected_at'")) {
            $issues[] = 'rejeicao manual sem carimbo rejected_at';
        }

        if (!str_contains($validation, "'processed_at' => \$timestamp")) {
            $issues[] = 'validacao manual sem carimbo processed_at';
        }

        if (!str_contains($validation, "'status' => 'paid'")) {
            $issues[] = 'aprovacao manual sem transicao explicita para status paid';
        }

        if (!str_contains($validation, "'status' => 'failed'")) {
            $issues[] = 'rejeicao manual sem transicao explicita para status failed';
        }

        if (!str_contains($validation, 'markInvoicePaid($invoiceId, $paymentMethod)')) {
            $issues[] = 'aprovacao manual sem sincronismo de fatura paga';
        }

        if (!str_contains($validation, "activatePlan(\$userId, \$planId, 'active')")) {
            $issues[] = 'aprovacao manual sem ativacao de plano';
        }

        if ($issues === []) {
            $this->pass('Fluxo critico de checkout e validacao manual de pagamentos atende ao contrato operacional.');
            return;
        }

        $this->fail(
            'Fluxo de checkout/validacao manual fora do contrato critico esperado.',
            $this->relative($checkoutFile) . ' + ' . $this->relative($validationFile) . ' | ' . implode(' | ', $issues)
        );
    }

    private function testSocialPublishingPipelineContract(): void
    {
        $controllerFile = $this->root . '/client/Controller/Concerns/SocialPublishingActionsTrait.php';
        $queueFile = $this->root . '/system/Library/SocialPublishingQueueTrait.php';
        $deliveryFile = $this->root . '/system/Library/SocialPublishingDeliveryTrait.php';

        $controller = $this->readFile($controllerFile);
        $queue = $this->readFile($queueFile);
        $delivery = $this->readFile($deliveryFile);
        $issues = [];

        $controllerMethods = $this->extractPublicMethods($controller);
        foreach (['queuePublication', 'publishNow', 'processQueue'] as $method) {
            if (!isset($controllerMethods[$method])) {
                $issues[] = 'controller sem metodo ' . $method;
                continue;
            }

            if (!str_contains($controllerMethods[$method], 'ensurePostWithCsrf();')) {
                $issues[] = $method . ' sem guard POST+CSRF';
            }
        }

        foreach (['queuePublication', 'queueFromPlanItem', 'processDueQueue'] as $method) {
            if (!str_contains($queue, 'public function ' . $method . '(')) {
                $issues[] = 'pipeline sem metodo de fila ' . $method;
            }
        }

        if (!str_contains($queue, "\$status = 'manual_review';")) {
            $issues[] = 'fila sem fallback para manual_review quando conexao da plataforma nao existe';
        }

        if (!str_contains($queue, "'manual_review' => 0")) {
            $issues[] = 'resumo de processamento de fila sem contador manual_review';
        }

        if (!str_contains($queue, "status IN (\\'queued\\', \\'failed\\')")) {
            $issues[] = 'processamento de fila sem seletor de status queued|failed';
        }

        if (!str_contains($delivery, "integrations.social_publisher.dry_run")) {
            $issues[] = 'entrega sem controle de dry_run por configuracao';
        }

        if (!str_contains($delivery, "\$this->setStatus(\$publicationId, 'processing', null);")) {
            $issues[] = 'entrega sem transicao para processing';
        }

        if (!str_contains($delivery, "\$this->setPublished(\$publicationId, \$providerPostId);")) {
            $issues[] = 'entrega sem transicao para published';
        }

        if (!str_contains($delivery, "\$this->setStatus(\$publicationId, 'manual_review', 'Conexao da plataforma nao encontrada.');")) {
            $issues[] = 'entrega sem transicao manual_review para conexao ausente';
        }

        if (!str_contains($delivery, "\$this->setStatus(\$publicationId, 'failed', \$reason);")) {
            $issues[] = 'entrega sem transicao para failed em erro de provedor';
        }

        if (!str_contains($delivery, "['queued', 'processing', 'published', 'failed', 'manual_review']")) {
            $issues[] = 'governanca de status permitidos do hub social incompleta';
        }

        if ($issues === []) {
            $this->pass('Pipeline critico de publicacao social atende ao contrato de fila, entrega e protecoes HTTP.');
            return;
        }

        $this->fail(
            'Pipeline de publicacao social fora do contrato critico esperado.',
            $this->relative($controllerFile)
            . ' + ' . $this->relative($queueFile)
            . ' + ' . $this->relative($deliveryFile)
            . ' | ' . implode(' | ', $issues)
        );
    }

    private function testCalendarMutationGuardsContract(): void
    {
        $file = $this->root . '/client/Controller/CalendarController.php';
        $content = $this->readFile($file);
        $methods = $this->extractPublicMethods($content);
        $issues = [];

        foreach (['saveNote', 'saveExtraEvent', 'saveColors', 'deleteExtraEvent'] as $method) {
            $body = $methods[$method] ?? null;
            if (!is_string($body)) {
                $issues[] = 'metodo ausente: ' . $method;
                continue;
            }

            if (!str_contains($body, "\$this->boot('client.calendar');")) {
                $issues[] = $method . ' sem bootstrap de dominio client.calendar';
            }

            if (!str_contains($body, '$this->ensurePostWithCsrf();')) {
                $issues[] = $method . ' sem guard POST+CSRF';
            }
        }

        if (!str_contains($content, 'deleteExtraEvent((int) ($this->auth->user()[\'id\'] ?? 0), $eventId);')) {
            $issues[] = 'deleteExtraEvent sem escopo de remocao por usuario autenticado';
        }

        if ($issues === []) {
            $this->pass('Mutacoes criticas de calendario protegidas por POST+CSRF e escopo de usuario.');
            return;
        }

        $this->fail(
            'Mutacoes do calendario fora do contrato critico esperado.',
            $this->relative($file) . ' | ' . implode(' | ', $issues)
        );
    }

    private function testRuntimeCriticalDatabaseContracts(): void
    {
        $db = $this->runtimeDb();
        if (!$db instanceof \System\Library\Database) {
            $this->fail(
                'Smoke de banco dos fluxos criticos nao conseguiu abrir conexao.',
                'Verifique config de runtime/ambiente e disponibilidade do MySQL.'
            );
            return;
        }

        $issues = [];
        $tableContracts = [
            'password_resets' => [
                'columns' => ['user_id', 'email', 'token_hash', 'expires_at', 'used_at', 'created_at', 'updated_at'],
                'indexes' => ['idx_password_resets_token', 'idx_password_resets_expires'],
            ],
            'billing_invoices' => [
                'columns' => ['user_id', 'plan_id', 'status', 'payment_method', 'total_cents', 'paid_at', 'created_at'],
                'indexes' => ['idx_billing_invoices_user'],
            ],
            'payment_transactions' => [
                'columns' => ['user_id', 'invoice_id', 'status', 'payment_method', 'amount_cents', 'payload_json', 'processed_at'],
                'indexes' => ['idx_payment_transactions_invoice', 'idx_payment_transactions_user'],
            ],
            'social_publications' => [
                'columns' => ['user_id', 'platform_slug', 'status', 'scheduled_at', 'published_at', 'error_message', 'attempt_count'],
                'indexes' => ['idx_social_publications_user', 'idx_social_publications_item'],
            ],
            'social_publication_logs' => [
                'columns' => ['publication_id', 'log_level', 'message', 'context_json', 'created_at'],
                'index_columns' => [['publication_id', 'created_at']],
            ],
            'content_day_notes' => [
                'columns' => ['user_id', 'note_date', 'context_type', 'note_text', 'created_at', 'updated_at'],
                'index_columns' => [['user_id', 'note_date', 'context_type']],
            ],
            'calendar_extra_events' => [
                'columns' => ['user_id', 'event_date', 'title', 'event_type', 'color_hex', 'created_at', 'updated_at'],
                'indexes' => ['idx_calendar_extra_user_date'],
            ],
        ];

        foreach ($tableContracts as $table => $contract) {
            if (!$this->tableExists($db, $table)) {
                $issues[] = 'table_missing:' . $table;
                continue;
            }

            foreach ((array) ($contract['columns'] ?? []) as $column) {
                if (!$this->columnExists($db, $table, (string) $column)) {
                    $issues[] = 'column_missing:' . $table . '.' . $column;
                }
            }

            foreach ((array) ($contract['indexes'] ?? []) as $index) {
                if (!$this->indexExists($db, $table, (string) $index)) {
                    $issues[] = 'index_missing:' . $table . '.' . $index;
                }
            }

            foreach ((array) ($contract['index_columns'] ?? []) as $columns) {
                $columnList = array_values(array_filter(array_map(
                    static fn ($value): string => strtolower(trim((string) $value)),
                    (array) $columns
                ), static fn (string $value): bool => $value !== ''));
                if ($columnList === []) {
                    continue;
                }

                if (!$this->indexWithColumnsExists($db, $table, $columnList)) {
                    $issues[] = 'index_columns_missing:' . $table . '.' . implode(',', $columnList);
                }
            }
        }

        $enumContracts = [
            ['table' => 'password_resets', 'column' => 'token_hash', 'expected' => [], 'kind' => 'char(64)'],
            ['table' => 'billing_invoices', 'column' => 'status', 'expected' => ['open', 'paid', 'void', 'failed'], 'kind' => 'enum'],
            ['table' => 'payment_transactions', 'column' => 'status', 'expected' => ['pending', 'paid', 'failed', 'refunded'], 'kind' => 'enum'],
            ['table' => 'social_publications', 'column' => 'status', 'expected' => ['queued', 'processing', 'published', 'failed', 'manual_review'], 'kind' => 'enum'],
            ['table' => 'social_publication_logs', 'column' => 'log_level', 'expected' => ['info', 'warning', 'error'], 'kind' => 'enum'],
            ['table' => 'content_day_notes', 'column' => 'context_type', 'expected' => ['commercial', 'institutional', 'seasonal', 'editorial'], 'kind' => 'enum'],
        ];

        foreach ($enumContracts as $contract) {
            $table = (string) ($contract['table'] ?? '');
            $column = (string) ($contract['column'] ?? '');
            $expected = (array) ($contract['expected'] ?? []);
            $kind = (string) ($contract['kind'] ?? '');

            $metadata = $this->columnMetadata($db, $table, $column);
            if ($metadata === null) {
                continue;
            }

            $columnType = strtolower(trim((string) ($metadata['COLUMN_TYPE'] ?? '')));
            if ($kind === 'char(64)' && $columnType !== 'char(64)') {
                $issues[] = 'type_drift:' . $table . '.' . $column . '=' . $columnType;
                continue;
            }

            if ($kind !== 'enum') {
                continue;
            }

            $actualValues = $this->enumValuesFromColumnType($columnType);
            foreach ($expected as $value) {
                if (!in_array((string) $value, $actualValues, true)) {
                    $issues[] = 'enum_drift:' . $table . '.' . $column . ' missing=' . $value;
                }
            }
        }

        $smokeQueries = [
            'SELECT COUNT(*) AS c FROM password_resets',
            'SELECT COUNT(*) AS c FROM billing_invoices',
            'SELECT COUNT(*) AS c FROM payment_transactions',
            'SELECT COUNT(*) AS c FROM social_publications',
            'SELECT COUNT(*) AS c FROM content_day_notes',
            'SELECT COUNT(*) AS c FROM calendar_extra_events',
        ];

        foreach ($smokeQueries as $sql) {
            try {
                $row = $db->fetch($sql);
                if (!is_array($row) || !array_key_exists('c', $row)) {
                    $issues[] = 'smoke_query_invalid:' . $sql;
                }
            } catch (\Throwable $exception) {
                $issues[] = 'smoke_query_error:' . mb_substr($sql, 0, 80) . ' err=' . $exception->getMessage();
            }
        }

        if ($issues === []) {
            $this->pass('Smoke dinamico de banco para fluxos criticos validou schema, indices e contratos de status.');
            return;
        }

        $this->fail(
            'Smoke de banco encontrou divergencias em contratos criticos de runtime.',
            implode(' | ', $issues)
        );
    }

    /**
     * @return array<string, string>
     */
    private function extractPublicMethods(string $code): array
    {
        $methods = [];
        $pattern = '/public function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*:\s*[^{]+\{/m';
        $matches = [];
        if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE) !== 1 && empty($matches[0])) {
            return $methods;
        }

        foreach ($matches[0] as $index => $match) {
            $signature = (string) ($match[0] ?? '');
            $offset = (int) ($match[1] ?? 0);
            $name = (string) ($matches[1][$index][0] ?? '');

            if ($name === '' || $signature === '') {
                continue;
            }

            $bracePos = strpos($code, '{', $offset + strlen($signature) - 1);
            if ($bracePos === false) {
                continue;
            }

            $endPos = $this->findMatchingBrace($code, $bracePos);
            if (!is_int($endPos)) {
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

    private function readFile(string $file): string
    {
        $content = file_get_contents($file);
        if (!is_string($content)) {
            throw new RuntimeException('Nao foi possivel ler arquivo: ' . $file);
        }

        return $content;
    }

    private function runtimeDb(): mixed
    {
        if ($this->runtimeDbInitialized) {
            return $this->runtimeDb;
        }

        $this->runtimeDbInitialized = true;

        $dbFile = $this->root . '/system/Library/Database.php';
        if (!is_file($dbFile)) {
            $this->warn('Arquivo Database.php nao encontrado para smoke dinamico de banco.');
            $this->runtimeDb = null;
            return null;
        }

        require_once $dbFile;
        $dbConfig = $this->runtimeDatabaseConfig();
        $required = ['host', 'database', 'username'];
        foreach ($required as $field) {
            if (trim((string) ($dbConfig[$field] ?? '')) === '') {
                $this->warn('Config de banco incompleta para smoke dinamico: campo vazio -> ' . $field);
                $this->runtimeDb = null;
                return null;
            }
        }

        $this->runtimeDb = new \System\Library\Database($dbConfig);
        return $this->runtimeDb;
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeDatabaseConfig(): array
    {
        $rootConfig = $this->loadPhpArrayFile($this->root . '/config.php');
        $runtimeConfig = [];

        foreach ([
            $this->root . '/system/Storage/config.php',
            $this->root . '/system/storage/config.php',
        ] as $candidate) {
            $loaded = $this->loadPhpArrayFile($candidate);
            if ($loaded !== []) {
                $runtimeConfig = $loaded;
                break;
            }
        }

        $database = array_replace_recursive(
            (array) ($rootConfig['database'] ?? []),
            (array) ($runtimeConfig['database'] ?? [])
        );

        $environmentOverrides = [
            'host' => ['DB_HOST', 'NOSFIRSOLIS_DB_HOST'],
            'port' => ['DB_PORT', 'NOSFIRSOLIS_DB_PORT'],
            'database' => ['DB_DATABASE', 'NOSFIRSOLIS_DB_DATABASE'],
            'username' => ['DB_USERNAME', 'NOSFIRSOLIS_DB_USERNAME'],
            'password' => ['DB_PASSWORD', 'NOSFIRSOLIS_DB_PASSWORD'],
            'charset' => ['DB_CHARSET', 'NOSFIRSOLIS_DB_CHARSET'],
            'collation' => ['DB_COLLATION', 'NOSFIRSOLIS_DB_COLLATION'],
        ];

        foreach ($environmentOverrides as $field => $keys) {
            $value = $this->envFirst((array) $keys);
            if (!is_string($value)) {
                continue;
            }

            if ($field === 'port') {
                if (ctype_digit($value)) {
                    $database[$field] = (int) $value;
                }
                continue;
            }

            $database[$field] = $value;
        }

        return $database;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPhpArrayFile(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $loaded = require $file;
        return is_array($loaded) ? $loaded : [];
    }

    private function envFirst(array $keys): ?string
    {
        foreach ($keys as $key) {
            $name = trim((string) $key);
            if ($name === '') {
                continue;
            }

            if (isset($_ENV[$name]) && is_string($_ENV[$name]) && trim($_ENV[$name]) !== '') {
                return trim($_ENV[$name]);
            }

            if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && trim($_SERVER[$name]) !== '') {
                return trim($_SERVER[$name]);
            }

            $value = getenv($name);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function tableExists(\System\Library\Database $db, string $table): bool
    {
        $row = $db->fetch(
            'SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1',
            ['table_name' => $table]
        );

        return is_array($row);
    }

    private function columnExists(\System\Library\Database $db, string $table, string $column): bool
    {
        return $this->columnMetadata($db, $table, $column) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function columnMetadata(\System\Library\Database $db, string $table, string $column): ?array
    {
        $row = $db->fetch(
            'SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1',
            [
                'table_name' => $table,
                'column_name' => $column,
            ]
        );

        return is_array($row) ? $row : null;
    }

    private function indexExists(\System\Library\Database $db, string $table, string $indexName): bool
    {
        $row = $db->fetch(
            'SELECT 1
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND INDEX_NAME = :index_name
             LIMIT 1',
            [
                'table_name' => $table,
                'index_name' => $indexName,
            ]
        );

        return is_array($row);
    }

    /**
     * @param list<string> $columns
     */
    private function indexWithColumnsExists(\System\Library\Database $db, string $table, array $columns): bool
    {
        $rows = $db->fetchAll(
            'SELECT INDEX_NAME, GROUP_CONCAT(LOWER(COLUMN_NAME) ORDER BY SEQ_IN_INDEX SEPARATOR \',\') AS cols
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             GROUP BY INDEX_NAME',
            ['table_name' => $table]
        );

        $expected = implode(',', $columns);
        foreach ($rows as $row) {
            $actual = strtolower(trim((string) ($row['cols'] ?? '')));
            if ($actual === $expected) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function enumValuesFromColumnType(string $columnType): array
    {
        if (!str_starts_with($columnType, 'enum(')) {
            return [];
        }

        $body = trim(substr($columnType, 5), ')');
        if ($body === '') {
            return [];
        }

        $parts = str_getcsv($body, ',', "'");
        $values = [];
        foreach ($parts as $part) {
            $value = trim((string) $part);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
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

    private function fail(string $message, string $details): void
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

    private function printSummary(): void
    {
        echo PHP_EOL;
        echo '--- Critical Flow Suite Summary ---' . PHP_EOL;
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

$root = dirname(__DIR__, 2);
$suite = new CriticalFlowSuite($root);
exit($suite->run());
