<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['action']) && $_GET['action'] === 'get_settings') {
    if (!isset($_SESSION['user'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Доступ запрещён. Авторизуйтесь.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Метод не разрешён']);
        exit;
    }

    require_once __DIR__ . '/../../database.php';
    $db = getDB();
    $pdo = $db->dbs;

    $boilerCode = $_GET['boiler'] ?? 'tgm96';

    $stmt = $pdo->prepare("SELECT id, code, name, load_min, load_max FROM boilers WHERE code = ?");
    $stmt->execute([$boilerCode]);
    $boiler = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$boiler) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Котёл не найден']);
        exit;
    }

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
?>