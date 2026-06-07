<?php
if (isset($_GET['action']) && $_GET['action'] === 'boilers') {
    require_once __DIR__ . '/../../database.php';
    
    $db = getDB();
    $pdo = $db->dbs;
    
    $stmt = $pdo->query("SELECT id, code, name, nominal_load, load_min, load_max FROM boilers ORDER BY name");
    $boilers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($boilers);
    exit;
}