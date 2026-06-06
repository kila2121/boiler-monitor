<?php
require_once __DIR__ . '/classes/pdo_create.php';

function getDB() {
    static $instance = null;
    if ($instance !== null) return $instance;

    $ini = parse_ini_file(__DIR__ . '/config.ini', true);
    $dbConfig = $ini['database'];

    $user = $dbConfig['user'];
    $host = $dbConfig['host'] . (isset($dbConfig['port']) ? ':' . $dbConfig['port'] : '');
    $pass = $dbConfig['pass'] ?? '';
    $name = $dbConfig['name'];

    $instance = new pdo_create($user, $host, $pass, $name);
    return $instance;
}