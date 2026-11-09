<?php
// OPCIONAL: sem este arquivo a aplicação usa o padrão do XAMPP (root, sem senha, localhost:3306).
// Copie para config/local.php apenas para sobrescrever esses valores nesta máquina.
// Precedência: variável de ambiente > config/local.php > padrão.
return [
    'APP_ENV' => 'development',
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_NAME' => 'frota_ferroviaria',
    'DB_USER' => 'root',
    'DB_PASSWORD' => '',
];
