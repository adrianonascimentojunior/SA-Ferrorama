<?php
require __DIR__ . '/includes/proteger.php';
exigirGestor();
$_GET['page'] = 'sensores';
require __DIR__ . '/index.php';
