<?php
declare(strict_types=1);

if ($path === 'profile') {
    if ($method === 'GET') {
        respond($user);
    }

    if ($method === 'PATCH') {
        $data = body();
        $name = requiredString($data, 'name', 120);
        $title = requiredString($data, 'job_title', 120);
        query('UPDATE users SET name = ?, job_title = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$name, $title, $user['id']]);
        audit('update_profile', 'users', (int)$user['id']);
        respond(currentUser());
    }
}

if ($path === 'profile/password' && $method === 'POST') {
    $data = body();
    $old = (string)($data['old_password'] ?? '');
    $new = (string)($data['new_password'] ?? '');
    $hash = query('SELECT password_hash FROM users WHERE id = ?', [$user['id']])->fetchColumn();
    if (!password_verify($old, $hash) || strlen($new) < 12 || strlen($new) > 128) {
        fail('Senha atual ou nova senha inválida.');
    }
    query('UPDATE users SET password_hash = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
    session_regenerate_id(true);
    audit('change_password', 'users', (int)$user['id']);
    respond(['message' => 'Senha atualizada.']);
}

if ($path === 'profile/export' && $method === 'GET') {
    respond([
        'user' => $user,
        'ticket_simulations' => query('SELECT * FROM ticket_simulations WHERE user_id = ? ORDER BY created_at DESC', [$user['id']])->fetchAll(),
        'support_requests' => query('SELECT * FROM support_requests WHERE user_id = ? ORDER BY created_at DESC', [$user['id']])->fetchAll(),
    ]);
}

if (in_array($path, ['profile/deactivate', 'profile/delete'], true) && $method === 'POST') {
    $password = (string)(body()['password'] ?? '');
    $hash = query('SELECT password_hash FROM users WHERE id = ?', [$user['id']])->fetchColumn();
    if (!password_verify($password, $hash)) {
        fail('Senha incorreta.', 403);
    }

    $ultimoSuperAdmin = $user['role'] === 'super_admin'
        && query("SELECT count(*) FROM users WHERE role = 'super_admin' AND status = 'active'")->fetchColumn() <= 1;
    if ($ultimoSuperAdmin) {
        fail('O último Super Admin não pode remover a própria conta.', 409);
    }

    if ($path === 'profile/delete') {
        audit('delete_self', 'users', (int)$user['id']);
        query('DELETE FROM users WHERE id = ?', [$user['id']]);
    } else {
        query("UPDATE users SET status = 'disabled' WHERE id = ?", [$user['id']]);
        audit('deactivate_self', 'users', (int)$user['id']);
    }

    $_SESSION = [];
    session_destroy();
    respond(['message' => 'Conta encerrada.']);
}
