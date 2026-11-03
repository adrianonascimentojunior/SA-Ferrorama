<?php
declare(strict_types=1);

function admCamposPermitidosPorEntidade(): array {
    return [
        'users' => ['name', 'job_title', 'role', 'status'],
        'trains' => ['name', 'status', 'capacity', 'latitude', 'longitude', 'station_id'],
        'sensors' => ['status'],
        'maintenances' => ['status', 'notes', 'scheduled_at'],
        'alerts' => ['status', 'severity', 'message'],
    ];
}

function admValoresAceitosPorCampo(string $entity): array {
    $statusPorEntidade = match ($entity) {
        'users' => ['active', 'disabled', 'banned'],
        'trains' => ['operating', 'maintenance', 'stopped', 'inactive'],
        'sensors' => ['active', 'warning', 'offline'],
        'maintenances' => ['pending', 'in_progress', 'completed', 'cancelled'],
        default => ['active', 'resolved'],
    };

    return [
        'role' => ['operator', 'manager', 'super_admin'],
        'status' => $statusPorEntidade,
        'severity' => ['critical', 'warning', 'info', 'system'],
    ];
}

function admValidarCampo(string $entity, string $field, mixed $value, array $data): mixed {
    $enums = admValoresAceitosPorCampo($entity);
    if (isset($enums[$field])) {
        return enumValue($value, $enums[$field], $field);
    }
    if (in_array($field, ['name', 'job_title', 'message', 'notes'], true)) {
        return requiredString($data, $field, $field === 'message' ? 3000 : 180);
    }
    if ($field === 'capacity' && (filter_var($value, FILTER_VALIDATE_INT) === false || $value < 0)) {
        fail('Capacidade inválida.');
    }
    if (in_array($field, ['latitude', 'longitude', 'latest_value'], true) && $value !== null && !is_numeric($value)) {
        fail("Valor inválido: $field.");
    }
    if ($field === 'latest_value' && $value === null) {
        fail('Leitura inválida.');
    }
    if ($field === 'station_id' && $value !== null && !ctype_digit((string)$value)) {
        fail('Estação inválida.');
    }
    if ($field === 'scheduled_at' && strtotime((string)$value) === false) {
        fail('Data inválida.');
    }
    return $value;
}

if (($segments[0] ?? '') === 'admin' || $path === 'users') {
    requireAdmin();

    if ($path === 'users' && $method === 'GET') {
        respond(query('SELECT id, name, email, role, status, job_title, created_at FROM users ORDER BY id')->fetchAll());
    }

    if ($path === 'admin/overview' && $method === 'GET') {
        respond([
            'users' => query('SELECT count(*) FROM users')->fetchColumn(),
            'trains' => query('SELECT count(*) FROM trains')->fetchColumn(),
            'sensors' => query('SELECT count(*) FROM sensors')->fetchColumn(),
            'alerts' => query("SELECT count(*) FROM alerts WHERE status = 'active'")->fetchColumn(),
            'logs' => query(
                'SELECT l.*, u.name AS actor_name FROM audit_logs l LEFT JOIN users u ON u.id = l.actor_id ORDER BY l.created_at DESC LIMIT 40'
            )->fetchAll(),
            'maintenances' => query(
                'SELECT m.*, t.code AS train_code FROM maintenances m JOIN trains t ON t.id = m.train_id ORDER BY m.scheduled_at DESC LIMIT 30'
            )->fetchAll(),
            'alerts_list' => query('SELECT * FROM alerts ORDER BY created_at DESC LIMIT 30')->fetchAll(),
        ]);
    }

    if ($path === 'admin/settings') {
        if ($method === 'GET') {
            respond(query('SELECT * FROM app_settings ORDER BY `key`')->fetchAll());
        }

        if ($method === 'PATCH') {
            $data = body();
            $key = enumValue($data['key'] ?? '', ['db_statement_timeout_ms', 'alert_retention_days', 'maintenance_mode'], 'key');
            $value = (string)($data['value'] ?? '');

            if ($key === 'maintenance_mode') {
                $value = enumValue($value, ['true', 'false'], 'value');
            } else {
                if (!ctype_digit($value)) {
                    fail('Valor numérico inválido.');
                }
                $number = (int)$value;
                $limites = ['db_statement_timeout_ms' => [1000, 30000], 'alert_retention_days' => [7, 3650]];
                [$minimo, $maximo] = $limites[$key];
                if ($number < $minimo || $number > $maximo) {
                    fail("Valor permitido: $minimo a $maximo.");
                }
                $value = (string)$number;
            }

            query('UPDATE app_settings SET value = ? WHERE `key` = ?', [$value, $key]);
            audit('update_setting', 'app_settings', null, ['key' => $key, 'value' => $value]);
            respond(['message' => 'Configuração salva.']);
        }
    }

    if (preg_match('#^admin/(users|trains|sensors|maintenances|alerts)/(\d+)$#', $path, $match) && $method === 'PATCH') {
        $entity = $match[1];
        $id = (int)$match[2];
        $data = body();

        $fields = [];
        $params = [];
        foreach (admCamposPermitidosPorEntidade()[$entity] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $fields[] = "$field = ?";
            $params[] = admValidarCampo($entity, $field, $data[$field], $data);
        }
        if (!$fields) {
            fail('Nenhum campo permitido.');
        }

        $removendoAcessoProprio = $entity === 'users' && $id === (int)$user['id']
            && (($data['role'] ?? 'super_admin') !== 'super_admin' || ($data['status'] ?? 'active') !== 'active');
        if ($removendoAcessoProprio) {
            fail('Não é possível retirar o próprio acesso administrativo.', 409);
        }

        if ($entity === 'alerts' && ($data['status'] ?? null) === 'resolved') {
            $fields[] = 'resolved_at = UTC_TIMESTAMP()';
        }
        if (in_array($entity, ['users', 'trains', 'sensors', 'maintenances'], true)) {
            $fields[] = 'updated_at = UTC_TIMESTAMP()';
        }
        $params[] = $id;

        db()->beginTransaction();
        $atualizados = query('UPDATE ' . $entity . ' SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        if (!$atualizados->rowCount() && !query('SELECT 1 FROM ' . $entity . ' WHERE id = ?', [$id])->fetchColumn()) {
            fail('Registro não encontrado.', 404);
        }
        audit('admin_update', $entity, $id, $data);
        db()->commit();

        respond(['message' => 'Registro atualizado.']);
    }
}
