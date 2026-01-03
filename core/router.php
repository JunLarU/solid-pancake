<?php
require_once (dirname(__DIR__) . '/core/DBManager.php');
require_once (dirname(__DIR__) . '/responders/users.php');


$request = $_SERVER['REQUEST_URI'];
if ($request[0]== '/') {
    $request = substr($request, 1);
}
$position = strpos($request, '?');
if($position !== false){
    $request = substr($request, 0, $position);
}
//echo $request;
switch ($request) {
    case 'api/users/signup':
        $data = json_decode(file_get_contents('php://input'), true);
        signupUser($data);
        break;

    case 'api/users/login':
        $data = json_decode(file_get_contents('php://input'), true);
        loginUser($data);
        break;
    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint no encontrado"]);
        break;
}