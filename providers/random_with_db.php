<?php
function getCurrentData($boiler) {
    usleep(mt_rand(10000, 50000));
    $load = mt_rand($boiler['load_range'][0], $boiler['load_range'][1]);

    try {
        $db = getDB();
        $pdo = $db->dbs;
        
        $stmt = $pdo->prepare("SELECT id FROM boilers WHERE code = :code");
        $stmt->execute([':code' => $boiler['id']]);
        $boilerId = $stmt->fetchColumn();
        
        if (!$boilerId) {
            return ['error' => 'Котёл не найден в БД: ' . $boiler['id']];
        }
        
        $stmt = $pdo->prepare("INSERT INTO measurements (boiler_id, `load`, `timestamp`) VALUES (:bid, :ld, :ts)");
        $stmt->execute([
            ':bid' => $boilerId,
            ':ld'  => $load,
            ':ts'  => date('Y-m-d H:i:s')
        ]);
        $measurementId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            SELECT p.id, p.code, rv.reference_value
            FROM reference_values rv
            JOIN parameters p ON p.id = rv.parameter_id
            WHERE rv.boiler_id = :bid 
              AND rv.load_min <= :ld1 
              AND rv.load_max >= :ld2
        ");
        $stmt->execute([
            ':bid' => $boilerId,
            ':ld1' => $load,
            ':ld2' => $load
        ]);
        $refs = $stmt->fetchAll();
        
        $fact = [
            'boiler_id'  => $boiler['id'],
            'load'       => $load,
            'timestamp'  => date('Y-m-d H:i:s'),
            'insert_id'  => $measurementId
        ];
        
        $stmtVal = $pdo->prepare("INSERT IGNORE INTO measurement_values (measurement_id, parameter_id, value) VALUES (:mid, :pid, :val)");
        
        foreach ($refs as $ref) {
            $base = (float)$ref['reference_value'];
            $value = round($base * (1 + (mt_rand(-200, 200) / 10000)), 2);
            
            $stmtVal->execute([
                ':mid' => $measurementId,
                ':pid' => $ref['id'],
                ':val' => $value
            ]);
            
            $fact[$ref['code']] = $value;
        }
        
        return $fact;
        
    } catch (Exception $e) {
        return ['error' => 'Ошибка БД: ' . $e->getMessage()];
    }
}
?>