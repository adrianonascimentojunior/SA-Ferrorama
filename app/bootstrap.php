<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/limites.php';

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
        $pdo = new PDO($dsn, envv('DB_USER', 'root'), envv('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        $timeout = (int)($pdo->query("SELECT value FROM app_settings WHERE `key` = 'db_statement_timeout_ms'")->fetchColumn() ?: 5000);
        try {
            $pdo->exec('SET SESSION max_execution_time = ' . max(1000, min(30000, $timeout)));
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
        }
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

function requireManager(): array {
    $user = requireUser();
    if (!in_array($user['role'], ['manager', 'super_admin'], true)) fail('Acesso restrito à gestão de trens e sensores.', 403);
    return $user;
}

function trainCode(array $data): string {
    $code = strtoupper(requiredString($data, 'code', 32));
    if (!preg_match('/^TR-[0-9]{3,}$/', $code)) fail('O prefixo deve seguir o padrão TR-204.');
    return $code;
}

function modelYear(mixed $value): int {
    $year = filter_var($value, FILTER_VALIDATE_INT);
    if ($year === false || $year < 1900 || $year > 2100) fail('Informe um ano entre 1900 e 2100.');
    return $year;
}

function capacityTons(mixed $value): string {
    if (!is_scalar($value) || !preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', (string)$value) || (float)$value <= 0) {
        fail('Informe capacidade em toneladas maior que zero, com até duas casas decimais.');
    }
    return (string)$value;
}

function inspectionDate(mixed $value): ?string {
    if ($value === null || $value === '') return null;
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) fail('Data da última inspeção inválida.');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) fail('Data da última inspeção inválida.');
    return $value;
}

function sensorCode(array $data): string {
    $code = strtoupper(requiredString($data, 'code', 40));
    if (!preg_match('/^S-[A-Z]{2,10}-[0-9]{3,}$/', $code)) fail('O código deve seguir o padrão S-TEMP-001.');
    return $code;
}

function sensorTrainId(mixed $value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1 || !query('SELECT 1 FROM trains WHERE id=?', [$id])->fetchColumn()) {
        fail('Selecione um trem existente.');
    }
    return $id;
}

function optionalText(mixed $value, string $field, int $max): ?string {
    if ($value === null || $value === '') return null;
    if (!is_string($value) || strlen(trim($value)) > $max) fail("Campo inválido: $field.");
    return trim($value) ?: null;
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

function modoDeManutencaoAtivo(): bool {
    $valor = query("SELECT value FROM app_settings WHERE `key`='maintenance_mode'")->fetchColumn();
    return $valor === 'true';
}

function filtroId(mixed $value, string $label): int {
    if ($value === '' || $value === null) {
        return 0;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        fail("Filtro inválido: $label.");
    }
    return $id;
}

function sensorSelect(): string {
    return 'SELECT s.*, t.code AS train_code, t.name AS train_name,
        (SELECT r.value FROM sensor_readings r WHERE r.sensor_id = s.id ORDER BY r.recorded_at DESC, r.id DESC LIMIT 1) AS calculated_value,
        (SELECT r.recorded_at FROM sensor_readings r WHERE r.sensor_id = s.id ORDER BY r.recorded_at DESC, r.id DESC LIMIT 1) AS calculated_at
        FROM sensors s JOIN trains t ON t.id = s.train_id';
}

function indicadorCalculado(array $sensor): array {
    $sensor['latest_value'] = $sensor['calculated_value'];
    $sensor['latest_reading_at'] = $sensor['calculated_at'];

    $temPerfilConhecido = isset(perfisSensores()[$sensor['type']]);
    $sensor['reading_indicator'] = ($sensor['calculated_value'] === null || !$temPerfilConhecido)
        ? null
        : classificarLeitura($sensor['type'], (float)$sensor['calculated_value']);

    unset($sensor['calculated_value'], $sensor['calculated_at']);
    return $sensor;
}

function rotaDados(array $data): array {
    $trainId = sensorTrainId($data['train_id'] ?? null);
    $origin = requiredString($data, 'origin', 80);
    $destination = requiredString($data, 'destination', 80);
    if (mb_strtolower($origin) === mb_strtolower($destination)) {
        fail('Origem e destino devem ser diferentes.');
    }

    $travelDate = requiredString($data, 'travel_date', 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $travelDate);
    if (!$date || $date->format('Y-m-d') !== $travelDate) {
        fail('Data da viagem inválida.');
    }

    $departure = requiredString($data, 'departure_time', 8);
    $arrival = $data['arrival_time'] ?? null;
    if ($arrival === '') {
        $arrival = null;
    }
    $padraoHora = '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/';
    foreach ([$departure, $arrival] as $horario) {
        if ($horario !== null && (!is_string($horario) || !preg_match($padraoHora, $horario))) {
            fail('Horário inválido.');
        }
    }
    if ($arrival !== null && $arrival <= $departure) {
        fail('A chegada deve ser posterior à partida no mesmo dia.');
    }

    $status = enumValue($data['status'] ?? 'scheduled', ['scheduled', 'in_progress', 'completed', 'cancelled'], 'status');

    return [$trainId, $origin, $destination, $travelDate, $departure, $arrival, $status];
}
