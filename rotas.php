<?php
require __DIR__ . '/includes/proteger.php';
exigirGestor();
$_GET['page'] = 'rotas';
require __DIR__ . '/index.php';
