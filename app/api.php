<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/permissao.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin !== ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'))) fail('Origem não permitida.', 403);
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/');
$path = preg_replace('#^SA-Ferroama/api/?#', '', $path);
$segments = $path === '' ? [] : explode('/', $path);
if ($method === 'OPTIONS') respond(['ok' => true]);

try {
    if ($path === 'health' && $method === 'GET') respond(['status' => 'ok', 'database' => db()->query('SELECT 1')->fetchColumn() == 1]);

    if ($path === 'auth/register' && $method === 'POST') {
        $data = body();
        $name = requiredString($data, 'name', 120);
        $email = strtolower(requiredString($data, 'email'));
        $password = (string)($data['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 128) fail('Informe e-mail válido e senha de 12 a 128 caracteres.');
        if (query('SELECT 1 FROM users WHERE email = ?', [$email])->fetchColumn()) fail('E-mail já cadastrado.', 409);
        $id = query('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]); $id = db()->lastInsertId();
        audit('register', 'users', (int)$id);
        respond(['message' => 'Conta criada. Faça login.'], 201);
    }
    if ($path === 'auth/login' && $method === 'POST') {
        $data = body();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $user = query('SELECT id,password_hash,status FROM users WHERE email = ?', [$email])->fetch();
        if (!$user || !password_verify($password, $user['password_hash']) || $user['status'] !== 'active') fail('Credenciais inválidas.', 401);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        audit('login', 'users', (int)$user['id']);
        respond(['user' => currentUser(), 'csrf' => $_SESSION['csrf']]);
    }
    if ($path === 'auth/me' && $method === 'GET') {
        $user = requireUser();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        respond(['user' => $user, 'csrf' => $_SESSION['csrf']]);
    }
    if ($path === 'auth/logout' && $method === 'POST') {
        requireUser(); csrf();
        audit('logout', 'users', (int)$_SESSION['user_id']);
        encerrarSessao();
        respond(['message' => 'Sessão encerrada.']);
    }
    if ($path === 'auth/forgot' && $method === 'POST') {
        $email = strtolower(trim((string)(body()['email'] ?? '')));
        $userId = query("SELECT id FROM users WHERE email = ? AND status = 'active'", [$email])->fetchColumn();
        $response = ['message' => 'Se a conta existir, o código de recuperação foi gerado.'];
        if ($userId) {
            $code = (string)random_int(100000, 999999);
            query('INSERT INTO password_resets (user_id,code_hash,expires_at,attempts) VALUES (?,?,UTC_TIMESTAMP() + INTERVAL 10 MINUTE,0) ON DUPLICATE KEY UPDATE code_hash=VALUES(code_hash),expires_at=VALUES(expires_at),attempts=0', [$userId, password_hash($code, PASSWORD_DEFAULT)]);
            if (envv('APP_ENV') === 'development') $response['development_code'] = $code;
        }
        respond($response);
    }
    if ($path === 'auth/reset' && $method === 'POST') {
        $data = body();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $code = (string)($data['code'] ?? '');
        $password = (string)($data['password'] ?? '');
        if (!preg_match('/^[0-9]{6}$/', $code) || strlen($password) < 12 || strlen($password) > 128) fail('Código ou senha inválidos.');
        $reset = query('SELECT r.user_id,r.code_hash,r.attempts FROM password_resets r JOIN users u ON u.id=r.user_id WHERE u.email=? AND r.expires_at>UTC_TIMESTAMP()', [$email])->fetch();
        if (!$reset || $reset['attempts'] >= 5) fail('Código inválido ou expirado.', 400);
        if (!password_verify($code, $reset['code_hash'])) {
            query('UPDATE password_resets SET attempts=attempts+1 WHERE user_id=?', [$reset['user_id']]);
            fail('Código inválido ou expirado.', 400);
        }
        db()->beginTransaction();
        query('UPDATE users SET password_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
        query('DELETE FROM password_resets WHERE user_id=?', [$reset['user_id']]);
        db()->commit();
        audit('password_reset', 'users', (int)$reset['user_id']);
        respond(['message' => 'Senha redefinida.']);
    }

    $user = requireUser();
    if (!in_array($method, ['GET', 'HEAD'], true)) csrf();
    if ($user['role'] !== 'super_admin' && query("SELECT value FROM app_settings WHERE `key`='maintenance_mode'")->fetchColumn() === 'true') fail('Plataforma em manutenção. Tente novamente em instantes.', 503);

    if ($path === 'dashboard' && $method === 'GET') {
        $period = enumValue($_GET['period'] ?? '30d', ['7d','30d','90d'], 'period');
        $days = (int)$period;
        $metrics = query("SELECT (SELECT count(*) FROM trains WHERE status='operating') AS operating_trains, (SELECT count(*) FROM sensors WHERE status='active') AS active_sensors, (SELECT count(*) FROM maintenances WHERE DATE(scheduled_at)=UTC_DATE() AND status<>'cancelled') AS maintenances_today, (SELECT count(*) FROM alerts WHERE status='active') AS active_alerts")->fetch();
        $fleet = query('SELECT status,count(*) AS total FROM trains GROUP BY status ORDER BY status')->fetchAll();
        $summary = query('SELECT COALESCE(sum(distance_km),0) AS distance_km, COALESCE(sum(consumption_l),0) AS consumption_l, COALESCE(round(avg(punctuality_pct),1),0) AS punctuality_pct FROM trains')->fetch();
        $trends = query("SELECT DATE(recorded_at) AS day,count(*) AS readings FROM sensor_readings WHERE recorded_at>=DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY) GROUP BY 1 ORDER BY 1", [$days])->fetchAll();
        $maintenance = query('SELECT m.*,t.code AS train_code FROM maintenances m JOIN trains t ON t.id=m.train_id WHERE m.status IN (\'pending\',\'in_progress\') ORDER BY m.scheduled_at LIMIT 5')->fetchAll();
        $alerts = query('SELECT a.*,t.code AS train_code FROM alerts a LEFT JOIN trains t ON t.id=a.train_id ORDER BY a.created_at DESC LIMIT 5')->fetchAll();
        respond(compact('metrics','fleet','summary','trends','maintenance','alerts'));
    }
    if ($path === 'trains' && $method === 'GET') {
        $term = trim((string)($_GET['q'] ?? ''));
        if (strlen($term) > 120) fail('Busca longa demais.');
        $status = $_GET['status'] ?? '';
        if ($status !== '') enumValue($status, ['operating','maintenance','stopped','inactive'], 'status');
        $sql = 'SELECT t.*,s.name AS station_name,(SELECT COUNT(*) FROM sensors se WHERE se.train_id=t.id) AS sensor_count FROM trains t LEFT JOIN stations s ON s.id=t.station_id WHERE (t.code LIKE ? OR t.name LIKE ?)';
        $params = ['%' . $term . '%', '%' . $term . '%'];
        if ($status !== '') { $sql .= ' AND t.status=?'; $params[] = $status; }
        respond(query($sql . ' ORDER BY t.code', $params)->fetchAll());
    }
    if ($path === 'trains' && $method === 'POST') {
        requireManager();
        $data = body();
        $code = trainCode($data);
        $name = requiredString($data,'name',120);
        $type = enumValue($data['type'] ?? '', ['locomotive','composition'], 'type');
        $status = enumValue($data['status'] ?? 'stopped', ['operating','maintenance','stopped','inactive'], 'status');
        $capacity = filter_var($data['capacity'] ?? 0, FILTER_VALIDATE_INT);
        if ($capacity === false || $capacity < 0) fail('Capacidade inválida.');
        $year = modelYear($data['model_year'] ?? null);
        $tons = capacityTons($data['capacity_tons'] ?? null);
        $inspection = inspectionDate($data['last_inspection'] ?? null);
        if (query('SELECT 1 FROM trains WHERE code=?', [$code])->fetchColumn()) fail('Prefixo já cadastrado.',409);
        query('INSERT INTO trains (code,name,type,status,capacity,model_year,capacity_tons,last_inspection) VALUES (?,?,?,?,?,?,?,?)', [$code,$name,$type,$status,$capacity,$year,$tons,$inspection]);
        $id = db()->lastInsertId();
        audit('create','trains',(int)$id,$data);
        respond(['id' => $id],201);
    }
    if (($segments[0] ?? '') === 'trains' && ctype_digit($segments[1] ?? '') && count($segments) === 2) {
        $id = (int)$segments[1];
        if ($method === 'GET') {
            $train = query('SELECT t.*,s.name AS station_name FROM trains t LEFT JOIN stations s ON s.id=t.station_id WHERE t.id=?', [$id])->fetch();
            if (!$train) fail('Trem não encontrado.',404);
            $train['sensors'] = query('SELECT * FROM sensors WHERE train_id=? ORDER BY code',[$id])->fetchAll();
            $train['maintenances'] = query('SELECT * FROM maintenances WHERE train_id=? ORDER BY scheduled_at DESC',[$id])->fetchAll();
            respond($train);
        }
        if ($method === 'PATCH') {
            requireManager();
            $data = body();
            $fields = [];$params=[];
            foreach (['code','name','type','status','capacity','model_year','capacity_tons','last_inspection'] as $key) if (array_key_exists($key,$data)) {
                $value = $data[$key];
                if ($key === 'code') {
                    $value = trainCode($data);
                    if (query('SELECT 1 FROM trains WHERE code=? AND id<>?', [$value,$id])->fetchColumn()) fail('Prefixo já cadastrado.',409);
                }
                if ($key === 'name') $value = requiredString($data,$key,120);
                if ($key === 'type') $value = enumValue($value,['locomotive','composition'],$key);
                if ($key === 'status') $value = enumValue($value,['operating','maintenance','stopped','inactive'],$key);
                if ($key === 'capacity' && (filter_var($value,FILTER_VALIDATE_INT) === false || $value < 0)) fail('Capacidade inválida.');
                if ($key === 'model_year') $value = modelYear($value);
                if ($key === 'capacity_tons') $value = capacityTons($value);
                if ($key === 'last_inspection') $value = inspectionDate($value);
                $fields[] = "$key=?";$params[]=$value;
            }
            if (!$fields) fail('Nenhum campo válido.');
            $params[]=$id;
            $row=query('UPDATE trains SET '.implode(',',$fields).',updated_at=UTC_TIMESTAMP() WHERE id=?',$params);
            if (!$row->rowCount() && !query('SELECT 1 FROM trains WHERE id=?',[$id])->fetchColumn()) fail('Trem não encontrado.',404);
            audit('update','trains',$id,$data);respond(['id'=>$id]);
        }
        if ($method === 'DELETE') {
            requireManager();
            if (query('SELECT 1 FROM sensors WHERE train_id=? LIMIT 1', [$id])->fetchColumn()) fail('Remova ou transfira os sensores vinculados antes de excluir o trem.',409);
            $row=query('DELETE FROM trains WHERE id=?',[$id]);
            if (!$row->rowCount()) fail('Trem não encontrado.',404);
            audit('delete','trains',$id);respond(['message'=>'Trem excluído.']);
        }
    }
    if ($path === 'map' && $method === 'GET') {
        respond(['stations'=>query('SELECT * FROM stations ORDER BY name')->fetchAll(),'trains'=>query('SELECT id,code,name,status,latitude,longitude,station_id FROM trains ORDER BY code')->fetchAll()]);
    }
    if ($path === 'schedules' && $method === 'GET') {
        $origin = (int)($_GET['origin'] ?? 0);$destination=(int)($_GET['destination'] ?? 0);
        $sql='SELECT sc.*,t.code AS train_code,o.name AS origin_name,d.name AS destination_name FROM schedules sc JOIN trains t ON t.id=sc.train_id JOIN stations o ON o.id=sc.origin_id JOIN stations d ON d.id=sc.destination_id WHERE sc.departure_at>UTC_TIMESTAMP()';
        $params=[];
        if ($origin) {$sql.=' AND sc.origin_id=?';$params[]=$origin;}
        if ($destination) {$sql.=' AND sc.destination_id=?';$params[]=$destination;}
        respond(query($sql.' ORDER BY sc.departure_at LIMIT 50',$params)->fetchAll());
    }
    if ($path === 'tickets/simulate' && $method === 'POST') {
        $data=body();$scheduleId=(int)($data['schedule_id']??0);$returnId=(int)($data['return_schedule_id']??0);$passengers=(int)($data['passengers']??1);
        if ($passengers<1 || $passengers>8) fail('Passageiros: 1 a 8.');
        $out=query('SELECT * FROM schedules WHERE id=? AND departure_at>UTC_TIMESTAMP()',[$scheduleId])->fetch();
        if (!$out) fail('Horário de ida indisponível.');
        $back=null;
        if ($returnId) {
            $back=query('SELECT * FROM schedules WHERE id=? AND departure_at>? AND origin_id=? AND destination_id=?',[$returnId,$out['arrival_at'],$out['destination_id'],$out['origin_id']])->fetch();
            if (!$back) fail('Horário de volta inválido.');
        }
        $total=((float)$out['base_price']+(float)($back['base_price']??0))*$passengers;
        $id=query('INSERT INTO ticket_simulations (user_id,schedule_id,return_schedule_id,passengers,total) VALUES (?,?,?,?,?)',[$user['id'],$scheduleId,$returnId?:null,$passengers,$total]); $id = db()->lastInsertId();
        audit('simulate','ticket_simulations',(int)$id);
        respond(['id'=>$id,'total'=>$total,'message'=>'Simulação registrada; nenhuma compra ou reserva foi feita.'],201);
    }
    if ($path === 'notifications' && $method === 'GET') {
        $days=(int)(query("SELECT value FROM app_settings WHERE `key`='alert_retention_days'")->fetchColumn() ?: 90);
        $rows=query('SELECT a.*,t.code AS train_code,(nr.read_at IS NOT NULL) AS is_read FROM alerts a LEFT JOIN trains t ON t.id=a.train_id LEFT JOIN notification_reads nr ON nr.alert_id=a.id AND nr.user_id=? WHERE a.created_at>=DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY) ORDER BY a.created_at DESC LIMIT 100',[$user['id'],$days])->fetchAll();
        respond($rows);
    }
    if (preg_match('#^notifications/(\d+)/read$#',$path,$match) && $method === 'POST') {
        if (!query('SELECT 1 FROM alerts WHERE id=?',[$match[1]])->fetchColumn()) fail('Notificação não encontrada.',404);
        query('INSERT INTO notification_reads (user_id,alert_id) VALUES (?,?) ON DUPLICATE KEY UPDATE read_at=read_at',[$user['id'],$match[1]]);
        respond(['message'=>'Marcada como lida.']);
    }
    if ($path === 'notifications/preferences') {
        if ($method === 'GET') respond(query('SELECT email_enabled,push_enabled,sms_enabled FROM notification_preferences WHERE user_id=?',[$user['id']])->fetch() ?: ['email_enabled'=>true,'push_enabled'=>true,'sms_enabled'=>false]);
        if ($method === 'PATCH') {
            $data=body();$email=(int)filter_var($data['email_enabled']??false,FILTER_VALIDATE_BOOLEAN);$push=(int)filter_var($data['push_enabled']??false,FILTER_VALIDATE_BOOLEAN);$sms=(int)filter_var($data['sms_enabled']??false,FILTER_VALIDATE_BOOLEAN);
            query('INSERT INTO notification_preferences VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE email_enabled=VALUES(email_enabled),push_enabled=VALUES(push_enabled),sms_enabled=VALUES(sms_enabled)',[$user['id'],$email,$push,$sms]);
            respond(['message'=>'Preferências salvas.']);
        }
    }
    if ($path === 'reports' && $method === 'GET') {
        $from=$_GET['from']??date('Y-m-d',strtotime('-30 days'));$to=$_GET['to']??date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$to) || $from>$to) fail('Período inválido.');
        $train=(int)($_GET['train_id']??0);$sensor=(int)($_GET['sensor_id']??0);
        $sql="SELECT t.code AS train_code,s.id AS sensor_id,s.code AS sensor_code,s.type,s.unit,s.status,s.latest_value,s.latest_reading_at, (SELECT count(*) FROM alerts a WHERE a.sensor_id=s.id AND DATE(a.created_at) BETWEEN ? AND ?) AS occurrences FROM sensors s JOIN trains t ON t.id=s.train_id WHERE 1=1";
        $params=[$from,$to];if ($train) {$sql.=' AND t.id=?';$params[]=$train;}if ($sensor) {$sql.=' AND s.id=?';$params[]=$sensor;}
        respond(query($sql.' ORDER BY t.code,s.code',$params)->fetchAll());
    }
    if ($path === 'sensors' && $method === 'GET') respond(query('SELECT s.*,t.code AS train_code FROM sensors s JOIN trains t ON t.id=s.train_id ORDER BY t.code,s.code')->fetchAll());
    if (preg_match('#^sensors/(\d+)/readings$#', $path, $match) && $method === 'GET') {
        if (!query('SELECT 1 FROM sensors WHERE id=?', [$match[1]])->fetchColumn()) fail('Sensor não encontrado.', 404);
        respond(query('SELECT id,value,recorded_at,source FROM sensor_readings WHERE sensor_id=? ORDER BY recorded_at DESC,id DESC LIMIT 100', [$match[1]])->fetchAll());
    }
    if ($path === 'profile') {
        if ($method === 'GET') respond($user);
        if ($method === 'PATCH') {
            $data=body();$name=requiredString($data,'name',120);$title=requiredString($data,'job_title',120);
            query('UPDATE users SET name=?,job_title=?,updated_at=UTC_TIMESTAMP() WHERE id=?',[$name,$title,$user['id']]);
            audit('update_profile','users',(int)$user['id']);respond(currentUser());
        }
    }
    if ($path === 'profile/password' && $method === 'POST') {
        $data=body();$old=(string)($data['old_password']??'');$new=(string)($data['new_password']??'');
        $hash=query('SELECT password_hash FROM users WHERE id=?',[$user['id']])->fetchColumn();
        if (!password_verify($old,$hash) || strlen($new)<12 || strlen($new)>128) fail('Senha atual ou nova senha inválida.');
        query('UPDATE users SET password_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$user['id']]);
        session_regenerate_id(true);audit('change_password','users',(int)$user['id']);respond(['message'=>'Senha atualizada.']);
    }
    if ($path === 'profile/export' && $method === 'GET') respond(['user'=>$user,'ticket_simulations'=>query('SELECT * FROM ticket_simulations WHERE user_id=? ORDER BY created_at DESC',[$user['id']])->fetchAll(),'support_requests'=>query('SELECT * FROM support_requests WHERE user_id=? ORDER BY created_at DESC',[$user['id']])->fetchAll()]);
    if (in_array($path,['profile/deactivate','profile/delete'],true) && $method === 'POST') {
        $password=(string)(body()['password']??'');$hash=query('SELECT password_hash FROM users WHERE id=?',[$user['id']])->fetchColumn();
        if (!password_verify($password,$hash)) fail('Senha incorreta.',403);
        if ($user['role']==='super_admin' && query("SELECT count(*) FROM users WHERE role='super_admin' AND status='active'")->fetchColumn() <= 1) fail('O último Super Admin não pode remover a própria conta.',409);
        if ($path==='profile/delete') {audit('delete_self','users',(int)$user['id']);query('DELETE FROM users WHERE id=?',[$user['id']]);}
        else {query("UPDATE users SET status='disabled' WHERE id=?",[$user['id']]);audit('deactivate_self','users',(int)$user['id']);}
        $_SESSION=[];session_destroy();respond(['message'=>'Conta encerrada.']);
    }
    if ($path === 'support' && $method === 'POST') {
        $data=body();$subject=requiredString($data,'subject',160);$message=requiredString($data,'message',3000);
        $id=query('INSERT INTO support_requests (user_id,subject,message) VALUES (?,?,?)',[$user['id'],$subject,$message]); $id = db()->lastInsertId();
        respond(['id'=>$id,'message'=>'Solicitação registrada.'],201);
    }

    if (($segments[0] ?? '') === 'admin' || $path === 'users') {
        requireAdmin();
        if ($path === 'users' && $method === 'GET') respond(query('SELECT id,name,email,role,status,job_title,created_at FROM users ORDER BY id')->fetchAll());
        if ($path === 'admin/overview' && $method === 'GET') respond([
            'users'=>query('SELECT count(*) FROM users')->fetchColumn(),
            'trains'=>query('SELECT count(*) FROM trains')->fetchColumn(),
            'sensors'=>query('SELECT count(*) FROM sensors')->fetchColumn(),
            'alerts'=>query("SELECT count(*) FROM alerts WHERE status='active'")->fetchColumn(),
            'logs'=>query('SELECT l.*,u.name AS actor_name FROM audit_logs l LEFT JOIN users u ON u.id=l.actor_id ORDER BY l.created_at DESC LIMIT 40')->fetchAll(),
            'maintenances'=>query('SELECT m.*,t.code AS train_code FROM maintenances m JOIN trains t ON t.id=m.train_id ORDER BY m.scheduled_at DESC LIMIT 30')->fetchAll(),
            'alerts_list'=>query('SELECT * FROM alerts ORDER BY created_at DESC LIMIT 30')->fetchAll()
        ]);
        if ($path === 'admin/settings') {
            if ($method === 'GET') respond(query('SELECT * FROM app_settings ORDER BY `key`')->fetchAll());
            if ($method === 'PATCH') {
                $data=body();$key=enumValue($data['key']??'',['db_statement_timeout_ms','alert_retention_days','maintenance_mode','critical_temperature_c'],'key');$value=(string)($data['value']??'');
                if ($key==='maintenance_mode') $value=enumValue($value,['true','false'],'value');
                else {if (!ctype_digit($value)) fail('Valor numérico inválido.');$number=(int)$value;$bounds=['db_statement_timeout_ms'=>[1000,30000],'alert_retention_days'=>[7,3650],'critical_temperature_c'=>[40,150]];[$min,$max]=$bounds[$key];if ($number<$min || $number>$max) fail("Valor permitido: $min a $max.");$value=(string)$number;}
                query('UPDATE app_settings SET value=? WHERE `key`=?',[$value,$key]);audit('update_setting','app_settings',null,['key'=>$key,'value'=>$value]);respond(['message'=>'Configuração salva.']);
            }
        }
        if (preg_match('#^admin/(users|trains|sensors|maintenances|alerts)/(\d+)$#',$path,$match) && $method === 'PATCH') {
            $entity=$match[1];$id=(int)$match[2];$data=body();
            $allowed=[
                'users'=>['name','job_title','role','status'],
                'trains'=>['name','status','capacity','latitude','longitude','station_id'],
                'sensors'=>['status','latest_value'],
                'maintenances'=>['status','notes','scheduled_at'],
                'alerts'=>['status','severity','message']
            ];
            $enums=['role'=>['operator','manager','super_admin'],'status'=>match($entity){'users'=>['active','disabled','banned'],'trains'=>['operating','maintenance','stopped','inactive'],'sensors'=>['active','warning','offline'],'maintenances'=>['pending','in_progress','completed','cancelled'],default=>['active','resolved']},'severity'=>['critical','warning','info','system']];
            $fields=[];$params=[];
            foreach ($allowed[$entity] as $field) if (array_key_exists($field,$data)) {
                $value=$data[$field];
                if (isset($enums[$field])) $value=enumValue($value,$enums[$field],$field);
                if (in_array($field,['name','job_title','message','notes'],true)) $value=requiredString($data,$field,$field==='message'?3000:180);
                if ($field==='capacity' && (filter_var($value,FILTER_VALIDATE_INT)===false || $value<0)) fail('Capacidade inválida.');
                if (in_array($field,['latitude','longitude','latest_value'],true) && $value!==null && !is_numeric($value)) fail("Valor inválido: $field.");
                if ($field==='latest_value' && $value===null) fail('Leitura inválida.');
                if ($field==='station_id' && $value!==null && !ctype_digit((string)$value)) fail('Estação inválida.');
                if ($field==='scheduled_at' && strtotime((string)$value)===false) fail('Data inválida.');
                $fields[]="$field=?";$params[]=$value;
            }
            if (!$fields) fail('Nenhum campo permitido.');
            if ($entity==='users' && $id===(int)$user['id'] && (($data['role']??'super_admin')!=='super_admin' || ($data['status']??'active')!=='active')) fail('Não é possível retirar o próprio acesso administrativo.',409);
            if ($entity==='sensors' && array_key_exists('latest_value',$data)) $fields[]='latest_reading_at=UTC_TIMESTAMP()';
            if ($entity==='alerts' && ($data['status']??null)==='resolved') $fields[]='resolved_at=UTC_TIMESTAMP()';
            if (in_array($entity,['users','trains','sensors','maintenances'],true)) $fields[]='updated_at=UTC_TIMESTAMP()';
            $params[]=$id;
            db()->beginTransaction();
            $row=query('UPDATE '.$entity.' SET '.implode(',',$fields).' WHERE id=?',$params);
            if (!$row->rowCount() && !query('SELECT 1 FROM '.$entity.' WHERE id=?',[$id])->fetchColumn()) fail('Registro não encontrado.',404);
            if ($entity==='sensors' && array_key_exists('latest_value',$data) && $data['latest_value']!==null) {
                query('INSERT INTO sensor_readings (sensor_id,value,source,actor_id) VALUES (?,?,\'admin\',?)',[$id,$data['latest_value'],$user['id']]);
                $sensor=query('SELECT train_id,code,type FROM sensors WHERE id=?',[$id])->fetch();
                $threshold=(float)(query("SELECT value FROM app_settings WHERE `key`='critical_temperature_c'")->fetchColumn() ?: 85);
                if ($sensor['type']==='Temperatura' && (float)$data['latest_value'] >= $threshold) query("INSERT INTO alerts (train_id,sensor_id,title,message,severity) VALUES (?,?,? ,?,'critical')",[$sensor['train_id'],$id,'Temperatura crítica','Leitura manual de '.$sensor['code'].' acima do limite configurado.']);
            }
            audit('admin_update',$entity,$id,$data);
            db()->commit();
            respond(['message'=>'Registro atualizado.']);
        }
    }
    fail('Endpoint não encontrado.',404);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable) { /* Conexão pode ter falhado antes de iniciar a transação. */ }
    if (($exception->errorInfo[1] ?? null) === 1062) fail('Registro duplicado.',409);
    if (in_array(($exception->errorInfo[1] ?? null), [1451,1452], true)) fail('Registro referenciado por outros dados.',409);
    fail('Erro de banco de dados.',500);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    fail('Erro interno.',500);
}
