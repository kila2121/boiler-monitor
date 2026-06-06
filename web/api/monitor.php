<?php
if (isset($_GET['action']) && $_GET['action'] === 'monitor') {
    $ini = parse_ini_file(__DIR__ . '/../../config.ini', true);
    $modules = $ini['modules'];

    $boiler = require __DIR__ . '/../../boilers/' . $modules['active_boiler'] . '.php';
    require_once __DIR__ . '/../../providers/' . $modules['provider'] . '.php';
    $fact = getCurrentData($boiler);

    if (isset($fact['error'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $fact['error']]);
        exit;
    }

    require_once __DIR__ . '/../../references/' . $modules['reference'] . '.php';
    $ref = getReference($boiler, $fact['load'] ?? 0);

    require_once __DIR__ . '/../../calculators/' . $modules['calculator'] . '.php';
    $deviations = calculate($fact, $ref, $boiler);

    unset($fact['insert_id']);

    $response = [
        'boiler'     => $boiler['name'],
        'timestamp'  => $fact['timestamp'] ?? date('Y-m-d H:i:s'),
        'fact'       => $fact,
        'ref'        => $ref,
        'deviations' => $deviations
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}