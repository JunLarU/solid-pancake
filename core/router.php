<?php
require_once dirname(__DIR__) . '/core/DBManager.php';
require_once dirname(__DIR__) . '/responders/users.php';
require_once dirname(__DIR__) . '/responders/ingredientes.php';
require_once dirname(__DIR__) . '/responders/productos.php';
require_once dirname(__DIR__) . '/responders/menu.php';
require_once dirname(__DIR__) . '/responders/productos_especiales.php';
require_once dirname(__DIR__) . '/responders/avisos.php'; // NUEVO: agregamos avisos

$request = $_SERVER['REQUEST_URI'];
if ($request[0] == '/') {
    $request = substr($request, 1);
}
$position = strpos($request, '?');
if ($position !== false) {
    $request = substr($request, 0, $position);
}

switch ($request) {
    case 'api/users/signup':
        $data = json_decode(file_get_contents('php://input'), true);
        signupUser($data);
        break;

    case 'api/users/login':
        $data = json_decode(file_get_contents('php://input'), true);
        loginUser($data);
        break;

    case 'api/users':
        $method      = $_SERVER['REQUEST_METHOD'];
        $queryParams = $_GET;

        if ($method == 'GET' && isset($queryParams['role']) && $queryParams['role'] == 'admin') {
            listAdmins();
        }
        break;

    case 'api/users/update':
        $data = json_decode(file_get_contents('php://input'), true);
        updateUser($data);
        break;

    case 'api/users/delete':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteUser($data);
        break;

    case 'api/ingredientes':
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            listIngredientes();
        } elseif ($method === 'DELETE') {
                                 // Para DELETE: usar parámetros GET
            deleteIngrediente(); // Se manejará internamente
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(["error" => "JSON inválido en POST"]);
                return;
            }

            createIngrediente($data);
        } elseif ($method === 'PUT') {
            $data = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(["error" => "JSON inválido en PUT"]);
                return;
            }

            updateIngrediente($data);
        }
        break;

    // En tu index.php, actualiza la sección de productos:
    case 'api/productos':
        require_once dirname(__DIR__) . '/responders/productos.php';
        handleProductosRequest();
        break;

    case 'api/categorias-productos':
        require_once dirname(__DIR__) . '/responders/productos.php';
        listCategoriasProductos();
        break;

    case 'api/menus':
    case 'api/menus/secciones':
    case 'api/menus/semanal':
    case 'api/menus/hoy':
    case 'api/menus/fecha':
        handleMenusRequest();
        break;
    case 'api/menus/verificar':
        $fecha   = $_GET['fecha'] ?? '';
        $horario = $_GET['horario'] ?? '';
        verificarMenuExistente($fecha, $horario);
        break;

    case 'api/productos-especiales':
        handleProductosEspecialesRequest();
        break;

    // NUEVA RUTA: Avisos
    case 'api/avisos':
        handleAvisosRequest();
        break;

    // En tu switch statement, agrega:
    case 'api/estadisticas':
        require_once dirname(__DIR__) . '/responders/estadisticas.php';
        break;

    // NUEVA RUTA: Endpoints para usuarios normales
    case strpos($request, 'api/normaluser/') === 0:
        require_once dirname(__DIR__) . '/responders/normaluser.php';
        handleNormalUserRequest();
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint no encontrado"]);
        break;
}
