<?php
if (isset($_GET['action']) && $_GET['action'] === 'stats') {
    require_once __DIR__ . '/../../database.php';
    
    $boilerCode = $_GET['boiler'] ?? 'tgm96';
    
    $db = getDB();
    $pdo = $db->dbs;
    
    // Получаем ID котла
    $stmt = $pdo->prepare("SELECT id FROM boilers WHERE code = :code");
    $stmt->execute([':code' => $boilerCode]);
    $boilerId = $stmt->fetchColumn();
    
    if (!$boilerId) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Котёл не найден']);
        exit;
    }
    
    // Всего записей за последний час
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM measurements WHERE boiler_id = :id AND `timestamp` >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)");
    $stmt->execute([':id' => $boilerId]);
    $totalCount = (int)$stmt->fetchColumn();
    
    // Средняя нагрузка
    $stmt = $pdo->prepare("SELECT AVG(`load`) FROM measurements WHERE boiler_id = :id AND `timestamp` >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)");
    $stmt->execute([':id' => $boilerId]);
    $avgLoad = round((float)$stmt->fetchColumn(), 0);
    
    // Количество отклонений за последний час
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM deviation_log dl
        JOIN measurements m ON m.id = dl.measurement_id
        WHERE m.boiler_id = :id AND dl.created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
    ");
    $stmt->execute([':id' => $boilerId]);
    $deviationCount = (int)$stmt->fetchColumn();
    
    $normalCount = $totalCount - $deviationCount;
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'total_count'     => $totalCount,
        'avg_load'        => $avgLoad ?: 0,
        'normal_count'    => $normalCount > 0 ? $normalCount : 0,
        'deviation_count' => $deviationCount
    ]);
    exit;
}