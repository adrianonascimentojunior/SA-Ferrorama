<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/sessao.php';
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/includes/permissao.php';

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$base = '/SA-Ferrorama-main/';

$authPages = ['login', 'cadastro', 'recuperar', 'redefinir'];
$pages = [
    'dashboard', 'trens', 'trens_form', 'sensores', 'sensores_form',
    'rotas', 'rotas_form', 'leituras', 'simulador', 'notificacoes_alertas',
    'alertas', 'localizacao', 'bilhetes', 'relatorios', 'notificacoes',
    'perfil', 'configuracoes', 'ajuda', 'usuarios', 'admin',
];
$titles = [
    'dashboard' => 'Visão geral',
    'trens' => 'Trens',
    'trens_form' => 'Trem',
    'sensores' => 'Sensores',
    'sensores_form' => 'Sensor',
    'rotas' => 'Rotas e viagens',
    'rotas_form' => 'Rota',
    'leituras' => 'Leituras',
    'simulador' => 'Simulador',
    'notificacoes_alertas' => 'Central de alertas',
    'alertas' => 'Alertas',
    'localizacao' => 'Localização',
    'bilhetes' => 'Horários e bilhetes',
    'relatorios' => 'Relatórios',
    'notificacoes' => 'Notificações',
    'perfil' => 'Perfil',
    'configuracoes' => 'Configurações',
    'ajuda' => 'Ajuda',
    'usuarios' => 'Usuários',
    'admin' => 'Painel Admin',
];

$page = (string)($_GET['page'] ?? 'dashboard');
if (!in_array($page, [...$authPages, ...$pages], true)) {
    http_response_code(404);
    $page = 'nao-encontrado';
}

try {
    $user = currentUser();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $user = null;
}

if ($user) {
    $_SESSION['usuario_nome'] = $user['name'];
    $_SESSION['usuario_papel'] = $user['role'];
}

if (!$user && !in_array($page, $authPages, true) && $page !== 'nao-encontrado') {
    header('Location: ' . $base . 'index.php?page=login');
    exit;
}

if ($user && in_array($page, $authPages, true)) {
    header('Location: ' . $base);
    exit;
}

if (in_array($page, ['usuarios', 'admin'], true) && ($user['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    $page = 'sem-acesso';
}

$paginasDeGestao = ['trens', 'trens_form', 'sensores', 'sensores_form', 'rotas', 'rotas_form', 'simulador'];
if (in_array($page, $paginasDeGestao, true) && !podeGerenciarCadastros()) {
    http_response_code(403);
    $page = 'sem-acesso';
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titles[$page] ?? 'A-Train') ?> · A-Train</title>
<link rel="stylesheet" href="<?= $base ?>public/assets/css/app.css">
<link rel="stylesheet" href="<?= $base ?>public/assets/css/local.css">
<link rel="stylesheet" href="<?= $base ?>public/assets/css/bloco2.css">
<link rel="stylesheet" href="<?= $base ?>public/assets/css/bloco3.css">
</head>
<body data-page="<?= h($page) ?>" data-base="<?= h($base) ?>" data-csrf="<?= h($_SESSION['csrf']) ?>" data-user="<?= h(json_encode($user, JSON_UNESCAPED_UNICODE) ?: 'null') ?>">
<?php if (in_array($page, $authPages, true)): ?>
<div class="auth-layout">
    <aside class="auth-aside">
        <div class="auth-brand">A<span>·</span>TRAIN</div>
        <div class="auth-aside-body">
            <span class="eyebrow light">INTELIGÊNCIA FERROVIÁRIA</span>
            <h2>Sua operação em movimento.</h2>
            <p>Monitore a frota, acompanhe alertas e planeje viagens em um único lugar.</p>
            <div class="auth-track">
                <div class="track-line"></div>
                <div class="track-train">🚆</div>
                <div class="track-station a"></div>
                <div class="track-station b"></div>
                <div class="track-station c"></div>
            </div>
        </div>
        <div class="auth-aside-footer">A-Train · SA-Ferrorama</div>
    </aside>
    <main class="auth-main">
        <div class="auth-card">
            <a class="auth-mobile-brand" href="<?= $base ?>">🚆 A-TRAIN</a>
            <div class="auth-mini">ACESSO À PLATAFORMA</div>
            <div id="app"></div>
        </div>
    </main>
</div>
<?php else: ?>
<div class="app-shell">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
    <main class="content"><div id="app"></div></main>
</div>
</div>
<?php endif; ?>
<script src="<?= $base ?>public/assets/js/bloco3.js" defer></script>
<script src="<?= $base ?>public/assets/js/app.js" defer></script>
</body>
</html>
