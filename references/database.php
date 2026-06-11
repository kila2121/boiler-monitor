<?php
function getReference($boiler, $load) {
    $db = getDB();
    $pdo = $db->dbs;
    
    $stmt = $pdo->prepare("
        SELECT p.code, 
               ANY_VALUE(rv.reference_value) as reference_value, 
               ANY_VALUE(rv.max_deviation) as max_deviation
        FROM reference_values rv
        JOIN parameters p ON p.id = rv.parameter_id
        JOIN boilers b ON b.id = rv.boiler_id
        WHERE b.code = :code 
          AND rv.load_min <= :ld1 
          AND rv.load_max >= :ld2
        GROUP BY p.code
    ");
    $stmt->execute([
        ':code' => $boiler['id'],
        ':ld1'  => $load,
        ':ld2'  => $load
    ]);
    
    $ref = [];
    $max = [];
    
    foreach ($stmt->fetchAll() as $row) {
        $ref[$row['code']] = (float)$row['reference_value'];
        $max[$row['code']] = (float)$row['max_deviation'];
    }
    
    $ref['max_deviation'] = $max;
    
    return $ref;
}
?>