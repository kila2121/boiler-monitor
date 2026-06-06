<?php
function calculate($fact, $ref, $boiler) {
    try {
        $db = getDB();
        $pdo = $db->dbs;
    } catch (Exception $e) {
        return ['error' => 'Нет соединения с БД: ' . $e->getMessage()];
    }

    $measurementId = $fact['insert_id'] ?? null;
    $result = [];

    foreach ($fact as $key => $value) {
        if (in_array($key, ['boiler_id', 'load', 'timestamp', 'insert_id', 'error'])) {
            continue;
        }
        if (!isset($ref[$key])) continue;

        $dev = round($value - $ref[$key], 2);
        $max = $ref['max_deviation'][$key] ?? 999;
        $status = abs($dev) > $max ? '⚠️' : '✓';

        $result[$key] = [
            'fact'   => $value,
            'ref'    => $ref[$key],
            'dev'    => $dev,
            'status' => $status
        ];

        if ($measurementId && $status === '⚠️') {
            $stmt = $pdo->prepare("INSERT INTO deviation_log 
                (measurement_id, parameter, deviation, status)
                VALUES (:mid, :param, :dev, :status)");
            $stmt->execute([
                ':mid'    => $measurementId,
                ':param'  => $key,
                ':dev'    => $dev,
                ':status' => $status
            ]);
        }
    }

    return $result;
}