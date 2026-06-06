<?php
function getCurrentData($boiler) {
    $load = mt_rand($boiler['load_range'][0], $boiler['load_range'][1]);

    try {
        $db = getDB();
        $pdo = $db->dbs;
        
        $stmt = $pdo->prepare("SELECT id FROM boilers WHERE code = :code");
        $stmt->execute([':code' => $boiler['id']]);
        $boilerRow = $stmt->fetch();
        
        if (!$boilerRow) {
            return ['error' => 'Котёл не найден в БД: ' . $boiler['id']];
        }
        
        $boilerId = $boilerRow['id'];
        
        $measurementData = [
            'boiler_id' => $boilerId,
            'load'      => $load,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $result = $db->actionTable('add', $measurementData, 'measurements');
        if (!$result) {
            return ['error' => 'Ошибка записи замера: ' . $db->last_error];
        }
        
        $measurementId = $pdo->lastInsertId();
        
        foreach ($boiler['parameters'] as $param) {
            $stmt = $pdo->prepare("SELECT id FROM parameters WHERE code = :code");
            $stmt->execute([':code' => $param]);
            $paramRow = $stmt->fetch();
            
            if (!$paramRow) continue;
            
            $base = $boiler['reference'][$param] ?? 0;
            $value = round($base * (1 + (mt_rand(-200, 200) / 10000)), 2);
            
            $db->actionTable('add', [
                'measurement_id' => $measurementId,
                'parameter_id'   => $paramRow['id'],
                'value'          => $value
            ], 'measurement_values');
        }
        
        $fact = [
            'boiler_id'  => $boiler['id'],
            'load'       => $load,
            'timestamp'  => $measurementData['timestamp'],
            'insert_id'  => $measurementId
        ];
        
        $stmt = $pdo->prepare("
            SELECT p.code, mv.value 
            FROM measurement_values mv 
            JOIN parameters p ON p.id = mv.parameter_id 
            WHERE mv.measurement_id = :mid
        ");
        $stmt->execute([':mid' => $measurementId]);
        $values = $stmt->fetchAll();
        
        foreach ($values as $row) {
            $fact[$row['code']] = (float)$row['value'];
        }
        
        return $fact;
        
    } catch (Exception $e) {
        return ['error' => 'Ошибка БД: ' . $e->getMessage()];
    }
}