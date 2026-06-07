<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/../../database.php';
    $db = getDB();
    $pdo = $db->dbs;
    
    if ($_POST['action'] === 'saveReference') {
        $boilerId = (int)$_POST['boiler_id'];
        $loadMin = (float)$_POST['load_min'];
        $loadMax = (float)$_POST['load_max'];
        
        foreach ($_POST['values'] as $paramId => $data) {
            $stmt = $pdo->prepare("
                UPDATE reference_values 
                SET reference_value = :val, max_deviation = :dev
                WHERE boiler_id = :bid AND parameter_id = :pid 
                  AND load_min = :lmin AND load_max = :lmax
            ");
            $stmt->execute([
                ':val'  => (float)$data['value'],
                ':dev'  => (float)$data['deviation'],
                ':bid'  => $boilerId,
                ':pid'  => (int)$paramId,
                ':lmin' => $loadMin,
                ':lmax' => $loadMax
            ]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'saveBoilerParams') {
        $stmt = $pdo->prepare("UPDATE boilers SET nominal_load = :n, load_min = :min, load_max = :max WHERE id = :id");
        $stmt->execute([
            ':n'   => (float)$_POST['nominal_load'],
            ':min' => (float)$_POST['load_min'],
            ':max' => (float)$_POST['load_max'],
            ':id'  => (int)$_POST['boiler_id']
        ]);
        echo json_encode(['success' => true]);
        exit;
    }
}