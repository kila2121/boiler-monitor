<?php
if (isset($_GET['action']) && $_GET['action'] === 'history') {
    require_once __DIR__ . '/../../database.php';
    
    $limit = (int)($_GET['limit'] ?? 30);
    
    $db = getDB();
    $pdo = $db->dbs;
    
    $stmt = $pdo->prepare("SELECT id FROM boilers WHERE code = :code");
    $stmt->execute([':code' => 'tgm96']);
    $boilerRow = $stmt->fetch();
    
    if (!$boilerRow) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Котёл не найден']);
        exit;
    }
    
    $boilerId = $boilerRow['id'];
    
    $stmt = $pdo->prepare("
        SELECT id, boiler_id, `load`, `timestamp` 
        FROM measurements 
        WHERE boiler_id = :id 
        ORDER BY `timestamp` DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':id', $boilerId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $measurements = $stmt->fetchAll();
    
    $data = [];
    foreach ($measurements as $m) {
        $row = [
            'id'         => $m['id'],
            'boiler_id'  => 'tgm96',
            'load'       => $m['load'],
            'timestamp'  => $m['timestamp']
        ];
        
        $stmt2 = $pdo->prepare("
            SELECT p.code, mv.value 
            FROM measurement_values mv 
            JOIN parameters p ON p.id = mv.parameter_id 
            WHERE mv.measurement_id = :mid
        ");
        $stmt2->execute([':mid' => $m['id']]);
        $values = $stmt2->fetchAll();
        
        foreach ($values as $v) {
            $row[$v['code']] = (float)$v['value'];
        }
        
        $data[] = $row;
    }
    
    $data = array_reverse($data);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}