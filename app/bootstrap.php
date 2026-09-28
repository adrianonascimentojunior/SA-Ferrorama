<?php
declare(strict_types=1);

function envv(string $key, string $default = ''): string {
    static $local = null;
    if ($local === null) $local = is_file(__DIR__ . '/../config/local.php') ? require __DIR__ . '/../config/local.php' : [];
    $value = getenv($key);
    return $value === false ? (string)($local[$key] ?? $default) : $value;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', envv('DB_HOST', 'localhost'), envv('DB_PORT', '3306'), envv('DB_NAME', 'frota_ferroviaria'));
        $pdo = new PDO($dsn, envv('DB_USER'), envv('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        $timeout = (int)($pdo->query("SELECT value FROM app_settings WHERE `key` = 'db_statement_timeout_ms'")->fetchColumn() ?: 5000);
        $pdo->exec('SET SESSION max_execution_time = ' . max(1000, min(30000, $timeout)));
    }
    return $pdo;
}

function respond(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(string $message, int $status = 400): never { respond(['error' => $message], $status); }

function body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) fail('JSON inválido.');
    return $data;
}

function query(string $sql, array $params = []): PDOStatement {
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function requiredString(array $data, string $key, int $max = 255): string {
    $value = trim((string)($data[$key] ?? ''));
    if ($value === '' || strlen($value) > $max) fail("Campo inválido: $key.");
    return $value;
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $user = query('SELECT id, name, email, role, status, job_title, avatar_url, created_at FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch();
    return $user && $user['status'] === 'active' ? $user : null;
}

function requireUser(): array {
    $user = currentUser();
    if (!$user) fail('Sessão inválida ou expirada.', 401);
    return $user;
}

function requireAdmin(): array {
    $user = requireUser();
    if ($user['role'] !== 'super_admin') fail('Acesso restrito ao Super Admin.', 403);
    return $user;
}

function csrf(): void {
    $given = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($given) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $given)) fail('Token CSRF inválido.', 403);
}

function audit(string $action, string $entity, ?int $entityId, array $details = []): void {
    query('INSERT INTO audit_logs (actor_id, action, entity, entity_id, details) VALUES (?, ?, ?, ?, ?)', [$_SESSION['user_id'] ?? null, $action, $entity, $entityId, json_encode($details, JSON_UNESCAPED_UNICODE)]);
}

function enumValue(mixed $value, array $allowed, string $field): string {
    if (!is_string($value) || !in_array($value, $allowed, true)) fail("Valor inválido: $field.");
    return $value;
}
