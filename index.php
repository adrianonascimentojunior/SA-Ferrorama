<?php
declare(strict_types=1);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('ATRAINSESSID');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/SA-Ferroama/']);
session_start();
require_once __DIR__ . '/app/bootstrap.php';
$page = (string)($_GET['page'] ?? 'dashboard');
$authPages = ['login', 'cadastro', 'recuperar', 'redefinir'];
$pages = ['dashboard', 'trens', 'alertas', 'localizacao', 'bilhetes', 'relatorios', 'notificacoes', 'perfil', 'configuracoes', 'ajuda', 'usuarios', 'admin'];
if (!in_array($page, [...$authPages, ...$pages], true)) { http_response_code(404); $page = 'nao-encontrado'; }
try { $user = currentUser(); } catch (Throwable $e) { error_log($e->getMessage()); $user = null; }
if (!$user && !in_array($page, $authPages, true) && $page !== 'nao-encontrado') { header('Location: /SA-Ferroama/index.php?page=login'); exit; }
if ($user && in_array($page, $authPages, true)) { header('Location: /SA-Ferroama/'); exit; }
if (in_array($page, ['usuarios','admin'], true) && ($user['role'] ?? '') !== 'super_admin') { http_response_code(403); $page = 'sem-acesso'; }
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$titles = ['dashboard'=>'Visão geral','trens'=>'Trens','alertas'=>'Alertas','localizacao'=>'Localização','bilhetes'=>'Horários e bilhetes','relatorios'=>'Relatórios','notificacoes'=>'Notificações','perfil'=>'Perfil','configuracoes'=>'Configurações','ajuda'=>'Ajuda','usuarios'=>'Usuários','admin'=>'Painel Admin'];
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$base = '/SA-Ferroama/';
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titles[$page] ?? 'A-Train') ?> · A-Train</title>
<link rel="stylesheet" href="<?= $base ?>public/assets/css/app.css"><link rel="stylesheet" href="<?= $base ?>public/assets/css/local.css"></head>
<body data-page="<?= h($page) ?>" data-base="<?= h($base) ?>" data-csrf="<?= h($_SESSION['csrf']) ?>" data-user="<?= h(json_encode($user, JSON_UNESCAPED_UNICODE) ?: 'null') ?>">
<?php if (in_array($page, $authPages, true)): ?>
<div class="auth-layout"><aside class="auth-aside"><div class="auth-brand">A<span>·</span>TRAIN</div><div class="auth-aside-body"><span class="eyebrow light">INTELIGÊNCIA FERROVIÁRIA</span><h2>Sua operação em movimento.</h2><p>Monitore a frota, acompanhe alertas e planeje viagens em um único lugar.</p><div class="auth-track"><div class="track-line"></div><div class="track-train">🚆</div><div class="track-station a"></div><div class="track-station b"></div><div class="track-station c"></div></div></div><div class="auth-aside-footer">A-Train · SA-Ferrorama</div></aside><main class="auth-main"><div class="auth-card"><a class="auth-mobile-brand" href="<?= $base ?>">🚆 A-TRAIN</a><div class="auth-mini">ACESSO À PLATAFORMA</div><div id="app"></div></div></main></div>
<?php else: ?>
<div class="app-shell"><aside class="sidebar" id="sidebar"><a class="brand" href="<?= $base ?>"><span class="brand-mark">🚆</span><span>A<span>·</span>TRAIN</span></a><nav class="side-nav" aria-label="Navegação principal">
<?php foreach (['dashboard'=>'⌂','trens'=>'🚆','alertas'=>'⚡','localizacao'=>'⌖','bilhetes'=>'◷','relatorios'=>'▤','notificacoes'=>'♧','usuarios'=>'♧','admin'=>'◎','configuracoes'=>'⚙','ajuda'=>'?'] as $key=>$icon): if (in_array($key,['usuarios','admin']) && ($user['role']??'') !== 'super_admin') continue; ?>
<a class="side-link <?= $page===$key?'active':'' ?>" href="<?= $base ?>index.php?page=<?= $key ?>"><span><?= $icon ?></span><?= h($titles[$key]) ?></a>
<?php endforeach; ?></nav><div class="side-bottom"><a class="side-link" href="<?= $base ?>index.php?page=perfil">◯ Perfil</a><button class="side-logout" id="logout">Sair da conta</button></div></aside>
<div class="main-frame"><header class="topbar"><button class="mobile-menu" id="menu" aria-label="Abrir menu">☰</button><div class="topbar-title"><?= h($titles[$page]??'A-Train') ?></div><div class="topbar-right"><span class="live-pill">● Dados atualizados</span><a href="<?= $base ?>index.php?page=perfil"><?= h($user['name']??'Conta') ?></a></div></header><main class="content"><div id="app"></div></main></div></div>
<?php endif; ?>
<script src="<?= $base ?>public/assets/js/app.js" defer></script></body></html>
