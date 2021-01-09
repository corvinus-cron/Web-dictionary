<?php
require_once("api/requestHandler.php");
use api\requestHandler;

$uri = explode('/', $_SERVER['REQUEST_URI']);
if (empty($uri[1])) {
    require('pages/mainpage.php');exit;
} elseif (isset($uri[1])) {
    //header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    if ($uri[1] !== 'api') {
        $uri[2] = 'checkword';
        $input['word'] = urldecode($uri[1]);
    }
    try {
        resp((new requestHandler($input))->{$uri[2]}());
    } catch (Exception $e) {
        resp([false, $e->getMessage()]);
    }
}
// ответ от сервера
function resp($text) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($text,271);exit;
}