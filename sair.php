<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/sessao.php';
require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$csrfEnviado = $_POST['csrf'] ?? null;
$csrfValido = is_string($csrfEnviado) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $csrfEnviado);
if (!$csrfValido) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}
try {
    if (currentUser()) {
        audit('logout', 'users', (int)$_SESSION['user_id']);
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}
encerrarSessao();
header('Location: /SA-Ferrorama-main/index.php?page=login', true, 303);
exit;
