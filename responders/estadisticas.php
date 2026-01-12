<?php
// api/estadisticas.php
require_once dirname(__DIR__) . '/core/DBManager.php';

header("Content-Type: application/json; charset=UTF-8");

function getEstadisticasPorPeriodo($periodo = 'hoy') {
    try {
        $pdo = (new DBManager())->getPDO();
        
        $estadisticas = [];
        
        // 1. Estadísticas de Usuarios
        $estadisticas['usuarios'] = getEstadisticasUsuariosPeriodo($pdo, $periodo);
        
        // 2. Estadísticas de Productos
        $estadisticas['productos'] = getEstadisticasProductosPeriodo($pdo, $periodo);
        
        // 3. Estadísticas de Categorías
        $estadisticas['categorias'] = getEstadisticasCategoriasPeriodo($pdo, $periodo);
        
        // 4. Estadísticas de Menús
        $estadisticas['menus'] = getEstadisticasMenusPeriodo($pdo, $periodo);
        
        // 5. Estadísticas de Avisos
        $estadisticas['avisos'] = getEstadisticasAvisosPeriodo($pdo, $periodo);
        
        // 6. Estadísticas de Ingredientes
        $estadisticas['ingredientes'] = getEstadisticasIngredientesPeriodo($pdo, $periodo);
        
        echo json_encode([
            "success" => true,
            "estadisticas" => $estadisticas,
            "periodo" => $periodo,
            "fecha_actual" => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage(),
            "trace" => $e->getTraceAsString()
        ]);
    }
}

function getEstadisticasUsuariosPeriodo($pdo, $periodo) {
    $stats = [];
    $where = getWhereClause($periodo, 'FechaRegistro');
    
    // Total de usuarios en el período
    $sql = "SELECT COUNT(*) as total_periodo FROM Usuarios WHERE 1 $where";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_' . $periodo] = $result ? $result['total_periodo'] : 0;
    
    // Total general
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Usuarios");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total'] = $result ? $result['total'] : 0;
    
    // Usuarios por tipo en el período
    $sql = "SELECT Tipo, COUNT(*) as cantidad FROM Usuarios WHERE 1 $where GROUP BY Tipo";
    $stmt = $pdo->query($sql);
    $stats['por_tipo_' . $periodo] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

function getEstadisticasProductosPeriodo($pdo, $periodo) {
    $stats = [];
    $where = getWhereClause($periodo, 'FechaCreacion');
    
    // Productos creados en el período
    $sql = "SELECT COUNT(*) as total_periodo FROM Productos WHERE 1 $where";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_' . $periodo] = $result ? $result['total_periodo'] : 0;
    
    // Total general
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Productos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total'] = $result ? $result['total'] : 0;
    
    // Productos disponibles vs no disponibles en el período
    $sql = "SELECT Disponible, COUNT(*) as cantidad FROM Productos WHERE 1 $where GROUP BY Disponible";
    $stmt = $pdo->query($sql);
    $stats['por_disponibilidad_' . $periodo] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

function getEstadisticasCategoriasPeriodo($pdo, $periodo) {
    $stats = [];
    
    // Total de categorías (no cambia por período)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM CategoriasProductos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total'] = $result ? $result['total'] : 0;
    $stats['total_' . $periodo] = $stats['total']; // Mismo para todos los períodos
    
    // Productos por categoría en el período
    $where = getWhereClause($periodo, 'p.FechaCreacion');
    $sql = "
        SELECT 
            cp.Nombre as categoria,
            COUNT(p.ID) as cantidad_productos
        FROM CategoriasProductos cp
        LEFT JOIN Productos p ON cp.ID = p.IDCategoria
        WHERE 1 $where
        GROUP BY cp.ID, cp.Nombre
        ORDER BY cantidad_productos DESC
        LIMIT 10
    ";
    $stmt = $pdo->query($sql);
    $stats['productos_por_categoria_' . $periodo] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

function getEstadisticasMenusPeriodo($pdo, $periodo) {
    $stats = [];
    $where = getWhereClause($periodo, 'FechaCreacion');
    
    // Menús creados en el período
    $sql = "SELECT COUNT(*) as total_periodo FROM MenuSemanal WHERE 1 $where";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_' . $periodo] = $result ? $result['total_periodo'] : 0;
    
    // Total general
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM MenuSemanal");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total'] = $result ? $result['total'] : 0;
    
    // Menús por día de la semana en el período
    $sql = "
        SELECT DiaSemana, COUNT(*) as cantidad 
        FROM MenuSemanal 
        WHERE 1 $where
        GROUP BY DiaSemana 
        ORDER BY FIELD(DiaSemana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo')
    ";
    $stmt = $pdo->query($sql);
    $stats['por_dia_semana_' . $periodo] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

function getEstadisticasAvisosPeriodo($pdo, $periodo) {
    $stats = [];
    $where = getWhereClause($periodo, 'FechaPublicacion');
    
    // Avisos publicados en el período
    $sql = "SELECT COUNT(*) as total_periodo FROM Avisos WHERE 1 $where";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_' . $periodo] = $result ? $result['total_periodo'] : 0;
    
    // Total general
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Avisos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total'] = $result ? $result['total'] : 0;
    
    // Avisos por establecimiento en el período
    $sql = "SELECT Establecimiento, COUNT(*) as cantidad FROM Avisos WHERE 1 $where GROUP BY Establecimiento";
    $stmt = $pdo->query($sql);
    $stats['por_establecimiento_' . $periodo] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

function getEstadisticasIngredientesPeriodo($pdo, $periodo) {
    $stats = [];
    
    // Total de ingredientes (no cambia por período)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Ingredientes");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total'] = $result ? $result['total'] : 0;
    $stats['total_' . $periodo] = $stats['total']; // Mismo para todos los períodos
    
    return $stats;
}

function getWhereClause($periodo, $field) {
    $today = date('Y-m-d');
    
    switch($periodo) {
        case 'hoy':
            return "AND DATE($field) = '$today'";
        case 'semana':
            return "AND $field >= DATE_SUB('$today', INTERVAL 7 DAY)";
        case 'mes':
            return "AND $field >= DATE_SUB('$today', INTERVAL 30 DAY)";
        case 'seis_meses':
            return "AND $field >= DATE_SUB('$today', INTERVAL 6 MONTH)";
        default:
            return "";
    }
}

// Manejar la solicitud
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $periodo = $_GET['periodo'] ?? 'hoy';
    
    // Validar período
    $periodosValidos = ['hoy', 'semana', 'mes', 'seis_meses'];
    if (!in_array($periodo, $periodosValidos)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Período no válido. Use: hoy, semana, mes, seis_meses"
        ]);
        return;
    }
    
    getEstadisticasPorPeriodo($periodo);
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
?>