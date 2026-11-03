<?php
declare(strict_types=1);

if ($path === 'sensors' && $method === 'GET') {
    $type = trim((string)($_GET['type'] ?? ''));
    if (strlen($type) > 40) {
        fail('Filtro de tipo inválido.');
    }
    $sql = sensorSelect();
    $params = [];
    if ($type !== '') {
        $sql .= ' WHERE s.type = ?';
        $params[] = $type;
    }
    $sensores = query($sql . ' ORDER BY t.code, s.code', $params)->fetchAll();
    respond(array_map('indicadorCalculado', $sensores));
}

if ($path === 'sensors' && $method === 'POST') {
    requireManager();
    $data = body();
    $trainId = sensorTrainId($data['train_id'] ?? null);
    $code = sensorCode($data);
    $type = requiredString($data, 'type', 40);
    $unit = requiredString($data, 'unit', 20);
    $status = enumValue($data['status'] ?? 'active', ['active', 'warning', 'offline'], 'status');
    $location = optionalText($data['location'] ?? null, 'location', 80);
    $segment = optionalText($data['segment'] ?? null, 'segment', 80);

    if (query('SELECT 1 FROM sensors WHERE code = ?', [$code])->fetchColumn()) {
        fail('Código de sensor já cadastrado.', 409);
    }
    query(
        'INSERT INTO sensors (train_id, code, type, unit, status, location, segment) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$trainId, $code, $type, $unit, $status, $location, $segment]
    );
    $id = db()->lastInsertId();
    audit('create', 'sensors', (int)$id, $data);
    respond(['id' => $id], 201);
}

if (($segments[0] ?? '') === 'sensors' && ctype_digit($segments[1] ?? '') && count($segments) === 2) {
    $id = (int)$segments[1];

    if ($method === 'GET') {
        $sensor = query(sensorSelect() . ' WHERE s.id = ?', [$id])->fetch();
        if (!$sensor) {
            fail('Sensor não encontrado.', 404);
        }
        respond(indicadorCalculado($sensor));
    }

    if ($method === 'PATCH') {
        requireManager();
        $data = body();
        $fields = [];
        $params = [];
        $camposAceitos = ['train_id', 'code', 'type', 'unit', 'status', 'location', 'segment'];

        foreach ($camposAceitos as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($key === 'train_id') {
                $value = sensorTrainId($value);
            }
            if ($key === 'code') {
                $value = sensorCode($data);
                if (query('SELECT 1 FROM sensors WHERE code = ? AND id <> ?', [$value, $id])->fetchColumn()) {
                    fail('Código de sensor já cadastrado.', 409);
                }
            }
            if ($key === 'type') {
                $value = requiredString($data, $key, 40);
            }
            if ($key === 'unit') {
                $value = requiredString($data, $key, 20);
            }
            if ($key === 'status') {
                $value = enumValue($value, ['active', 'warning', 'offline'], $key);
            }
            if (in_array($key, ['location', 'segment'], true)) {
                $value = optionalText($value, $key, 80);
            }
            $fields[] = "$key = ?";
            $params[] = $value;
        }

        if (!$fields) {
            fail('Nenhum campo válido.');
        }
        $params[] = $id;
        $atualizados = query('UPDATE sensors SET ' . implode(', ', $fields) . ', updated_at = UTC_TIMESTAMP() WHERE id = ?', $params);
        if (!$atualizados->rowCount() && !query('SELECT 1 FROM sensors WHERE id = ?', [$id])->fetchColumn()) {
            fail('Sensor não encontrado.', 404);
        }
        audit('update', 'sensors', $id, $data);
        respond(['id' => $id]);
    }
}

if (($segments[0] ?? '') === 'sensors' && ctype_digit($segments[1] ?? '') && ($segments[2] ?? '') === 'delete' && count($segments) === 3 && $method === 'POST') {
    requireManager();
    $id = (int)$segments[1];
    $excluidos = query('DELETE FROM sensors WHERE id = ?', [$id]);
    if (!$excluidos->rowCount()) {
        fail('Sensor não encontrado.', 404);
    }
    audit('delete', 'sensors', $id);
    respond(['message' => 'Sensor excluído.']);
}

if (preg_match('#^sensors/(\d+)/readings$#', $path, $match) && $method === 'GET') {
    if (!query('SELECT 1 FROM sensors WHERE id = ?', [$match[1]])->fetchColumn()) {
        fail('Sensor não encontrado.', 404);
    }
    respond(query(
        'SELECT id, value, recorded_at, source FROM sensor_readings WHERE sensor_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 100',
        [$match[1]]
    )->fetchAll());
}
