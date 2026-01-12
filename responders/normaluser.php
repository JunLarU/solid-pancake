<?php
// responders/normaluser.php - Endpoints para usuarios normales (alumnos)
require_once dirname(__DIR__) . '/core/DBManager.php';

header("Content-Type: application/json; charset=UTF-8");

// Cambiar nombres de funciones para evitar conflictos
function normaluser_getMenuSemanal()
{
    try {
        $pdo = (new DBManager())->getPDO();

        // Obtener parámetros
        $semana  = $_GET['semana'] ?? null;
        $anio    = $_GET['anio'] ?? null;
        $horario = $_GET['horario'] ?? 'Todos';

        // Si no se especifica semana/anio, usar la actual
        if (! $semana || ! $anio) {
            $semana = date('W');
            $anio   = date('Y');
        }

        $menusCompletos = normaluser_getMenusSemana($pdo, $semana, $anio, $horario);

        // Si no hay menús para la semana actual, intentar semana siguiente
        if (empty($menusCompletos)) {
            $semana         = date('W', strtotime('+1 week'));
            $anio           = date('Y', strtotime('+1 week'));
            $menusCompletos = normaluser_getMenusSemana($pdo, $semana, $anio, $horario);
        }

        // Formatear fecha para mostrar
        $fechaInicioSemana = date('Y-m-d', strtotime("{$anio}-W{$semana}-1"));
        $fechaFinSemana    = date('Y-m-d', strtotime("{$anio}-W{$semana}-5"));

        echo json_encode([
            "success"      => true,
            "menus"        => $menusCompletos,
            "semana"       => (int) $semana,
            "anio"         => (int) $anio,
            "fecha_inicio" => $fechaInicioSemana,
            "fecha_fin"    => $fechaFinSemana,
            "total_menus"  => count($menusCompletos),
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error"   => $e->getMessage(),
        ]);
    }
}

function normaluser_getMenusSemana($pdo, $semana, $anio, $horario)
{
    $sql = "
        SELECT
            ms.ID,
            ms.Fecha,
            ms.DiaSemana,
            ms.Horario,
            ms.NumeroSemana,
            ms.Anio
        FROM MenuSemanal ms
        WHERE ms.NumeroSemana = :semana
            AND ms.Anio = :anio
            AND ms.Activo = 1
    ";

    if ($horario !== 'Todos') {
        $sql .= " AND ms.Horario = :horario";
    }

    $sql .= " ORDER BY ms.Fecha,
        CASE ms.Horario
            WHEN 'Desayuno' THEN 1
            WHEN 'Comida' THEN 2
            ELSE 3
        END";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':semana', $semana, PDO::PARAM_INT);
    $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);

    if ($horario !== 'Todos') {
        $stmt->bindParam(':horario', $horario, PDO::PARAM_STR);
    }

    $stmt->execute();
    $menusSemana = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $menusCompletos = [];

    foreach ($menusSemana as $menu) {
        $secciones         = normaluser_getSeccionesMenu($pdo, $menu['ID']);
        $menu['secciones'] = $secciones;
        $menusCompletos[]  = $menu;
    }

    return $menusCompletos;
}

function normaluser_getSeccionesMenu($pdo, $idMenu)
{
    $sql = "
        SELECT
            mss.ID,
            mss.Orden,
            sm.ID as id_seccion,
            sm.Nombre as nombre_seccion,
            sm.Descripcion as descripcion_seccion,
            sm.Color as color_seccion,
            sm.URLFoto as foto_seccion
        FROM MenuSemanalSecciones mss
        INNER JOIN SeccionesMenu sm ON mss.IDSeccion = sm.ID
        WHERE mss.IDMenuSemanal = :id_menu
            AND sm.Activo = 1
        ORDER BY mss.Orden
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_menu', $idMenu, PDO::PARAM_INT);
    $stmt->execute();
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($secciones as &$seccion) {
        $seccion['productos'] = normaluser_getProductosSeccion($pdo, $seccion['id_seccion']);
    }

    return $secciones;
}

function normaluser_getProductosSeccion($pdo, $idSeccion)
{
    $sql = "
        SELECT
            p.ID,
            p.Nombre,
            p.Descripcion,
            p.PrecioBase,
            p.Gramaje,
            p.Calorias,
            p.URLFoto,
            cp.Nombre as categoria,
            smp.Orden,
            smp.Destacado
        FROM SeccionesMenuProductos smp
        INNER JOIN Productos p ON smp.IDProducto = p.ID
        INNER JOIN CategoriasProductos cp ON p.IDCategoria = cp.ID
        WHERE smp.IDSeccion = :id_seccion
            AND p.Disponible = 1
        ORDER BY smp.Orden
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_seccion', $idSeccion, PDO::PARAM_INT);
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($productos as &$producto) {
        $producto['tamanos']      = normaluser_getTamanosProducto($pdo, $producto['ID']);
        $producto['ingredientes'] = normaluser_getIngredientesProducto($pdo, $producto['ID']);
        $producto['tiene_oferta'] = normaluser_tieneOfertaEspecial($pdo, $producto['ID']);
    }

    return $productos;
}

function normaluser_getTamanosProducto($pdo, $idProducto)
{
    $sql = "
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
        WHERE IDProducto = :id_producto
            AND Disponible = 1
        ORDER BY Orden
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function normaluser_getIngredientesProducto($pdo, $idProducto)
{
    $sql = "
        SELECT
            pi.ID,
            pi.IDIngrediente,
            i.Nombre as nombre_ingrediente,
            i.Alergeno,
            ci.Nombre as categoria_ingrediente,
            pi.Cantidad,
            pi.Eliminable,
            pi.Sustituible,
            pi.Orden
        FROM ProductosIngredientes pi
        INNER JOIN Ingredientes i ON pi.IDIngrediente = i.ID
        LEFT JOIN CategoriasIngredientes ci ON i.IDCategoria = ci.ID
        WHERE pi.IDProducto = :id_producto
        ORDER BY pi.Orden
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
    $stmt->execute();
    $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ingredientes as &$ingrediente) {
        if ($ingrediente['Sustituible']) {
            $ingrediente['sustitutos'] = normaluser_getSustitutosIngrediente($pdo, $ingrediente['ID']);
        } else {
            $ingrediente['sustitutos'] = [];
        }
    }

    return $ingredientes;
}

function normaluser_getSustitutosIngrediente($pdo, $idProductoIngrediente)
{
    $sql = "
        SELECT
            si.ID,
            si.IDIngredienteSustituto,
            i.Nombre as nombre_sustituto,
            i.Alergeno,
            si.CostoExtra,
            si.Disponible
        FROM SustitucionesIngredientes si
        INNER JOIN Ingredientes i ON si.IDIngredienteSustituto = i.ID
        WHERE si.IDProductoIngrediente = :id_producto_ingrediente
            AND si.Disponible = 1
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_producto_ingrediente', $idProductoIngrediente, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function normaluser_tieneOfertaEspecial($pdo, $idProducto)
{
    $sql = "
        SELECT 1
        FROM ProductosEspeciales
        WHERE IDProducto = :id_producto
            AND Activo = 1
            AND NOW() BETWEEN FechaInicio AND FechaFin
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

/**
 * Obtener avisos vigentes para usuarios normales
 * GET /api/normaluser/avisos?establecimiento=Todos&tipo=Todos&prioridad=Todos
 */
/**
 * Obtener avisos vigentes para usuarios normales
 * GET /api/normaluser/avisos?establecimiento=Todos&tipo=Todos&prioridad=Todos
 */
function normaluser_getAvisos()
{
    try {
        $pdo = (new DBManager())->getPDO();

        // Obtener parámetros de filtro
        $establecimiento = $_GET['establecimiento'] ?? 'Todos';
        $tipo            = $_GET['tipo'] ?? 'Todos';
        $prioridad       = $_GET['prioridad'] ?? 'Todos';

        $sql = "
            SELECT
                a.ID,
                a.Titulo,
                a.Contenido,
                a.Establecimiento,
                a.TipoAviso,
                a.Prioridad,
                a.FechaPublicacion,
                a.FechaInicio,
                a.FechaFin,
                u.Nombre as creador_nombre,
                u.ApellidoPaterno as creador_apellido
            FROM Avisos a
            LEFT JOIN Usuarios u ON a.IDUsuarioCreador = u.ID
            WHERE a.Activo = 1
                AND NOW() BETWEEN a.FechaInicio AND a.FechaFin
        ";

        // Aplicar filtros
        $params = [];

        if ($establecimiento !== 'Todos') {
            if ($establecimiento === 'Ambos') {
                $sql .= " AND a.Establecimiento IN ('Cafeteria', 'Cafecito', 'Ambos')";
            } else {
                $sql .= " AND (a.Establecimiento = :establecimiento OR a.Establecimiento = 'Ambos')";
                $params[':establecimiento'] = $establecimiento;
            }
        }

        if ($tipo !== 'Todos') {
            $sql .= " AND a.TipoAviso = :tipo";
            $params[':tipo'] = $tipo;
        }

        if ($prioridad !== 'Todos') {
            $sql .= " AND a.Prioridad = :prioridad";
            $params[':prioridad'] = $prioridad;
        }

        $sql .= " ORDER BY
            CASE a.Prioridad
                WHEN 'Importante' THEN 1
                ELSE 2
            END,
            a.FechaPublicacion DESC
            LIMIT 50";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatear fechas para mostrar
        foreach ($avisos as &$aviso) {
            // Fecha de publicación: mantener formato completo
            if ($aviso['FechaPublicacion']) {
                $aviso['FechaPublicacion'] = date('d/m/Y H:i', strtotime($aviso['FechaPublicacion']));
            }

            // Fechas de inicio y fin: mostrar solo fecha para simplificar
            if ($aviso['FechaInicio']) {
                $aviso['FechaInicio'] = date('d/m/Y', strtotime($aviso['FechaInicio']));
            }
            if ($aviso['FechaFin']) {
                $aviso['FechaFin'] = date('d/m/Y', strtotime($aviso['FechaFin']));
            }

            // Determinar color según prioridad
            if ($aviso['Prioridad'] === 'Importante') {
                $aviso['color_borde'] = '#dc3545';
            } else if ($aviso['Establecimiento'] === 'Cafeteria') {
                $aviso['color_borde'] = '#007bff';
            } else if ($aviso['Establecimiento'] === 'Cafecito') {
                $aviso['color_borde'] = '#28a745';
            } else {
                $aviso['color_borde'] = '#6c757d';
            }

            // Determinar icono
            if ($aviso['Establecimiento'] === 'Cafeteria') {
                $aviso['icono'] = '🍽️';
            } else if ($aviso['Establecimiento'] === 'Cafecito') {
                $aviso['icono'] = '☕';
            } else {
                $aviso['icono'] = '📢';
            }
        }

        echo json_encode([
            "success" => true,
            "avisos"  => $avisos,
            "total"   => count($avisos),
            "filtros" => [
                "establecimiento" => $establecimiento,
                "tipo"            => $tipo,
                "prioridad"       => $prioridad,
            ],
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error"   => $e->getMessage(),
        ]);
    }
}

/**
 * Obtener productos especiales vigentes
 * GET /api/normaluser/especiales
 */
function normaluser_getProductosEspeciales()
{
    try {
        $pdo = (new DBManager())->getPDO();

        $sql = "
            SELECT
                pe.ID,
                pe.IDProducto,
                pe.FechaInicio,
                pe.FechaFin,
                pe.Descripcion as descripcion_especial,
                pe.PrecioEspecial,
                p.Nombre as nombre_producto,
                p.Descripcion as descripcion_producto,
                p.PrecioBase,
                p.Gramaje,
                p.Calorias,
                p.URLFoto,
                cp.Nombre as categoria
            FROM ProductosEspeciales pe
            INNER JOIN Productos p ON pe.IDProducto = p.ID
            INNER JOIN CategoriasProductos cp ON p.IDCategoria = cp.ID
            WHERE pe.Activo = 1
                AND p.Disponible = 1
                AND NOW() BETWEEN pe.FechaInicio AND pe.FechaFin
            ORDER BY pe.FechaInicio DESC
            LIMIT 20
        ";

        $stmt       = $pdo->query($sql);
        $especiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular porcentaje de descuento
        foreach ($especiales as &$especial) {
            if ($especial['PrecioBase'] > 0) {
                $descuento                        = (($especial['PrecioBase'] - $especial['PrecioEspecial']) / $especial['PrecioBase']) * 100;
                $especial['porcentaje_descuento'] = round($descuento, 0);
            } else {
                $especial['porcentaje_descuento'] = 0;
            }

            // Formatear fechas
            $especial['FechaInicio'] = date('d/m/Y H:i', strtotime($especial['FechaInicio']));
            $especial['FechaFin']    = date('d/m/Y H:i', strtotime($especial['FechaFin']));
        }

        echo json_encode([
            "success"              => true,
            "productos_especiales" => $especiales,
            "total"                => count($especiales),
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error"   => $e->getMessage(),
        ]);
    }
}

/**
 * Obtener semanas disponibles con menú
 * GET /api/normaluser/semanas
 */
function normaluser_getSemanasDisponibles()
{
    try {
        $pdo = (new DBManager())->getPDO();

        $sql = "
            SELECT
                NumeroSemana,
                Anio,
                COUNT(*) as total_menus,
                MIN(Fecha) as fecha_inicio,
                MAX(Fecha) as fecha_fin
            FROM MenuSemanal
            WHERE Activo = 1
            GROUP BY NumeroSemana, Anio
            ORDER BY Anio DESC, NumeroSemana DESC
            LIMIT 12
        ";

        $stmt    = $pdo->query($sql);
        $semanas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatear información
        foreach ($semanas as &$semana) {
            $semana['NumeroSemana'] = (int) $semana['NumeroSemana'];
            $semana['Anio']         = (int) $semana['Anio'];
            $semana['total_menus']  = (int) $semana['total_menus'];
            $semana['rango_fechas'] = date('d/m', strtotime($semana['fecha_inicio'])) . ' - ' .
            date('d/m', strtotime($semana['fecha_fin']));

            // Determinar si es la semana actual
            $semana['es_actual'] = ($semana['NumeroSemana'] == date('W') && $semana['Anio'] == date('Y'));
        }

        echo json_encode([
            "success"       => true,
            "semanas"       => $semanas,
            "semana_actual" => date('W'),
            "anio_actual"   => date('Y'),
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error"   => $e->getMessage(),
        ]);
    }
}
function _parseDate($dateStr)
{
    if (empty($dateStr)) {
        throw new Exception("Fecha vacía");
    }

    // Log para depuración
    error_log("parseDate input original: " . $dateStr);

    // Limpiar la cadena - eliminar espacios extra y partes duplicadas
    $dateStr = trim($dateStr);
    
    // Si hay múltiples espacios consecutivos, reducirlos a uno
    $dateStr = preg_replace('/\s+/', ' ', $dateStr);
    
    // Si la fecha ya está en formato DATETIME completo, usarla directamente
    if (strlen($dateStr) <= 10) {
        // Es solo fecha YYYY-MM-DD, agregar hora por defecto
        $dateStr .= " 00:00:00";
    }
    
    // Dividir por espacios
    $parts = explode(' ', $dateStr);
    
    // Si hay más de 2 partes (fecha + hora + algo extra), tomar solo las primeras 2
    if (count($parts) > 2) {
        $dateStr = $parts[0] . ' ' . $parts[1];
    }
    
    error_log("parseDate input procesado: " . $dateStr);

    // Intentar múltiples formatos
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s',
        'd/m/Y H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd/m/Y',
        'Y-m-d H:i:s.v', // Para microsegundos si los hay
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date !== false) {
            error_log("parseDate success con formato '$format': " . $date->format('Y-m-d H:i:s'));
            return $date;
        } else {
            error_log("parseDate falló con formato '$format' para: " . $dateStr);
        }
    }

    // Si fallan todos los formatos, intentar con strtotime como último recurso
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        error_log("parseDate fallback strtotime: " . $date->format('Y-m-d H:i:s'));
        return $date;
    }

    throw new Exception("Formato de fecha no reconocido: " . $dateStr);
}

/**
 * Obtener menú de hoy
 * GET /api/normaluser/menu/hoy
 */
function normaluser_getMenuHoy()
{
    try {
        $pdo = (new DBManager())->getPDO();
        $hoy = date('Y-m-d');

        $sql = "
            SELECT
                ms.ID,
                ms.Fecha,
                ms.DiaSemana,
                ms.Horario
            FROM MenuSemanal ms
            WHERE ms.Fecha = :hoy
                AND ms.Activo = 1
            ORDER BY
                CASE ms.Horario
                    WHEN 'Desayuno' THEN 1
                    WHEN 'Comida' THEN 2
                    ELSE 3
                END
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':hoy', $hoy, PDO::PARAM_STR);
        $stmt->execute();
        $menusHoy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener secciones de cada menú
        foreach ($menusHoy as &$menu) {
            $menu['secciones'] = normaluser_getSeccionesMenu($pdo, $menu['ID']);
        }

        echo json_encode([
            "success"        => true,
            "fecha"          => $hoy,
            "menus"          => $menusHoy,
            "total_horarios" => count($menusHoy),
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error"   => $e->getMessage(),
        ]);
    }
}

/**
 * Manejar solicitudes al endpoint de normaluser
 */
function handleNormalUserRequest()
{
    $request = $_SERVER['REQUEST_URI'];
    if ($request[0] == '/') {
        $request = substr($request, 1);
    }

    // Extraer la parte específica después de /api/normaluser/
    $position = strpos($request, 'api/normaluser/');
    if ($position !== false) {
        $endpoint = substr($request, $position + strlen('api/normaluser/'));

        // Eliminar parámetros de consulta
        $position = strpos($endpoint, '?');
        if ($position !== false) {
            $endpoint = substr($endpoint, 0, $position);
        }

        switch ($endpoint) {
            case 'menu':
                normaluser_getMenuSemanal();
                break;

            case 'avisos':
                normaluser_getAvisos();
                break;

            case 'especiales':
                normaluser_getProductosEspeciales();
                break;

            case 'semanas':
                normaluser_getSemanasDisponibles();
                break;

            case 'menu/hoy':
                normaluser_getMenuHoy();
                break;

            default:
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "error"   => "Endpoint no encontrado: " . $endpoint,
                ]);
                break;
        }
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Ruta no válida"]);
    }
}

// Si se llama directamente, manejar la solicitud
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleNormalUserRequest();
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido"]);
    }
}
