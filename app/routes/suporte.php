<?php
declare(strict_types=1);

if ($path === 'support' && $method === 'POST') {
    $data = body();
    $subject = requiredString($data, 'subject', 160);
    $message = requiredString($data, 'message', 3000);
    query('INSERT INTO support_requests (user_id, subject, message) VALUES (?, ?, ?)', [$user['id'], $subject, $message]);
    $id = db()->lastInsertId();
    respond(['id' => $id, 'message' => 'Solicitação registrada.'], 201);
}
