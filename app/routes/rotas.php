<?php
declare(strict_types=1);

if ($path === 'routes' && $method === 'GET') {
    $status = $_GET['status'] ?? '';
    if ($status !== '') {
        enumValue($status, ['scheduled', 'in_progress', 'completed', 'cancelled'], 'status');
    }
    $trainId = filtroId($_GET['train_id'] ?? '', 'trem');

    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    foreach ([$from, $to] as $data) {
        if ($data === '') {
            continue;
        }
        if (!is_string($data)) {
            fail('Filtro de data inválido.');
        }
        $convertida = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$convertida || $convertida->format('Y-m-d') !== $data) {
            fail('Filtro de data inválido.');
        }
    }
    if ($from !== '' && $to !== '' && $from > $to) {
        fail('Período inválido.');
    }

    $search = trim((string)($_GET['q'] ?? ''));
    if (strlen($search) > 80) {
        fail('Busca de rota longa demais.');
    }

    $sql = 'SELECT r.*, t.code AS train_code, t.name AS train_name FROM routes r JOIN trains t ON t.id = r.train_id WHERE 1=1';
    $params = [];
    if ($status !== '') {
        $sql .= ' AND r.status = ?';
        $params[] = $status;
    }
    if ($trainId) {
        $sql .= ' AND r.train_id = ?';
        $params[] = $trainId;
    }
    if ($from !== '') {
        $sql .= ' AND r.travel_date >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $sql .= ' AND r.travel_date <= ?';
        $params[] = $to;
    }
    if ($search !== '') {
        $sql .= ' AND (r.origin LIKE ? OR r.destination LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    respond(query($sql . ' ORDER BY r.travel_date DESC, r.departure_time, r.id DESC', $params)->fetchAll());
}

if ($path === 'routes' && $method === 'POST') {
    requireManager();
    $values = rotaDados(body());
    query(
        'INSERT INTO routes (train_id, origin, destination, travel_date, departure_time, arrival_time, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
        $values
    );
    $id = (int)db()->lastInsertId();
    audit('create', 'routes', $id);
    respond(['id' => $id], 201);
}

if (preg_match('#^routes/([1-9][0-9]*)$#', $path, $match)) {
    $id = (int)$match[1];

    if ($method === 'GET') {
        $route = query('SELECT r.*, t.code AS train_code FROM routes r JOIN trains t ON t.id = r.train_id WHERE r.id = ?', [$id])->fetch();
        if (!$route) {
            fail('Rota não encontrada.', 404);
        }
        respond($route);
    }

    if ($method === 'PATCH') {
        requireManager();
        if (!query('SELECT 1 FROM routes WHERE id = ?', [$id])->fetchColumn()) {
            fail('Rota não encontrada.', 404);
        }
        $values = rotaDados(body());
        $values[] = $id;
        query(
            'UPDATE routes SET train_id = ?, origin = ?, destination = ?, travel_date = ?, departure_time = ?, arrival_time = ?, status = ? WHERE id = ?',
            $values
        );
        audit('update', 'routes', $id);
        respond(['id' => $id]);
    }
}

if (preg_match('#^routes/([1-9][0-9]*)/delete$#', $path, $match) && $method === 'POST') {
    requireManager();
    $id = (int)$match[1];
    if (!query('DELETE FROM routes WHERE id = ?', [$id])->rowCount()) {
        fail('Rota não encontrada.', 404);
    }
    audit('delete', 'routes', $id);
    respond(['message' => 'Rota excluída.']);
}
