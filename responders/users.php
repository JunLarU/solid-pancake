<?php
require_once (dirname(__DIR__) . '/core/DBManager.php');
function signupUser($data) {
    header("Content-Type: application/json");

    if (!$data) {
        http_response_code(400);
        echo json_encode(["error" => "JSON inválido"]);
        return;
    }

    // Campos esperados
    $expediente    = trim($data["expediente"] ?? "");
    $nombre        = trim($data["nombre"] ?? "");
    $apellidoP     = trim($data["apellidoPaterno"] ?? "");
    $apellidoM     = trim($data["apellidoMaterno"] ?? "");
    $correo        = trim($data["correo"] ?? "");
    $telefono      = trim($data["telefono"] ?? "");
    $nip           = trim($data["nip"] ?? "");
    $tipo          = trim($data["tipo"] ?? "Usuario");

    // Validar campos
    if (empty($expediente) || empty($nombre) || empty($apellidoP) ||
        empty($correo) || empty($nip)) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan campos obligatorios"]);
        return;
    }

    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        // Verificar duplicados
        $stmt = $pdo->prepare("SELECT ID FROM Usuarios WHERE Expediente = :exp OR Correo = :correo LIMIT 1");
        $stmt->execute([
            ":exp"    => $expediente,
            ":correo" => $correo
        ]);

        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(["error" => "Expediente o correo ya registrados"]);
            return;
        }

        // Hash del NIP
        $nipHash = password_hash($nip, PASSWORD_BCRYPT);

        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO Usuarios 
                (Expediente, Nombre, ApellidoPaterno, ApellidoMaterno, NIP, Correo, Telefono, Tipo)
            VALUES 
                (:exp, :nom, :ap, :am, :nip, :correo, :tel, :tipo)
        ");

        $stmt->execute([
            ":exp"    => $expediente,
            ":nom"    => $nombre,
            ":ap"     => $apellidoP,
            ":am"     => $apellidoM,
            ":nip"    => $nipHash,
            ":correo" => $correo,
            ":tel"    => $telefono,
            ":tipo"   => $tipo
        ]);

        http_response_code(201);
        echo json_encode([
            "message" => "Usuario registrado correctamente",
            "userId"  => $pdo->lastInsertId()
        ]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}

function loginUser($data) {
    header("Content-Type: application/json");

    if (!$data) {
        http_response_code(400);
        echo json_encode(["error" => "JSON inválido"]);
        return;
    }

    $expediente = trim($data["expediente"] ?? "");
    $nip        = trim($data["nip"] ?? "");

    if (empty($expediente) || empty($nip)) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan campos obligatorios"]);
        return;
    }

    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        // Buscar usuario por expediente
        $stmt = $pdo->prepare(
            "SELECT ID, Expediente, Nombre, ApellidoPaterno, ApellidoMaterno, NIP, Correo, Telefono, Tipo
            FROM Usuarios
            WHERE Expediente = :exp
            LIMIT 1"
        );
        $stmt->execute([":exp" => $expediente]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(["error" => "Usuario no encontrado"]);
            return;
        }

        // Verificar NIP
        if (!password_verify($nip, $user["NIP"])) {
            http_response_code(401);
            echo json_encode(["error" => "NIP incorrecto"]);
            return;
        }

        // Login correcto: mandar datos
        unset($user["NIP"]); // no enviar la contraseña

        http_response_code(200);
        echo json_encode([
            "message" => "Login exitoso",
            "user" => $user
        ]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}
