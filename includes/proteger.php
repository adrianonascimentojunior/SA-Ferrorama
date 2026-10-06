<?php
declare(strict_types=1);

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/permissao.php';

try {
    $user = currentUser();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $user = null;
}

if (!$user) {
    header('Location: /SA-Ferroama/index.php?page=login', true, 302);
    exit;
}

$_SESSION['usuario_nome'] = $user['name'];
$_SESSION['usuario_papel'] = $user['role'];

function exigirGestor(): void
{
    if (!podeGerenciarCadastros()) {
        http_response_code(403);
        exit('Acesso restrito à gestão de trens e sensores.');
    }
}
