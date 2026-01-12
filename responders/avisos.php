<?php
// api/avisos.php
require_once dirname(__DIR__) . '/core/DBManager.php';

function listAvisos()
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();

        $sql = "SELECT
                    a.ID,
                    a.Titulo,
                    a.Contenido,
                    a.Establecimiento,
                    a.TipoAviso,
                    a.Prioridad,
                    a.FechaInicio,
                    a.FechaFin,
                    a.Activo,
                    a.IDUsuarioCreador,
                    a.FechaPublicacion,
                    CONCAT(u.Nombre, ' ', u.ApellidoPaterno) AS Creador
                FROM Avisos a
                LEFT JOIN Usuarios u ON a.IDUsuarioCreador = u.ID
                ORDER BY a.FechaInicio DESC, a.ID DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // NO convertir fechas a solo fecha - mantener DATETIME completo
        foreach ($avisos as &$aviso) {
            if ($aviso['FechaInicio']) {
                // Mantener como DATETIME si tiene hora, si no agregar hora
                if (strlen($aviso['FechaInicio']) <= 10) {
                    $aviso['FechaInicio'] = $aviso['FechaInicio'] . ' 00:00:00';
                }
            }
            if ($aviso['FechaFin']) {
                if (strlen($aviso['FechaFin']) <= 10) {
                    $aviso['FechaFin'] = $aviso['FechaFin'] . ' 23:59:59';
                }
            }
            // FechaPublicacion ya debería ser DATETIME completo
        }

        echo json_encode(["avisos" => $avisos]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function getAviso($id)
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();

        $sql = "SELECT
                    a.*,
                    CONCAT(u.Nombre, ' ', u.ApellidoPaterno) AS Creador
                FROM Avisos a
                LEFT JOIN Usuarios u ON a.IDUsuarioCreador = u.ID
                WHERE a.ID = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id]);
        $aviso = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $aviso) {
            http_response_code(404);
            echo json_encode(["error" => "Aviso no encontrado"]);
            return;
        }

        // Formatear fechas como DATETIME completo
        if ($aviso['FechaInicio']) {
            if (strlen($aviso['FechaInicio']) <= 10) {
                $aviso['FechaInicio'] = $aviso['FechaInicio'] . ' 00:00:00';
            }
        }
        if ($aviso['FechaFin']) {
            if (strlen($aviso['FechaFin']) <= 10) {
                $aviso['FechaFin'] = $aviso['FechaFin'] . ' 23:59:59';
            }
        }

        echo json_encode(["aviso" => $aviso]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function createAviso($data)
{
    header("Content-Type: application/json; charset=UTF-8");
    ob_start();

    try {
        error_log("=== CREATE AVISO INICIADO ===");

        if (empty($data)) {
            $rawInput = file_get_contents('php://input');
            error_log("Raw input: " . $rawInput);
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON inválido: " . json_last_error_msg());
            }
        }

        // Log de datos recibidos
        error_log("Datos recibidos: " . print_r($data, true));

        // Validar campos obligatorios
        $camposRequeridos = ["titulo", "contenido", "establecimiento", "tipoAviso", "fechaInicio", "fechaFin"];
        foreach ($camposRequeridos as $campo) {
            if (! isset($data[$campo]) || empty($data[$campo])) {
                throw new Exception("El campo '$campo' es obligatorio");
            }
        }

        // Validar y procesar fechas
        $fechaInicio = null;
        $fechaFin    = null;

        try {
            // Procesar fecha de inicio
            $fechaInicioStr = $data["fechaInicio"];

            // Si se envían hora y minuto separados, combinarlos
            if (isset($data["horaInicio"]) && isset($data["minutoInicio"])) {
                // Extraer solo la parte de fecha (antes del espacio o 'T')
                if (strpos($fechaInicioStr, ' ') !== false) {
                    $fechaPart = explode(' ', $fechaInicioStr)[0];
                } elseif (strpos($fechaInicioStr, 'T') !== false) {
                    $fechaPart = explode('T', $fechaInicioStr)[0];
                } else {
                    $fechaPart = $fechaInicioStr;
                }

                $horaInicio     = intval($data["horaInicio"]);
                $minutoInicio   = intval($data["minutoInicio"]);
                $fechaInicioStr = $fechaPart . " " . sprintf("%02d:%02d:00", $horaInicio, $minutoInicio);
            } elseif (! strpos($fechaInicioStr, ':')) {
                // Si no tiene hora, agregar 00:00:00
                $fechaInicioStr .= " 00:00:00";
            }

            $fechaInicio = parseDate($fechaInicioStr);
            error_log("Fecha inicio procesada: " . $fechaInicio->format('Y-m-d H:i:s'));
        } catch (Exception $e) {
            throw new Exception("Formato de fecha de inicio inválido: " . $e->getMessage());
        }

        try {
            // Procesar fecha de fin
            $fechaFinStr = $data["fechaFin"];

            // Si se envían hora y minuto separados, combinarlos
            if (isset($data["horaFin"]) && isset($data["minutoFin"])) {
                // Extraer solo la parte de fecha
                if (strpos($fechaFinStr, ' ') !== false) {
                    $fechaPart = explode(' ', $fechaFinStr)[0];
                } elseif (strpos($fechaFinStr, 'T') !== false) {
                    $fechaPart = explode('T', $fechaFinStr)[0];
                } else {
                    $fechaPart = $fechaFinStr;
                }

                $horaFin     = intval($data["horaFin"]);
                $minutoFin   = intval($data["minutoFin"]);
                $fechaFinStr = $fechaPart . " " . sprintf("%02d:%02d:00", $horaFin, $minutoFin);
            } elseif (! strpos($fechaFinStr, ':')) {
                // Si no tiene hora, agregar 23:59:59
                $fechaFinStr .= " 23:59:59";
            }

            $fechaFin = parseDate($fechaFinStr);
            error_log("Fecha fin procesada: " . $fechaFin->format('Y-m-d H:i:s'));
        } catch (Exception $e) {
            throw new Exception("Formato de fecha de fin inválido: " . $e->getMessage());
        }

        if ($fechaInicio > $fechaFin) {
            throw new Exception("La fecha de inicio no puede ser posterior a la fecha de fin");
        }

        // Validar establecimiento
        $establecimientosPermitidos = ['Cafeteria', 'Cafecito', 'Ambos'];
        if (! in_array($data["establecimiento"], $establecimientosPermitidos)) {
            throw new Exception("Establecimiento inválido");
        }

        // Validar tipo de aviso
        $tiposPermitidos = ['General', 'Horario', 'NoLaboral', 'Oferta', 'Evento'];
        if (! in_array($data["tipoAviso"], $tiposPermitidos)) {
            throw new Exception("Tipo de aviso inválido");
        }

        // Validar prioridad
        $prioridad = isset($data["prioridad"]) ? $data["prioridad"] : 'Normal';
        if (! in_array($prioridad, ['Normal', 'Importante'])) {
            throw new Exception("Prioridad inválida");
        }

        // Obtener ID del usuario
        $idUsuario = isset($data["idUsuarioCreador"]) ? intval($data["idUsuarioCreador"]) : null;

        $pdo = (new DBManager())->getPDO();
        $pdo->beginTransaction();

        $sql = "INSERT INTO Avisos
                (Titulo, Contenido, Establecimiento, TipoAviso, Prioridad,
                 FechaInicio, FechaFin, IDUsuarioCreador, Activo)
                VALUES (:titulo, :contenido, :estab, :tipo, :prioridad,
                        :fechaInicio, :fechaFin, :idUsuario, :activo)";

        $stmt   = $pdo->prepare($sql);
        $params = [
            ":titulo"      => trim($data["titulo"]),
            ":contenido"   => trim($data["contenido"]),
            ":estab"       => $data["establecimiento"],
            ":tipo"        => $data["tipoAviso"],
            ":prioridad"   => $prioridad,
            ":fechaInicio" => $fechaInicio->format('Y-m-d H:i:s'),
            ":fechaFin"    => $fechaFin->format('Y-m-d H:i:s'),
            ":idUsuario"   => $idUsuario,
            ":activo"      => isset($data["activo"]) ? (filter_var($data["activo"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
        ];

        error_log("Params para INSERT: " . print_r($params, true));

        $result = $stmt->execute($params);

        if (! $result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Error al crear aviso: " . $errorInfo[2]);
        }

        $idAviso = $pdo->lastInsertId();

        $pdo->commit();
        ob_clean();

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "id"      => $idAviso,
            "message" => "Aviso creado exitosamente",
        ]);

        error_log("=== CREATE AVISO FINALIZADO CON ÉXITO ===");

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

        error_log("ERROR en createAviso: " . $e->getMessage());
    }
}

// También necesitamos corregir la función updateAviso:
function updateAviso($data)
{
    header("Content-Type: application/json; charset=UTF-8");
    ob_start();

    try {
        error_log("=== UPDATE AVISO INICIADO ===");

        if (! isset($data["id"])) {
            throw new Exception("ID del aviso faltante");
        }

        $idAviso = intval($data["id"]);

        // Verificar si el aviso existe
        $pdo       = (new DBManager())->getPDO();
        $stmtCheck = $pdo->prepare("SELECT ID FROM Avisos WHERE ID = ?");
        $stmtCheck->execute([$idAviso]);
        $avisoExiste = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (! $avisoExiste) {
            throw new Exception("Aviso no encontrado");
        }

        $pdo->beginTransaction();

        // Construir query dinámica
        $updates = [];
        $params  = [":id" => $idAviso];

        // Manejar fechas con hora/minuto
        if (isset($data["fechaInicio"])) {
            try {
                // Si se envían hora y minuto separados, combinarlos
                $fechaInicioStr = $data["fechaInicio"];

                if (isset($data["horaInicio"]) && isset($data["minutoInicio"])) {
                    // Extraer solo la parte de fecha (antes del espacio o 'T')
                    if (strpos($fechaInicioStr, ' ') !== false) {
                        $fechaPart = explode(' ', $fechaInicioStr)[0];
                    } elseif (strpos($fechaInicioStr, 'T') !== false) {
                        $fechaPart = explode('T', $fechaInicioStr)[0];
                    } else {
                        $fechaPart = $fechaInicioStr;
                    }

                    $horaInicio     = intval($data["horaInicio"]);
                    $minutoInicio   = intval($data["minutoInicio"]);
                    $fechaInicioStr = $fechaPart . " " . sprintf("%02d:%02d:00", $horaInicio, $minutoInicio);
                }

                $fechaInicio            = parseDate($fechaInicioStr);
                $updates[]              = "FechaInicio = :fechaInicio";
                $params[":fechaInicio"] = $fechaInicio->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                throw new Exception("Formato de fecha de inicio inválido: " . $e->getMessage());
            }
        }

        if (isset($data["fechaFin"])) {
            try {
                // Si se envían hora y minuto separados, combinarlos
                $fechaFinStr = $data["fechaFin"];

                if (isset($data["horaFin"]) && isset($data["minutoFin"])) {
                    // Extraer solo la parte de fecha
                    if (strpos($fechaFinStr, ' ') !== false) {
                        $fechaPart = explode(' ', $fechaFinStr)[0];
                    } elseif (strpos($fechaFinStr, 'T') !== false) {
                        $fechaPart = explode('T', $fechaFinStr)[0];
                    } else {
                        $fechaPart = $fechaFinStr;
                    }

                    $horaFin     = intval($data["horaFin"]);
                    $minutoFin   = intval($data["minutoFin"]);
                    $fechaFinStr = $fechaPart . " " . sprintf("%02d:%02d:00", $horaFin, $minutoFin);
                }

                $fechaFin            = parseDate($fechaFinStr);
                $updates[]           = "FechaFin = :fechaFin";
                $params[":fechaFin"] = $fechaFin->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                throw new Exception("Formato de fecha de fin inválido: " . $e->getMessage());
            }
        }

        // Validar que fecha de inicio no sea posterior a fecha de fin
        if (isset($params[":fechaInicio"]) && isset($params[":fechaFin"])) {
            $fechaInicio = new DateTime($params[":fechaInicio"]);
            $fechaFin    = new DateTime($params[":fechaFin"]);

            if ($fechaInicio > $fechaFin) {
                throw new Exception("La fecha de inicio no puede ser posterior a la fecha de fin");
            }
        }

        // Resto de campos (sin cambios)
        if (isset($data["titulo"])) {
            $updates[]         = "Titulo = :titulo";
            $params[":titulo"] = trim($data["titulo"]);
        }
        if (isset($data["contenido"])) {
            $updates[]            = "Contenido = :contenido";
            $params[":contenido"] = trim($data["contenido"]);
        }
        if (isset($data["establecimiento"])) {
            $establecimientosPermitidos = ['Cafeteria', 'Cafecito', 'Ambos'];
            if (! in_array($data["establecimiento"], $establecimientosPermitidos)) {
                throw new Exception("Establecimiento inválido");
            }
            $updates[]        = "Establecimiento = :estab";
            $params[":estab"] = $data["establecimiento"];
        }
        if (isset($data["tipoAviso"])) {
            $tiposPermitidos = ['General', 'Horario', 'NoLaboral', 'Oferta', 'Evento'];
            if (! in_array($data["tipoAviso"], $tiposPermitidos)) {
                throw new Exception("Tipo de aviso inválido");
            }
            $updates[]       = "TipoAviso = :tipo";
            $params[":tipo"] = $data["tipoAviso"];
        }
        if (isset($data["prioridad"])) {
            $prioridadesPermitidas = ['Normal', 'Importante'];
            if (! in_array($data["prioridad"], $prioridadesPermitidas)) {
                throw new Exception("Prioridad inválida");
            }
            $updates[]            = "Prioridad = :prioridad";
            $params[":prioridad"] = $data["prioridad"];
        }
        if (isset($data["activo"])) {
            $updates[]         = "Activo = :activo";
            $params[":activo"] = filter_var($data["activo"], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (empty($updates)) {
            throw new Exception("No se proporcionaron datos para actualizar");
        }

        $sql = "UPDATE Avisos SET " . implode(", ", $updates) . " WHERE ID = :id";
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));

        $stmt   = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        if (! $result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Error al actualizar aviso: " . $errorInfo[2]);
        }

        $pdo->commit();
        ob_clean();

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Aviso actualizado exitosamente",
        ]);

        error_log("=== UPDATE AVISO FINALIZADO CON ÉXITO ===");

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

        error_log("ERROR en updateAviso: " . $e->getMessage());
    }
}

function deleteAviso($id)
{
    header("Content-Type: application/json");

    try {
        $pdo = (new DBManager())->getPDO();

        // Verificar si existe
        $stmtCheck = $pdo->prepare("SELECT ID FROM Avisos WHERE ID = ?");
        $stmtCheck->execute([$id]);
        $avisoExiste = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (! $avisoExiste) {
            http_response_code(404);
            echo json_encode(["error" => "Aviso no encontrado"]);
            return;
        }

        // Eliminar el aviso
        $stmtDelete = $pdo->prepare("DELETE FROM Avisos WHERE ID = ?");
        $stmtDelete->execute([$id]);

        if ($stmtDelete->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Aviso no encontrado o ya eliminado"]);
            return;
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Aviso eliminado exitosamente",
            "id"      => $id,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al eliminar aviso: " . $e->getMessage()]);
    }
}

function parseDate($dateStr)
{
    if (empty($dateStr)) {
        throw new Exception("Fecha vacía");
    }

    // Limpiar la cadena - eliminar espacios extra y partes duplicadas
    $dateStr = trim($dateStr);

    // Si hay múltiples espacios consecutivos, reducirlos a uno
    $dateStr = preg_replace('/\s+/', ' ', $dateStr);

    // Dividir por espacios
    $parts = explode(' ', $dateStr);

    // Si hay más de 2 partes (fecha + hora + algo extra), tomar solo las primeras 2
    if (count($parts) > 2) {
        $dateStr = $parts[0] . ' ' . $parts[1];
    }

    // Log para depuración
    error_log("parseDate input: " . $dateStr);

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
            error_log("parseDate success: " . $date->format('Y-m-d H:i:s'));
            return $date;
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
function handleAvisosRequest()
{
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (isset($_GET['id']) && ! empty($_GET['id'])) {
            getAviso(intval($_GET['id']));
        } else {
            listAvisos();
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "JSON inválido en POST"]);
            return;
        }

        createAviso($data);
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "JSON inválido en PUT"]);
            return;
        }

        updateAviso($data);
    } elseif ($method === 'DELETE') {
        if (isset($_GET['id']) && ! empty($_GET['id'])) {
            deleteAviso(intval($_GET['id']));
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID del aviso faltante"]);
        }
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido"]);
    }
}

// Llamar a la función principal
