<?php
// menus.php - Actualizado para nueva estructura de base de datos
require_once dirname(__DIR__) . '/core/DBManager.php';

// ============================================
// FUNCIONES PARA SECCIONES DEL MENÚ
// ============================================

function listSeccionesMenu()
{
    header("Content-Type: application/json; charset=utf-8");

    try {
        $pdo = (new DBManager())->getPDO();

        $stmt = $pdo->prepare("
            SELECT
                sm.ID,
                sm.Nombre,
                sm.Descripcion,
                sm.URLFoto,
                sm.Color,
                sm.Activo,
                DATE_FORMAT(sm.FechaCreacion, '%Y-%m-%d %H:%i:%s') as FechaCreacion,
                COUNT(smp.IDProducto) as CantidadProductos
            FROM SeccionesMenu sm
            LEFT JOIN SeccionesMenuProductos smp ON sm.ID = smp.IDSeccion
            WHERE sm.Activo = 1
            GROUP BY sm.ID
            ORDER BY sm.Nombre
        ");
        $stmt->execute();
        $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada sección, obtener sus productos
        foreach ($secciones as &$seccion) {
            $seccion['Productos'] = getProductosSeccion($pdo, $seccion['ID']);
        }

        $response = ["secciones" => $secciones];
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        http_response_code(500);
        $errorResponse = ["error" => "Error interno: " . $e->getMessage()];
        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    }
}

function getProductosSeccion($pdo, $idSeccion)
{
    $stmt = $pdo->prepare("
        SELECT
            smp.ID,
            smp.IDProducto,
            p.Nombre,
            p.Descripcion,
            p.PrecioBase,
            p.IDCategoria,
            cp.Nombre as Categoria,
            smp.Orden,
            smp.Destacado
        FROM SeccionesMenuProductos smp
        JOIN Productos p ON smp.IDProducto = p.ID
        JOIN CategoriasProductos cp ON p.IDCategoria = cp.ID
        WHERE smp.IDSeccion = :id
        ORDER BY smp.Orden
    ");
    $stmt->execute([":id" => $idSeccion]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createSeccionMenu($data)
{
    header("Content-Type: application/json");

    try {
        // Validar campos obligatorios
        if (! isset($data["nombre"]) || empty(trim($data["nombre"]))) {
            throw new Exception("El campo 'nombre' es obligatorio");
        }

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        // Insertar sección
        $stmt = $pdo->prepare("
            INSERT INTO SeccionesMenu
            (Nombre, Descripcion, URLFoto, Color, Activo)
            VALUES (:n, :d, :foto, :color, :activo)
        ");

        $result = $stmt->execute([
            ":n"      => trim($data["nombre"]),
            ":d"      => isset($data["descripcion"]) ? trim($data["descripcion"]) : "",
            ":foto"   => isset($data["urlFoto"]) ? trim($data["urlFoto"]) : null,
            ":color"  => isset($data["color"]) ? trim($data["color"]) : "#3498db",
            ":activo" => isset($data["activo"]) ? (filter_var($data["activo"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
        ]);

        if (! $result) {
            throw new Exception("Error al crear sección");
        }

        $idSeccion = $pdo->lastInsertId();

        // Insertar productos si se enviaron
        if (! empty($data["productos"])) {
            $orden = 1;
            foreach ($data["productos"] as $producto) {
                if (! isset($producto["idProducto"])) {
                    continue;
                }

                $stmtProducto = $pdo->prepare("
                    INSERT INTO SeccionesMenuProductos
                    (IDSeccion, IDProducto, Orden, Destacado)
                    VALUES (?, ?, ?, ?)
                ");

                $stmtProducto->execute([
                    $idSeccion,
                    intval($producto["idProducto"]),
                    $orden,
                    isset($producto["destacado"]) ? (filter_var($producto["destacado"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0,
                ]);

                $orden++;
            }
        }

        $pdo->commit();

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "id"      => $idSeccion,
            "message" => "Sección creada exitosamente",
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function updateSeccionMenu($data)
{
    header("Content-Type: application/json");

    if (! isset($data["id"])) {
        http_response_code(400);
        echo json_encode(["error" => "ID faltante"]);
        return;
    }

    $idSeccion = intval($data["id"]);

    try {
        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        // Actualizar sección
        $stmt = $pdo->prepare("
            UPDATE SeccionesMenu SET
                Nombre = :n,
                Descripcion = :d,
                URLFoto = :foto,
                Color = :color,
                Activo = :activo
            WHERE ID = :id
        ");

        $stmt->execute([
            ":id"     => $idSeccion,
            ":n"      => isset($data["nombre"]) ? trim($data["nombre"]) : "",
            ":d"      => isset($data["descripcion"]) ? trim($data["descripcion"]) : "",
            ":foto"   => isset($data["urlFoto"]) ? trim($data["urlFoto"]) : null,
            ":color"  => isset($data["color"]) ? trim($data["color"]) : "#3498db",
            ":activo" => isset($data["activo"]) ? (filter_var($data["activo"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
        ]);

        // Manejar productos de la sección
        if (isset($data["productos"])) {
            // Eliminar productos actuales
            $pdo->prepare("DELETE FROM SeccionesMenuProductos WHERE IDSeccion = ?")
                ->execute([$idSeccion]);

            // Insertar nuevos productos
            $orden = 1;
            foreach ($data["productos"] as $producto) {
                if (! isset($producto["idProducto"])) {
                    continue;
                }

                $stmtProducto = $pdo->prepare("
                    INSERT INTO SeccionesMenuProductos
                    (IDSeccion, IDProducto, Orden, Destacado)
                    VALUES (?, ?, ?, ?)
                ");

                $stmtProducto->execute([
                    $idSeccion,
                    intval($producto["idProducto"]),
                    $orden,
                    isset($producto["destacado"]) ? (filter_var($producto["destacado"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0,
                ]);

                $orden++;
            }
        }

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Sección actualizada exitosamente",
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function deleteSeccionMenu($id)
{
    header("Content-Type: application/json");

    if (! $id) {
        http_response_code(400);
        echo json_encode(["error" => "ID faltante"]);
        return;
    }

    try {
        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        // Eliminar productos de la sección (cascada)
        $pdo->prepare("DELETE FROM SeccionesMenuProductos WHERE IDSeccion = ?")
            ->execute([$id]);

        // Eliminar asignaciones del menú semanal
        $pdo->prepare("DELETE FROM MenuSemanalSecciones WHERE IDSeccion = ?")
            ->execute([$id]);

        // Eliminar la sección
        $stmt = $pdo->prepare("DELETE FROM SeccionesMenu WHERE ID = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Sección eliminada exitosamente",
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// ============================================
// FUNCIONES PARA MENÚ SEMANAL
// ============================================

function generarMenuSemana($fechaInicio, $idUsuario)
{
    header("Content-Type: application/json");

    try {
        // Validar fecha
        if (! $fechaInicio || ! strtotime($fechaInicio)) {
            throw new Exception("Fecha de inicio inválida");
        }

        $fecha      = new DateTime($fechaInicio);
        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        $menusCreados = [];

        // 🔴 **CORRECCIÓN CRÍTICA: Solo crear menús si no existen**
        for ($i = 0; $i < 5; $i++) {
            $fechaActual = clone $fecha;
            $fechaActual->modify("+{$i} days");
            $fechaStr  = $fechaActual->format('Y-m-d');
            $diaSemana = $diasSemana[$i];

            // ✅ **CORRECCIÓN: Verificar si ya existe menú para Desayuno**
            $menuDesayunoId = verificarOCrearMenu($pdo, $fechaStr, $diaSemana, 'Desayuno');
            if ($menuDesayunoId) {
                $menusCreados[] = $menuDesayunoId;
            }

            // ✅ **CORRECCIÓN: Verificar si ya existe menú para Comida**
            $menuComidaId = verificarOCrearMenu($pdo, $fechaStr, $diaSemana, 'Comida');
            if ($menuComidaId) {
                $menusCreados[] = $menuComidaId;
            }
        }

        $pdo->commit();

        echo json_encode([
            "success"      => true,
            "message"      => "Menú semanal generado exitosamente",
            "menusCreados" => count($menusCreados),
            "detalle"      => "Se crearon " . count($menusCreados) . " menús nuevos",
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function verificarOCrearMenu($pdo, $fecha, $diaSemana, $horario)
{
    // Verificar si ya existe
    $stmtCheck = $pdo->prepare("
        SELECT ID FROM MenuSemanal
        WHERE Fecha = :fecha AND Horario = :horario
    ");
    $stmtCheck->execute([
        ":fecha"   => $fecha,
        ":horario" => $horario,
    ]);

    $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        // Ya existe, retornar el ID existente
        return $existente['ID'];
    }

    // Crear nuevo menú
    $numeroSemana = date('W', strtotime($fecha));
    $anio         = date('Y', strtotime($fecha));

    $stmt = $pdo->prepare("
        INSERT INTO MenuSemanal
        (Fecha, DiaSemana, Horario, NumeroSemana, Anio, Activo)
        VALUES (:fecha, :dia, :horario, :semana, :anio, 1)
    ");

    $stmt->execute([
        ":fecha"   => $fecha,
        ":dia"     => $diaSemana,
        ":horario" => $horario,
        ":semana"  => $numeroSemana,
        ":anio"    => $anio,
    ]);

    return $pdo->lastInsertId();
}

function crearMenuDiario($pdo, $fecha, $diaSemana, $horario, $idUsuario)
{
    $numeroSemana = $fecha->format('W');
    $anio         = $fecha->format('Y');

    // Verificar si ya existe
    $stmtCheck = $pdo->prepare("
        SELECT ID FROM MenuSemanal
        WHERE Fecha = :fecha AND Horario = :horario
    ");
    $stmtCheck->execute([
        ":fecha"   => $fecha->format('Y-m-d'),
        ":horario" => $horario,
    ]);

    if ($stmtCheck->fetch()) {
        throw new Exception("Ya existe menú para {$diaSemana} {$horario} {$fecha->format('Y-m-d')}");
    }

    // Crear nuevo menú
    $stmt = $pdo->prepare("
        INSERT INTO MenuSemanal
        (Fecha, DiaSemana, Horario, NumeroSemana, Anio, Activo)
        VALUES (:fecha, :dia, :horario, :semana, :anio, 1)
    ");

    $stmt->execute([
        ":fecha"   => $fecha->format('Y-m-d'),
        ":dia"     => $diaSemana,
        ":horario" => $horario,
        ":semana"  => $numeroSemana,
        ":anio"    => $anio,
    ]);

    return $pdo->lastInsertId();
}

function getMenuSemana($numeroSemana, $anio)
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();

        // Calcular fecha de lunes de la semana ISO
        // Usar DateTime para cálculo ISO más preciso
        $fecha = new DateTime();
        $fecha->setISODate($anio, $numeroSemana);
        $lunesSemana = $fecha->format('Y-m-d');

        // Viernes de la semana (Lunes + 4 días)
        $fecha->modify('+4 days');
        $viernesSemana = $fecha->format('Y-m-d');

        error_log("[DEBUG] Rango de fechas ISO: $lunesSemana al $viernesSemana");

        $stmt = $pdo->prepare("
            SELECT
                ms.ID,
                ms.Fecha,
                ms.DiaSemana,
                ms.Horario,
                ms.NumeroSemana,
                ms.Anio,
                ms.Activo,
                DATE_FORMAT(ms.FechaCreacion, '%Y-%m-%d %H:%i:%s') as FechaCreacion
            FROM MenuSemanal ms
            WHERE ms.Fecha BETWEEN :fechaInicio AND :fechaFin
            AND ms.Activo = 1
            ORDER BY ms.Fecha, FIELD(ms.Horario, 'Desayuno', 'Comida')
        ");

        $stmt->execute([
            ":fechaInicio" => $lunesSemana,
            ":fechaFin"    => $viernesSemana,
        ]);

        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("[DEBUG] Encontrados: " . count($menus) . " menús activos");

        if (empty($menus)) {
            echo json_encode([
                "success" => true,
                "semana"  => $numeroSemana,
                "anio"    => $anio,
                "menus"   => [],
                "message" => "No hay menús para esta semana",
            ]);
            return;
        }

        // Para cada menú, obtener sus secciones
        foreach ($menus as &$menu) {
            $menu['Secciones'] = getSeccionesMenuDia($pdo, $menu['ID']);
        }

        echo json_encode([
            "success" => true,
            "semana"  => $numeroSemana,
            "anio"    => $anio,
            "menus"   => $menus,
        ]);

    } catch (Exception $e) {
        error_log("[ERROR] getMenuSemana: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "error"  => $e->getMessage(),
            "semana" => $numeroSemana,
            "anio"   => $anio,
        ]);
    }
}

function getSeccionesMenuDia($pdo, $idMenu)
{
    $stmt = $pdo->prepare("
        SELECT
            mss.ID,
            mss.IDSeccion,
            sm.Nombre,
            sm.Descripcion,
            sm.Color,
            mss.Orden,
            DATE_FORMAT(mss.FechaAsignacion, '%Y-%m-%d %H:%i:%s') as FechaAsignacion,
            u.Expediente as UsuarioAsigno,
            u.ID as IDUsuarioAsigno
        FROM MenuSemanalSecciones mss
        JOIN SeccionesMenu sm ON mss.IDSeccion = sm.ID
        LEFT JOIN Usuarios u ON mss.IDUsuarioAsigno = u.ID
        WHERE mss.IDMenuSemanal = :id
        ORDER BY mss.Orden
    ");

    $stmt->execute([":id" => $idMenu]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function asignarSeccionMenu($data)
{
    header("Content-Type: application/json");

    try {
        if (! isset($data["idMenu"]) || ! isset($data["idSeccion"])) {
            throw new Exception("ID de menú y sección son obligatorios");
        }

        $idUsuario = isset($data["idUsuario"]) ? intval($data["idUsuario"]) : null;

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        // Verificar si ya está asignada
        $stmtCheck = $pdo->prepare("
            SELECT ID FROM MenuSemanalSecciones
            WHERE IDMenuSemanal = :menu AND IDSeccion = :seccion
        ");

        $stmtCheck->execute([
            ":menu"    => intval($data["idMenu"]),
            ":seccion" => intval($data["idSeccion"]),
        ]);

        if ($stmtCheck->fetch()) {
            // 🔴 **CORRECCIÓN: No lanzar error, solo retornar éxito**
            $pdo->commit();
            echo json_encode([
                "success"       => true,
                "message"       => "La sección ya estaba asignada a este menú",
                "alreadyExists" => true,
            ]);
            return;
        }

        // Obtener orden máximo actual
        $stmtOrden = $pdo->prepare("
            SELECT MAX(Orden) as maxOrden
            FROM MenuSemanalSecciones
            WHERE IDMenuSemanal = ?
        ");

        $stmtOrden->execute([intval($data["idMenu"])]);
        $result = $stmtOrden->fetch(PDO::FETCH_ASSOC);
        $orden  = ($result && $result['maxOrden']) ? $result['maxOrden'] + 1 : 1;

        // Insertar asignación
        $stmt = $pdo->prepare("
            INSERT INTO MenuSemanalSecciones
            (IDMenuSemanal, IDSeccion, Orden, IDUsuarioAsigno)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            intval($data["idMenu"]),
            intval($data["idSeccion"]),
            $orden,
            $idUsuario,
        ]);

        $pdo->commit();

        echo json_encode([
            "success"       => true,
            "message"       => "Sección asignada exitosamente",
            "id"            => $pdo->lastInsertId(),
            "alreadyExists" => false,
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function removerSeccionMenu($id)
{
    header("Content-Type: application/json");

    if (! $id) {
        http_response_code(400);
        echo json_encode(["error" => "ID faltante"]);
        return;
    }

    try {
        $pdo = (new DBManager())->getPDO();

        $stmt = $pdo->prepare("DELETE FROM MenuSemanalSecciones WHERE ID = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Sección removida exitosamente",
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Registro no encontrado"]);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function updateOrdenSecciones($data)
{
    header("Content-Type: application/json");

    try {
        if (! isset($data["idMenu"]) || ! is_array($data["secciones"])) {
            throw new Exception("Datos inválidos");
        }

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        foreach ($data["secciones"] as $index => $idSeccionMenu) {
            $stmt = $pdo->prepare("
                UPDATE MenuSemanalSecciones
                SET Orden = ?
                WHERE ID = ? AND IDMenuSemanal = ?
            ");

            $stmt->execute([
                $index + 1,
                intval($idSeccionMenu),
                intval($data["idMenu"]),
            ]);
        }

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Orden actualizado exitosamente",
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function eliminarMenuCompleto($data)
{
    header("Content-Type: application/json");

    try {
        // DEBUG: Registrar qué datos recibimos
        error_log("[DEBUG] eliminarMenuCompleto - Datos recibidos: " . json_encode($data));
        error_log("[DEBUG] eliminarMenuCompleto - GET params: " . json_encode($_GET));

        // Obtener semana y año de múltiples fuentes
        $semana = 0;
        $anio   = 0;

        // 1. Intentar del array $data
        if (is_array($data)) {
            $semana = isset($data['semana']) ? intval($data['semana']) : 0;
            $anio   = isset($data['anio']) ? intval($data['anio']) : 0;
        }

        // 2. Si no, intentar de parámetros GET
        if ($semana == 0 && isset($_GET['semana'])) {
            $semana = intval($_GET['semana']);
        }
        if ($anio == 0 && isset($_GET['anio'])) {
            $anio = intval($_GET['anio']);
        }

        // 3. Si aún no tenemos datos, verificar si $data es un número (ID único)
        if ($semana == 0 && is_numeric($data)) {
            // En este caso, asumir que $data es la semana y buscar el año
            $semana = intval($data);
            $anio   = date('Y'); // Año actual por defecto
        }

        // Validar que tenemos datos válidos
        if ($semana <= 0 || $anio < 2000) {
            error_log("[ERROR] eliminarMenuCompleto - Datos inválidos: semana=$semana, año=$anio");
            throw new Exception("Semana y año son requeridos (semana: $semana, año: $anio). Datos recibidos: " . json_encode($data));
        }

        error_log("[DEBUG] Eliminar menú - semana: $semana, año: $anio");

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        // Obtener IDs de menús a eliminar usando cálculo de fechas ISO
        $fecha = new DateTime();
        $fecha->setISODate($anio, $semana);
        $lunesSemana = $fecha->format('Y-m-d');
        $fecha->modify('+4 days');
        $viernesSemana = $fecha->format('Y-m-d');

        error_log("[DEBUG] Rango a eliminar: $lunesSemana al $viernesSemana");

        $stmtGetMenus = $pdo->prepare("
            SELECT ID FROM MenuSemanal
            WHERE Fecha BETWEEN ? AND ?
        ");

        $stmtGetMenus->execute([$lunesSemana, $viernesSemana]);
        $menus = $stmtGetMenus->fetchAll(PDO::FETCH_COLUMN);

        error_log("[DEBUG] Encontrados para eliminar: " . count($menus) . " menús");

        if (empty($menus)) {
            $pdo->rollBack();
            echo json_encode([
                "success" => true, // Cambiado a true porque no hay nada que eliminar
                "message" => "No se encontraron menús para la semana especificada",
                "debug"   => [
                    "semana" => $semana,
                    "anio"   => $anio,
                    "rango"  => "$lunesSemana al $viernesSemana",
                ],
            ]);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($menus), '?'));

        // Eliminar secciones asignadas
        $deleteSecciones     = $pdo->prepare("DELETE FROM MenuSemanalSecciones WHERE IDMenuSemanal IN ($placeholders)");
        $seccionesEliminadas = $deleteSecciones->execute($menus);

        // Eliminar menús
        $deleteMenus     = $pdo->prepare("DELETE FROM MenuSemanal WHERE ID IN ($placeholders)");
        $menusEliminados = $deleteMenus->execute($menus);

        $pdo->commit();

        echo json_encode([
            "success"         => true,
            "message"         => "Menú semanal eliminado exitosamente",
            "menusEliminados" => $menusEliminados,
            "semana"          => $semana,
            "anio"            => $anio,
            "debug"           => [
                "rango"                => "$lunesSemana al $viernesSemana",
                "ids_eliminados"       => $menus,
                "secciones_eliminadas" => $seccionesEliminadas,
            ],
        ]);

    } catch (Exception $e) {
        error_log("[ERROR] eliminarMenuCompleto: " . $e->getMessage());
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode([
            "error"          => $e->getMessage(),
            "data_recibida"  => $data,
            "get_params"     => $_GET,
            "request_method" => $_SERVER['REQUEST_METHOD'],
        ]);
    }
}

// ============================================
// MENÚ DEL DÍA
// ============================================

function getMenuHoy()
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();
        $hoy = date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT
                ms.ID,
                ms.Fecha,
                ms.DiaSemana,
                ms.Horario,
                ms.NumeroSemana,
                ms.Anio
            FROM MenuSemanal ms
            WHERE ms.Fecha = :hoy
            AND ms.Activo = 1
            ORDER BY FIELD(ms.Horario, 'Desayuno', 'Comida')
        ");

        $stmt->execute([":hoy" => $hoy]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada menú, obtener secciones y productos
        foreach ($menus as &$menu) {
            $secciones = getSeccionesMenuDia($pdo, $menu['ID']);

            // Para cada sección, obtener productos
            foreach ($secciones as &$seccion) {
                $seccion['Productos'] = getProductosSeccion($pdo, $seccion['IDSeccion']);
            }

            $menu['Secciones'] = $secciones;
        }

        echo json_encode([
            "success" => true,
            "fecha"   => $hoy,
            "menus"   => $menus,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function getMenuPorFecha($fecha)
{
    header("Content-Type: application/json");

    try {
        if (! strtotime($fecha)) {
            throw new Exception("Fecha inválida");
        }

        $pdo = (new DBManager())->getPDO();

        $stmt = $pdo->prepare("
            SELECT
                ms.ID,
                ms.Fecha,
                ms.DiaSemana,
                ms.Horario,
                ms.NumeroSemana,
                ms.Anio
            FROM MenuSemanal ms
            WHERE ms.Fecha = :fecha
            AND ms.Activo = 1
            ORDER BY FIELD(ms.Horario, 'Desayuno', 'Comida')
        ");

        $stmt->execute([":fecha" => $fecha]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada menú, obtener secciones y productos
        foreach ($menus as &$menu) {
            $secciones = getSeccionesMenuDia($pdo, $menu['ID']);

            // Para cada sección, obtener productos
            foreach ($secciones as &$seccion) {
                $seccion['Productos'] = getProductosSeccion($pdo, $seccion['IDSeccion']);
            }

            $menu['Secciones'] = $secciones;
        }

        echo json_encode([
            "success" => true,
            "fecha"   => $fecha,
            "menus"   => $menus,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// ============================================
// PRODUCTOS DE SECCIONES
// ============================================

function agregarProductoSeccion($data)
{
    header("Content-Type: application/json");

    try {
        if (! isset($data["idSeccion"]) || ! isset($data["idProducto"])) {
            throw new Exception("ID de sección y producto son obligatorios");
        }

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        // Verificar si ya existe
        $stmtCheck = $pdo->prepare("
            SELECT ID FROM SeccionesMenuProductos
            WHERE IDSeccion = :seccion AND IDProducto = :producto
        ");

        $stmtCheck->execute([
            ":seccion"  => intval($data["idSeccion"]),
            ":producto" => intval($data["idProducto"]),
        ]);

        if ($stmtCheck->fetch()) {
            throw new Exception("El producto ya está en esta sección");
        }

        // Obtener orden máximo actual
        $stmtOrden = $pdo->prepare("
            SELECT MAX(Orden) as maxOrden
            FROM SeccionesMenuProductos
            WHERE IDSeccion = ?
        ");

        $stmtOrden->execute([intval($data["idSeccion"])]);
        $result = $stmtOrden->fetch(PDO::FETCH_ASSOC);
        $orden  = ($result && $result['maxOrden']) ? $result['maxOrden'] + 1 : 1;

        // Insertar producto
        $stmt = $pdo->prepare("
            INSERT INTO SeccionesMenuProductos
            (IDSeccion, IDProducto, Orden, Destacado)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            intval($data["idSeccion"]),
            intval($data["idProducto"]),
            $orden,
            isset($data["destacado"]) ? (filter_var($data["destacado"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0,
        ]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Producto agregado exitosamente",
            "id"      => $pdo->lastInsertId(),
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function removerProductoSeccion($idSeccion, $idProducto)
{
    header("Content-Type: application/json");

    if (! $idSeccion || ! $idProducto) {
        http_response_code(400);
        echo json_encode(["error" => "ID de sección y producto son requeridos"]);
        return;
    }

    try {
        $pdo = (new DBManager())->getPDO();

        $stmt = $pdo->prepare("
            DELETE FROM SeccionesMenuProductos
            WHERE IDSeccion = ? AND IDProducto = ?
        ");
        $stmt->execute([$idSeccion, $idProducto]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Producto removido exitosamente",
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Producto no encontrado en la sección"]);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function updateOrdenProductos($data)
{
    header("Content-Type: application/json");

    try {
        if (! isset($data["idSeccion"]) || ! is_array($data["productos"])) {
            throw new Exception("Datos inválidos");
        }

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        foreach ($data["productos"] as $index => $idProducto) {
            $stmt = $pdo->prepare("
                UPDATE SeccionesMenuProductos
                SET Orden = ?
                WHERE IDSeccion = ? AND IDProducto = ?
            ");

            $stmt->execute([
                $index + 1,
                intval($data["idSeccion"]),
                intval($idProducto),
            ]);
        }

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Orden de productos actualizado exitosamente",
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// ============================================
// MANEJADOR PRINCIPAL DE MENÚS
// ============================================

function handleMenusRequest()
{
    try {
        $method   = $_SERVER['REQUEST_METHOD'];
        $path     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));

        // DEBUG: Verificar qué endpoint se está llamando
        error_log("Menus Request: " . $path . " - Method: " . $method);

        // Determinar el endpoint
        if (in_array('secciones', $segments)) {
            handleSeccionesRequest();
        } elseif (in_array('semanal', $segments)) {
            handleMenuSemanalRequest();
        } elseif (in_array('hoy', $segments)) {
            getMenuHoy();
        } elseif (in_array('fecha', $segments)) {
            $fecha = $_GET['fecha'] ?? date('Y-m-d');
            getMenuPorFecha($fecha);
        } elseif (in_array('verificar', $segments)) {
            // Nuevo endpoint para verificar menú existente
            $fecha   = $_GET['fecha'] ?? '';
            $horario = $_GET['horario'] ?? '';
            verificarMenuExistente($fecha, $horario);
        } else {
            // Endpoint por defecto: /api/menus
            listSeccionesMenu();
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error en handleMenusRequest: " . $e->getMessage()]);
    }
}

function handleSeccionesRequest()
{
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getSeccionById($_GET['id']);
            } else {
                listSeccionesMenu();
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (! $data) {
                $data = $_POST;
            }

            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'addProducto':
                        agregarProductoSeccion($data);
                        break;
                    case 'ordenProductos':
                        updateOrdenProductos($data);
                        break;
                    default:
                        createSeccionMenu($data);
                }
            } else {
                createSeccionMenu($data);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            updateSeccionMenu($data);
            break;

        case 'DELETE':
            if (isset($_GET['idSeccion']) && isset($_GET['idProducto'])) {
                removerProductoSeccion($_GET['idSeccion'], $_GET['idProducto']);
            } else {
                $id = $_GET['id'] ?? 0;
                deleteSeccionMenu($id);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["error" => "Método no permitido"]);
    }
}

function crearMenuIndividual($data)
{
    header("Content-Type: application/json");

    try {
        $fecha        = trim($data["fecha"] ?? "");
        $diaSemana    = trim($data["diaSemana"] ?? "");
        $horario      = trim($data["horario"] ?? "");
        $numeroSemana = intval($data["numeroSemana"] ?? 0);
        $anio         = intval($data["anio"] ?? 0);
        $idUsuario    = intval($data["idUsuario"] ?? 0);

        if (empty($fecha) || empty($diaSemana) || empty($horario) ||
            $numeroSemana <= 0 || $anio <= 0) {
            throw new Exception("Parámetros incompletos");
        }

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        // Verificar si ya existe
        $stmtCheck = $pdo->prepare("
            SELECT ID FROM MenuSemanal
            WHERE Fecha = :fecha AND Horario = :horario
        ");
        $stmtCheck->execute([
            ":fecha"   => $fecha,
            ":horario" => $horario,
        ]);

        $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $pdo->commit();
            echo json_encode([
                "success"       => true,
                "id"            => $existente['ID'],
                "message"       => "Menú ya existente",
                "alreadyExists" => true,
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
            ":fecha"   => $fecha,
            ":dia"     => $diaSemana,
            ":horario" => $horario,
            ":semana"  => $numeroSemana,
            ":anio"    => $anio,
            ":usuario" => $idUsuario > 0 ? $idUsuario : null,
        ]);

        $idMenu = $pdo->lastInsertId();
        $pdo->commit();

        echo json_encode([
            "success"       => true,
            "id"            => $idMenu,
            "message"       => "Menú creado exitosamente",
            "alreadyExists" => false,
        ]);

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function handleMenuSemanalRequest()
{
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $semana = $_GET['semana'] ?? date('W');
            $anio   = $_GET['anio'] ?? date('Y');
            getMenuSemana($semana, $anio);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (! $data && ! empty($_POST)) {
                $data = $_POST;
            }

            // Si no hay data JSON, usar GET parameters para action=eliminar
            if (empty($data) && isset($_GET['action']) && $_GET['action'] === 'eliminar') {
                $data = [
                    'semana' => $_GET['semana'] ?? 0,
                    'anio'   => $_GET['anio'] ?? 0,
                ];
            }

            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'generar':
                        generarMenuSemana(
                            $data['fechaInicio'] ?? date('Y-m-d'),
                            $data['idUsuario'] ?? 1
                        );
                        break;

                    case 'asignar':
                        asignarSeccionMenu($data);
                        break;

                    case 'remover':
                        $id = $data['id'] ?? $_GET['id'] ?? 0;
                        removerSeccionMenu($id);
                        break;

                    case 'orden':
                        updateOrdenSecciones($data);
                        break;

                    case 'crearIndividual':
                        crearMenuIndividual($data);
                        break;

                    case 'eliminar': // AÑADIR ESTE CASO
                        eliminarMenuCompleto($data);
                        break;

                    default:
                        http_response_code(400);
                        echo json_encode(["error" => "Acción no válida: " . ($_GET['action'] ?? '')]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["error" => "Acción requerida"]);
            }
            break;

        case 'DELETE':
            // Manejar DELETE con parámetros GET si no hay body
            $data = json_decode(file_get_contents('php://input'), true);
            if (! $data && (isset($_GET['semana']) || isset($_GET['anio']))) {
                $data = [
                    'semana' => $_GET['semana'] ?? 0,
                    'anio'   => $_GET['anio'] ?? 0,
                ];
            }
            eliminarMenuCompleto($data);
            break;

        default:
            http_response_code(405);
            echo json_encode(["error" => "Método no permitido: " . $method]);
    }
}

function getSeccionById($id)
{
    header("Content-Type: application/json");

    if (! $id) {
        http_response_code(400);
        echo json_encode(["error" => "ID requerido"]);
        return;
    }

    try {
        $pdo = (new DBManager())->getPDO();

        $stmt = $pdo->prepare("
            SELECT
                sm.ID,
                sm.Nombre,
                sm.Descripcion,
                sm.URLFoto,
                sm.Color,
                sm.Activo,
                DATE_FORMAT(sm.FechaCreacion, '%Y-%m-%d %H:%i:%s') as FechaCreacion
            FROM SeccionesMenu sm
            WHERE sm.ID = :id
        ");
        $stmt->execute([":id" => $id]);
        $seccion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $seccion) {
            http_response_code(404);
            echo json_encode(["error" => "Sección no encontrada"]);
            return;
        }

        $seccion['Productos']         = getProductosSeccion($pdo, $id);
        $seccion['CantidadProductos'] = count($seccion['Productos']);

        echo json_encode([
            "success" => true,
            "seccion" => $seccion,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}
// Agregar esta función en menu.php
function verificarMenuExistente($fecha, $horario)
{
    header("Content-Type: application/json");

    try {
        if (empty($fecha) || empty($horario)) {
            throw new Exception("Fecha y horario son requeridos");
        }

        $pdo = (new DBManager())->getPDO();

        $stmt = $pdo->prepare("
            SELECT ID FROM MenuSemanal
            WHERE Fecha = :fecha AND Horario = :horario
        ");

        $stmt->execute([
            ":fecha"   => $fecha,
            ":horario" => $horario,
        ]);

        $menu = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "existe"  => ($menu !== false),
            "id"      => $menu ? $menu['ID'] : 0,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}
