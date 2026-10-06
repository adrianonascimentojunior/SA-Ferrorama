<?php
declare(strict_types=1);

function temPapel(array $papeisPermitidos): bool
{
    return in_array($_SESSION['usuario_papel'] ?? null, $papeisPermitidos, true);
}

function podeGerenciarCadastros(): bool
{
    return temPapel(['manager', 'super_admin']);
}
