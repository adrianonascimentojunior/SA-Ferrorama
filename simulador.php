<?php
require __DIR__ . '/includes/proteger.php';
exigirGestor();
$_GET['page'] = 'simulador';
require __DIR__ . '/index.php';
