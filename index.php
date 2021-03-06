<?php

$allowedMethods = array('FETCH', 'POST', 'DELETE', 'SAVE', 'INFO', 'GET', 'ADDPRICE', 'TRANSLIT', 'UPLOAD',
    'CHECKNAMES', 'SORT', 'EXPORT', 'IMPORT', 'LOGOUT', 'EXEC');
$allowedMethods = implode(",", $allowedMethods);

$headers = getallheaders();
if (!empty($headers['Secookie']))
    session_id($headers['Secookie']);
session_start();

chdir($_SERVER['DOCUMENT_ROOT']);
date_default_timezone_set("Europe/Moscow");
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-type: text/html; charset="utf-8"');

define('API_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/api/');
define('API_ROOT_URL', "http://" . $_SERVER['SERVER_NAME'] . "/api");

function writeLog($data)
{
    if (!is_string($data))
        $data = print_r($data, 1);
    $file = fopen($_SERVER['DOCUMENT_ROOT'] . "/api/debug.log", "a+");
    $query = "$data" . "\n";
    fputs($file, $query);
    fclose($file);
}

require_once 'lib/lib_function.php';
require_once API_ROOT . "vendor/autoload.php";

$apiMethod = $_SERVER['REQUEST_METHOD'];
$apiClass = parse_url($_SERVER["REQUEST_URI"]);
$apiClass = str_replace("api/", "", trim($apiClass['path'], "/"));
$origin = !empty($headers['Origin']) ? $headers['Origin'] : $headers['origin'];

if (!empty($origin)) {
    $url = parse_url($origin);
    if ($url) {
        if ($url['host'] == 'lapka.me')
            header("Access-Control-Allow-Origin: http://lapka.me");
        if ($url['host'] == 'localhost' && $url['port'] == 1500)
            header("Access-Control-Allow-Origin: http://localhost:1500");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Headers: Project, Secookie");
        header("Access-Control-Allow-Methods: $allowedMethods");
    }
    if ($apiMethod == "OPTIONS")
        exit;
}

if ($apiClass == "Auth" && strtolower($apiMethod) == "logout") {
    $_SESSION = array();
    session_destroy();
    echo "Session destroy!";
    exit;
}

if ($apiClass == "Auth" && strtolower($apiMethod) == "get") {
    if (empty($_SESSION['isAuth'])) {
        header("HTTP/1.1 401 Unauthorized");
        echo 'Сессия истекла! Необходима авторизация!';
        exit;
    }
}

$phpInput = file_get_contents('php://input');

if (empty($_SERVER['REQUEST_SCHEME']))
    $_SERVER['REQUEST_SCHEME'] = !empty($_SERVER['HTTPS']) ? 'https' : 'http';

define("HOSTNAME", $_SERVER["HTTP_HOST"]);
define('DOCUMENT_ROOT', $_SERVER["DOCUMENT_ROOT"]);
define('URL_ROOT', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']);
define('URL_FILES', URL_ROOT . "/files");
define('URL_IMAGES', URL_ROOT . "/images");
define('DIR_FILES', DOCUMENT_ROOT . "/files");

if ($apiClass != "Auth" && empty($_SESSION['isAuth'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo 'Необходима авторизация!';
    exit;
}

$apiObject = $apiClass;
if (!class_exists($apiClass = "\\SE\\Shop\\" . str_replace("/", "\\", $apiClass))) {
    header("HTTP/1.1 501 Not Implemented");
    echo "Объект '{$apiObject}' не найден!";
    exit;
}
if (!method_exists($apiClass, $apiMethod)) {
    header("HTTP/1.1 501 Not Implemented");
    echo "Метод'{$apiMethod}' не поддерживается!";
    exit;
}

$apiObject = new $apiClass($phpInput);
if ($apiObject->initConnection())
    $apiObject->$apiMethod();
$apiObject->output();
