<?php
declare(strict_types=1);

$itensDoMenu = [
    'dashboard' => '⌂',
    'trens' => '🚆',
    'sensores' => '◉',
    'rotas' => '⇄',
    'leituras' => '▥',
    'simulador' => '▶',
    'notificacoes_alertas' => '⚡',
    'alertas' => '⚡',
    'localizacao' => '⌖',
    'bilhetes' => '◷',
    'relatorios' => '▤',
    'notificacoes' => '♧',
    'usuarios' => '♧',
    'admin' => '◎',
    'configuracoes' => '⚙',
    'ajuda' => '?',
];
$paginasDeGestao = ['trens', 'sensores', 'rotas', 'simulador'];
$paginasDeAdmin = ['usuarios', 'admin'];
$paginasComArquivoProprio = ['trens', 'sensores', 'rotas', 'leituras', 'simulador', 'notificacoes_alertas'];
?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= $base ?>"><span class="brand-mark">🚆</span><span>A<span>·</span>TRAIN</span></a>
    <nav class="side-nav" aria-label="Navegação principal">
    <?php foreach ($itensDoMenu as $chave => $icone): ?>
        <?php if (in_array($chave, $paginasDeGestao, true) && !podeGerenciarCadastros()) continue; ?>
        <?php if (in_array($chave, $paginasDeAdmin, true) && !temPapel(['super_admin'])) continue; ?>
        <?php
        $href = in_array($chave, $paginasComArquivoProprio, true)
            ? $base . $chave . '.php'
            : $base . 'index.php?page=' . $chave;
        $estaAtiva = $page === $chave || $page === $chave . '_form';
        ?>
        <a class="side-link <?= $estaAtiva ? 'active' : '' ?>" href="<?= h($href) ?>">
            <span><?= $icone ?></span><?= h($titles[$chave]) ?>
        </a>
    <?php endforeach; ?>
    </nav>
    <div class="side-bottom">
        <a class="side-link" href="<?= $base ?>index.php?page=perfil">◯ Perfil</a>
        <form method="post" action="<?= $base ?>sair.php">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
            <button class="side-logout" type="submit">Sair da conta</button>
        </form>
    </div>
</aside>
<div class="main-frame">
    <header class="topbar">
        <button class="mobile-menu" id="menu" aria-label="Abrir menu">☰</button>
        <div class="topbar-title"><?= h($titles[$page] ?? 'A-Train') ?></div>
        <div class="topbar-right">
            <span class="live-pill">● Dados atualizados</span>
            <span>Conectado como <?= h($_SESSION['usuario_nome']) ?> (<?= h($_SESSION['usuario_papel']) ?>)</span>
            <a href="<?= $base ?>index.php?page=perfil">Perfil</a>
        </div>
    </header>
