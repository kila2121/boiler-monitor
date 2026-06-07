<?php
if (isset($_GET['action']) && $_GET['action'] === 'get_settings') {
    require_once __DIR__ . '/../../database.php';
    
    $boilerCode = $_GET['boiler'] ?? 'tgm96';
    $db = getDB();
    $pdo = $db->dbs;
    
    // Данные котла
    $stmt = $pdo->prepare("SELECT id, code, name, nominal_load, load_min, load_max FROM boilers WHERE code = :code");
    $stmt->execute([':code' => $boilerCode]);
    $boiler = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$boiler) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Котёл не найден']);
        exit;
    }
    
    // Эталоны
    $stmt = $pdo->prepare("
        SELECT rv.id, rv.parameter_id, p.code, p.name, p.unit, 
               rv.load_min, rv.load_max, rv.reference_value, rv.max_deviation
        FROM reference_values rv
        JOIN parameters p ON p.id = rv.parameter_id
        WHERE rv.boiler_id = :id
        ORDER BY rv.load_min, p.id
    ");
    $stmt->execute([':id' => $boiler['id']]);
    $references = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'boiler'     => $boiler,
        'references' => $references
    ]);
    exit;
}