<?php
declare(strict_types=1);
?>
<aside class="sidebar" id="sidebar"><a class="brand" href="<?= $base ?>"><span class="brand-mark">🚆</span><span>A<span>·</span>TRAIN</span></a><nav class="side-nav" aria-label="Navegação principal">
<?php foreach (['dashboard'=>'⌂','trens'=>'🚆','sensores'=>'◉','alertas'=>'⚡','localizacao'=>'⌖','bilhetes'=>'◷','relatorios'=>'▤','notificacoes'=>'♧','usuarios'=>'♧','admin'=>'◎','configuracoes'=>'⚙','ajuda'=>'?'] as $key=>$icon):
    if (in_array($key, ['trens','sensores'], true) && !podeGerenciarCadastros()) continue;
    if (in_array($key, ['usuarios','admin'], true) && !temPapel(['super_admin'])) continue;
    $href = in_array($key, ['trens','sensores'], true) ? $base . $key . '.php' : $base . 'index.php?page=' . $key;
?>
<a class="side-link <?= $page===$key || $page===$key.'_form'?'active':'' ?>" href="<?= h($href) ?>"><span><?= $icon ?></span><?= h($titles[$key]) ?></a>
<?php endforeach; ?></nav><div class="side-bottom"><a class="side-link" href="<?= $base ?>index.php?page=perfil">◯ Perfil</a><form method="post" action="<?= $base ?>sair.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button class="side-logout" type="submit">Sair da conta</button></form></div></aside>
<div class="main-frame"><header class="topbar"><button class="mobile-menu" id="menu" aria-label="Abrir menu">☰</button><div class="topbar-title"><?= h($titles[$page] ?? 'A-Train') ?></div><div class="topbar-right"><span class="live-pill">● Dados atualizados</span><span>Conectado como <?= h($_SESSION['usuario_nome']) ?> (<?= h($_SESSION['usuario_papel']) ?>)</span><a href="<?= $base ?>index.php?page=perfil">Perfil</a></div></header>
