<?php
declare(strict_types=1);

if ($path === 'auth/register' && $method === 'POST') {
    $data = body();
    $name = requiredString($data, 'name', 120);
    $email = strtolower(requiredString($data, 'email'));
    $password = (string)($data['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 128) {
        fail('Informe e-mail válido e senha de 12 a 128 caracteres.');
    }
    if (query('SELECT 1 FROM users WHERE email = ?', [$email])->fetchColumn()) {
        fail('E-mail já cadastrado.', 409);
    }
    query('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $id = db()->lastInsertId();
    audit('register', 'users', (int)$id);
    respond(['message' => 'Conta criada. Faça login.'], 201);
}

if ($path === 'auth/login' && $method === 'POST') {
    $data = body();
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $password = (string)($data['password'] ?? '');
    $user = query('SELECT id, password_hash, status FROM users WHERE email = ?', [$email])->fetch();
    $senhaConfere = $user && password_verify($password, $user['password_hash']);
    if (!$senhaConfere || $user['status'] !== 'active') {
        fail('Credenciais inválidas.', 401);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    audit('login', 'users', (int)$user['id']);
    respond(['user' => currentUser(), 'csrf' => $_SESSION['csrf']]);
}

if ($path === 'auth/me' && $method === 'GET') {
    $user = requireUser();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    respond(['user' => $user, 'csrf' => $_SESSION['csrf']]);
}

if ($path === 'auth/logout' && $method === 'POST') {
    requireUser();
    csrf();
    try {
        audit('logout', 'users', (int)$_SESSION['user_id']);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }
    encerrarSessao();
    respond(['message' => 'Sessão encerrada.']);
}

if ($path === 'auth/forgot' && $method === 'POST') {
    $email = strtolower(trim((string)(body()['email'] ?? '')));
    $userId = query("SELECT id FROM users WHERE email = ? AND status = 'active'", [$email])->fetchColumn();
    $response = ['message' => 'Se a conta existir, o código de recuperação foi gerado.'];
    if ($userId) {
        $code = (string)random_int(100000, 999999);
        query(
            'INSERT INTO password_resets (user_id, code_hash, expires_at, attempts)
             VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 10 MINUTE, 0)
             ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), expires_at = VALUES(expires_at), attempts = 0',
            [$userId, password_hash($code, PASSWORD_DEFAULT)]
        );
        if (envv('APP_ENV') === 'development') {
            $response['development_code'] = $code;
        }
    }
    respond($response);
}

if ($path === 'auth/reset' && $method === 'POST') {
    $data = body();
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $code = (string)($data['code'] ?? '');
    $password = (string)($data['password'] ?? '');
    if (!preg_match('/^[0-9]{6}$/', $code) || strlen($password) < 12 || strlen($password) > 128) {
        fail('Código ou senha inválidos.');
    }
    $reset = query(
        'SELECT r.user_id, r.code_hash, r.attempts FROM password_resets r
         JOIN users u ON u.id = r.user_id
         WHERE u.email = ? AND r.expires_at > UTC_TIMESTAMP()',
        [$email]
    )->fetch();
    if (!$reset || $reset['attempts'] >= 5) {
        fail('Código inválido ou expirado.', 400);
    }
    if (!password_verify($code, $reset['code_hash'])) {
        query('UPDATE password_resets SET attempts = attempts + 1 WHERE user_id = ?', [$reset['user_id']]);
        fail('Código inválido ou expirado.', 400);
    }
    db()->beginTransaction();
    query('UPDATE users SET password_hash = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
    query('DELETE FROM password_resets WHERE user_id = ?', [$reset['user_id']]);
    db()->commit();
    audit('password_reset', 'users', (int)$reset['user_id']);
    respond(['message' => 'Senha redefinida.']);
}
