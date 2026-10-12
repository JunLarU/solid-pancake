<?php

function uploadProductoImagen()
{
    header("Content-Type: application/json; charset=UTF-8");

    try {

        // =====================================================
        // 1. Verificar método
        // =====================================================

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            http_response_code(405);

            echo json_encode([
                "success" => false,
                "error" => "Método no permitido"
            ]);

            return;
        }


        // =====================================================
        // 2. Verificar archivo
        // =====================================================

        if (!isset($_FILES['imagen'])) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "error" => "No se recibió ninguna imagen"
            ]);

            return;
        }


        $archivo = $_FILES['imagen'];


        // =====================================================
        // 3. Comprobar errores de subida
        // =====================================================

        if ($archivo['error'] !== UPLOAD_ERR_OK) {

            throw new Exception(
                "Error al subir la imagen. Código: "
                . $archivo['error']
            );
        }


        // =====================================================
        // 4. Validar tamaño
        // Máximo 5 MB
        // =====================================================

        $maxSize = 5 * 1024 * 1024;

        if ($archivo['size'] > $maxSize) {

            http_response_code(413);

            echo json_encode([
                "success" => false,
                "error" => "La imagen supera el límite de 5 MB"
            ]);

            return;
        }


        // =====================================================
        // 5. Validar MIME real
        // =====================================================

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mime = $finfo->file(
            $archivo['tmp_name']
        );


        $tiposPermitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];


        if (!isset($tiposPermitidos[$mime])) {

            http_response_code(415);

            echo json_encode([
                "success" => false,
                "error" => "Formato de imagen no permitido"
            ]);

            return;
        }


        $extension = $tiposPermitidos[$mime];


        // =====================================================
        // 6. Crear nombre único
        // =====================================================

        $nombreArchivo =
            bin2hex(random_bytes(16))
            . '.'
            . $extension;


        // =====================================================
        // 7. Directorio físico
        // =====================================================

        $directorio =
            dirname(__DIR__)
            . '/uploads/productos/';


        if (!is_dir($directorio)) {

            if (!mkdir(
                $directorio,
                0755,
                true
            )) {

                throw new Exception(
                    "No se pudo crear el directorio de imágenes"
                );
            }
        }


        // =====================================================
        // 8. Guardar archivo
        // =====================================================

        $destino =
            $directorio
            . $nombreArchivo;


        if (!move_uploaded_file(
            $archivo['tmp_name'],
            $destino
        )) {

            throw new Exception(
                "No se pudo guardar la imagen"
            );
        }


        // =====================================================
        // 9. Ruta que se almacenará en MySQL
        // =====================================================

        $rutaRelativa =
            "uploads/productos/"
            . $nombreArchivo;


        // =====================================================
        // 10. Respuesta
        // =====================================================

        http_response_code(201);

        echo json_encode([
            "success" => true,
            "url" => $rutaRelativa,
            "message" => "Imagen subida correctamente"
        ]);

    } catch (Exception $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}