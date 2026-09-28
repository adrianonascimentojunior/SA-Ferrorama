"""Local integration smoke test. Creates and removes temporary accounts and a train."""
import http.cookiejar
import json
import os
import secrets
import subprocess
import urllib.error
import urllib.request
from pathlib import Path

BASE = 'http://localhost/SA-Ferroama/'
PHP = r'C:\xampp\php\php.exe'
MYSQL = r'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe'
MYSQL_ROOT = [MYSQL, '-h', '127.0.0.1', '-P', '3306', '-u', 'root']
root = Path(__file__).resolve().parents[1]

def mysql_env():
    password = os.environ.get('ATRAIN_MYSQL_ROOT_PASSWORD')
    if not password:
        raise RuntimeError('Defina ATRAIN_MYSQL_ROOT_PASSWORD no ambiente local.')
    return dict(os.environ, MYSQL_PWD=password)

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def call(opener, path, method='GET', payload=None, csrf=''):
    data = None if payload is None else json.dumps(payload).encode()
    headers = {'Content-Type': 'application/json'} if data is not None else {}
    if csrf: headers['X-CSRF-Token'] = csrf
    req = urllib.request.Request(BASE + 'api/' + path, data=data, headers=headers, method=method)
    try:
        with opener.open(req) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)

def expect(status, wanted, response):
    assert status == wanted, (status, wanted, response)

def expect_page(opener, name, wanted=200):
    try:
        with opener.open(BASE + 'index.php?page=' + name) as response:
            status, html = response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        status, html = error.code, error.read().decode()
    assert status == wanted and f'data-page="{name if wanted == 200 else "sem-acesso"}"' in html, (name, status)

def run():
    email = 'smoke-' + secrets.token_hex(6) + '@example.test'
    password = secrets.token_urlsafe(18)
    op = client()
    expect(*((lambda r: (r[0], 401, r[1]))(call(op, 'auth/me'))))
    status, body = call(op, 'auth/register', 'POST', {'name':'Validation User','email':email,'password':password})
    expect(status, 201, body)
    status, body = call(op, 'auth/login', 'POST', {'email':email,'password':password})
    expect(status, 200, body)
    assert body['user']['role'] == 'operator'
    operator_id = int(body['user']['id'])
    csrf = body['csrf']
    for name in ['dashboard','trens','alertas','localizacao','bilhetes','relatorios','notificacoes','perfil','configuracoes','ajuda']:
        expect_page(op, name)
    for name in ['usuarios','admin']:
        expect_page(op, name, 403)
    checks = ['auth/me','dashboard','trains','map','schedules','notifications','notifications/preferences','reports','sensors','profile','profile/export']
    for path in checks:
        status, body = call(op, path)
        expect(status, 200, body)
    status, body = call(op, 'admin/overview')
    expect(status, 403, body)
    subprocess.run([*MYSQL_ROOT,'frota_ferroviaria','-e',f"UPDATE users SET role='manager' WHERE id={operator_id}"],env=mysql_env(),check=True,stdout=subprocess.DEVNULL)
    assert call(op,'auth/me')[1]['user']['role'] == 'manager'
    expect(*((lambda r: (r[0],403,r[1]))(call(op,'admin/overview'))))
    expect(*((lambda r: (r[0],200,r[1]))(call(op,'dashboard'))))
    status, body = call(op, 'trains', 'POST', {'code':'T-' + secrets.token_hex(4).upper(),'name':'Validation Train','type':'locomotive','status':'stopped','capacity':0})
    expect(status, 403, body) # CSRF required
    status, body = call(op, 'trains', 'POST', {'code':'T-' + secrets.token_hex(4).upper(),'name':'Validation Train','type':'locomotive','status':'stopped','capacity':0}, csrf)
    expect(status, 201, body)
    train_id = body['id']
    for path, method, payload, wanted in [
        (f'trains/{train_id}', 'GET', None, 200),
        (f'trains/{train_id}', 'PATCH', {'name':'Validation Updated'}, 200),
        ('support', 'POST', {'subject':'Validation','message':'Temporary request'}, 201),
        ('notifications/preferences', 'PATCH', {'email_enabled':True,'push_enabled':False,'sms_enabled':False}, 200),
        ('profile', 'PATCH', {'name':'Validation Updated','job_title':'Operator'}, 200),
        ('reports?from=2020-01-01&to=2030-01-01', 'GET', None, 200),
        (f'trains/{train_id}', 'DELETE', None, 200),
    ]:
        status, body = call(op, path, method, payload, csrf)
        expect(status, wanted, body)
    schedules = call(op, 'schedules')[1]
    if schedules:
        status, body = call(op, 'tickets/simulate', 'POST', {'schedule_id':schedules[0]['id'],'passengers':2}, csrf)
        expect(status, 201, body)
    alerts = call(op, 'notifications')[1]
    if alerts:
        status, body = call(op, f"notifications/{alerts[0]['id']}/read", 'POST', {}, csrf)
        expect(status, 200, body)
    status, body = call(op, 'auth/forgot', 'POST', {'email':email})
    expect(status, 200, body)
    code = body['development_code']
    next_password = secrets.token_urlsafe(18)
    status, body = call(op, 'auth/reset', 'POST', {'email':email,'code':code,'password':next_password})
    expect(status, 200, body)
    expect(*((lambda r: (r[0],401,r[1]))(call(client(),'auth/login','POST',{'email':email,'password':password}))))
    final_password = secrets.token_urlsafe(18)
    status, body = call(op, 'profile/password', 'POST', {'old_password':next_password,'new_password':final_password}, csrf)
    expect(status, 200, body)
    status, body = call(op, 'profile/delete', 'POST', {'password':final_password}, csrf)
    expect(status, 200, body)
    status, body = call(op, 'auth/me')
    expect(status, 401, body)
    print('Operator/manager: auth, recovery, password, CSRF, role, dashboard, CRUD, support, notifications, reports, simulation, profile, self-delete OK')

    admin_email = 'admin-' + secrets.token_hex(6) + '@example.test'
    admin_password = secrets.token_urlsafe(18)
    env = dict(os.environ, ADMIN_EMAIL=admin_email, ADMIN_PASSWORD=admin_password)
    subprocess.run([PHP, str(root/'scripts/create_admin.php')], env=env, cwd=root, check=True, stdout=subprocess.DEVNULL)
    admin = client()
    status, body = call(admin, 'auth/login', 'POST', {'email':admin_email,'password':admin_password})
    expect(status, 200, body)
    token = body['csrf']
    admin_id = int(body['user']['id'])
    created_alert_id = None
    created_reading_id = None
    try:
        for name in ['usuarios','admin']:
            expect_page(admin, name)
        for path in ['admin/overview','admin/settings','users']:
            status, body = call(admin, path)
            expect(status, 200, body)
        settings = call(admin, 'admin/settings')[1]
        threshold = next(s['value'] for s in settings if s['key']=='critical_temperature_c')
        status, body = call(admin, 'admin/settings', 'PATCH', {'key':'critical_temperature_c','value':threshold}, token)
        expect(status, 200, body)
        sensor = call(admin, 'sensors')[1][0]
        reading_count = len(call(admin, f"sensors/{sensor['id']}/readings")[1])
        status, body = call(admin, f"admin/sensors/{sensor['id']}", 'PATCH', {'status':sensor['status']}, token)
        expect(status, 200, body)
        assert len(call(admin, f"sensors/{sensor['id']}/readings")[1]) == reading_count
        overview = call(admin, 'admin/overview')[1]
        same_value_edits = [
            (f"admin/users/{admin_id}", {'name':body_user['name']})
            for body_user in call(admin, 'users')[1] if int(body_user['id']) == admin_id
        ]
        train = call(admin, 'trains')[1][0]
        same_value_edits.append((f"admin/trains/{train['id']}", {'name':train['name']}))
        maintenance = overview['maintenances'][0]
        same_value_edits.append((f"admin/maintenances/{maintenance['id']}", {'status':maintenance['status']}))
        alert = overview['alerts_list'][0]
        same_value_edits.append((f"admin/alerts/{alert['id']}", {'status':alert['status']}))
        for path, payload in same_value_edits:
            status, body = call(admin, path, 'PATCH', payload, token)
            expect(status, 200, body)
        status, body = call(admin, f"admin/sensors/{sensor['id']}", 'PATCH', {'latest_value':float(threshold)}, token)
        expect(status, 200, body)
        critical = [a for a in call(admin,'notifications')[1] if a['sensor_id']==sensor['id'] and a['severity']=='critical']
        assert critical
        created_alert_id = int(critical[0]['id'])
        created_reading_id = subprocess.check_output([*MYSQL_ROOT,'-N','frota_ferroviaria','-e',f'SELECT MAX(id) FROM sensor_readings WHERE actor_id={admin_id}'],env=mysql_env()).decode().strip()
        print('Super Admin: login, overview, settings, five editors, optional sensor reading, critical alert, audit OK')
    finally:
        statements = []
        if created_alert_id: statements.append(f'DELETE FROM alerts WHERE id={created_alert_id}')
        if created_reading_id and created_reading_id != 'NULL': statements.append(f'DELETE FROM sensor_readings WHERE id={int(created_reading_id)}')
        statements.extend([f'DELETE FROM audit_logs WHERE actor_id IN ({operator_id},{admin_id})',f'DELETE FROM audit_logs WHERE action=\'register\' AND entity_id={operator_id}',f'DELETE FROM users WHERE id={admin_id}'])
        subprocess.run([*MYSQL_ROOT,'frota_ferroviaria','-e','; '.join(statements)],env=mysql_env(),check=True,stdout=subprocess.DEVNULL)

if __name__ == '__main__':
    run()
