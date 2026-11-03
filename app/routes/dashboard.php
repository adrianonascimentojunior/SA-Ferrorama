<?php
declare(strict_types=1);

if ($path === 'dashboard' && $method === 'GET') {
    $period = enumValue($_GET['period'] ?? '30d', ['7d', '30d', '90d'], 'period');
    $days = (int)$period;

    $metrics = query(
        "SELECT
            (SELECT count(*) FROM trains WHERE status = 'operating') AS operating_trains,
            (SELECT count(*) FROM sensors WHERE status = 'active') AS active_sensors,
            (SELECT count(*) FROM maintenances WHERE DATE(scheduled_at) = UTC_DATE() AND status <> 'cancelled') AS maintenances_today,
            (SELECT count(*) FROM alerts WHERE status = 'active') AS active_alerts"
    )->fetch();

    $fleet = query('SELECT status, count(*) AS total FROM trains GROUP BY status ORDER BY status')->fetchAll();

    $summary = query(
        'SELECT
            COALESCE(sum(distance_km), 0) AS distance_km,
            COALESCE(sum(consumption_l), 0) AS consumption_l,
            COALESCE(round(avg(punctuality_pct), 1), 0) AS punctuality_pct
         FROM trains'
    )->fetch();

    $trends = query(
        'SELECT DATE(recorded_at) AS day, count(*) AS readings
         FROM sensor_readings
         WHERE recorded_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
         GROUP BY 1 ORDER BY 1',
        [$days]
    )->fetchAll();

    $maintenance = query(
        "SELECT m.*, t.code AS train_code FROM maintenances m
         JOIN trains t ON t.id = m.train_id
         WHERE m.status IN ('pending', 'in_progress')
         ORDER BY m.scheduled_at LIMIT 5"
    )->fetchAll();

    $alerts = query(
        'SELECT a.*, t.code AS train_code FROM alerts a
         LEFT JOIN trains t ON t.id = a.train_id
         ORDER BY a.created_at DESC LIMIT 5'
    )->fetchAll();

    respond(compact('metrics', 'fleet', 'summary', 'trends', 'maintenance', 'alerts'));
}
