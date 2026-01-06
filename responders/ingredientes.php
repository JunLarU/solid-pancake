<?php
require_once dirname(__DIR__) . '/core/DBManager.php';
function listIngredientes()
{
    header("Content-Type: application/json");
    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        $stmt = $pdo->prepare("SELECT i.ID, i.Nombre, ci.Nombre AS Categoria, i.Descripcion, i.Calorias, i.Alergeno
                               FROM Ingredientes i
                               LEFT JOIN CategoriasIngredientes ci ON i.IDCategoria = ci.ID");
        $stmt->execute();
        $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["ingredientes" => $ingredientes]);
    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}
function createIngrediente($data)
{
    header("Content-Type: application/json");

    if (! $data || ! isset($data["nombre"])) {
        http_response_code(400);
        echo json_encode(["error" => "JSON inválido o faltan campos"]);
        return;
    }

    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        $stmt = $pdo->prepare("INSERT INTO Ingredientes
            (Nombre, IDCategoria, Descripcion, Calorias, Alergeno)
            VALUES (:nombre, :idcat, :desc, :cal, :alerg)");

        $stmt->execute([
            ":nombre" => $data["nombre"],
            ":idcat"  => $data["idCategoria"] ?? null,
            ":desc"   => $data["descripcion"] ?? "",
            ":cal"    => $data["calorias"] ?? 0,
            ":alerg"  => isset($data["alergeno"]) ? intval($data["alergeno"]) : 0,
        ]);

        http_response_code(201);
        echo json_encode(["message" => "Ingrediente creado", "id" => $pdo->lastInsertId()]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}
function updateIngrediente($data)
{
    header("Content-Type: application/json");
    if (! $data || ! isset($data["id"])) {
        http_response_code(400);
        echo json_encode(["error" => "JSON inválido o id faltante"]);
        return;
    }

    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        $stmt = $pdo->prepare("UPDATE Ingredientes SET
            Nombre = :nombre,
            IDCategoria = :idcat,
            Descripcion = :desc,
            Calorias = :cal,
            Alergeno = :alerg
            WHERE ID = :id");

        $stmt->execute([
            ":id"     => $data["id"],
            ":nombre" => $data["nombre"],
            ":idcat"  => $data["idCategoria"] ?? null,
            ":desc"   => $data["descripcion"] ?? "",
            ":cal"    => $data["calorias"] ?? 0,
            ":alerg"  => isset($data["alergeno"]) ? intval($data["alergeno"]) : 0,
        ]);

        echo json_encode(["message" => "Ingrediente actualizado"]);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno: " . $ex->getMessage()]);
    }
}
function deleteIngrediente($data = null)
{
    header("Content-Type: application/json");

    // 🔧 SOPORTAR DELETE con parámetros GET
    $id = null;

    // Buscar ID en parámetros GET primero
    if (isset($_GET['id']) && ! empty($_GET['id'])) {
        $id = intval($_GET['id']);
    }
    // Si no está en GET, intentar del body (para compatibilidad con POST)
    elseif ($data === null) {
        $raw = file_get_contents("php://input");
        if (! empty($raw)) {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data["id"])) {
                $id = intval($data["id"]);
            }
        }
    }
    // Si se pasó $data como parámetro
    elseif (is_array($data) && isset($data["id"])) {
        $id = intval($data["id"]);
    }

    if ($id === null) {
        http_response_code(400);
        echo json_encode(["error" => "ID faltante. Use DELETE /api/ingredientes?id=X"]);
        return;
    }

    try {
        $dbm = new DBManager();
        $pdo = $dbm->getPDO();

        // Verificar si el ingrediente está en uso antes de eliminar
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM ProductosIngredientes WHERE IDIngrediente = ?
            UNION ALL
            SELECT COUNT(*) FROM SustitucionesIngredientes WHERE IDIngredienteSustituto = ?
        ");
        $checkStmt->execute([$id, $id]);
        $results = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

        $totalUso = array_sum($results);

        if ($totalUso > 0) {
            http_response_code(409);
            echo json_encode([
                "error" => "No se puede eliminar: el ingrediente está en uso en " . $totalUso . " productos",
            ]);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM Ingredientes WHERE ID = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Ingrediente eliminado",
                "id"      => $id,
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "error" => "Ingrediente no encontrado con ID: " . $id,
            ]);
        }

    } catch (PDOException $ex) {
        // 🔒 FK RESTRICT
        if ($ex->getCode() === "23000") {
            http_response_code(409);
            echo json_encode([
                "error" => "No se puede eliminar: el ingrediente está en uso",
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "error" => "Error interno: " . $ex->getMessage(),
            ]);
        }
    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode([
            "error" => "Error interno: " . $ex->getMessage(),
        ]);
    }
}
