<?php
declare(strict_types=1);

/**
 * FONTE ÚNICA de permissões da interface.
 * index.php, cabecalho.php, a API (admin/access) e o painel "Acesso por papel" leem daqui.
 * A API continua aplicando as regras reais (requireUser/requireManager/requireAdmin em app/bootstrap.php).
 */
const PAPEIS = [
    'operator' => 'Operador',
    'manager' => 'Gerente',
    'super_admin' => 'Super Admin',
];

/** Páginas restritas: página => papéis que podem abrir. Páginas ausentes são livres para qualquer usuário logado. */
const PAGINAS_RESTRITAS = [
    'trens' => ['manager', 'super_admin'],
    'trens_form' => ['manager', 'super_admin'],
    'sensores' => ['manager', 'super_admin'],
    'sensores_form' => ['manager', 'super_admin'],
    'rotas' => ['manager', 'super_admin'],
    'rotas_form' => ['manager', 'super_admin'],
    'simulador' => ['manager', 'super_admin'],
    'usuarios' => ['super_admin'],
    'admin' => ['super_admin'],
];

/** Matriz exibida no painel admin: capacidade => [rótulo, descrição, papéis, tabelas afetadas]. */
function matrizDeAcesso(): array
{
    $todos = ['operator', 'manager', 'super_admin'];
    $gestao = ['manager', 'super_admin'];
    $admin = ['super_admin'];
    return [
        ['Consultar painel, mapa, leituras, alertas, notificações e relatórios', 'Somente leitura', $todos, '—'],
        ['Editar o próprio perfil e senha', 'Altera a própria conta', $todos, 'users'],
        ['Simular bilhetes', 'Grava uma estimativa, sem reserva', $todos, 'ticket_simulations'],
        ['Cadastrar, editar e excluir trens', 'Gestão da frota', $gestao, 'trains'],
        ['Cadastrar, editar e excluir sensores', 'Gestão da frota', $gestao, 'sensors'],
        ['Cadastrar, editar e excluir rotas/viagens', 'Gestão da frota', $gestao, 'routes'],
        ['Gerar leituras no simulador', 'Cria leituras e alertas', $gestao, 'sensor_readings, alerts'],
        ['Editar usuários (papel e status)', 'Painel Admin', $admin, 'users'],
        ['Editar trens, sensores, manutenções e alertas pelo Painel Admin', 'Painel Admin', $admin, 'trains, sensors, maintenances, alerts'],
        ['Alterar configurações do sistema (inclui modo de manutenção)', 'Painel Admin', $admin, 'app_settings'],
        ['Ver o histórico de auditoria e o status da conexão com o banco', 'Somente leitura', $admin, 'audit_logs'],
        ['Continuar usando a plataforma em modo de manutenção', 'Os demais papéis ficam bloqueados', $admin, '—'],
    ];
}

function temPapel(array $papeisPermitidos): bool
{
    return in_array($_SESSION['usuario_papel'] ?? null, $papeisPermitidos, true);
}

function podeGerenciarCadastros(): bool
{
    return temPapel(['manager', 'super_admin']);
}

/** Um papel pode abrir esta página? */
function papelPodeAcessarPagina(?string $papel, string $pagina): bool
{
    $permitidos = PAGINAS_RESTRITAS[$pagina] ?? null;
    return $permitidos === null || in_array($papel, $permitidos, true);
}

function podeAcessarPagina(string $pagina): bool
{
    return papelPodeAcessarPagina($_SESSION['usuario_papel'] ?? null, $pagina);
}
