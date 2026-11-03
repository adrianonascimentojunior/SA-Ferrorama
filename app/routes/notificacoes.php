<?php
declare(strict_types=1);

if ($path === 'notifications' && $method === 'GET') {
    $days = (int)(query("SELECT value FROM app_settings WHERE `key` = 'alert_retention_days'")->fetchColumn() ?: 90);

    $rows = query(
        'SELECT a.*, t.code AS train_code, (nr.read_at IS NOT NULL) AS is_read
         FROM alerts a
         LEFT JOIN trains t ON t.id = a.train_id
         LEFT JOIN notification_reads nr ON nr.alert_id = a.id AND nr.user_id = ?
         WHERE a.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
         ORDER BY a.created_at DESC LIMIT 100',
        [$user['id'], $days]
    )->fetchAll();

    respond($rows);
}

if (preg_match('#^notifications/(\d+)/read$#', $path, $match) && $method === 'POST') {
    if (!query('SELECT 1 FROM alerts WHERE id = ?', [$match[1]])->fetchColumn()) {
        fail('Notificação não encontrada.', 404);
    }
    query(
        'INSERT INTO notification_reads (user_id, alert_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE read_at = read_at',
        [$user['id'], $match[1]]
    );
    respond(['message' => 'Marcada como lida.']);
}

if ($path === 'notifications/preferences') {
    if ($method === 'GET') {
        $preferences = query(
            'SELECT email_enabled, push_enabled, sms_enabled FROM notification_preferences WHERE user_id = ?',
            [$user['id']]
        )->fetch();
        respond($preferences ?: ['email_enabled' => true, 'push_enabled' => true, 'sms_enabled' => false]);
    }

    if ($method === 'PATCH') {
        $data = body();
        $email = (int)filter_var($data['email_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $push = (int)filter_var($data['push_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sms = (int)filter_var($data['sms_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        query(
            'INSERT INTO notification_preferences VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE email_enabled = VALUES(email_enabled), push_enabled = VALUES(push_enabled), sms_enabled = VALUES(sms_enabled)',
            [$user['id'], $email, $push, $sms]
        );
        respond(['message' => 'Preferências salvas.']);
    }
}
