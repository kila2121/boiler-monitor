<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['action'])) {
    if ($_POST['action'] !== 'saveReference' && $_POST['action'] !== 'cleanNow' && $_POST['action'] !== 'updateRetention') {
        return;
    }

    require_once __DIR__ . '/../../classes/csrf.php';

    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Необходима авторизация']);
        exit;
    }
    
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!validate_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ошибка CSRF: неверный токен']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Метод не разрешён']);
        exit;
    }

    require_once __DIR__ . '/../../database.php';
    $db = getDB();
    $pdo = $db->dbs;

    $action = $_POST['action'];

    $boilerCode = $_POST['boiler_id'] ?? '';
    $boilerId = null;
    if ($boilerCode) {
        $stmt = $pdo->prepare("SELECT id FROM boilers WHERE code = ?");
        $stmt->execute([$boilerCode]);
        $boilerId = $stmt->fetchColumn();
    }

    if ($action === 'saveReference') {
        $loadMin = filter_var($_POST['load_min'] ?? 0, FILTER_VALIDATE_FLOAT);
        $loadMax = filter_var($_POST['load_max'] ?? 0, FILTER_VALIDATE_FLOAT);
        
        if ($loadMin === false || $loadMax === false) {
            echo json_encode(['success' => false, 'message' => 'Некорректный диапазон нагрузки']);
            exit;
        }
        
        $values = json_decode($_POST['values'] ?? '{}', true);
        if (!is_array($values)) {
            echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
            exit;
        }
        
        $pdo->beginTransaction();
        try {
            foreach ($values as $paramId => $data) {
                $paramId = filter_var($paramId, FILTER_VALIDATE_INT);
                $value = filter_var($data['value'] ?? 0, FILTER_VALIDATE_FLOAT);
                $deviation = filter_var($data['deviation'] ?? 0, FILTER_VALIDATE_FLOAT);
                
                if ($paramId === false || $value === false || $deviation === false) {
                    throw new Exception('Некорректные данные параметра');
                }
                
                $stmt = $pdo->prepare("DELETE FROM reference_values 
                                       WHERE boiler_id = ? 
                                       AND parameter_id = ? 
                                       AND load_min = ? 
                                       AND load_max = ?");
                $stmt->execute([$boilerId, $paramId, $loadMin, $loadMax]);
                
                $stmt = $pdo->prepare("INSERT INTO reference_values 
                                       (boiler_id, parameter_id, load_min, load_max, reference_value, max_deviation) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$boilerId, $paramId, $loadMin, $loadMax, $value, $deviation]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'cleanNow') {
        $pdo->exec("DELETE FROM measurement_values WHERE measurement_id IN (SELECT id FROM measurements WHERE timestamp < NOW() - INTERVAL 1 DAY)");
        $pdo->exec("DELETE FROM measurements WHERE timestamp < NOW() - INTERVAL 1 DAY");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'updateRetention') {
        $days = filter_var($_POST['days'] ?? 1, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 365) {
            echo json_encode(['success' => false, 'message' => 'Некорректное количество дней (1-365)']);
            exit;
        }
        
        $pdo->exec("DROP EVENT IF EXISTS clean_old_measurements");
        
        $sql = "CREATE EVENT clean_old_measurements 
                ON SCHEDULE EVERY 1 DAY 
                STARTS CURRENT_TIMESTAMP 
                DO BEGIN 
                    DELETE FROM measurement_values 
                    WHERE measurement_id IN (SELECT id FROM measurements WHERE timestamp < NOW() - INTERVAL $days DAY);
                    DELETE FROM measurements WHERE timestamp < NOW() - INTERVAL $days DAY;
                END";
        $pdo->exec($sql);
        
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;
}
?>