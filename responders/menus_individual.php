<?php
// menus_individual.php
require_once dirname(__DIR__) . '/core/DBManager.php';

function crearMenuIndividual($data) {
    header("Content-Type: application/json");

    try {
        $fecha = trim($data["fecha"] ?? "");
        $diaSemana = trim($data["diaSemana"] ?? "");
        $horario = trim($data["horario"] ?? "");
        $numeroSemana = intval($data["numeroSemana"] ?? 0);
        $anio = intval($data["anio"] ?? 0);
        $idUsuario = intval($data["idUsuario"] ?? 0);

        if (empty($fecha) || empty($diaSemana) || empty($horario) || 
            $numeroSemana <= 0 || $anio <= 0) {
            throw new Exception("Parámetros incompletos");
        }

        $pdo = (new DBManager())->getPDO();
        
        // Verificar si ya existe
        $stmtCheck = $pdo->prepare("
            SELECT ID FROM MenuSemanal 
            WHERE Fecha = :fecha AND Horario = :horario
        ");
        $stmtCheck->execute([
            ":fecha" => $fecha,
            ":horario" => $horario
        ]);
        
        $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existente) {
            echo json_encode([
                "success" => true,
                "id" => $existente['ID'],
                "message" => "Menú ya existente",
                "alreadyExists" => true
            ]);
            return;
        }
        
        // Crear nuevo menú
        $stmt = $pdo->prepare("
            INSERT INTO MenuSemanal 
            (Fecha, DiaSemana, Horario, NumeroSemana, Anio, Activo, IDUsuarioCreador)
            VALUES (:fecha, :dia, :horario, :semana, :anio, 1, :usuario)
        ");
        
        $stmt->execute([
            ":fecha" => $fecha,
            ":dia" => $diaSemana,
            ":horario" => $horario,
            ":semana" => $numeroSemana,
            ":anio" => $anio,
            ":usuario" => $idUsuario > 0 ? $idUsuario : null
        ]);
        
        $idMenu = $pdo->lastInsertId();
        
        echo json_encode([
            "success" => true,
            "id" => $idMenu,
            "message" => "Menú creado exitosamente",
            "alreadyExists" => false
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// Manejar solicitud
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    crearMenuIndividual($data);
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
?>