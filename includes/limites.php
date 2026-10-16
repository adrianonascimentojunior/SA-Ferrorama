<?php
declare(strict_types=1);

// Faixas de demonstração para os tipos aceitos pelo cadastro de sensores.
function perfisSensores(): array {
    return [
        'Temperatura' => ['min' => 45, 'normal_max' => 80, 'limit' => 85],
        'Velocidade' => ['min' => 30, 'normal_max' => 110, 'limit' => 120],
        'Consumo' => ['min' => 20, 'normal_max' => 70, 'limit' => 80],
        'Localização' => ['min' => 0, 'normal_max' => 10, 'limit' => 12], // desvio em km
        'Vibração' => ['min' => 0.5, 'normal_max' => 3, 'limit' => 4],
        'Freio' => ['min' => 3, 'normal_max' => 7, 'limit' => 8],
        'Motor' => ['min' => 800, 'normal_max' => 2000, 'limit' => 2200],
    ];
}

function classificarLeitura(string $tipo, float $valor): string {
    $perfil = perfisSensores()[$tipo] ?? null;
    if ($perfil === null) throw new InvalidArgumentException('Tipo de sensor sem limite configurado.');
    if ($valor <= $perfil['limit']) return 'normal';
    return $valor <= $perfil['limit'] * 1.1 ? 'attention' : 'critical';
}

function gerarValorLeitura(string $tipo, bool $foraDoLimite): float {
    $perfil = perfisSensores()[$tipo] ?? null;
    if ($perfil === null) throw new InvalidArgumentException('Tipo de sensor sem faixa de simulação.');
    $inicio = $foraDoLimite ? $perfil['limit'] * 1.02 : $perfil['min'];
    $fim = $foraDoLimite ? $perfil['limit'] * 1.25 : $perfil['normal_max'];
    return random_int((int)ceil($inicio * 100), (int)floor($fim * 100)) / 100;
}
