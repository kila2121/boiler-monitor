<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['action']) && $_GET['action'] === 'boilers') {
    if (!isset($_SESSION['user'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Доступ запрещён. Авторизуйтесь.']);
        exit;
    }

    require_once __DIR__ . '/../../database.php';
    $db = getDB();
    $pdo = $db->dbs;
    
    $stmt = $pdo->query("SELECT id, code, name, load_min, load_max FROM boilers ORDER BY name");
    $boilers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($boilers);
    exit;
}
?>