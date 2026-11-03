<?php
declare(strict_types=1);

if ($path === 'schedules' && $method === 'GET') {
    $origin = (int)($_GET['origin'] ?? 0);
    $destination = (int)($_GET['destination'] ?? 0);

    $sql = 'SELECT sc.*, t.code AS train_code, o.name AS origin_name, d.name AS destination_name
            FROM schedules sc
            JOIN trains t ON t.id = sc.train_id
            JOIN stations o ON o.id = sc.origin_id
            JOIN stations d ON d.id = sc.destination_id
            WHERE sc.departure_at > UTC_TIMESTAMP()';
    $params = [];
    if ($origin) {
        $sql .= ' AND sc.origin_id = ?';
        $params[] = $origin;
    }
    if ($destination) {
        $sql .= ' AND sc.destination_id = ?';
        $params[] = $destination;
    }

    respond(query($sql . ' ORDER BY sc.departure_at LIMIT 50', $params)->fetchAll());
}

if ($path === 'tickets/simulate' && $method === 'POST') {
    $data = body();
    $scheduleId = (int)($data['schedule_id'] ?? 0);
    $returnId = (int)($data['return_schedule_id'] ?? 0);
    $passengers = (int)($data['passengers'] ?? 1);
    if ($passengers < 1 || $passengers > 8) {
        fail('Passageiros: 1 a 8.');
    }

    $outbound = query('SELECT * FROM schedules WHERE id = ? AND departure_at > UTC_TIMESTAMP()', [$scheduleId])->fetch();
    if (!$outbound) {
        fail('Horário de ida indisponível.');
    }

    $returnTrip = null;
    if ($returnId) {
        $returnTrip = query(
            'SELECT * FROM schedules WHERE id = ? AND departure_at > ? AND origin_id = ? AND destination_id = ?',
            [$returnId, $outbound['arrival_at'], $outbound['destination_id'], $outbound['origin_id']]
        )->fetch();
        if (!$returnTrip) {
            fail('Horário de volta inválido.');
        }
    }

    $total = ((float)$outbound['base_price'] + (float)($returnTrip['base_price'] ?? 0)) * $passengers;
    query(
        'INSERT INTO ticket_simulations (user_id, schedule_id, return_schedule_id, passengers, total) VALUES (?, ?, ?, ?, ?)',
        [$user['id'], $scheduleId, $returnId ?: null, $passengers, $total]
    );
    $id = db()->lastInsertId();
    audit('simulate', 'ticket_simulations', (int)$id);

    respond(['id' => $id, 'total' => $total, 'message' => 'Simulação registrada; nenhuma compra ou reserva foi feita.'], 201);
}
