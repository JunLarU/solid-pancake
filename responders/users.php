<?php
require_once dirname(__DIR__) . '/core/DBManager.php';
function signupUser($data)
{
    header("Content-Type: application/json");

    if (! $data) {
        http_response_code(400);
        echo json_encode(["error" => "JSON inválido"]);
        return;
    }

    // Campos esperados
    $expediente = trim($data["expediente"] ?? "");
    $nombre     = trim($data["nombre"] ?? "");
    $apellidoP  = trim($data["apellidoPaterno"] ?? "");
    $apellidoM  = trim($data["apellidoMaterno"] ?? "");
    $correo     = trim($data["correo"] ?? "");
    $telefono   = trim($data["telefono"] ?? "");
    $nip        = trim($data["nip"] ?? "");
    $tipo       = trim($data["tipo"] ?? "Usuario");

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
            ":correo" => $correo,
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
            ":tipo"   => $tipo,
        ]);

        http_response_code(201);
        echo json_encode([
            "message" => "Usuario registrado correctamente",
            "userId"  => $pdo->lastInsertId(),
        ]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}

function loginUser($data)
{
    header("Content-Type: application/json");

    if (! $data) {
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

        if (! $user) {
            http_response_code(404);
            echo json_encode(["error" => "Usuario no encontrado"]);
            return;
        }

        // Verificar NIP
        if (! password_verify($nip, $user["NIP"])) {
            http_response_code(401);
            echo json_encode(["error" => "NIP incorrecto"]);
            return;
        }

                             // Login correcto: mandar datos
        unset($user["NIP"]); // no enviar la contraseña

        // Determinar si es admin
        $isAdmin = ($user["Tipo"] === "Administrador");

        http_response_code(200);
        echo json_encode([
            "message" => "Login exitoso",
            "user"    => $user,
            "isAdmin" => $isAdmin,
        ]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}

function listAdmins()
{
    header("Content-Type: application/json");
    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        $stmt = $pdo->prepare("SELECT Expediente, Nombre, ApellidoPaterno, ApellidoMaterno, Correo, Telefono, Tipo FROM Usuarios WHERE Tipo = 'Administrador'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(["admins" => $admins]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}

function updateUser($data)
{
    header("Content-Type: application/json");

    if (! $data || ! isset($data["expediente"])) {
        http_response_code(400);
        echo json_encode(["error" => "JSON inválido o expediente faltante"]);
        return;
    }

    $expediente = trim($data["expediente"]);
    $nombre     = trim($data["nombre"] ?? "");
    $apellidoP  = trim($data["apellidoPaterno"] ?? "");
    $apellidoM  = trim($data["apellidoMaterno"] ?? "");
    $correo     = trim($data["correo"] ?? "");
    $telefono   = trim($data["telefono"] ?? "");
    $tipo       = trim($data["tipo"] ?? "Usuario");
    $nip        = trim($data["nip"] ?? ""); // Puede venir vacío

    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        // Obtener el NIP actual si existe
        $stmt = $pdo->prepare("SELECT NIP FROM Usuarios WHERE Expediente = :exp LIMIT 1");
        $stmt->execute([":exp" => $expediente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            http_response_code(404);
            echo json_encode(["error" => "Usuario no encontrado"]);
            return;
        }

        // Determinar si se actualiza el NIP o se conserva el actual
        if (! empty($nip)) {
            // Nuevo NIP: hash
            $newNipHash = password_hash($nip, PASSWORD_BCRYPT);
        } else {
            // Mantener NIP actual
            $newNipHash = $row["NIP"];
        }

        // Preparar la actualización
        $stmtUpdate = $pdo->prepare("
            UPDATE Usuarios SET
                Nombre = :nombre,
                ApellidoPaterno = :ap,
                ApellidoMaterno = :am,
                Correo = :correo,
                Telefono = :tel,
                Tipo = :tipo,
                NIP = :nip
            WHERE Expediente = :exp
        ");

        $stmtUpdate->execute([
            ":exp"    => $expediente,
            ":nombre" => $nombre,
            ":ap"     => $apellidoP,
            ":am"     => $apellidoM,
            ":correo" => $correo,
            ":tel"    => $telefono,
            ":tipo"   => $tipo,
            ":nip"    => $newNipHash,
        ]);

        http_response_code(200);
        echo json_encode(["message" => "Usuario actualizado correctamente"]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}

function deleteUser($data)
{
    header("Content-Type: application/json");

    if (! $data || ! isset($data["expediente"])) {
        http_response_code(400);
        echo json_encode(["error" => "JSON inválido o expediente faltante"]);
        return;
    }

    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        $stmt = $pdo->prepare("DELETE FROM Usuarios WHERE Expediente = :exp");
        $stmt->execute([":exp" => $data["expediente"]]);

        http_response_code(200);
        echo json_encode(["message" => "Usuario eliminado"]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}
