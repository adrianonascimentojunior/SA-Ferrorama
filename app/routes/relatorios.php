<?php
declare(strict_types=1);

if ($path === 'reports' && $method === 'GET') {
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
        fail('Período inválido.');
    }

    $train = (int)($_GET['train_id'] ?? 0);
    $sensor = (int)($_GET['sensor_id'] ?? 0);

    $sql = 'SELECT t.code AS train_code, s.id AS sensor_id, s.code AS sensor_code, s.type, s.unit, s.status,
                   s.latest_value, s.latest_reading_at,
                   (SELECT count(*) FROM alerts a WHERE a.sensor_id = s.id AND DATE(a.created_at) BETWEEN ? AND ?) AS occurrences
            FROM sensors s
            JOIN trains t ON t.id = s.train_id
            WHERE 1=1';
    $params = [$from, $to];
    if ($train) {
        $sql .= ' AND t.id = ?';
        $params[] = $train;
    }
    if ($sensor) {
        $sql .= ' AND s.id = ?';
        $params[] = $sensor;
    }

    respond(query($sql . ' ORDER BY t.code, s.code', $params)->fetchAll());
}
