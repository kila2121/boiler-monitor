<?php
if (isset($_GET['action']) && $_GET['action'] === 'monitor') {
    $ini = parse_ini_file(__DIR__ . '/../../config.ini', true);
    $modules = $ini['modules'];

    $boilerCode = $_GET['boiler'] ?? $modules['active_boiler'];
    
    require_once __DIR__ . '/../../database.php';
    
    $db = getDB();
    $pdo = $db->dbs;
    
    $stmt = $pdo->prepare("SELECT id, code, name, nominal_load, load_min, load_max FROM boilers WHERE code = :code");
    $stmt->execute([':code' => $boilerCode]);
    $boilerRow = $stmt->fetch();
    
    if (!$boilerRow) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Котёл не найден']);
        exit;
    }
    
    $boiler = [
        'id'         => $boilerRow['code'],
        'name'       => $boilerRow['name'],
        'nominal'    => (float)$boilerRow['nominal_load'],
        'load_range' => [(float)$boilerRow['load_min'], (float)$boilerRow['load_max']],
        'parameters' => []
    ];
    
    $stmt = $pdo->prepare("
        SELECT p.code FROM parameters p
        JOIN boiler_parameters bp ON bp.parameter_id = p.id
        WHERE bp.boiler_id = :id
    ");
    $stmt->execute([':id' => $boilerRow['id']]);
    $boiler['parameters'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
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

    require_once __DIR__ . '/../../classes/BoilerCalculator.php';
    
    $o2Content = $fact['o2_content'] ?? null; 

    $calc = BoilerCalculator::calculateFull(
        $fact['load'] ?? 0,
        $fact['steam_pressure'] ?? 0,
        $fact['steam_temperature'] ?? 0,
        $fact['flue_gas_temp'] ?? 0,
        $fact['gas_flow'] ?? 0,
        $o2Content,
        20
    );
    
    $calcDeviations = [];
    if (isset($fact['feedwater_temp'])) {
        $calcDeviations['feedwater_temp'] = [
            'fact' => $fact['feedwater_temp'],
            'ref' => round($calc['feedwater_temp_ref'], 1),
            'dev' => round($fact['feedwater_temp'] - $calc['feedwater_temp_ref'], 1),
            'status' => abs($fact['feedwater_temp'] - $calc['feedwater_temp_ref']) > 5 ? '⚠️' : '✓'
        ];
    }
    
    if (isset($fact['o2_content'])) {
        $calcDeviations['o2_content'] = [
            'fact' => $fact['o2_content'],
            'ref' => round($calc['o2_ref'], 2),
            'dev' => round($fact['o2_content'] - $calc['o2_ref'], 2),
            'status' => abs($fact['o2_content'] - $calc['o2_ref']) > 0.5 ? '⚠️' : '✓'
        ];
    }

    $response = [
        'boiler'          => $boiler['name'],
        'boiler_nominal'  => $boiler['nominal'],
        'timestamp'       => $fact['timestamp'] ?? date('Y-m-d H:i:s'),
        'fact'            => $fact,
        'ref'             => $ref,
        'deviations'      => $deviations,
        'calculated'      => $calc,
        'calc_deviations' => $calcDeviations
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}