<?php
declare(strict_types=1);

if ($path === 'trains' && $method === 'GET') {
    $term = trim((string)($_GET['q'] ?? ''));
    if (strlen($term) > 120) {
        fail('Busca longa demais.');
    }
    $status = $_GET['status'] ?? '';
    if ($status !== '') {
        enumValue($status, ['operating', 'maintenance', 'stopped', 'inactive'], 'status');
    }

    $sql = 'SELECT t.*, s.name AS station_name, (SELECT COUNT(*) FROM sensors se WHERE se.train_id = t.id) AS sensor_count
            FROM trains t LEFT JOIN stations s ON s.id = t.station_id
            WHERE (t.code LIKE ? OR t.name LIKE ?)';
    $params = ['%' . $term . '%', '%' . $term . '%'];
    if ($status !== '') {
        $sql .= ' AND t.status = ?';
        $params[] = $status;
    }

    respond(query($sql . ' ORDER BY t.code', $params)->fetchAll());
}

if ($path === 'trains' && $method === 'POST') {
    requireManager();
    $data = body();
    $code = trainCode($data);
    $name = requiredString($data, 'name', 120);
    $type = enumValue($data['type'] ?? '', ['locomotive', 'composition'], 'type');
    $status = enumValue($data['status'] ?? 'stopped', ['operating', 'maintenance', 'stopped', 'inactive'], 'status');
    $capacity = filter_var($data['capacity'] ?? 0, FILTER_VALIDATE_INT);
    if ($capacity === false || $capacity < 0) {
        fail('Capacidade inválida.');
    }
    $year = modelYear($data['model_year'] ?? null);
    $tons = capacityTons($data['capacity_tons'] ?? null);
    $inspection = inspectionDate($data['last_inspection'] ?? null);

    if (query('SELECT 1 FROM trains WHERE code = ?', [$code])->fetchColumn()) {
        fail('Prefixo já cadastrado.', 409);
    }
    query(
        'INSERT INTO trains (code, name, type, status, capacity, model_year, capacity_tons, last_inspection)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$code, $name, $type, $status, $capacity, $year, $tons, $inspection]
    );
    $id = db()->lastInsertId();
    audit('create', 'trains', (int)$id, $data);
    respond(['id' => $id], 201);
}

if (($segments[0] ?? '') === 'trains' && ctype_digit($segments[1] ?? '') && ($segments[2] ?? '') === 'delete' && count($segments) === 3 && $method === 'POST') {
    requireManager();
    $id = (int)$segments[1];
    if (query('SELECT 1 FROM sensors WHERE train_id = ? LIMIT 1', [$id])->fetchColumn()) {
        fail('Remova ou transfira os sensores vinculados antes de excluir o trem.', 409);
    }
    if (query('SELECT 1 FROM routes WHERE train_id = ? LIMIT 1', [$id])->fetchColumn()) {
        fail('Remova ou transfira as rotas vinculadas antes de excluir o trem.', 409);
    }
    $excluidos = query('DELETE FROM trains WHERE id = ?', [$id]);
    if (!$excluidos->rowCount()) {
        fail('Trem não encontrado.', 404);
    }
    audit('delete', 'trains', $id);
    respond(['message' => 'Trem excluído.']);
}

if (($segments[0] ?? '') === 'trains' && ctype_digit($segments[1] ?? '') && count($segments) === 2) {
    $id = (int)$segments[1];

    if ($method === 'GET') {
        $train = query('SELECT t.*, s.name AS station_name FROM trains t LEFT JOIN stations s ON s.id = t.station_id WHERE t.id = ?', [$id])->fetch();
        if (!$train) {
            fail('Trem não encontrado.', 404);
        }
        $train['sensors'] = query('SELECT * FROM sensors WHERE train_id = ? ORDER BY code', [$id])->fetchAll();
        $train['maintenances'] = query('SELECT * FROM maintenances WHERE train_id = ? ORDER BY scheduled_at DESC', [$id])->fetchAll();
        respond($train);
    }

    if ($method === 'PATCH') {
        requireManager();
        $data = body();
        $fields = [];
        $params = [];
        $camposAceitos = ['code', 'name', 'type', 'status', 'capacity', 'model_year', 'capacity_tons', 'last_inspection'];

        foreach ($camposAceitos as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($key === 'code') {
                $value = trainCode($data);
                if (query('SELECT 1 FROM trains WHERE code = ? AND id <> ?', [$value, $id])->fetchColumn()) {
                    fail('Prefixo já cadastrado.', 409);
                }
            }
            if ($key === 'name') {
                $value = requiredString($data, $key, 120);
            }
            if ($key === 'type') {
                $value = enumValue($value, ['locomotive', 'composition'], $key);
            }
            if ($key === 'status') {
                $value = enumValue($value, ['operating', 'maintenance', 'stopped', 'inactive'], $key);
            }
            if ($key === 'capacity' && (filter_var($value, FILTER_VALIDATE_INT) === false || $value < 0)) {
                fail('Capacidade inválida.');
            }
            if ($key === 'model_year') {
                $value = modelYear($value);
            }
            if ($key === 'capacity_tons') {
                $value = capacityTons($value);
            }
            if ($key === 'last_inspection') {
                $value = inspectionDate($value);
            }
            $fields[] = "$key = ?";
            $params[] = $value;
        }

        if (!$fields) {
            fail('Nenhum campo válido.');
        }
        $params[] = $id;
        $atualizados = query('UPDATE trains SET ' . implode(', ', $fields) . ', updated_at = UTC_TIMESTAMP() WHERE id = ?', $params);
        if (!$atualizados->rowCount() && !query('SELECT 1 FROM trains WHERE id = ?', [$id])->fetchColumn()) {
            fail('Trem não encontrado.', 404);
        }
        audit('update', 'trains', $id, $data);
        respond(['id' => $id]);
    }

    if ($method === 'DELETE') {
        requireManager();
        if (query('SELECT 1 FROM sensors WHERE train_id = ? LIMIT 1', [$id])->fetchColumn()) {
            fail('Remova ou transfira os sensores vinculados antes de excluir o trem.', 409);
        }
        if (query('SELECT 1 FROM routes WHERE train_id = ? LIMIT 1', [$id])->fetchColumn()) {
            fail('Remova ou transfira as rotas vinculadas antes de excluir o trem.', 409);
        }
        $excluidos = query('DELETE FROM trains WHERE id = ?', [$id]);
        if (!$excluidos->rowCount()) {
            fail('Trem não encontrado.', 404);
        }
        audit('delete', 'trains', $id);
        respond(['message' => 'Trem excluído.']);
    }
}
