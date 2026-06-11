<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['action']) && $_GET['action'] === 'monitor') {
    
    if (!isset($_SESSION['user'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Доступ запрещён. Авторизуйтесь.']);
        exit;
    }
    
    try {
        $ini = parse_ini_file(__DIR__ . '/../../config.ini', true);
        $modules = $ini['modules'];
        $boilerCode = $_GET['boiler'] ?? $modules['active_boiler'];
        
        require_once __DIR__ . '/../../database.php';
        $db = getDB();
        $pdo = $db->dbs;
        
        $stmt = $pdo->prepare("SELECT id, code, name, load_min, load_max FROM boilers WHERE code = :code");
        $stmt->execute([':code' => $boilerCode]);
        $boilerRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$boilerRow) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Котёл не найден']);
            exit;
        }
        
        $boiler = [
            'id'         => $boilerRow['code'],
            'name'       => $boilerRow['name'],
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

        // ПРИНУДИТЕЛЬНО ДОБАВЛЯЕМ excess_air, ЕСЛИ ЕГО НЕТ
        if (!isset($deviations['excess_air']) && isset($fact['excess_air']) && isset($ref['excess_air'])) {
            $dev = round($fact['excess_air'] - $ref['excess_air'], 3);
            $maxDev = $ref['max_deviation']['excess_air'] ?? 0.05;
            $deviations['excess_air'] = [
                'fact' => $fact['excess_air'],
                'ref' => $ref['excess_air'],
                'dev' => $dev,
                'status' => abs($dev) > $maxDev ? '⚠️' : '✓'
            ];
        }

        unset($fact['insert_id']);

        require_once __DIR__ . '/../../classes/BoilerCalculator.php';
        
        $calc = BoilerCalculator::calculateFull(
            $fact['load'] ?? 0,
            $fact['steam_pressure'] ?? 0,
            $fact['steam_temperature'] ?? 0,
            $fact['flue_gas_temp'] ?? 0,
            $fact['gas_flow'] ?? 0,
            $fact['excess_air'] ?? 1.09,
            20
        );

        $fuelSavings = [];
        foreach ($deviations as $param => $data) {
            $paramKey = match($param) {
                'steam_pressure' => 'pressure',
                'steam_temperature' => 'temp',
                'feedwater_temp' => 'feedwater_temp',
                'o2_content' => 'o2',
                default => null
            };
            
            if ($paramKey) {
                $dev = $data['dev'] ?? 0;
                $saving = BoilerCalculator::calcFuelImpact($paramKey, $dev, $fact['load'] ?? 0);
                $fuelSavings[$param] = round($saving, 4);
            }
        }

        $targetEfficiency = $calc['efficiency'] + ($calc['flue_gas_loss'] + $calc['radiation_loss']) * 0.1;
        $efficiencyScore = BoilerCalculator::calculateEfficiencyScore($calc['efficiency'], $targetEfficiency, $deviations);

        $optimalLoad = BoilerCalculator::calculateOptimalLoad(
            $fact['load'] ?? 0,
            $boiler['load_range'][0],
            $boiler['load_range'][1],
            $calc['efficiency']
        );

        $response = [
            'boiler'          => $boiler['name'],
            'load_range'      => $boiler['load_range'],
            'timestamp'       => $fact['timestamp'] ?? date('Y-m-d H:i:s'),
            'fact'            => $fact,
            'ref'             => $ref,
            'deviations'      => $deviations,
            'calculated'      => $calc,
            'fuel_savings'    => $fuelSavings,
            'efficiency_score' => $efficiencyScore,
            'optimal_load'    => $optimalLoad
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
    }
    exit;
}
?>