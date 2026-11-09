<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/permissao.php';

function origemPermitida(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return true;
    }
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $hostEsperado = $protocolo . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $origin === $hostEsperado;
}

if (!origemPermitida()) {
    fail('Origem não permitida.', 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/');
$path = preg_replace('#^SA-Ferrorama-main/api/?#', '', $path);
$segments = $path === '' ? [] : explode('/', $path);

if ($method === 'OPTIONS') {
    respond(['ok' => true]);
}

try {
    if ($path === 'health' && $method === 'GET') {
        respond(['status' => 'ok', 'database' => db()->query('SELECT 1')->fetchColumn() == 1]);
    }

    require __DIR__ . '/routes/auth.php';

    $user = requireUser();
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        csrf();
    }
    if ($user['role'] !== 'super_admin' && modoDeManutencaoAtivo()) {
        fail('Plataforma em manutenção. Tente novamente em instantes.', 503);
    }

    require __DIR__ . '/routes/dashboard.php';
    require __DIR__ . '/routes/trens.php';
    require __DIR__ . '/routes/sensores.php';
    require __DIR__ . '/routes/rotas.php';
    require __DIR__ . '/routes/leituras.php';
    require __DIR__ . '/routes/alertas.php';
    require __DIR__ . '/routes/mapa.php';
    require __DIR__ . '/routes/bilhetes.php';
    require __DIR__ . '/routes/relatorios.php';
    require __DIR__ . '/routes/notificacoes.php';
    require __DIR__ . '/routes/perfil.php';
    require __DIR__ . '/routes/suporte.php';
    require __DIR__ . '/routes/admin.php';

    fail('Endpoint não encontrado.', 404);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    try {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
    } catch (Throwable) {
    }
    $codigoErroMysql = $exception->errorInfo[1] ?? null;
    if ($codigoErroMysql === 1062) {
        fail('Registro duplicado.', 409);
    }
    if (in_array($codigoErroMysql, [1451, 1452], true)) {
        fail('Registro referenciado por outros dados.', 409);
    }
    fail('Erro de banco de dados.', 500);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    fail('Erro interno.', 500);
}
