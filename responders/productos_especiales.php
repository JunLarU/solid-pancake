<?php
require_once dirname(__DIR__) . '/core/DBManager.php';

function listProductosEspeciales()
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();

        // Obtener productos especiales con información del producto base
        $stmt = $pdo->prepare("
            SELECT
                pe.ID,
                pe.IDProducto,
                pe.FechaInicio,
                pe.FechaFin,
                pe.Descripcion,
                pe.PrecioEspecial,
                pe.Activo,
                p.Nombre AS NombreProducto,
                p.PrecioBase AS PrecioNormal,
                p.URLFoto,
                c.Nombre AS Categoria
            FROM ProductosEspeciales pe
            JOIN Productos p ON pe.IDProducto = p.ID
            JOIN CategoriasProductos c ON p.IDCategoria = c.ID
            ORDER BY pe.FechaInicio DESC, pe.ID DESC
        ");
        $stmt->execute();
        $productosEspeciales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["productos_especiales" => $productosEspeciales]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function getProductoEspecial($id)
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();

        $stmt = $pdo->prepare("
            SELECT
                pe.ID,
                pe.IDProducto,
                pe.FechaInicio,
                pe.FechaFin,
                pe.Descripcion,
                pe.PrecioEspecial,
                pe.Activo,
                p.Nombre AS NombreProducto,
                p.PrecioBase AS PrecioNormal,
                p.Descripcion AS DescripcionProducto,
                p.IDCategoria,
                c.Nombre AS Categoria,
                p.URLFoto
            FROM ProductosEspeciales pe
            JOIN Productos p ON pe.IDProducto = p.ID
            JOIN CategoriasProductos c ON p.IDCategoria = c.ID
            WHERE pe.ID = :id
        ");
        $stmt->execute([":id" => $id]);
        $productoEspecial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $productoEspecial) {
            http_response_code(404);
            echo json_encode(["error" => "Producto especial no encontrado"]);
            return;
        }

        echo json_encode(["producto_especial" => $productoEspecial]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function createProductoEspecial($data)
{
    header("Content-Type: application/json; charset=UTF-8");
    ob_start();

    try {
        error_log("=== CREATE PRODUCTO ESPECIAL INICIADO ===");

        if (empty($data)) {
            $rawInput = file_get_contents('php://input');
            error_log("Raw input: " . $rawInput);
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON inválido: " . json_last_error_msg());
            }
        }

        if (! is_array($data)) {
            throw new Exception("Los datos no son un array válido");
        }

        // Validar campos obligatorios
        $camposRequeridos = ["idProducto", "fechaInicio", "fechaFin", "precioEspecial"];
        foreach ($camposRequeridos as $campo) {
            if (! isset($data[$campo]) || empty($data[$campo])) {
                throw new Exception("El campo '$campo' es obligatorio");
            }
        }

        // Validar fechas - AHORA ACEPTA FECHA Y HORA
        $fechaInicio = null;
        $fechaFin    = null;

        // Intentar diferentes formatos de fecha
        try {
            $fechaInicio = parseDateTime($data["fechaInicio"]);
        } catch (Exception $e) {
            throw new Exception("Formato de fecha de inicio inválido: " . $data["fechaInicio"]);
        }

        try {
            $fechaFin = parseDateTime($data["fechaFin"]);
        } catch (Exception $e) {
            throw new Exception("Formato de fecha de fin inválido: " . $data["fechaFin"]);
        }

        if ($fechaInicio > $fechaFin) {
            throw new Exception("La fecha de inicio no puede ser posterior a la fecha de fin");
        }

        // Validar precio
        $precioEspecial = floatval($data["precioEspecial"]);
        if ($precioEspecial <= 0) {
            throw new Exception("El precio especial debe ser mayor a 0");
        }

        // Verificar que el producto exista
        $pdo       = (new DBManager())->getPDO();
        $stmtCheck = $pdo->prepare("SELECT ID, PrecioBase FROM Productos WHERE ID = ?");
        $stmtCheck->execute([intval($data["idProducto"])]);
        $producto = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (! $producto) {
            throw new Exception("El producto con ID " . $data["idProducto"] . " no existe");
        }

        // Verificar si ya existe un producto especial en el mismo rango de fechas
        $stmtCheckFecha = $pdo->prepare("
            SELECT ID FROM ProductosEspeciales
            WHERE IDProducto = ?
            AND (
                (FechaInicio <= ? AND FechaFin >= ?) OR
                (FechaInicio <= ? AND FechaFin >= ?) OR
                (? BETWEEN FechaInicio AND FechaFin) OR
                (? BETWEEN FechaInicio AND FechaFin)
            )
            AND Activo = 1
        ");

        $fechaInicioStr = $fechaInicio->format('Y-m-d H:i:s');
        $fechaFinStr    = $fechaFin->format('Y-m-d H:i:s');

        $stmtCheckFecha->execute([
            intval($data["idProducto"]),
            $fechaInicioStr,
            $fechaInicioStr,
            $fechaFinStr,
            $fechaFinStr,
            $fechaInicioStr,
            $fechaFinStr,
        ]);

        $existeSolapamiento = $stmtCheckFecha->fetch(PDO::FETCH_ASSOC);
        if ($existeSolapamiento) {
            throw new Exception("Ya existe un producto especial para este producto en el rango de fechas especificado");
        }

        $pdo->beginTransaction();

        // Insertar producto especial - AHORA CON DATETIME
        $stmt = $pdo->prepare("
            INSERT INTO ProductosEspeciales
            (IDProducto, FechaInicio, FechaFin, Descripcion, PrecioEspecial, Activo)
            VALUES (:idp, :fi, :ff, :desc, :precio, :act)
        ");

        $result = $stmt->execute([
            ":idp"    => intval($data["idProducto"]),
            ":fi"     => $fechaInicioStr,
            ":ff"     => $fechaFinStr,
            ":desc"   => isset($data["descripcion"]) ? trim($data["descripcion"]) : null,
            ":precio" => $precioEspecial,
            ":act"    => isset($data["activo"]) ? (filter_var($data["activo"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
        ]);

        if (! $result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Error al insertar producto especial: " . $errorInfo[2]);
        }

        $idProductoEspecial = $pdo->lastInsertId();

        $pdo->commit();
        ob_clean();

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "id"      => $idProductoEspecial,
            "message" => "Producto especial creado exitosamente",
        ]);

        error_log("=== CREATE PRODUCTO ESPECIAL FINALIZADO CON ÉXITO ===");

    } catch (Exception $e) {
        ob_clean();

        if (isset($pdo)) {
            try {
                $pdo->rollBack();
            } catch (Exception $rollbackEx) {
                error_log("Error en rollback: " . $rollbackEx->getMessage());
            }
        }

        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error"   => $e->getMessage(),
        ]);

        error_log("ERROR en createProductoEspecial: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
    }
}

// Función auxiliar para parsear fecha y hora
function parseDateTime($dateTimeStr)
{
    if (empty($dateTimeStr)) {
        throw new Exception("Fecha vacía");
    }

    // Intentar diferentes formatos
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s',
        'Y-m-d H:i',
        'Y-m-d',
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateTimeStr);
        if ($date !== false) {
            // Si es solo fecha (sin hora), agregar 00:00:00
            if ($format == 'Y-m-d') {
                $date->setTime(0, 0, 0);
            }
            return $date;
        }
    }

    throw new Exception("Formato de fecha no reconocido: " . $dateTimeStr);
}

function updateProductoEspecial($data)
{
    header("Content-Type: application/json; charset=UTF-8");
    ob_start();

    try {
        error_log("=== UPDATE PRODUCTO ESPECIAL INICIADO ===");

        if (!isset($data["id"])) {
            throw new Exception("ID del producto especial faltante");
        }

        $idProductoEspecial = intval($data["id"]);

        // Verificar si el producto especial existe
        $pdo = (new DBManager())->getPDO();
        $stmtCheck = $pdo->prepare("SELECT ID, IDProducto, FechaInicio, FechaFin FROM ProductosEspeciales WHERE ID = ?");
        $stmtCheck->execute([$idProductoEspecial]);
        $productoEspecialExiste = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$productoEspecialExiste) {
            throw new Exception("Producto especial no encontrado");
        }

        $idProductoOriginal = $productoEspecialExiste['IDProducto'];
        $idProducto = isset($data["idProducto"]) ? intval($data["idProducto"]) : $idProductoOriginal;

        // Validar campos de fecha si se envían
        $fechaInicioStr = null;
        $fechaFinStr = null;

        if (isset($data["fechaInicio"]) && isset($data["fechaFin"])) {
            // Usar la misma función parseDateTime que usa createProductoEspecial
            try {
                $fechaInicio = parseDateTime($data["fechaInicio"]);
                $fechaFin = parseDateTime($data["fechaFin"]);
                
                $fechaInicioStr = $fechaInicio->format('Y-m-d H:i:s');
                $fechaFinStr = $fechaFin->format('Y-m-d H:i:s');
                
                if ($fechaInicio > $fechaFin) {
                    throw new Exception("La fecha de inicio no puede ser posterior a la fecha de fin");
                }
                
                error_log("Fechas parseadas: Inicio=" . $fechaInicioStr . ", Fin=" . $fechaFinStr);
                
            } catch (Exception $e) {
                throw new Exception("Formato de fecha inválido. Use YYYY-MM-DD o YYYY-MM-DD HH:MM:SS. Error: " . $e->getMessage());
            }
        } else {
            // Usar fechas existentes si no se envían
            $fechaInicioStr = $productoEspecialExiste['FechaInicio'];
            $fechaFinStr = $productoEspecialExiste['FechaFin'];
        }

        // Validar precio si se envía
        if (isset($data["precioEspecial"])) {
            $precioEspecial = floatval($data["precioEspecial"]);
            if ($precioEspecial <= 0) {
                throw new Exception("El precio especial debe ser mayor a 0");
            }
        }

        // Verificar solapamiento (excluyendo el registro actual)
        if (isset($data["fechaInicio"]) && isset($data["fechaFin"])) {
            $stmtCheckFecha = $pdo->prepare("
                SELECT ID FROM ProductosEspeciales
                WHERE IDProducto = ?
                AND ID != ?
                AND (
                    (FechaInicio <= ? AND FechaFin >= ?) OR
                    (FechaInicio <= ? AND FechaFin >= ?) OR
                    (? BETWEEN FechaInicio AND FechaFin) OR
                    (? BETWEEN FechaInicio AND FechaFin)
                )
                AND Activo = 1
            ");
            $stmtCheckFecha->execute([
                $idProducto,
                $idProductoEspecial,
                $fechaInicioStr,
                $fechaInicioStr,
                $fechaFinStr,
                $fechaFinStr,
                $fechaInicioStr,
                $fechaFinStr,
            ]);

            $existeSolapamiento = $stmtCheckFecha->fetch(PDO::FETCH_ASSOC);
            if ($existeSolapamiento) {
                throw new Exception("Ya existe otro producto especial para este producto en el rango de fechas especificado");
            }
        }

        $pdo->beginTransaction();

        // Construir query dinámica
        $updates = [];
        $params = [":id" => $idProductoEspecial];

        if (isset($data["idProducto"])) {
            $updates[] = "IDProducto = :idp";
            $params[":idp"] = intval($data["idProducto"]);
        }
        if (isset($data["fechaInicio"])) {
            $updates[] = "FechaInicio = :fi";
            $params[":fi"] = $fechaInicioStr; // Usar el string ya formateado
        }
        if (isset($data["fechaFin"])) {
            $updates[] = "FechaFin = :ff";
            $params[":ff"] = $fechaFinStr; // Usar el string ya formateado
        }
        if (isset($data["descripcion"])) {
            $updates[] = "Descripcion = :desc";
            $params[":desc"] = trim($data["descripcion"]);
        }
        if (isset($data["precioEspecial"])) {
            $updates[] = "PrecioEspecial = :precio";
            $params[":precio"] = floatval($data["precioEspecial"]);
        }
        if (isset($data["activo"])) {
            $updates[] = "Activo = :act";
            $params[":act"] = filter_var($data["activo"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (empty($updates)) {
            throw new Exception("No se proporcionaron datos para actualizar");
        }

        $sql = "UPDATE ProductosEspeciales SET " . implode(", ", $updates) . " WHERE ID = :id";
        $stmt = $pdo->prepare($sql);

        error_log("SQL UPDATE: " . $sql);
        error_log("Params: " . print_r($params, true));

        $result = $stmt->execute($params);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Error al actualizar producto especial: " . $errorInfo[2]);
        }

        $pdo->commit();
        ob_clean();

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Producto especial actualizado exitosamente",
        ]);

        error_log("=== UPDATE PRODUCTO ESPECIAL FINALIZADO CON ÉXITO ===");

    } catch (Exception $e) {
        ob_clean();

        if (isset($pdo)) {
            try {
                $pdo->rollBack();
            } catch (Exception $rollbackEx) {
                error_log("Error en rollback: " . $rollbackEx->getMessage());
            }
        }

        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage(),
        ]);

        error_log("ERROR en updateProductoEspecial: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
    }
}

function deleteProductoEspecial($data = null)
{
    header("Content-Type: application/json");

    // Manejar parámetros GET
    if ($data === null || ! is_array($data)) {
        $data = [];

        if (isset($_GET['id']) && ! empty($_GET['id'])) {
            $data['id'] = intval($_GET['id']);
        }
    }

    if (! isset($data['id']) || empty($data['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "ID del producto especial faltante"]);
        return;
    }

    $id = intval($data['id']);

    try {
        $pdo = (new DBManager())->getPDO();

        // Verificar si existe
        $stmtCheck = $pdo->prepare("SELECT ID FROM ProductosEspeciales WHERE ID = ?");
        $stmtCheck->execute([$id]);
        $productoEspecialExiste = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (! $productoEspecialExiste) {
            http_response_code(404);
            echo json_encode(["error" => "Producto especial no encontrado"]);
            return;
        }

        // Eliminar el producto especial
        $stmtDelete = $pdo->prepare("DELETE FROM ProductosEspeciales WHERE ID = ?");
        $stmtDelete->execute([$id]);

        if ($stmtDelete->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Producto especial no encontrado o ya eliminado"]);
            return;
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Producto especial eliminado exitosamente",
            "id"      => $id,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al eliminar producto especial: " . $e->getMessage()]);
    }
}

function listProductosEspecialesVigentes()
{
    header("Content-Type: application/json");

    try {
        $pdo         = (new DBManager())->getPDO();
        $fechaActual = date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT
                pe.ID,
                pe.IDProducto,
                pe.FechaInicio,
                pe.FechaFin,
                pe.Descripcion,
                pe.PrecioEspecial,
                p.Nombre AS NombreProducto,
                p.PrecioBase AS PrecioNormal,
                p.URLFoto,
                c.Nombre AS Categoria,
                TIMESTAMPDIFF(DAY, CURDATE(), pe.FechaFin) AS DiasRestantes
            FROM ProductosEspeciales pe
            JOIN Productos p ON pe.IDProducto = p.ID
            JOIN CategoriasProductos c ON p.IDCategoria = c.ID
            WHERE pe.Activo = 1
            AND pe.FechaInicio <= :fecha
            AND pe.FechaFin >= :fecha
            ORDER BY pe.FechaFin ASC
        ");
        $stmt->execute([":fecha" => $fechaActual]);
        $productosEspeciales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "productos_especiales" => $productosEspeciales,
            "fecha_actual"         => $fechaActual,
            "total"                => count($productosEspeciales),
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function handleProductosEspecialesRequest()
{
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Verificar si es una solicitud específica
        if (isset($_GET['id']) && ! empty($_GET['id'])) {
            getProductoEspecial(intval($_GET['id']));
        } elseif (isset($_GET['vigentes']) && $_GET['vigentes'] === 'true') {
            listProductosEspecialesVigentes();
        } else {
            listProductosEspeciales();
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "JSON inválido en POST"]);
            return;
        }

        createProductoEspecial($data);
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "JSON inválido en PUT"]);
            return;
        }

        updateProductoEspecial($data);
    } elseif ($method === 'DELETE') {
        // Usar parámetros GET para DELETE
        deleteProductoEspecial();
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido"]);
    }
}
