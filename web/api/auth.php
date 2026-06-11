<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . "/../../classes/csrf.php";

if(isset($_REQUEST['action'])){

    if ($_REQUEST['action'] === 'logout') {
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Вы успешно вышли']);
        exit;
    }

    if ($_REQUEST['action'] === 'auth') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /');
            exit;
        }

        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!$token || !($isAjax ? validate_csrf_token($token) : verify_csrf_token($token))) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Ошибка безопасности: неверный токен.']);
                exit;
            }
            $_SESSION['error'] = 'Ошибка безопасности: неверный токен';
            header('Location: /');
            exit;
        }

        $ini = parse_ini_file('../config.ini', true);
        if (!$ini) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ошибка конфигурации']);
            exit;
        }
        $userConfig = $ini['accessUser'];
        $login = $userConfig['login'];
        $password = $userConfig['password'];

        if ($_POST['login'] === $login && $_POST['pass'] === $password) {
            $_SESSION['user'] = $login;
            $_SESSION['success'] = 'Добро пожаловать!';
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => '/']);
                exit;
            }
            
            header('Location: /');
            exit;
        } else {
            $_SESSION['error'] = 'Неверный логин или пароль';
            $_SESSION['form_data'] = $_POST;
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Неверный логин или пароль']);
                exit;
            }
            
            header('Location: /');
            exit;
        }
    }
}
?>