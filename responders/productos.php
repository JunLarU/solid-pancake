<?php
require_once dirname(__DIR__) . '/core/DBManager.php';

function listProductos()
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();

        // Obtener productos base
        $stmt = $pdo->prepare("
            SELECT
                p.ID,
                p.Nombre,
                p.Descripcion,
                p.PrecioBase,
                p.Disponible,
                p.IDCategoria,
                c.Nombre AS Categoria,
                p.Gramaje,
                p.Calorias,
                p.URLFoto
            FROM Productos p
            JOIN CategoriasProductos c ON p.IDCategoria = c.ID
            ORDER BY p.Nombre
        ");
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar ingredientes y tamaños
        foreach ($productos as &$p) {
            $p["Ingredientes"] = getIngredientesProducto($pdo, $p["ID"]);
            $p["Tamanos"]      = getTamanosProducto($pdo, $p["ID"]);
        }

        echo json_encode(["productos" => $productos]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function getIngredientesProducto($pdo, $idProducto)
{
    $stmt = $pdo->prepare("
        SELECT
            pi.ID,
            pi.IDIngrediente,
            i.Nombre,
            pi.Cantidad,
            pi.Eliminable,
            pi.Sustituible,
            pi.Orden
        FROM ProductosIngredientes pi
        JOIN Ingredientes i ON pi.IDIngrediente = i.ID
        WHERE pi.IDProducto = :id
        ORDER BY pi.Orden
    ");
    $stmt->execute([":id" => $idProducto]);
    $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada ingrediente, obtener sus sustitutos si es sustituible
    foreach ($ingredientes as &$ing) {
        if ($ing['Sustituible']) {
            $ing['Sustitutos'] = getSustitutosIngrediente($pdo, $ing['ID']);
        } else {
            $ing['Sustitutos'] = [];
        }
    }

    return $ingredientes;
}

function getSustitutosIngrediente($pdo, $idProductoIngrediente)
{
    // USAR EL NOMBRE CORRECTO DE LA TABLA - verificando cuál existe realmente
    $stmt = $pdo->prepare("
        SELECT
            s.IDIngredienteSustituto,
            i.Nombre,
            s.CostoExtra,
            s.Disponible
        FROM SustitucionesIngredientes s
        JOIN Ingredientes i ON s.IDIngredienteSustituto = i.ID
        WHERE s.IDProductoIngrediente = :id
        ORDER BY i.Nombre
    ");
    $stmt->execute([":id" => $idProductoIngrediente]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTamanosProducto($pdo, $idProducto)
{
    $stmt = $pdo->prepare("
        SELECT
            ID,
            Nombre,
            Descripcion,
            Capacidad,
            Gramaje,
            Piezas,
            Precio,
            Orden,
            Disponible
        FROM TamanosProductos
        WHERE IDProducto = :id
        ORDER BY Orden
    ");
    $stmt->execute([":id" => $idProducto]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createProducto($data)
{
    // Configurar headers ANTES de cualquier output
    header("Content-Type: application/json; charset=UTF-8");

    // Iniciar buffer de salida
    ob_start();

    try {
        // Debug: Log datos recibidos
        error_log("=== CREATE PRODUCTO INICIADO ===");

        // Validar que el JSON sea válido
        if (empty($data)) {
            $rawInput = file_get_contents('php://input');
            error_log("Raw input: " . $rawInput);
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON inválido: " . json_last_error_msg());
            }
        }

        // Validar que los datos sean un array
        if (! is_array($data)) {
            throw new Exception("Los datos no son un array válido");
        }

        // Validar campos obligatorios
        if (! isset($data["nombre"]) || empty(trim($data["nombre"]))) {
            throw new Exception("El campo 'nombre' es obligatorio");
        }

        if (! isset($data["precioBase"]) || ! is_numeric($data["precioBase"])) {
            throw new Exception("El campo 'precioBase' es obligatorio y debe ser numérico");
        }

        if (! isset($data["idCategoria"]) || ! is_numeric($data["idCategoria"])) {
            throw new Exception("El campo 'idCategoria' es obligatorio y debe ser numérico");
        }

        $idCategoriaValue = intval($data["idCategoria"]);

        // Verificar que la categoría existe
        $pdo       = (new DBManager())->getPDO();
        $stmtCheck = $pdo->prepare("SELECT ID FROM CategoriasProductos WHERE ID = ?");
        $stmtCheck->execute([$idCategoriaValue]);
        $categoriaExiste = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (! $categoriaExiste) {
            throw new Exception("La categoría con ID " . $idCategoriaValue . " no existe");
        }

        $pdo->beginTransaction();

        // Insertar producto
        $stmt = $pdo->prepare("
            INSERT INTO Productos
            (Nombre, Descripcion, PrecioBase, IDCategoria, Gramaje, Calorias, URLFoto, Disponible)
            VALUES (:n, :d, :p, :c, :g, :cal, :foto, :disp)
        ");

        $result = $stmt->execute([
            ":n"    => trim($data["nombre"]),
            ":d"    => isset($data["descripcion"]) ? trim($data["descripcion"]) : "",
            ":p"    => floatval($data["precioBase"]),
            ":c"    => $idCategoriaValue,
            ":g"    => isset($data["gramaje"]) && $data["gramaje"] > 0 ? floatval($data["gramaje"]) : null,
            ":cal"  => isset($data["calorias"]) && $data["calorias"] > 0 ? floatval($data["calorias"]) : null,
            ":foto" => isset($data["urlFoto"]) ? trim($data["urlFoto"]) : null,
            ":disp" => isset($data["disponible"]) ? (filter_var($data["disponible"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
        ]);

        if (! $result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Error al insertar producto: " . $errorInfo[2]);
        }

        $idProducto = $pdo->lastInsertId();
        error_log("Producto insertado con ID: " . $idProducto);

        // Insertar ingredientes
        if (! empty($data["ingredientes"])) {
            foreach ($data["ingredientes"] as $i) {
                if (! isset($i["idIngrediente"])) {
                    continue; // Saltar ingredientes sin ID
                }

                $stmtIng = $pdo->prepare("
                    INSERT INTO ProductosIngredientes
                    (IDProducto, IDIngrediente, Cantidad, Eliminable, Sustituible, Orden)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $idIngrediente = intval($i["idIngrediente"]);
                $cantidad      = isset($i["cantidad"]) ? floatval($i["cantidad"]) : 1;
                $eliminable    = isset($i["eliminable"]) ? (filter_var($i["eliminable"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0;
                $sustituible   = isset($i["sustituible"]) ? (filter_var($i["sustituible"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0;
                $orden         = isset($i["orden"]) ? intval($i["orden"]) : 1;

                $resultIng = $stmtIng->execute([
                    $idProducto,
                    $idIngrediente,
                    $cantidad,
                    $eliminable,
                    $sustituible,
                    $orden,
                ]);

                if (! $resultIng) {
                    $errorInfo = $stmtIng->errorInfo();
                    throw new Exception("Error al insertar ingrediente: " . $errorInfo[2]);
                }

                $idProductoIngrediente = $pdo->lastInsertId();

                // Insertar sustitutos si es sustituible
                if ($sustituible && ! empty($i["sustitutos"])) {
                    foreach ($i["sustitutos"] as $sustituto) {
                        if (! isset($sustituto["idIngrediente"])) {
                            continue;
                        }

                        $stmtSust = $pdo->prepare("
                            INSERT INTO SustitucionesIngredientes
                            (IDProductoIngrediente, IDIngredienteSustituto, CostoExtra, Disponible)
                            VALUES (?, ?, ?, ?)
                        ");

                        $idSustituto = intval($sustituto["idIngrediente"]);
                        $costoExtra  = isset($sustituto["costoExtra"]) ? floatval($sustituto["costoExtra"]) : 0;

                        $stmtSust->execute([
                            $idProductoIngrediente,
                            $idSustituto,
                            $costoExtra,
                            1,
                        ]);
                    }
                }
            }
        }

        // Insertar tamaños
        if (! empty($data["tamanos"])) {
            foreach ($data["tamanos"] as $t) {
                // Normalizar nombres de campos (aceptar tanto mayúsculas como minúsculas)
                $nombre = $t['Nombre'] ?? $t['nombre'] ?? '';
                if (empty($nombre)) {
                    continue;
                }

                $descripcion = $t['Descripcion'] ?? $t['descripcion'] ?? null;
                $capacidad   = $t['Capacidad'] ?? $t['capacidad'] ?? null;
                $gramaje     = $t['Gramaje'] ?? $t['gramaje'] ?? null;
                $piezas      = $t['Piezas'] ?? $t['piezas'] ?? null;
                $precio      = $t['Precio'] ?? $t['precio'] ?? 0;
                $orden       = $t['Orden'] ?? $t['orden'] ?? 1;
                $disponible  = $t['Disponible'] ?? $t['disponible'] ?? 1;

                $stmtTam = $pdo->prepare("
                    INSERT INTO TamanosProductos
                    (IDProducto, Nombre, Descripcion, Capacidad, Gramaje, Piezas, Precio, Orden, Disponible)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $capacidadVal = ! empty($capacidad) && $capacidad > 0 ? floatval($capacidad) : null;
                $gramajeVal   = ! empty($gramaje) && $gramaje > 0 ? floatval($gramaje) : null;
                $piezasVal    = ! empty($piezas) && $piezas > 0 ? intval($piezas) : null;

                $resultTam = $stmtTam->execute([
                    $idProducto,
                    trim($nombre),
                    $descripcion,
                    $capacidadVal,
                    $gramajeVal,
                    $piezasVal,
                    floatval($precio),
                    intval($orden),
                    filter_var($disponible, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                ]);

                if (! $resultTam) {
                    $errorInfo = $stmtTam->errorInfo();
                    throw new Exception("Error al insertar tamaño: " . $errorInfo[2]);
                }
            }
        }

        $pdo->commit();

        // Limpiar buffer y enviar respuesta JSON correcta
        ob_clean();

                                 // Respuesta consistente
        http_response_code(201); // 201 Created
        echo json_encode([
            "success" => true,
            "id"      => $idProducto,
            "message" => "Producto creado exitosamente",
        ]);

        error_log("=== CREATE PRODUCTO FINALIZADO CON ÉXITO ===");

    } catch (Exception $e) {
        // Limpiar buffer antes de enviar error
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

        error_log("ERROR en createProducto: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
    }

    // Asegurar que no haya salida extra
    if (ob_get_length() > 0) {
        ob_end_flush();
    }
}

function updateProducto($data)
{
    header("Content-Type: application/json");

    // Debug: Log datos recibidos
    error_log("Datos recibidos en updateProducto: " . json_encode($data));

    if (! isset($data["id"])) {
        http_response_code(400);
        echo json_encode(["error" => "ID faltante"]);
        return;
    }

    $idProducto = intval($data["id"]);

    // Verificar si el producto existe
    try {
        $pdo       = (new DBManager())->getPDO();
        $stmtCheck = $pdo->prepare("SELECT ID FROM Productos WHERE ID = ?");
        $stmtCheck->execute([$idProducto]);
        $productoExiste = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (! $productoExiste) {
            http_response_code(404);
            echo json_encode(["error" => "Producto no encontrado"]);
            return;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al verificar producto: " . $e->getMessage()]);
        return;
    }

    // Convertir categoría a IDCategoria si solo se envía el nombre
    if (isset($data["categoria"]) && ! isset($data["idCategoria"])) {
        $idCategoria = obtenerIdCategoriaPorNombre($data["categoria"]);
        if ($idCategoria === null) {
            http_response_code(400);
            echo json_encode(["error" => "Categoría no válida: " . $data["categoria"]]);
            return;
        }
        $data["idCategoria"] = $idCategoria;
    }

    if (! isset($data["idCategoria"])) {
        http_response_code(400);
        echo json_encode(["error" => "ID de categoría faltante"]);
        return;
    }

    try {
        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        // 1. Actualizar producto base
        $stmt = $pdo->prepare("
            UPDATE Productos SET
                Nombre = :n,
                Descripcion = :d,
                PrecioBase = :p,
                IDCategoria = :c,
                Disponible = :disp,
                Gramaje = :g,
                Calorias = :cal,
                URLFoto = :foto
            WHERE ID = :id
        ");

        $stmt->execute([
            ":id"   => $idProducto,
            ":n"    => $data["nombre"] ?? "",
            ":d"    => $data["descripcion"] ?? "",
            ":p"    => floatval($data["precioBase"] ?? 0),
            ":c"    => intval($data["idCategoria"]),
            ":disp" => isset($data["disponible"]) ? (filter_var($data["disponible"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
            ":g"    => isset($data["gramaje"]) && $data["gramaje"] > 0 ? floatval($data["gramaje"]) : null,
            ":cal"  => isset($data["calorias"]) && $data["calorias"] > 0 ? floatval($data["calorias"]) : null,
            ":foto" => $data["urlFoto"] ?? null,
        ]);

        // 2. Manejar ingredientes y sus sustitutos
        if (isset($data["ingredientes"])) {
            // Obtener ingredientes actuales para comparar
            $stmtCurrent = $pdo->prepare("
                SELECT ID, IDIngrediente FROM ProductosIngredientes
                WHERE IDProducto = ?
            ");
            $stmtCurrent->execute([$idProducto]);
            $currentIngredients = $stmtCurrent->fetchAll(PDO::FETCH_ASSOC);

            // Crear array de ingredientes actuales por ID de ingrediente
            $currentIngredientMap = [];
            foreach ($currentIngredients as $ing) {
                $currentIngredientMap[$ing['IDIngrediente']] = $ing['ID'];
            }

            // Procesar cada ingrediente enviado
            foreach ($data["ingredientes"] as $i) {
                if (! isset($i["idIngrediente"])) {
                    continue;
                }

                $idIngrediente = intval($i["idIngrediente"]);
                $cantidad      = isset($i["cantidad"]) ? floatval($i["cantidad"]) : 0;
                $eliminable    = isset($i["eliminable"]) ? (filter_var($i["eliminable"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0;
                $sustituible   = isset($i["sustituible"]) ? (filter_var($i["sustituible"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0;
                $orden         = isset($i["orden"]) ? intval($i["orden"]) : 1;

                // Determinar si el ingrediente ya existe
                $existingIngredientId = null;
                if (isset($currentIngredientMap[$idIngrediente])) {
                    $existingIngredientId = $currentIngredientMap[$idIngrediente];
                }

                if ($existingIngredientId) {
                    // Actualizar ingrediente existente
                    $stmtUpdateIng = $pdo->prepare("
                        UPDATE ProductosIngredientes SET
                            Cantidad = ?,
                            Eliminable = ?,
                            Sustituible = ?,
                            Orden = ?
                        WHERE ID = ?
                    ");

                    $stmtUpdateIng->execute([
                        $cantidad,
                        $eliminable,
                        $sustituible,
                        $orden,
                        $existingIngredientId,
                    ]);

                    $idProductoIngrediente = $existingIngredientId;
                } else {
                    // Insertar nuevo ingrediente
                    $stmtInsertIng = $pdo->prepare("
                        INSERT INTO ProductosIngredientes
                        (IDProducto, IDIngrediente, Cantidad, Eliminable, Sustituible, Orden)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    $stmtInsertIng->execute([
                        $idProducto,
                        $idIngrediente,
                        $cantidad,
                        $eliminable,
                        $sustituible,
                        $orden,
                    ]);

                    $idProductoIngrediente = $pdo->lastInsertId();
                }

                // 3. Manejar sustitutos del ingrediente
                if ($sustituible && isset($i["sustitutos"])) {
                    // Obtener sustitutos actuales para este ingrediente
                    $stmtCurrentSust = $pdo->prepare("
                        SELECT IDIngredienteSustituto FROM SustitucionesIngredientes
                        WHERE IDProductoIngrediente = ?
                    ");
                    $stmtCurrentSust->execute([$idProductoIngrediente]);
                    $currentSustitutos = $stmtCurrentSust->fetchAll(PDO::FETCH_COLUMN, 0);

                    $sustitutosEnviados = [];

                    // Procesar cada sustituto enviado
                    foreach ($i["sustitutos"] as $sustituto) {
                        if (! isset($sustituto["idIngrediente"])) {
                            continue;
                        }

                        $idSustituto          = intval($sustituto["idIngrediente"]);
                        $sustitutosEnviados[] = $idSustituto;
                        $costoExtra           = isset($sustituto["costoExtra"]) ? floatval($sustituto["costoExtra"]) : 0;
                        $disponible           = isset($sustituto["disponible"]) ? (filter_var($sustituto["disponible"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1;

                        // Verificar si el sustituto ya existe
                        if (in_array($idSustituto, $currentSustitutos)) {
                            // Actualizar sustituto existente
                            $stmtUpdateSust = $pdo->prepare("
                                UPDATE SustitucionesIngredientes SET
                                    CostoExtra = ?,
                                    Disponible = ?
                                WHERE IDProductoIngrediente = ? AND IDIngredienteSustituto = ?
                            ");

                            $stmtUpdateSust->execute([
                                $costoExtra,
                                $disponible,
                                $idProductoIngrediente,
                                $idSustituto,
                            ]);
                        } else {
                            // Insertar nuevo sustituto
                            $stmtInsertSust = $pdo->prepare("
                                INSERT INTO SustitucionesIngredientes
                                (IDProductoIngrediente, IDIngredienteSustituto, CostoExtra, Disponible)
                                VALUES (?, ?, ?, ?)
                            ");

                            $stmtInsertSust->execute([
                                $idProductoIngrediente,
                                $idSustituto,
                                $costoExtra,
                                $disponible,
                            ]);
                        }
                    }

                    // Eliminar sustitutos que no fueron enviados (si existen)
                    $sustitutosAEliminar = array_diff($currentSustitutos, $sustitutosEnviados);
                    if (! empty($sustitutosAEliminar)) {
                        $placeholders   = implode(',', array_fill(0, count($sustitutosAEliminar), '?'));
                        $stmtDeleteSust = $pdo->prepare("
                            DELETE FROM SustitucionesIngredientes
                            WHERE IDProductoIngrediente = ?
                            AND IDIngredienteSustituto IN ($placeholders)
                        ");

                        $params = array_merge([$idProductoIngrediente], array_values($sustitutosAEliminar));
                        $stmtDeleteSust->execute($params);
                    }
                } else if (isset($currentIngredientMap[$idIngrediente])) {
                    // Si el ingrediente ya existía pero ahora no es sustituible o no tiene sustitutos,
                    // eliminar sus sustitutos anteriores
                    $pdo->prepare("DELETE FROM SustitucionesIngredientes WHERE IDProductoIngrediente = ?")
                        ->execute([$existingIngredientId]);
                }
            }

            // 4. Eliminar ingredientes que no fueron enviados
            $ingredientesEnviados = array_map(function ($i) {
                return isset($i["idIngrediente"]) ? intval($i["idIngrediente"]) : null;
            }, $data["ingredientes"]);

            $ingredientesEnviados = array_filter($ingredientesEnviados); // Remover nulls

            $ingredientesAEliminar = array_diff(array_keys($currentIngredientMap), $ingredientesEnviados);
            if (! empty($ingredientesAEliminar)) {
                // Primero eliminar sustitutos de los ingredientes que se van a eliminar
                $ingredienteIdsAEliminar = array_values(array_intersect_key($currentIngredientMap, array_flip($ingredientesAEliminar)));

                if (! empty($ingredienteIdsAEliminar)) {
                    $placeholders = implode(',', array_fill(0, count($ingredienteIdsAEliminar), '?'));
                    $pdo->prepare("DELETE FROM SustitucionesIngredientes WHERE IDProductoIngrediente IN ($placeholders)")
                        ->execute($ingredienteIdsAEliminar);
                }

                // Luego eliminar los ingredientes
                $placeholders = implode(',', array_fill(0, count($ingredientesAEliminar), '?'));
                $pdo->prepare("DELETE FROM ProductosIngredientes WHERE IDProducto = ? AND IDIngrediente IN ($placeholders)")
                    ->execute(array_merge([$idProducto], array_values($ingredientesAEliminar)));
            }
        }

        // 5. Manejar tamaños del producto
        if (isset($data["tamanos"])) {
            // Obtener tamaños actuales
            $stmtCurrentTamanos = $pdo->prepare("
                SELECT ID, Nombre FROM TamanosProductos
                WHERE IDProducto = ?
            ");
            $stmtCurrentTamanos->execute([$idProducto]);
            $currentTamanos = $stmtCurrentTamanos->fetchAll(PDO::FETCH_ASSOC);

            // Crear array de tamaños actuales por nombre (o ID si hay duplicados)
            $currentTamanosMap = [];
            foreach ($currentTamanos as $tam) {
                $currentTamanosMap[$tam['Nombre']] = $tam['ID'];
            }

            $tamanosEnviados = [];

            foreach ($data["tamanos"] as $t) {
                if (! isset($t["nombre"]) || empty(trim($t["nombre"]))) {
                    continue;
                }

                $nombre            = trim($t["nombre"]);
                $tamanosEnviados[] = $nombre;

                $descripcion = $t["descripcion"] ?? null;
                $capacidad   = isset($t["capacidad"]) && $t["capacidad"] > 0 ? floatval($t["capacidad"]) : null;
                $gramaje     = isset($t["gramaje"]) && $t["gramaje"] > 0 ? floatval($t["gramaje"]) : null;
                $piezas      = isset($t["piezas"]) && $t["piezas"] > 0 ? intval($t["piezas"]) : null;
                $precio      = floatval($t["precio"] ?? 0);
                $orden       = isset($t["orden"]) ? intval($t["orden"]) : 1;
                $disponible  = isset($t["disponible"]) ? (filter_var($t["disponible"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1;

                if (isset($currentTamanosMap[$nombre])) {
                    // Actualizar tamaño existente
                    $stmtUpdateTam = $pdo->prepare("
                        UPDATE TamanosProductos SET
                            Descripcion = ?,
                            Capacidad = ?,
                            Gramaje = ?,
                            Piezas = ?,
                            Precio = ?,
                            Orden = ?,
                            Disponible = ?
                        WHERE ID = ?
                    ");

                    $stmtUpdateTam->execute([
                        $descripcion,
                        $capacidad,
                        $gramaje,
                        $piezas,
                        $precio,
                        $orden,
                        $disponible,
                        $currentTamanosMap[$nombre],
                    ]);
                } else {
                    // Insertar nuevo tamaño
                    $stmtInsertTam = $pdo->prepare("
                        INSERT INTO TamanosProductos
                        (IDProducto, Nombre, Descripcion, Capacidad, Gramaje, Piezas, Precio, Orden, Disponible)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmtInsertTam->execute([
                        $idProducto,
                        $nombre,
                        $descripcion,
                        $capacidad,
                        $gramaje,
                        $piezas,
                        $precio,
                        $orden,
                        $disponible,
                    ]);
                }
            }

            // Eliminar tamaños que no fueron enviados
            $tamanosAEliminar = array_diff(array_keys($currentTamanosMap), $tamanosEnviados);
            if (! empty($tamanosAEliminar)) {
                $placeholders = implode(',', array_fill(0, count($tamanosAEliminar), '?'));
                $pdo->prepare("DELETE FROM TamanosProductos WHERE IDProducto = ? AND Nombre IN ($placeholders)")
                    ->execute(array_merge([$idProducto], array_values($tamanosAEliminar)));
            }
        }

        $pdo->commit();
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Producto actualizado exitosamente",
        ]);

    } catch (Exception $e) {
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
            "error"   => "Error al actualizar producto: " . $e->getMessage(),
        ]);
        error_log("Error en updateProducto: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
    }
}

function deleteProducto($data = null)
{
    header("Content-Type: application/json");

    // Si no se pasan datos, intentar obtener de GET
    if ($data === null || ! is_array($data)) {
        $data = [];

        // Buscar ID en parámetros GET
        $idFields = ['id', 'ID', 'Id', 'producto_id', 'productId'];
        foreach ($idFields as $field) {
            if (isset($_GET[$field]) && ! empty($_GET[$field])) {
                $data = ['id' => intval($_GET[$field])];
                break;
            }
        }
    }

    // Verificar ID
    $id = null;
    if (isset($data['id']) && ! empty($data['id'])) {
        $id = intval($data['id']);
    }

    if ($id === null) {
        http_response_code(400);
        echo json_encode([
            "error" => "ID faltante",
            "hint"  => "Use DELETE /api/productos?id=5",
        ]);
        return;
    }

    try {
        $pdo = (new DBManager())->getPDO();

        // Verificar si el producto existe
        $stmtCheck = $pdo->prepare("SELECT ID FROM Productos WHERE ID = ?");
        $stmtCheck->execute([$id]);
        $productoExiste = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (! $productoExiste) {
            http_response_code(404);
            echo json_encode(["error" => "Producto no encontrado con ID: " . $id]);
            return;
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        // 1. Eliminar sustitutos primero
        try {
            $pdo->prepare("DELETE s FROM SustitucionesIngredientes s
                          INNER JOIN ProductosIngredientes pi ON s.IDProductoIngrediente = pi.ID
                          WHERE pi.IDProducto = ?")
                ->execute([$id]);
        } catch (Exception $e) {
            error_log("Note: Could not delete from SustitucionesIngredientes: " . $e->getMessage());
        }

        // 2. Eliminar ingredientes del producto
        $pdo->prepare("DELETE FROM ProductosIngredientes WHERE IDProducto = ?")
            ->execute([$id]);

        // 3. Eliminar tamaños
        $pdo->prepare("DELETE FROM TamanosProductos WHERE IDProducto = ?")
            ->execute([$id]);

        // 4. Finalmente eliminar el producto
        $stmtDelete = $pdo->prepare("DELETE FROM Productos WHERE ID = ?");
        $stmtDelete->execute([$id]);

        // Verificar si se eliminó algo
        if ($stmtDelete->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(["error" => "Producto no encontrado o ya eliminado"]);
            return;
        }

        $pdo->commit();

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Producto eliminado exitosamente",
            "id"      => $id,
        ]);

    } catch (PDOException $e) {
        if (isset($pdo)) {
            try {
                $pdo->rollBack();
            } catch (Exception $rollbackEx) {
                error_log("Error en rollback: " . $rollbackEx->getMessage());
            }
        }

        if ($e->getCode() === "23000") {
            http_response_code(409);
            echo json_encode(["error" => "Producto en uso, no se puede eliminar (restricción de clave foránea)"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Error de base de datos: " . $e->getMessage()]);
        }
        error_log("PDOException en deleteProducto: " . $e->getMessage());
    } catch (Exception $e) {
        if (isset($pdo)) {
            try {
                $pdo->rollBack();
            } catch (Exception $rollbackEx) {
                error_log("Error en rollback: " . $rollbackEx->getMessage());
            }
        }
        http_response_code(500);
        echo json_encode(["error" => "Error al eliminar producto: " . $e->getMessage()]);
        error_log("Exception en deleteProducto: " . $e->getMessage());
    }
}

// Función para obtener categorías de productos
function listCategoriasProductos()
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();

        $stmt = $pdo->prepare("
            SELECT ID, Nombre, Descripcion
            FROM CategoriasProductos
            ORDER BY Nombre
        ");
        $stmt->execute();
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["categorias" => $categorias]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// Función auxiliar para obtener ID de categoría por nombre
function obtenerIdCategoriaPorNombre($nombreCategoria)
{
    try {
        $pdo = (new DBManager())->getPDO();

        // Primero intentar buscar exactamente
        $stmt = $pdo->prepare("SELECT ID FROM CategoriasProductos WHERE Nombre = ?");
        $stmt->execute([$nombreCategoria]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['ID'];
        }

        // Si no existe, crear nueva categoría
        $stmt = $pdo->prepare("INSERT INTO CategoriasProductos (Nombre) VALUES (?)");
        $stmt->execute([$nombreCategoria]);

        return $pdo->lastInsertId();

    } catch (Exception $e) {
        error_log("Error en obtenerIdCategoriaPorNombre: " . $e->getMessage());
        return null;
    }
}

// Función para manejar diferentes métodos HTTP
// Función para manejar diferentes métodos HTTP
// Función para manejar diferentes métodos HTTP
function handleProductosRequest()
{
    $method = $_SERVER['REQUEST_METHOD'];

    // Obtener datos según el método
    if ($method === 'GET') {
        listProductos();
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "JSON inválido en POST"]);
            return;
        }

        // Determinar si es DELETE basado en los datos recibidos
        if (isset($data['_method']) && $data['_method'] === 'DELETE') {
            unset($data['_method']);
            deleteProducto($data);
        } elseif (isset($data['id']) && isset($data['_action']) && $data['_action'] === 'delete') {
            deleteProducto($data);
        } else {
            createProducto($data);
        }
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "JSON inválido en PUT"]);
            return;
        }

        updateProducto($data);
    } elseif ($method === 'DELETE') {
        // PARA DELETE: usar parámetros GET de la URL
        $id = null;

        // Buscar ID en diferentes lugares
        if (isset($_GET['id']) && ! empty($_GET['id'])) {
            $id = intval($_GET['id']);
        } elseif (isset($_GET['ID']) && ! empty($_GET['ID'])) {
            $id = intval($_GET['ID']);
        } elseif (isset($_GET['Id']) && ! empty($_GET['Id'])) {
            $id = intval($_GET['Id']);
        }

        if ($id === null) {
            http_response_code(400);
            echo json_encode(["error" => "ID faltante en parámetros de URL"]);
            return;
        }

        // Crear array con el ID y llamar a deleteProducto
        $data = ['id' => $id];
        deleteProducto($data);
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido"]);
    }
}
