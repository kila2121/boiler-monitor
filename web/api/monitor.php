<?php
if (isset($_GET['action']) && $_GET['action'] === 'monitor') {
    $ini = parse_ini_file(__DIR__ . '/../../config.ini', true);
    $modules = $ini['modules'];

    $boilerCode = $_GET['boiler'] ?? $modules['active_boiler'];
    
    // Получаем котел из БД
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
    
    // Получаем параметры котла из БД
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

    $response = [
        'boiler'          => $boiler['name'],
        'boiler_nominal'  => $boiler['nominal'],
        'timestamp'       => $fact['timestamp'] ?? date('Y-m-d H:i:s'),
        'fact'            => $fact,
        'ref'             => $ref,
        'deviations'      => $deviations
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}