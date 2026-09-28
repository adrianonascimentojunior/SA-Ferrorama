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
<html lang="pt-BR"><head><meta charset="utf-8"><title>A-Train</title></head><body><main id="app">A-Train</main></body></html>
