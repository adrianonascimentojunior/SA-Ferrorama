<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/sessao.php';
require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}
if (currentUser()) {
    audit('logout', 'users', (int)$_SESSION['user_id']);
}
encerrarSessao();
header('Location: /SA-Ferroama/index.php?page=login', true, 303);
exit;
