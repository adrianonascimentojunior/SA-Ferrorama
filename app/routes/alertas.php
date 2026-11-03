<?php
declare(strict_types=1);

if ($path === 'operational-alerts' && $method === 'GET') {
    $severity = $_GET['severity'] ?? '';
    if ($severity !== '') {
        enumValue($severity, ['warning', 'critical'], 'severity');
    }
    $type = trim((string)($_GET['type'] ?? ''));
    if (strlen($type) > 40) {
        fail('Filtro de tipo inválido.');
    }

    $sql = 'SELECT a.id, a.title, a.message, a.severity, a.created_at, a.sensor_type, r.value, r.unit, s.code AS sensor_code, t.code AS train_code
            FROM alerts a
            JOIN sensor_readings r ON r.id = a.reading_id
            JOIN sensors s ON s.id = r.sensor_id
            JOIN trains t ON t.id = s.train_id
            WHERE 1=1';
    $params = [];
    if ($severity !== '') {
        $sql .= ' AND a.severity = ?';
        $params[] = $severity;
    }
    if ($type !== '') {
        $sql .= ' AND s.type = ?';
        $params[] = $type;
    }

    respond(query($sql . ' ORDER BY a.created_at DESC, a.id DESC LIMIT 100', $params)->fetchAll());
}
