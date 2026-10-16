<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/limites.php';

function indicadorCalculado(array $sensor): array {
    $sensor['latest_value'] = $sensor['calculated_value'];
    $sensor['latest_reading_at'] = $sensor['calculated_at'];
    $sensor['reading_indicator'] = $sensor['calculated_value'] === null || !isset(perfisSensores()[$sensor['type']])
        ? null : classificarLeitura($sensor['type'], (float)$sensor['calculated_value']);
    unset($sensor['calculated_value'], $sensor['calculated_at']);
    return $sensor;
}

function sensorSelect(): string {
    return 'SELECT s.*,t.code AS train_code,t.name AS train_name,'
        . '(SELECT r.value FROM sensor_readings r WHERE r.sensor_id=s.id ORDER BY r.recorded_at DESC,r.id DESC LIMIT 1) AS calculated_value,'
        . '(SELECT r.recorded_at FROM sensor_readings r WHERE r.sensor_id=s.id ORDER BY r.recorded_at DESC,r.id DESC LIMIT 1) AS calculated_at'
        . ' FROM sensors s JOIN trains t ON t.id=s.train_id';
}

function rotaDados(array $data): array {
    $trainId = sensorTrainId($data['train_id'] ?? null);
    $origin = requiredString($data, 'origin', 80);
    $destination = requiredString($data, 'destination', 80);
    if (mb_strtolower($origin) === mb_strtolower($destination)) fail('Origem e destino devem ser diferentes.');
    $travelDate = requiredString($data, 'travel_date', 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $travelDate);
    if (!$date || $date->format('Y-m-d') !== $travelDate) fail('Data da viagem inválida.');
    $departure = requiredString($data, 'departure_time', 8);
    $arrival = $data['arrival_time'] ?? null;
    if ($arrival === '') $arrival = null;
    foreach ([$departure, $arrival] as $time) if ($time !== null && (!is_string($time) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time))) fail('Horário inválido.');
    if ($arrival !== null && $arrival <= $departure) fail('A chegada deve ser posterior à partida no mesmo dia.');
    $status = enumValue($data['status'] ?? 'scheduled', ['scheduled','in_progress','completed','cancelled'], 'status');
    return [$trainId,$origin,$destination,$travelDate,$departure,$arrival,$status];
}

function filtroId(mixed $value, string $label): int {
    if ($value === '' || $value === null) return 0;
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) fail("Filtro inválido: $label.");
    return $id;
}

function bloco3(string $path, string $method, array $user): void {
    if ($path === 'routes' && $method === 'GET') {
        $status = $_GET['status'] ?? '';
        if ($status !== '') enumValue($status, ['scheduled','in_progress','completed','cancelled'], 'status');
        $trainId = filtroId($_GET['train_id'] ?? '', 'trem');
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        foreach ([$from,$to] as $date) if ($date !== '' && (!is_string($date) || !DateTimeImmutable::createFromFormat('!Y-m-d', $date) || DateTimeImmutable::createFromFormat('!Y-m-d', $date)->format('Y-m-d') !== $date)) fail('Filtro de data inválido.');
        if ($from !== '' && $to !== '' && $from > $to) fail('Período inválido.');
        $search = trim((string)($_GET['q'] ?? ''));
        if (strlen($search) > 80) fail('Busca de rota longa demais.');
        $sql = 'SELECT r.*,t.code AS train_code,t.name AS train_name FROM routes r JOIN trains t ON t.id=r.train_id WHERE 1=1';
        $params = [];
        if ($status !== '') { $sql .= ' AND r.status=?'; $params[]=$status; }
        if ($trainId) { $sql .= ' AND r.train_id=?'; $params[]=$trainId; }
        if ($from !== '') { $sql .= ' AND r.travel_date>=?'; $params[]=$from; }
        if ($to !== '') { $sql .= ' AND r.travel_date<=?'; $params[]=$to; }
        if ($search !== '') { $sql .= ' AND (r.origin LIKE ? OR r.destination LIKE ?)'; $params[]='%'.$search.'%'; $params[]='%'.$search.'%'; }
        respond(query($sql.' ORDER BY r.travel_date DESC,r.departure_time,r.id DESC',$params)->fetchAll());
    }
    if ($path === 'routes' && $method === 'POST') {
        requireManager();
        $values = rotaDados(body());
        query('INSERT INTO routes (train_id,origin,destination,travel_date,departure_time,arrival_time,status) VALUES (?,?,?,?,?,?,?)',$values);
        $id = (int)db()->lastInsertId();
        audit('create','routes',$id);
        respond(['id'=>$id],201);
    }
    if (preg_match('#^routes/([1-9][0-9]*)$#',$path,$match)) {
        $id = (int)$match[1];
        if ($method === 'GET') {
            $route = query('SELECT r.*,t.code AS train_code FROM routes r JOIN trains t ON t.id=r.train_id WHERE r.id=?',[$id])->fetch();
            if (!$route) fail('Rota não encontrada.',404);
            respond($route);
        }
        if ($method === 'PATCH') {
            requireManager();
            if (!query('SELECT 1 FROM routes WHERE id=?',[$id])->fetchColumn()) fail('Rota não encontrada.',404);
            $values = rotaDados(body());
            $values[] = $id;
            query('UPDATE routes SET train_id=?,origin=?,destination=?,travel_date=?,departure_time=?,arrival_time=?,status=? WHERE id=?',$values);
            audit('update','routes',$id);
            respond(['id'=>$id]);
        }
    }
    if (preg_match('#^routes/([1-9][0-9]*)/delete$#',$path,$match) && $method === 'POST') {
        requireManager();
        $id = (int)$match[1];
        if (!query('DELETE FROM routes WHERE id=?',[$id])->rowCount()) fail('Rota não encontrada.',404);
        audit('delete','routes',$id);
        respond(['message'=>'Rota excluída.']);
    }
    if ($path === 'readings' && $method === 'GET') {
        $sensorId = filtroId($_GET['sensor_id'] ?? '', 'sensor');
        $trainId = filtroId($_GET['train_id'] ?? '', 'trem');
        $sql = 'SELECT r.id,r.value,r.unit,r.recorded_at,s.code AS sensor_code,s.type AS sensor_type,t.code AS train_code FROM sensor_readings r JOIN sensors s ON s.id=r.sensor_id JOIN trains t ON t.id=s.train_id WHERE 1=1';
        $params = [];
        if ($sensorId) { $sql .= ' AND s.id=?'; $params[]=$sensorId; }
        if ($trainId) { $sql .= ' AND t.id=?'; $params[]=$trainId; }
        respond(query($sql.' ORDER BY r.recorded_at DESC,r.id DESC LIMIT 100',$params)->fetchAll());
    }
    if ($path === 'sensor-profiles' && $method === 'GET') respond(array_keys(perfisSensores()));
    if ($path === 'simulator' && $method === 'POST') {
        requireManager();
        $data = body();
        $sensorId = filtroId($data['sensor_id'] ?? '', 'sensor');
        if (!$sensorId) fail('Selecione um sensor.');
        $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1 || $quantity > 100) fail('Gere de 1 a 100 leituras por vez.');
        $outside = $data['outside_limit'] ?? false;
        if (!is_bool($outside)) fail('Opção de limite inválida.');
        $sensor = query('SELECT s.id,s.train_id,s.code,s.type,s.unit FROM sensors s WHERE s.id=?',[$sensorId])->fetch();
        if (!$sensor) fail('Sensor não encontrado.',404);
        if (!isset(perfisSensores()[$sensor['type']])) fail('Tipo de sensor sem faixa de simulação.');
        $reading = db()->prepare('INSERT INTO sensor_readings (sensor_id,value,unit,recorded_at,source,actor_id) VALUES (?,?,?,? ,\'device\',?)');
        $alert = db()->prepare('INSERT INTO alerts (train_id,sensor_id,reading_id,sensor_type,title,message,severity,status,created_at) VALUES (?,?,?,?,?,?,?,\'active\',?)');
        $now = time();
        $alerts = 0;
        db()->beginTransaction();
        for ($i=0; $i<$quantity; $i++) {
            $value = $outside && $i === 0
                ? round(perfisSensores()[$sensor['type']]['limit'] * 1.15, 2)
                : gerarValorLeitura($sensor['type'],$outside);
            $timestamp = gmdate('Y-m-d H:i:s',$now - $i*300);
            $reading->execute([$sensorId,$value,$sensor['unit'],$timestamp,$user['id']]);
            $severity = classificarLeitura($sensor['type'],$value);
            if ($severity !== 'normal') {
                $readingId = (int)db()->lastInsertId();
                $title = $sensor['type'] . ($severity === 'critical' ? ' em nível crítico' : ' acima do limite');
                $message = sprintf('%s registrou %.2f %s.', $sensor['code'], $value, $sensor['unit']);
                $alert->execute([$sensor['train_id'],$sensorId,$readingId,$sensor['type'],$title,$message,$severity === 'critical' ? 'critical' : 'warning',$timestamp]);
                $alerts++;
            }
        }
        audit('simulate','sensor_readings',$sensorId,['quantity'=>$quantity,'outside_limit'=>$outside,'alerts'=>$alerts]);
        db()->commit();
        respond(['readings'=>$quantity,'alerts'=>$alerts],201);
    }
    if ($path === 'operational-alerts' && $method === 'GET') {
        $severity = $_GET['severity'] ?? '';
        if ($severity !== '') enumValue($severity,['warning','critical'],'severity');
        $type = trim((string)($_GET['type'] ?? ''));
        if (strlen($type) > 40) fail('Filtro de tipo inválido.');
        $sql = 'SELECT a.id,a.title,a.message,a.severity,a.created_at,a.sensor_type,r.value,r.unit,s.code AS sensor_code,t.code AS train_code FROM alerts a JOIN sensor_readings r ON r.id=a.reading_id JOIN sensors s ON s.id=r.sensor_id JOIN trains t ON t.id=s.train_id WHERE 1=1';
        $params = [];
        if ($severity !== '') { $sql .= ' AND a.severity=?'; $params[]=$severity; }
        if ($type !== '') { $sql .= ' AND s.type=?'; $params[]=$type; }
        respond(query($sql.' ORDER BY a.created_at DESC,a.id DESC LIMIT 100',$params)->fetchAll());
    }
}
