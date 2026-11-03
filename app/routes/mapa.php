<?php
declare(strict_types=1);

if ($path === 'map' && $method === 'GET') {
    $stations = query('SELECT * FROM stations ORDER BY name')->fetchAll();
    $trains = query('SELECT id, code, name, status, latitude, longitude, station_id FROM trains ORDER BY code')->fetchAll();
    respond(['stations' => $stations, 'trains' => $trains]);
}
