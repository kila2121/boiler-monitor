<?php
// Включаем ошибки для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем БД
require_once __DIR__ . '/../../database.php';
$db = getDB();
$pdo = $db->dbs;

// Логируем всё подряд
$log = date('Y-m-d H:i:s') . " " . $_SERVER['REQUEST_METHOD'] . " " . json_encode($_REQUEST) . "\n";
file_put_contents(__DIR__ . '/api_debug.log', $log, FILE_APPEND);

// GET запросы - получаем данные
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_settings') {
    $boilerCode = $_GET['boiler'] ?? 'tgm96';
    
    $stmt = $pdo->prepare("SELECT id, code, name, nominal_load, load_min, load_max FROM boilers WHERE code = ?");
    $stmt->execute([$boilerCode]);
    $boiler = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT rv.*, p.code, p.name, p.unit
        FROM reference_values rv
        JOIN parameters p ON p.id = rv.parameter_id
        WHERE rv.boiler_id = ?
        ORDER BY rv.load_min, p.id
    ");
    $stmt->execute([$boiler['id']]);
    $references = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['boiler' => $boiler, 'references' => $references]);
    exit;
}

// POST запросы - сохраняем данные
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Получаем ID котла
    $boilerCode = $_POST['boiler_id'] ?? '';
    $boilerId = null;
    if ($boilerCode) {
        $stmt = $pdo->prepare("SELECT id FROM boilers WHERE code = ?");
        $stmt->execute([$boilerCode]);
        $boilerId = $stmt->fetchColumn();
    }
    
    // Сохраняем параметры котла
    if ($action === 'saveBoilerParams') {
        $stmt = $pdo->prepare("UPDATE boilers SET nominal_load = ?, load_min = ?, load_max = ? WHERE id = ?");
        $stmt->execute([
            $_POST['nominal_load'],
            $_POST['load_min'],
            $_POST['load_max'],
            $boilerId
        ]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Сохраняем эталонные значения
    if ($action === 'saveReference') {
        $loadMin = $_POST['load_min'];
        $loadMax = $_POST['load_max'];
        $values = json_decode($_POST['values'], true);
        
        foreach ($values as $paramId => $data) {
            // Сначала удаляем старую запись
            $stmt = $pdo->prepare("DELETE FROM reference_values 
                                   WHERE boiler_id = ? 
                                   AND parameter_id = ? 
                                   AND load_min = ? 
                                   AND load_max = ?");
            $stmt->execute([$boilerId, $paramId, $loadMin, $loadMax]);
            
            // Вставляем новую
            $stmt = $pdo->prepare("INSERT INTO reference_values 
                                   (boiler_id, parameter_id, load_min, load_max, reference_value, max_deviation) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $boilerId,
                $paramId,
                $loadMin,
                $loadMax,
                $data['value'],
                $data['deviation']
            ]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Очистка старых записей
    if ($action === 'cleanNow') {
        $pdo->exec("DELETE FROM measurement_values WHERE measurement_id IN (SELECT id FROM measurements WHERE timestamp < NOW() - INTERVAL 1 DAY)");
        $pdo->exec("DELETE FROM measurements WHERE timestamp < NOW() - INTERVAL 1 DAY");
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Обновление периода хранения
    if ($action === 'updateRetention') {
        $days = (int)$_POST['days'];
        $pdo->exec("DROP EVENT IF EXISTS clean_old_measurements");
        $pdo->exec("CREATE EVENT clean_old_measurements ON SCHEDULE EVERY 1 DAY STARTS CURRENT_TIMESTAMP DO BEGIN DELETE FROM measurement_values WHERE measurement_id IN (SELECT id FROM measurements WHERE timestamp < NOW() - INTERVAL $days DAY); DELETE FROM measurements WHERE timestamp < NOW() - INTERVAL $days DAY; END");
        echo json_encode(['success' => true]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;
}
?>