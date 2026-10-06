<?php
require __DIR__ . '/includes/proteger.php';
exigirGestor();
$_GET['page'] = 'trens';
require __DIR__ . '/index.php';
