<?php
if (isset($_GET['action']) && $_GET['action'] === 'history') {
    require_once __DIR__ . '/../../database.php';
    
    $limit = (int)($_GET['limit'] ?? 30);
    $minutes = (int)($_GET['minutes'] ?? 0);
    
    $db = getDB();
    $pdo = $db->dbs;
    
    // Получаем ID котла по коду
    $stmt = $pdo->prepare("SELECT id FROM boilers WHERE code = :code");
    $stmt->execute([':code' => 'tgm96']);
    $boilerId = $stmt->fetchColumn();
    
    if (!$boilerId) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    
    // Если указан период в минутах
    if ($minutes > 0) {
        $sql = "
            SELECT 
                m.id,
                m.boiler_id,
                m.`load`,
                m.`timestamp`,
                MAX(CASE WHEN p.code = 'excess_air' THEN mv.value END) AS excess_air,
                MAX(CASE WHEN p.code = 'flue_gas_temp' THEN mv.value END) AS flue_gas_temp,
                MAX(CASE WHEN p.code = 'gas_flow' THEN mv.value END) AS gas_flow,
                MAX(CASE WHEN p.code = 'steam_pressure' THEN mv.value END) AS steam_pressure,
                MAX(CASE WHEN p.code = 'steam_temperature' THEN mv.value END) AS steam_temperature
            FROM measurements m
            LEFT JOIN measurement_values mv ON mv.measurement_id = m.id
            LEFT JOIN parameters p ON p.id = mv.parameter_id
            WHERE m.boiler_id = :id 
              AND m.`timestamp` >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
            GROUP BY m.id, m.boiler_id, m.`load`, m.`timestamp`
            ORDER BY m.`timestamp` ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $boilerId, PDO::PARAM_INT);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
    } else {
        $sql = "
            SELECT 
                m.id,
                m.boiler_id,
                m.`load`,
                m.`timestamp`,
                MAX(CASE WHEN p.code = 'excess_air' THEN mv.value END) AS excess_air,
                MAX(CASE WHEN p.code = 'flue_gas_temp' THEN mv.value END) AS flue_gas_temp,
                MAX(CASE WHEN p.code = 'gas_flow' THEN mv.value END) AS gas_flow,
                MAX(CASE WHEN p.code = 'steam_pressure' THEN mv.value END) AS steam_pressure,
                MAX(CASE WHEN p.code = 'steam_temperature' THEN mv.value END) AS steam_temperature
            FROM measurements m
            LEFT JOIN measurement_values mv ON mv.measurement_id = m.id
            LEFT JOIN parameters p ON p.id = mv.parameter_id
            WHERE m.boiler_id = :id
            GROUP BY m.id, m.boiler_id, m.`load`, m.`timestamp`
            ORDER BY m.`timestamp` DESC
            LIMIT :limit
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $boilerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Преобразуем числовые значения
    foreach ($data as &$row) {
        foreach (['excess_air', 'flue_gas_temp', 'gas_flow', 'steam_pressure', 'steam_temperature'] as $param) {
            if (isset($row[$param]) && $row[$param] !== null) {
                $row[$param] = (float)$row[$param];
            } else {
                $row[$param] = null;
            }
        }
        if (isset($row['load']) && $row['load'] !== null) {
            $row['load'] = (float)$row['load'];
        }
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}