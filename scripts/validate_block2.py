"""HTTP integration check for session, logout, trains and sensors on an isolated database.

Set ATRAIN_TEST_BASE_URL, DB_NAME and ATRAIN_MYSQL_ROOT_PASSWORD before running.
The database must already contain schema.mysql.sql and seed.mysql.sql.
"""

import json
import os
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from http.cookiejar import CookieJar


BASE = os.environ.get("ATRAIN_TEST_BASE_URL", "http://127.0.0.1:8888/SA-Ferroama/")
DATABASE = os.environ["DB_NAME"]
if DATABASE == "frota_ferroviaria":
    raise SystemExit("Use um banco de testes isolado para esta validação.")
MYSQL = os.environ.get("ATRAIN_MYSQL_CLI", r"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe")
PHP = os.environ.get("ATRAIN_PHP_CLI", r"C:\xampp\php\php.exe")


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, newurl):
        return None


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()), NoRedirect())


def call(opener, path, method="GET", data=None, csrf=None):
    headers = {}
    payload = None
    if data is not None:
        payload = json.dumps(data).encode()
        headers["Content-Type"] = "application/json"
    if csrf:
        headers["X-CSRF-Token"] = csrf
    request = urllib.request.Request(BASE + path, data=payload, headers=headers, method=method)
    try:
        response = opener.open(request)
    except urllib.error.HTTPError as error:
        response = error
    raw = response.read()
    try:
        body = json.loads(raw)
    except ValueError:
        body = raw.decode(errors="replace")
    return response.status, body, response.headers


def expect(actual, status, label):
    assert actual[0] == status, f"{label}: expected {status}, got {actual[0]}: {actual[1]}"
    return actual[1]


def mysql(statement):
    environment = os.environ.copy()
    environment["MYSQL_PWD"] = environment["ATRAIN_MYSQL_ROOT_PASSWORD"]
    subprocess.run([MYSQL, "-h", "127.0.0.1", "-u", "root", DATABASE, "-e", statement], env=environment, check=True, capture_output=True)


suffix = secrets.token_hex(4)
admin_email = f"block2-admin-{suffix}@example.invalid"
admin_password = secrets.token_urlsafe(18)
environment = os.environ.copy()
environment.update(ADMIN_EMAIL=admin_email, ADMIN_PASSWORD=admin_password)
subprocess.run([PHP, "scripts/create_admin.php"], env=environment, check=True, capture_output=True)

anonymous = client()
assert call(anonymous, "trens.php")[0] == 302
assert call(anonymous, "sensores_form.php")[0] == 302
expect(call(anonymous, "api/trains", "POST", {}), 401, "anonymous train write")

admin = client()
session = expect(call(admin, "api/auth/login", "POST", {"email": admin_email, "password": admin_password}), 200, "admin login")
token = session["csrf"]
assert call(admin, "trens.php")[0] == 200
assert call(admin, "sensores.php")[0] == 200
assert call(admin, "api/auth/me")[0] == 200

code = "TR-" + str(secrets.randbelow(900000) + 100000)
train = {"code": code, "name": "Trem de validação", "type": "locomotive", "status": "stopped", "model_year": 2020, "capacity_tons": "135.50", "last_inspection": "2026-09-10"}
expect(call(admin, "api/trains", "POST", train), 403, "train CSRF")
train_id = expect(call(admin, "api/trains", "POST", train, token), 201, "create train")["id"]
expect(call(admin, "api/trains", "POST", train, token), 409, "duplicate prefix")
expect(call(admin, "api/trains", "POST", {**train, "code": "ABC", "model_year": 1800}, token), 400, "invalid prefix")
expect(call(admin, "api/trains", "POST", {**train, "code": "TR-999", "capacity_tons": 0}, token), 400, "invalid tons")
assert any(str(row["id"]) == str(train_id) for row in expect(call(admin, "api/trains?q=" + code + "&status=stopped"), 200, "search train"))
expect(call(admin, f"api/trains/{train_id}", "PATCH", {"name": "Trem editado"}, token), 200, "edit train")
assert expect(call(admin, f"api/trains/{train_id}"), 200, "read train")["name"] == "Trem editado"

sensor = {"train_id": train_id, "code": "S-TEMP-" + str(secrets.randbelow(900000) + 100000), "type": "Temperatura", "unit": "°C", "location": "Motor", "segment": "Pátio de testes", "reading_indicator": "attention"}
expect(call(admin, "api/sensors", "POST", {**sensor, "train_id": 999999999}, token), 400, "unknown train")
sensor_id = expect(call(admin, "api/sensors", "POST", sensor, token), 201, "create sensor")["id"]
expect(call(admin, "api/sensors", "POST", sensor, token), 409, "duplicate sensor code")
expect(call(admin, f"api/trains/{train_id}/delete", "POST", None, token), 409, "linked train deletion")
listed = expect(call(admin, "api/sensors?type=Temperatura"), 200, "filter sensors")
assert any(str(row["id"]) == str(sensor_id) and row["train_code"] == code for row in listed)
expect(call(admin, f"api/sensors/{sensor_id}", "PATCH", {"reading_indicator": "critical", "location": "Cabine"}, token), 200, "edit sensor")
assert expect(call(admin, f"api/sensors/{sensor_id}"), 200, "read sensor")["reading_indicator"] == "critical"
expect(call(admin, f"api/sensors/{sensor_id}/delete", "POST", None, token), 200, "delete sensor")
expect(call(admin, f"api/trains/{train_id}/delete", "POST", None, token), 200, "delete train")

operator = client()
operator_email = f"block2-operator-{suffix}@example.invalid"
expect(call(operator, "api/auth/register", "POST", {"name": "Operador de validação", "email": operator_email, "password": admin_password}), 201, "register operator")
operator_token = expect(call(operator, "api/auth/login", "POST", {"email": operator_email, "password": admin_password}), 200, "operator login")["csrf"]
assert call(operator, "trens.php")[0] == 403
assert call(operator, "sensores.php")[0] == 403
expect(call(operator, "api/trains", "POST", train, operator_token), 403, "operator train write")
expect(call(operator, "api/sensors", "POST", sensor, operator_token), 403, "operator sensor write")
mysql(f"UPDATE users SET role='manager' WHERE email='{operator_email}'")
assert call(operator, "trens.php")[0] == 200
assert call(operator, "sensores.php")[0] == 200
managed_train = {**train, "code": "TR-" + str(secrets.randbelow(900000) + 100000)}
managed_id = expect(call(operator, "api/trains", "POST", managed_train, operator_token), 201, "manager train write")["id"]
expect(call(operator, f"api/trains/{managed_id}/delete", "POST", None, operator_token), 200, "manager train delete")
expect(call(operator, "api/auth/logout", "POST", None, operator_token), 200, "API logout")
expect(call(operator, "api/auth/me"), 401, "session after API logout")

form = urllib.parse.urlencode({"csrf": token}).encode()
request = urllib.request.Request(BASE + "sair.php", data=form, method="POST")
try:
    response = admin.open(request)
except urllib.error.HTTPError as error:
    response = error
assert response.status == 303 and response.headers["Location"].endswith("page=login")
status, _, headers = call(admin, "api/auth/me")
assert status == 401
assert call(admin, "trens.php")[0] == 302
mysql(f"DELETE FROM users WHERE email IN ('{admin_email}', '{operator_email}')")

print("Bloco 2: sessão, CSRF, permissões, CRUD de trens/sensores e logout OK")
