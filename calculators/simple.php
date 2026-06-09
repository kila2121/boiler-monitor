<?php
function calculate($fact, $ref, $boiler) {
    $db = getDB();
    $pdo = $db->dbs;
    
    $measurementId = $fact['insert_id'] ?? null;
    $result = [];
    
    $maxDeviations = $ref['max_deviation'] ?? [];
    
    $paramKeys = [
        'steam_pressure', 'steam_temperature', 'flue_gas_temp', 
        'gas_flow', 'excess_air', 'o2_content', 'feedwater_temp'
    ];
    
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
            $stmt = $pdo->prepare("INSERT INTO deviation_log (measurement_id, parameter, deviation, status) VALUES (:mid, :param, :dev, :status)");
            $stmt->execute([
                ':mid'    => $measurementId,
                ':param'  => $key,
                ':dev'    => $dev,
                ':status' => $status
            ]);
        }
    }

    return $result;
}