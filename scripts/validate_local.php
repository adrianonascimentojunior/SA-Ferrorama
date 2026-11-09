<?php
declare(strict_types=1);

// Validação HTTP com a mesma stack PHP da aplicação. Use somente banco isolado.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$database = getenv('DB_NAME');
if (!is_string($database) || $database === '' || $database === 'frota_ferroviaria') {
    fwrite(STDERR, "Defina DB_NAME para um banco de testes isolado.\n");
    exit(1);
}

$mode = $argv[1] ?? '--all';
if (!in_array($mode, ['--all', '--block2'], true)) {
    fwrite(STDERR, "Uso: php scripts/validate_local.php [--all|--block2]\n");
    exit(1);
}

require_once __DIR__ . '/../app/bootstrap.php';

final class HttpTestClient
{
    private array $cookies = [];

    public function __construct(private readonly string $base)
    {
    }

    public function request(string $path, string $method = 'GET', ?array $data = null, ?string $csrf = null, bool $form = false): array
    {
        $headers = [];
        $content = null;
        if ($data !== null) {
            $content = $form ? http_build_query($data) : json_encode($data, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: ' . ($form ? 'application/x-www-form-urlencoded' : 'application/json');
        }
        if ($csrf !== null) {
            $headers[] = 'X-CSRF-Token: ' . $csrf;
        }
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }
        $options = ['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10,
        ]];
        if ($content !== null) {
            $options['http']['content'] = $content;
        }
        $raw = @file_get_contents($this->base . ltrim($path, '/'), false, stream_context_create($options));
        $responseHeaders = $http_response_header ?? [];
        if ($raw === false || !$responseHeaders) {
            throw new RuntimeException('Servidor HTTP indisponível em ' . $this->base);
        }
        $status = 0;
        $namedHeaders = [];
        foreach ($responseHeaders as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $match)) {
                $status = (int)$match[1];
                continue;
            }
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            $value = trim($value);
            $namedHeaders[$name] = $value;
            if ($name === 'set-cookie' && preg_match('/^([^=;]+)=([^;]*)/', $value, $cookie)) {
                if ($cookie[2] === '') {
                    unset($this->cookies[$cookie[1]]);
                } else {
                    $this->cookies[$cookie[1]] = $cookie[2];
                }
            }
        }
        $body = json_decode($raw, true);
        return ['status' => $status, 'body' => json_last_error() === JSON_ERROR_NONE ? $body : $raw, 'headers' => $namedHeaders];
    }
}

$base = rtrim(getenv('ATRAIN_TEST_BASE_URL') ?: 'http://127.0.0.1:8888/SA-Ferrorama-main/', '/') . '/';
$checks = 0;
function verify(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('Falhou: ' . $label);
    }
    $checks++;
}
function expect(array $response, int $status, string $label): mixed
{
    verify($response['status'] === $status, $label . ' (HTTP ' . $response['status'] . ', esperado ' . $status . ': ' . substr(json_encode($response['body'], JSON_UNESCAPED_UNICODE) ?: '', 0, 180) . ')');
    return $response['body'];
}
function api(HttpTestClient $client, string $path, string $method = 'GET', ?array $data = null, ?string $csrf = null): array
{
    return $client->request('api/' . $path, $method, $data, $csrf);
}

$anonymous = new HttpTestClient($base);
expect($anonymous->request('trens.php'), 302, 'tela de trens exige login');
expect($anonymous->request('sensores_form.php'), 302, 'formulário de sensor exige login');
expect(api($anonymous, 'trains', 'POST', []), 401, 'API rejeita escrita anônima');

$suffix = bin2hex(random_bytes(5));
$password = bin2hex(random_bytes(18));
$adminEmail = 'bloco2-admin-' . $suffix . '@example.invalid';
$operatorEmail = 'bloco2-operador-' . $suffix . '@example.invalid';
$adminId = null;
$operatorId = null;
$trainId = null;
$sensorId = null;
$routeId = null;

try {
    query("INSERT INTO users (name,email,password_hash,role,job_title) VALUES (?,?,?,'super_admin','Administrador de validação')", ['Admin de validação', $adminEmail, password_hash($password, PASSWORD_DEFAULT)]);
    $adminId = (int)db()->lastInsertId();
    $admin = new HttpTestClient($base);
    $adminSession = expect(api($admin, 'auth/login', 'POST', ['email' => $adminEmail, 'password' => $password]), 200, 'login administrativo');
    $adminCsrf = $adminSession['csrf'];
    expect($admin->request('trens.php'), 200, 'sessão entre páginas de trens');
    expect($admin->request('sensores.php'), 200, 'sessão entre páginas de sensores');
    expect(api($admin, 'auth/me'), 200, 'sessão persistida na API');
    foreach (['dashboard', 'map', 'schedules', 'notifications', 'reports'] as $path) {
        expect(api($admin, $path), 200, 'endpoint existente ' . $path);
    }

    $operator = new HttpTestClient($base);
    expect(api($operator, 'auth/register', 'POST', ['name' => 'Operador de validação', 'email' => $operatorEmail, 'password' => $password]), 201, 'cadastro existente');
    $operatorSession = expect(api($operator, 'auth/login', 'POST', ['email' => $operatorEmail, 'password' => $password]), 200, 'login operador');
    $operatorId = (int)$operatorSession['user']['id'];
    $operatorCsrf = $operatorSession['csrf'];
    expect($operator->request('trens.php'), 403, 'operador sem tela de gestão de trens');
    expect($operator->request('sensores.php'), 403, 'operador sem tela de gestão de sensores');
    expect($operator->request('rotas.php'), 403, 'operador sem tela de gestão de rotas');
    expect($operator->request('simulador.php'), 403, 'operador sem simulador');
    expect($operator->request('leituras.php'), 200, 'operador pode consultar leituras');
    expect($operator->request('notificacoes_alertas.php'), 200, 'operador pode consultar alertas');
    expect(api($operator, 'trains', 'POST', [], $operatorCsrf), 403, 'operador sem escrita de trens');
    expect(api($operator, 'sensors', 'POST', [], $operatorCsrf), 403, 'operador sem escrita de sensores');
    expect(api($operator, 'routes', 'POST', [], $operatorCsrf), 403, 'operador sem escrita de rotas');
    expect(api($operator, 'simulator', 'POST', [], $operatorCsrf), 403, 'operador sem acesso ao simulador');
    expect(api($operator, 'admin/overview'), 403, 'operador sem painel administrativo');

    query("UPDATE users SET role='manager' WHERE id=?", [$operatorId]);
    expect($operator->request('trens.php'), 200, 'gerente acessa trens');
    expect($operator->request('sensores.php'), 200, 'gerente acessa sensores');
    expect(api($operator, 'admin/overview'), 403, 'gerente sem painel de super admin');

    $code = 'TR-' . random_int(100000, 999999);
    $train = ['code' => $code, 'name' => 'Trem de validação', 'type' => 'locomotive', 'status' => 'stopped', 'model_year' => 2020, 'capacity_tons' => '135.50', 'last_inspection' => '2026-09-10'];
    expect(api($operator, 'trains', 'POST', $train), 403, 'cadastro de trem exige CSRF');
    $trainId = (int)expect(api($operator, 'trains', 'POST', $train, $operatorCsrf), 201, 'cadastrar trem')['id'];
    expect(api($operator, 'trains', 'POST', $train, $operatorCsrf), 409, 'prefixo duplicado');
    expect(api($operator, 'trains', 'POST', array_replace($train, ['code' => 'ABC']), $operatorCsrf), 400, 'prefixo inválido');
    expect(api($operator, 'trains', 'POST', array_replace($train, ['code' => 'TR-999', 'capacity_tons' => 0]), $operatorCsrf), 400, 'capacidade inválida');
    $rows = expect(api($operator, 'trains?q=' . $code . '&status=stopped'), 200, 'buscar e filtrar trem');
    verify(count(array_filter($rows, fn($row) => (int)$row['id'] === $trainId)) === 1, 'trem encontrado na busca');
    expect(api($operator, 'trains/' . $trainId, 'PATCH', ['name' => 'Trem editado'], $operatorCsrf), 200, 'editar trem');
    verify(expect(api($operator, 'trains/' . $trainId), 200, 'reler trem')['name'] === 'Trem editado', 'edição persistida');

    $sensor = ['train_id' => $trainId, 'code' => 'S-TEMP-' . random_int(100000, 999999), 'type' => 'Temperatura', 'unit' => '°C', 'location' => 'Motor', 'segment' => 'Pátio de testes'];
    expect(api($operator, 'sensors', 'POST', array_replace($sensor, ['train_id' => 999999999]), $operatorCsrf), 400, 'trem inexistente');
    $sensorId = (int)expect(api($operator, 'sensors', 'POST', $sensor, $operatorCsrf), 201, 'cadastrar sensor')['id'];
    expect(api($operator, 'sensors', 'POST', $sensor, $operatorCsrf), 409, 'código de sensor duplicado');
    expect(api($operator, 'trains/' . $trainId . '/delete', 'POST', null, $operatorCsrf), 409, 'trem vinculado protegido');
    $sensors = expect(api($operator, 'sensors?type=Temperatura'), 200, 'filtro de sensores');
    verify(count(array_filter($sensors, fn($row) => (int)$row['id'] === $sensorId && $row['train_code'] === $code)) === 1, 'sensor mostra prefixo do trem');
    expect(api($operator, 'sensors/' . $sensorId, 'PATCH', ['location' => 'Cabine'], $operatorCsrf), 200, 'editar sensor');
    $sensorRead = expect(api($operator, 'sensors/' . $sensorId), 200, 'reler sensor');
    verify($sensorRead['location'] === 'Cabine' && $sensorRead['reading_indicator'] === null, 'sensor editado continua sem leitura');
    if ($mode === '--all') {
        expect($operator->request('rotas.php'), 200, 'tela de rotas');
        expect($operator->request('rotas_form.php'), 200, 'formulário de rota');
        expect($operator->request('leituras.php'), 200, 'tela de leituras');
        expect($operator->request('simulador.php'), 200, 'tela do simulador');
        expect($operator->request('notificacoes_alertas.php'), 200, 'central de alertas');
        $route = ['train_id'=>$trainId,'origin'=>'Pátio Norte','destination'=>'Pátio Sul','travel_date'=>'2026-10-20','departure_time'=>'08:00','arrival_time'=>'09:30','status'=>'scheduled'];
        expect(api($operator, 'routes', 'POST', $route), 403, 'rota exige CSRF');
        expect(api($operator, 'routes', 'POST', array_replace($route,['destination'=>'Pátio Norte']), $operatorCsrf), 400, 'rota rejeita origem igual ao destino');
        $routeId=(int)expect(api($operator, 'routes', 'POST', $route, $operatorCsrf), 201, 'cadastrar rota')['id'];
        $filtered=expect(api($operator, 'routes?status=scheduled&from=2026-10-20&to=2026-10-20&train_id='.$trainId.'&q=Sul'), 200, 'quatro filtros combinados');
        verify(count($filtered)===1 && (int)$filtered[0]['id']===$routeId && $filtered[0]['train_code']===$code, 'rota vinculada ao trem');
        foreach (['status=cancelled','from=2026-10-21&to=2026-10-22','train_id=999999999','q=Inexistente'] as $filter) {
            $rows=expect(api($operator, 'routes?'.$filter), 200, 'filtro de rota isolado');
            verify(count(array_filter($rows, fn($row)=>(int)$row['id']===$routeId))===0, 'filtro exclui rota incompatível');
        }
        expect(api($operator, 'routes/'.$routeId, 'PATCH', array_replace($route,['status'=>'completed']), $operatorCsrf), 200, 'editar rota');
        verify(expect(api($operator, 'routes/'.$routeId), 200, 'reler rota')['status']==='completed', 'edição de rota persistida');
        expect(api($operator, 'trains/'.$trainId.'/delete', 'POST', null, $operatorCsrf), 409, 'rota impede excluir trem');
        expect(api($operator, 'simulator', 'POST', ['sensor_id'=>$sensorId,'quantity'=>2,'outside_limit'=>false]), 403, 'simulador exige CSRF');
        $normal=expect(api($operator, 'simulator', 'POST', ['sensor_id'=>$sensorId,'quantity'=>3,'outside_limit'=>false], $operatorCsrf), 201, 'simular leituras normais');
        verify($normal['readings']===3 && $normal['alerts']===0, 'leituras normais não geram alerta');
        $generated=expect(api($operator, 'simulator', 'POST', ['sensor_id'=>$sensorId,'quantity'=>4,'outside_limit'=>true], $operatorCsrf), 201, 'simular acima do limite');
        verify($generated['readings']===4 && $generated['alerts']===4, 'cada leitura anormal gera alerta');
        $readings=expect(api($operator, 'readings?sensor_id='.$sensorId.'&train_id='.$trainId), 200, 'filtrar leituras por sensor e trem');
        verify(count($readings)===7 && $readings[0]['sensor_code']===$sensor['code'] && $readings[0]['train_code']===$code, 'leituras ordenadas e vinculadas');
        $calculated=expect(api($operator, 'sensors/'.$sensorId), 200, 'indicador calculado');
        verify($calculated['reading_indicator']==='critical' && $calculated['latest_value']!==null, 'sensor mostra última leitura crítica');
        $alerts=expect(api($operator, 'operational-alerts?severity=critical&type=Temperatura'), 200, 'filtrar alertas por severidade e tipo');
        verify(count(array_filter($alerts, fn($row)=>$row['sensor_code']===$sensor['code'] && $row['train_code']===$code))>=1, 'alerta crítico chega ao trem por leitura e sensor');
        expect(api($operator, 'routes/'.$routeId.'/delete', 'POST', null, $operatorCsrf), 200, 'excluir rota');
        $routeId=null;
    }
    expect(api($operator, 'sensors/' . $sensorId . '/delete', 'POST', null, $operatorCsrf), 200, 'excluir sensor');
    $sensorId = null;
    expect(api($operator, 'trains/' . $trainId . '/delete', 'POST', null, $operatorCsrf), 200, 'excluir trem');
    $trainId = null;

    if ($mode === '--all') {
        foreach (['dashboard', 'trains', 'map', 'schedules', 'notifications', 'notifications/preferences', 'reports', 'sensors', 'profile', 'profile/export'] as $path) {
            expect(api($operator, $path), 200, 'fluxo existente ' . $path);
        }
        expect(api($operator, 'support', 'POST', ['subject' => 'Validação', 'message' => 'Solicitação temporária'], $operatorCsrf), 201, 'suporte');
        expect(api($operator, 'notifications/preferences', 'PATCH', ['email_enabled' => true, 'push_enabled' => false, 'sms_enabled' => false], $operatorCsrf), 200, 'preferências');
        expect(api($operator, 'profile', 'PATCH', ['name' => 'Operador atualizado', 'job_title' => 'Operador'], $operatorCsrf), 200, 'perfil');
        expect(api($operator, 'reports?from=2020-01-01&to=2030-01-01'), 200, 'relatório');
        $schedules = expect(api($operator, 'schedules'), 200, 'horários');
        if ($schedules) {
            expect(api($operator, 'tickets/simulate', 'POST', ['schedule_id' => $schedules[0]['id'], 'passengers' => 2], $operatorCsrf), 201, 'simulação existente');
        }
        $alerts = expect(api($operator, 'notifications'), 200, 'notificações');
        if ($alerts) {
            expect(api($operator, 'notifications/' . $alerts[0]['id'] . '/read', 'POST', [], $operatorCsrf), 200, 'marcar notificação');
        }
        foreach (['admin/overview', 'admin/settings', 'users'] as $path) {
            expect(api($admin, $path), 200, 'admin ' . $path);
        }
    }

    expect(api($operator, 'auth/logout', 'POST', null, $operatorCsrf), 200, 'logout da API');
    expect(api($operator, 'auth/me'), 401, 'sessão da API encerrada');
    expect($admin->request('sair.php', 'POST', ['csrf' => $adminCsrf], null, true), 303, 'logout por formulário');
    expect(api($admin, 'auth/me'), 401, 'sessão do formulário encerrada');
    expect($admin->request('trens.php'), 302, 'voltar à tela interna exige login');
    echo "Validação PHP concluída: $checks verificações ($mode).\n";
} finally {
    if ($routeId !== null) {
        query('DELETE FROM routes WHERE id=?', [$routeId]);
    }
    if ($sensorId !== null) {
        query('DELETE FROM sensors WHERE id=?', [$sensorId]);
    }
    if ($trainId !== null) {
        query('DELETE FROM trains WHERE id=?', [$trainId]);
    }
    if ($operatorId !== null) {
        query('DELETE FROM users WHERE id=?', [$operatorId]);
    }
    if ($adminId !== null) {
        query('DELETE FROM users WHERE id=?', [$adminId]);
    }
}
