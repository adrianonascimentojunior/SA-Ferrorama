<?php
declare(strict_types=1);

/**
 * Rotas do Painel Admin (somente super_admin).
 *
 * O que cada ação grava no banco:
 *   PATCH admin/users/{id}         -> users          (name, job_title, role, status)
 *   PATCH admin/trains/{id}        -> trains         (name, status, capacity_tons, latitude, longitude, station_id)
 *   PATCH admin/sensors/{id}       -> sensors        (status)
 *   PATCH admin/maintenances/{id}  -> maintenances   (status, notes, scheduled_at)
 *   PATCH admin/alerts/{id}        -> alerts         (status, severity, message; resolved_at é derivado do status)
 *   PATCH admin/settings           -> app_settings   (value)
 * Toda gravação também insere uma linha em audit_logs com {campo: {before, after}}.
 * Somente as colunas que realmente mudaram são gravadas.
 */

function admCamposPermitidosPorEntidade(): array {
    return [
        'users' => ['name', 'job_title', 'role', 'status'],
        'trains' => ['name', 'status', 'capacity_tons', 'latitude', 'longitude', 'station_id'],
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
    if ($value === '') {
        $value = null;
    }
    if (in_array($field, ['name', 'job_title', 'message'], true)) {
        return requiredString($data, $field, $field === 'message' ? 3000 : 180);
    }
    if ($field === 'notes') {
        return optionalText($value, 'notes', 3000);
    }
    if ($field === 'capacity_tons') {
        return capacityTons($value);
    }
    if (in_array($field, ['latitude', 'longitude'], true)) {
        if ($value === null) {
            return null;
        }
        $limite = $field === 'latitude' ? 90 : 180;
        if (!is_numeric($value) || abs((float)$value) > $limite) {
            fail("Valor inválido: $field deve estar entre -$limite e $limite.");
        }
        return (string)$value;
    }
    if ($field === 'station_id') {
        if ($value === null) {
            return null;
        }
        if (!ctype_digit((string)$value) || !query('SELECT 1 FROM stations WHERE id = ?', [(int)$value])->fetchColumn()) {
            fail('Estação inválida.');
        }
        return (int)$value;
    }
    if ($field === 'scheduled_at') {
        $time = $value === null ? false : strtotime((string)$value . ' UTC');
        if ($time === false) {
            fail('Data inválida.');
        }
        return gmdate('Y-m-d H:i:s', $time);
    }
    return $value;
}

/** Compara o valor salvo com o novo, tolerando formatos do MySQL (135.50 = 135.5). */
function admValoresIguais(mixed $antes, mixed $depois): bool {
    if ($antes === null || $depois === null) {
        return $antes === $depois;
    }
    if (is_numeric($antes) && is_numeric($depois)) {
        return (float)$antes === (float)$depois;
    }
    return (string)$antes === (string)$depois;
}

/** Tabelas da aplicação para contagem de linhas (lista fixa, nunca vinda do cliente). */
function admTabelasDaAplicacao(): array {
    return ['users', 'password_resets', 'stations', 'trains', 'sensors', 'routes', 'sensor_readings', 'maintenances',
        'alerts', 'notification_reads', 'notification_preferences', 'schedules', 'ticket_simulations',
        'support_requests', 'app_settings', 'audit_logs'];
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
                'SELECT l.*, u.name AS actor_name FROM audit_logs l LEFT JOIN users u ON u.id = l.actor_id ORDER BY l.created_at DESC, l.id DESC LIMIT 40'
            )->fetchAll(),
            'maintenances' => query(
                'SELECT m.*, t.code AS train_code FROM maintenances m JOIN trains t ON t.id = m.train_id ORDER BY m.scheduled_at DESC LIMIT 30'
            )->fetchAll(),
            'alerts_list' => query('SELECT * FROM alerts ORDER BY created_at DESC LIMIT 30')->fetchAll(),
        ]);
    }

    // Matriz de acesso por papel (fonte única: includes/permissao.php).
    if ($path === 'admin/access' && $method === 'GET') {
        respond([
            'roles' => PAPEIS,
            'matrix' => matrizDeAcesso(),
            'current_role' => $user['role'],
        ]);
    }

    // Conexão com o banco e volume de dados (nunca expõe a senha).
    if ($path === 'admin/system' && $method === 'GET') {
        $tabelas = [];
        foreach (admTabelasDaAplicacao() as $tabela) {
            $tabelas[] = ['table' => $tabela, 'rows' => (int)query("SELECT COUNT(*) FROM `$tabela`")->fetchColumn()];
        }
        respond([
            'api' => 'ok',
            'database' => [
                'connected' => query('SELECT 1')->fetchColumn() == 1,
                'host' => envv('DB_HOST', 'localhost') . ':' . envv('DB_PORT', '3306'),
                'name' => (string)query('SELECT DATABASE()')->fetchColumn(),
                'user' => envv('DB_USER', 'root'),
                'version' => (string)query('SELECT VERSION()')->fetchColumn(),
                'timezone' => 'UTC',
            ],
            'maintenance_mode' => modoDeManutencaoAtivo(),
            'environment' => envv('APP_ENV', 'development'),
            'server_time_utc' => gmdate('Y-m-d H:i:s'),
            'tables' => $tabelas,
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

            $antes = query('SELECT value FROM app_settings WHERE `key` = ?', [$key])->fetchColumn();
            if ($antes === false) {
                fail('Configuração não encontrada.', 404);
            }
            if ((string)$antes === $value) {
                respond(['message' => 'Nenhuma alteração: o valor já era este.', 'table' => 'app_settings', 'key' => $key, 'changes' => []]);
            }

            db()->beginTransaction();
            query('UPDATE app_settings SET value = ? WHERE `key` = ?', [$value, $key]);
            audit('update_setting', 'app_settings', null, ['key' => $key, 'changes' => ['value' => ['before' => $antes, 'after' => $value]]]);
            db()->commit();
            respond([
                'message' => 'Configuração salva.',
                'table' => 'app_settings',
                'key' => $key,
                'changes' => [['field' => 'value', 'before' => $antes, 'after' => $value]],
            ]);
        }
    }

    if (preg_match('#^admin/(users|trains|sensors|maintenances|alerts)/(\d+)$#', $path, $match) && $method === 'PATCH') {
        $entity = $match[1];
        $id = (int)$match[2];
        $data = body();

        db()->beginTransaction();
        $antes = query('SELECT * FROM ' . $entity . ' WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$antes) {
            fail('Registro não encontrado.', 404);
        }

        // Só entram na lista as colunas que realmente mudam.
        $novos = [];
        $changes = [];
        foreach (admCamposPermitidosPorEntidade()[$entity] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $valor = admValidarCampo($entity, $field, $data[$field], $data);
            if (admValoresIguais($antes[$field], $valor)) {
                continue;
            }
            $novos[$field] = $valor;
            $changes[] = ['field' => $field, 'before' => $antes[$field], 'after' => $valor];
        }
        if (!$changes) {
            db()->rollBack();
            respond(['message' => 'Nenhuma alteração: os valores enviados já são os salvos.', 'table' => $entity, 'id' => $id, 'changes' => []]);
        }

        $removendoAcessoProprio = $entity === 'users' && $id === (int)$user['id']
            && (($novos['role'] ?? 'super_admin') !== 'super_admin' || ($novos['status'] ?? 'active') !== 'active');
        if ($removendoAcessoProprio) {
            fail('Não é possível retirar o próprio acesso administrativo.', 409);
        }

        $sets = [];
        $params = [];
        foreach ($novos as $field => $valor) {
            $sets[] = "$field = ?";
            $params[] = $valor;
        }
        if ($entity === 'alerts' && isset($novos['status'])) {
            $sets[] = $novos['status'] === 'resolved' ? 'resolved_at = UTC_TIMESTAMP()' : 'resolved_at = NULL';
        }
        if (in_array($entity, ['users', 'trains', 'sensors', 'maintenances'], true)) {
            $sets[] = 'updated_at = UTC_TIMESTAMP()';
        }
        $params[] = $id;

        query('UPDATE ' . $entity . ' SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

        $detalhes = [];
        foreach ($changes as $c) {
            $detalhes[$c['field']] = ['before' => $c['before'], 'after' => $c['after']];
        }
        audit('admin_update', $entity, $id, ['changes' => $detalhes]);
        db()->commit();

        respond(['message' => 'Registro atualizado.', 'table' => $entity, 'id' => $id, 'changes' => $changes]);
    }
}
