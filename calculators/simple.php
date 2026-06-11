<?php
function calculate($fact, $ref, $boiler) {
    $db = getDB();
    $pdo = $db->dbs;
    
    $measurementId = $fact['insert_id'] ?? null;
    $result = [];
    
    $maxDeviations = $ref['max_deviation'] ?? [];
    
    $paramKeys = [
        'steam_pressure', 'steam_temperature', 'flue_gas_temp', 
        'gas_flow', 'excess_air', 'o2_content', 'feedwater_temp',
        'efficiency', 'fuel_consumption', 'heat_output', 'excess_air_target'
    ];
    
    // ВРЕМЕННАЯ ОТЛАДКА
    $debug = [];
    $debug['fact_keys'] = array_keys($fact);
    $debug['ref_keys'] = array_keys($ref);
    $debug['fact_excess_air'] = $fact['excess_air'] ?? 'NOT SET';
    $debug['ref_excess_air'] = $ref['excess_air'] ?? 'NOT SET';
    
    file_put_contents(__DIR__ . '/debug_calc.log', print_r($debug, true), FILE_APPEND);
    
    $stmtLog = null;
    if ($measurementId) {
        $stmtLog = $pdo->prepare("INSERT IGNORE INTO deviation_log (measurement_id, parameter, deviation, status) VALUES (:mid, :param, :dev, :status)");
    }
    
    foreach ($paramKeys as $key) {
        if (!isset($fact[$key])) continue;
        if (!isset($ref[$key])) continue;
        
        $dev = round($fact[$key] - $ref[$key], 2);
        $max = $maxDeviations[$key] ?? 999;
        $status = abs($dev) > $max ? '⚠️' : '✓';

        $result[$key] = [
            'fact'   => $fact[$key],
            'ref'    => $ref[$key],
            'dev'    => $dev,
            'status' => $status
        ];

        if ($measurementId && $status === '⚠️') {
            $stmtLog->execute([
                ':mid'    => $measurementId,
                ':param'  => $key,
                ':dev'    => $dev,
                ':status' => $status
            ]);
        }
    }

    return $result;
}
?>