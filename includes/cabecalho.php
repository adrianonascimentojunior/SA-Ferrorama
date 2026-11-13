<?php
declare(strict_types=1);

/** Ícones SVG (24x24, traço) — conteúdo interno do <svg>. */
$icones = [
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'localizacao' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
    'trens' => '<rect x="5" y="3" width="14" height="14" rx="3"/><path d="M5 11h14M9 21l2-4M15 21l-2-4"/><circle cx="9" cy="14" r=".6"/><circle cx="15" cy="14" r=".6"/>',
    'sensores' => '<circle cx="12" cy="12" r="2.5"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.9 4.9a10 10 0 0 0 0 14.2M19.1 4.9a10 10 0 0 1 0 14.2"/>',
    'rotas' => '<circle cx="6" cy="19" r="2.5"/><circle cx="18" cy="5" r="2.5"/><path d="M8.5 19H15a3.5 3.5 0 0 0 0-7H9a3.5 3.5 0 0 1 0-7h6.5"/>',
    'leituras' => '<path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>',
    'simulador' => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5z"/>',
    'notificacoes_alertas' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/>',
    'alertas' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
    'notificacoes' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    'bilhetes' => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
    'relatorios' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    'usuarios' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
    'admin' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
    'configuracoes' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    'ajuda' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 1-1 1.7M12 17h.01"/>',
    'perfil' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'sair' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    'recolher' => '<path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/>',
    'trem' => '<rect x="5" y="3" width="14" height="14" rx="3"/><path d="M5 11h14M8 21l2-4M16 21l-2-4"/>',
];
$svg = static fn (string $nome): string =>
    '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . ($icones[$nome] ?? '') . '</svg>';

/** Grupos do menu: rótulo => itens (chaves de $titles). */
$gruposDoMenu = [
    'Operação' => ['dashboard', 'localizacao', 'trens', 'sensores', 'rotas', 'leituras'],
    'Monitoramento' => ['notificacoes_alertas', 'alertas', 'notificacoes', 'simulador'],
    'Negócio' => ['bilhetes', 'relatorios'],
    'Administração' => ['usuarios', 'admin'],
    'Conta' => ['configuracoes', 'ajuda'],
];
$paginasComArquivoProprio = ['trens', 'sensores', 'rotas', 'leituras', 'simulador', 'notificacoes_alertas'];

$rotulosDePapel = PAPEIS;
$nomeUsuario = (string)($_SESSION['usuario_nome'] ?? 'Usuário');
$papelUsuario = (string)($_SESSION['usuario_papel'] ?? '');
$rotuloPapel = $rotulosDePapel[$papelUsuario] ?? $papelUsuario;
$partesNome = preg_split('/\s+/', trim($nomeUsuario)) ?: [];
$iniciais = mb_strtoupper(mb_substr($partesNome[0] ?? 'U', 0, 1) . (count($partesNome) > 1 ? mb_substr(end($partesNome), 0, 1) : ''));

$paginaBase = preg_replace('/_form$/', '', (string)$page);
$grupoAtual = '';
foreach ($gruposDoMenu as $rotuloGrupo => $chaves) {
    if (in_array($paginaBase, $chaves, true)) { $grupoAtual = $rotuloGrupo; break; }
}
if ($paginaBase === 'perfil') { $grupoAtual = 'Conta'; }
?>
<script>try{if(localStorage.getItem('atrain.nav')==='collapsed')document.documentElement.classList.add('nav-collapsed')}catch(e){}</script>
<aside class="sidebar" id="sidebar" aria-label="Menu lateral">
    <div class="brand-row">
        <a class="brand" href="<?= $base ?>" title="A-Train — início">
            <span class="brand-mark"><?= $svg('trem') ?></span>
            <span class="brand-text"><b class="brand-title">A<i>·</i>TRAIN</b><small>Inteligência ferroviária</small></span>
        </a>
        <button class="collapse-btn" id="nav-collapse" type="button" aria-label="Recolher menu" aria-pressed="false" title="Recolher / expandir menu"><?= $svg('recolher') ?></button>
    </div>

    <nav class="side-nav" aria-label="Navegação principal">
    <?php foreach ($gruposDoMenu as $rotuloGrupo => $chaves): ?>
        <?php
        $visiveis = array_filter($chaves, static fn (string $chave): bool => podeAcessarPagina($chave));
        if (!$visiveis) continue;
        ?>
        <div class="nav-group">
            <div class="nav-label"><?= h($rotuloGrupo) ?></div>
            <?php foreach ($visiveis as $chave): ?>
                <?php
                $href = in_array($chave, $paginasComArquivoProprio, true)
                    ? $base . $chave . '.php'
                    : $base . 'index.php?page=' . $chave;
                $estaAtiva = $paginaBase === $chave;
                $titulo = (string)($titles[$chave] ?? ucfirst($chave));
                ?>
                <a class="nav-link<?= $estaAtiva ? ' active' : '' ?>" href="<?= h($href) ?>" data-tip="<?= h($titulo) ?>"<?= $estaAtiva ? ' aria-current="page"' : '' ?>>
                    <?= $svg($chave) ?><span class="nav-text"><?= h($titulo) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    </nav>

    <div class="side-bottom">
        <a class="user-card<?= $paginaBase === 'perfil' ? ' active' : '' ?>" href="<?= $base ?>index.php?page=perfil" data-tip="Meu perfil">
            <span class="avatar-nav"><?= h($iniciais) ?></span>
            <span class="user-meta"><strong><?= h($nomeUsuario) ?></strong><small><?= h($rotuloPapel) ?></small></span>
        </a>
        <form method="post" action="<?= $base ?>sair.php">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
            <button class="logout-btn" type="submit" data-tip="Sair da conta"><?= $svg('sair') ?><span class="nav-text">Sair da conta</span></button>
        </form>
    </div>
</aside>
<button class="nav-overlay" id="nav-overlay" type="button" aria-label="Fechar menu" tabindex="-1"></button>
<div class="main-frame">
    <header class="topbar">
        <button class="menu-btn mobile-menu" id="menu" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false"><?= $svg('menu') ?></button>
        <nav class="crumbs" aria-label="Você está em">
            <span class="crumb-root">A-Train</span>
            <?php if ($grupoAtual !== ''): ?><span class="crumb-sep">/</span><span class="crumb-group"><?= h($grupoAtual) ?></span><?php endif; ?>
            <span class="crumb-sep">/</span>
            <strong class="crumb-page"><?= h((string)($titles[$page] ?? 'A-Train')) ?></strong>
        </nav>
        <div class="topbar-right">
            <span class="live-pill" id="live-pill" role="status"><i></i><b>Dados atualizados</b></span>
            <a class="icon-link" href="<?= $base ?>index.php?page=notificacoes" aria-label="Notificações" title="Notificações"><?= $svg('notificacoes') ?></a>
            <a class="icon-link" href="<?= $base ?>index.php?page=ajuda" aria-label="Ajuda" title="Ajuda"><?= $svg('ajuda') ?></a>
            <a class="top-user" href="<?= $base ?>index.php?page=perfil" title="Conectado como <?= h($nomeUsuario) ?> (<?= h($rotuloPapel) ?>)">
                <span class="avatar-nav sm"><?= h($iniciais) ?></span>
                <span class="top-user-name"><?= h($nomeUsuario) ?></span>
            </a>
        </div>
    </header>
