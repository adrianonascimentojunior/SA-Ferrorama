<?php
declare(strict_types=1);

if ($path === 'readings' && $method === 'GET') {
    $sensorId = filtroId($_GET['sensor_id'] ?? '', 'sensor');
    $trainId = filtroId($_GET['train_id'] ?? '', 'trem');

    $sql = 'SELECT r.id, r.value, r.unit, r.recorded_at, s.code AS sensor_code, s.type AS sensor_type, t.code AS train_code
            FROM sensor_readings r
            JOIN sensors s ON s.id = r.sensor_id
            JOIN trains t ON t.id = s.train_id
            WHERE 1=1';
    $params = [];
    if ($sensorId) {
        $sql .= ' AND s.id = ?';
        $params[] = $sensorId;
    }
    if ($trainId) {
        $sql .= ' AND t.id = ?';
        $params[] = $trainId;
    }

    respond(query($sql . ' ORDER BY r.recorded_at DESC, r.id DESC LIMIT 100', $params)->fetchAll());
}

if ($path === 'sensor-profiles' && $method === 'GET') {
    respond(array_keys(perfisSensores()));
}

if ($path === 'simulator' && $method === 'POST') {
    requireManager();
    $data = body();

    $sensorId = filtroId($data['sensor_id'] ?? '', 'sensor');
    if (!$sensorId) {
        fail('Selecione um sensor.');
    }
    $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
    if ($quantity === false || $quantity < 1 || $quantity > 100) {
        fail('Gere de 1 a 100 leituras por vez.');
    }
    $outsideLimit = $data['outside_limit'] ?? false;
    if (!is_bool($outsideLimit)) {
        fail('Opção de limite inválida.');
    }

    $sensor = query('SELECT s.id, s.train_id, s.code, s.type, s.unit FROM sensors s WHERE s.id = ?', [$sensorId])->fetch();
    if (!$sensor) {
        fail('Sensor não encontrado.', 404);
    }
    if (!isset(perfisSensores()[$sensor['type']])) {
        fail('Tipo de sensor sem faixa de simulação.');
    }

    $inserirLeitura = db()->prepare(
        "INSERT INTO sensor_readings (sensor_id, value, unit, recorded_at, source, actor_id) VALUES (?, ?, ?, ?, 'device', ?)"
    );
    $inserirAlerta = db()->prepare(
        "INSERT INTO alerts (train_id, sensor_id, reading_id, sensor_type, title, message, severity, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)"
    );

    $agora = time();
    $totalAlertas = 0;

    db()->beginTransaction();
    for ($i = 0; $i < $quantity; $i++) {
        $forcarPrimeiraForaDoLimite = $outsideLimit && $i === 0;
        $value = $forcarPrimeiraForaDoLimite
            ? round(perfisSensores()[$sensor['type']]['limit'] * 1.15, 2)
            : gerarValorLeitura($sensor['type'], $outsideLimit);
        $timestamp = gmdate('Y-m-d H:i:s', $agora - $i * 300);

        $inserirLeitura->execute([$sensorId, $value, $sensor['unit'], $timestamp, $user['id']]);

        $severity = classificarLeitura($sensor['type'], $value);
        if ($severity !== 'normal') {
            $readingId = (int)db()->lastInsertId();
            $title = $sensor['type'] . ($severity === 'critical' ? ' em nível crítico' : ' acima do limite');
            $message = sprintf('%s registrou %.2f %s.', $sensor['code'], $value, $sensor['unit']);
            $severidadeAlerta = $severity === 'critical' ? 'critical' : 'warning';
            $inserirAlerta->execute([$sensor['train_id'], $sensorId, $readingId, $sensor['type'], $title, $message, $severidadeAlerta, $timestamp]);
            $totalAlertas++;
        }
    }

    audit('simulate', 'sensor_readings', $sensorId, ['quantity' => $quantity, 'outside_limit' => $outsideLimit, 'alerts' => $totalAlertas]);
    db()->commit();

    respond(['readings' => $quantity, 'alerts' => $totalAlertas], 201);
}
