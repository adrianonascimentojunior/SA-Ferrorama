<?php
require __DIR__ . '/includes/proteger.php';
exigirGestor();
$_GET['page'] = 'trens_form';
require __DIR__ . '/index.php';
