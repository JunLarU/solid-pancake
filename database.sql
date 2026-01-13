CREATE TABLE `Usuarios` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Expediente` VARCHAR(20) NOT NULL UNIQUE,
  `Nombre` VARCHAR(100) NOT NULL,
  `ApellidoPaterno` VARCHAR(100) NOT NULL,
  `ApellidoMaterno` VARCHAR(100),
  `NIP` VARCHAR(255) NOT NULL, 
  `Correo` VARCHAR(255) NOT NULL UNIQUE,
  `Telefono` VARCHAR(15),
  `Tipo` ENUM('Administrador','Usuario') NOT NULL DEFAULT 'Usuario',
  `FechaRegistro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `Activo` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`ID`),
  INDEX `idx_expediente` (`Expediente`),
  INDEX `idx_tipo` (`Tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `CategoriasProductos` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Nombre` VARCHAR(50) NOT NULL,
  `Descripcion` TEXT,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `CategoriasProductos` (`Nombre`, `Descripcion`) VALUES
('Desayuno Mexicano', 'Chilaquiles, huevos, molletes, etc.'),
('Desayuno Continental', 'Hot cakes, waffles, pan francés'),
('Desayuno Express', 'Opciones rápidas para llevar'),

-- CAFETERÍA - COMIDAS
('Plato Fuerte', 'Comidas completas del día'),
('Antojitos Mexicanos', 'Tacos, quesadillas, sopes, tostadas'),
('Hamburguesas', 'Hamburguesas y variantes gourmet'),
('Tortas y Sandwiches', 'Tortas mexicanas y sandwiches'),
('Ensaladas', 'Ensaladas frescas y saludables'),
('Sopas y Cremas', 'Sopas del día y cremas'),
('Pastas', 'Pastas y espaguetis'),
('Alitas y Boneless', 'Alitas de pollo y boneless'),

-- CAFETERÍA - GUARNICIONES Y COMPLEMENTOS
('Guarniciones', 'Arroz, frijoles, papas, etc.'),
('Extras', 'Complementos adicionales'),

-- CAFETERÍA - POSTRES
('Postres', 'Pays, flanes, gelatinas'),
('Repostería', 'Pasteles, brownies, galletas'),

-- CAFECITO - BEBIDAS CALIENTES
('Café', 'Americano, cappuccino, latte, etc.'),
('Té e Infusiones', 'Variedades de té'),
('Chocolate Caliente', 'Chocolate y malteadas calientes'),
('Bebidas de Temporada Calientes', 'Especiales de temporada'),

-- CAFECITO - BEBIDAS FRÍAS
('Café Frío', 'Frappés, cold brew, iced coffee'),
('Smoothies', 'Smoothies de frutas'),
('Jugos y Licuados', 'Naturales y combinados'),
('Aguas Frescas', 'Aguas de sabor del día'),
('Refrescos', 'Bebidas gaseosas y embotelladas'),
('Bebidas Energéticas', 'Bebidas deportivas y energizantes'),
('Bebidas de Temporada Frías', 'Especiales de temporada'),

-- CAFECITO - SNACKS Y PANADERÍA
('Snacks Dulces', 'Galletas, chocolates, dulces'),
('Snacks Salados', 'Papas, nachos, palomitas'),
('Panadería', 'Pan dulce, donas, muffins'),
('Baguettes y Croissants', 'Pan francés y variantes'),
('Yogurt y Parfait', 'Yogurt con frutas y granola');


CREATE TABLE `Productos` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Nombre` VARCHAR(255) NOT NULL,
  `Descripcion` TEXT,
  `PrecioBase` DECIMAL(10,2) NOT NULL,
  `IDCategoria` INT(11) NOT NULL,
  `Gramaje` DECIMAL(10,2), 
  `Calorias` DECIMAL(10,2), 
  `URLFoto` TEXT,
  `Disponible` TINYINT(1) DEFAULT 1,
  `FechaCreacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDCategoria`) REFERENCES `CategoriasProductos`(`ID`) ON DELETE RESTRICT,
  INDEX `idx_categoria` (`IDCategoria`),
  INDEX `idx_disponible` (`Disponible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TamanosProductos` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `IDProducto` INT(11) NOT NULL,
  `Nombre` VARCHAR(50) NOT NULL, 
  `Descripcion` VARCHAR(255), 
  `Capacidad` DECIMAL(10,2), 
  `Gramaje` DECIMAL(10,2), 
  `Piezas` INT(3), 
  `Precio` DECIMAL(10,2) NOT NULL,
  `Orden` INT(3) DEFAULT 1, 
  `Disponible` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDProducto`) REFERENCES `Productos`(`ID`) ON DELETE CASCADE,
  INDEX `idx_producto` (`IDProducto`),
  INDEX `idx_disponible` (`Disponible`),
  INDEX `idx_orden` (`Orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `CategoriasIngredientes` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Nombre` VARCHAR(100) NOT NULL,
  `Descripcion` TEXT,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `CategoriasIngredientes` (`Nombre`, `Descripcion`) VALUES
('Lácteos', 'Leches, quesos, cremas'),
('Proteínas', 'Carnes, pollo, pescado'),
('Vegetales', 'Verduras y hortalizas'),
('Panes', 'Tipos de pan'),
('Aderezos', 'Salsas y aderezos'),
('Endulzantes', 'Azúcares y sustitutos'),
('Lácteos Vegetales', 'Leches vegetales y alternativas sin lactosa');


CREATE TABLE `Ingredientes` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Nombre` VARCHAR(255) NOT NULL,
  `IDCategoria` INT(11),
  `Descripcion` TEXT,
  `Calorias` DECIMAL(10,2), 
  `Alergeno` TINYINT(1) DEFAULT 0, 
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDCategoria`) REFERENCES `CategoriasIngredientes`(`ID`) ON DELETE SET NULL,
  INDEX `idx_categoria` (`IDCategoria`),
  INDEX `idx_nombre` (`Nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `ProductosIngredientes` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `IDProducto` INT(11) NOT NULL,
  `IDIngrediente` INT(11) NOT NULL,
  `Cantidad` DECIMAL(10,2), 
  `Eliminable` TINYINT(1) DEFAULT 0,
  `Sustituible` TINYINT(1) DEFAULT 0,
  `Orden` INT(3), 
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDProducto`) REFERENCES `Productos`(`ID`) ON DELETE CASCADE,
  FOREIGN KEY (`IDIngrediente`) REFERENCES `Ingredientes`(`ID`) ON DELETE RESTRICT,
  INDEX `idx_producto` (`IDProducto`),
  INDEX `idx_ingrediente` (`IDIngrediente`),
  UNIQUE KEY `unique_producto_ingrediente` (`IDProducto`, `IDIngrediente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `SustitucionesIngredientes` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `IDProductoIngrediente` INT(11) NOT NULL, 
  `IDIngredienteSustituto` INT(11) NOT NULL, 
  `CostoExtra` DECIMAL(10,2) DEFAULT 0.00,
  `Disponible` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDProductoIngrediente`) REFERENCES `ProductosIngredientes`(`ID`) ON DELETE CASCADE,
  FOREIGN KEY (`IDIngredienteSustituto`) REFERENCES `Ingredientes`(`ID`) ON DELETE RESTRICT,
  INDEX `idx_producto_ingrediente` (`IDProductoIngrediente`),
  INDEX `idx_ingrediente_sustituto` (`IDIngredienteSustituto`),
  UNIQUE KEY `unique_sustitucion` (`IDProductoIngrediente`, `IDIngredienteSustituto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `SeccionesMenu` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Nombre` VARCHAR(100) NOT NULL,
  `Descripcion` TEXT,
  `URLFoto` TEXT, 
  `Color` VARCHAR(7), 
  `Activo` TINYINT(1) DEFAULT 1,
  `FechaCreacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  INDEX `idx_nombre` (`Nombre`),
  INDEX `idx_activo` (`Activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `SeccionesMenuProductos` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `IDSeccion` INT(11) NOT NULL,
  `IDProducto` INT(11) NOT NULL,
  `Orden` INT(3), 
  `Destacado` TINYINT(1) DEFAULT 0, 
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDSeccion`) REFERENCES `SeccionesMenu`(`ID`) ON DELETE CASCADE,
  FOREIGN KEY (`IDProducto`) REFERENCES `Productos`(`ID`) ON DELETE CASCADE,
  INDEX `idx_seccion` (`IDSeccion`),
  INDEX `idx_producto` (`IDProducto`),
  UNIQUE KEY `unique_seccion_producto` (`IDSeccion`, `IDProducto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `MenuSemanal` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Fecha` DATE NOT NULL,
  `DiaSemana` ENUM('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') NOT NULL,
  `Horario` ENUM('Desayuno','Comida') NOT NULL,
  `NumeroSemana` INT(2) NOT NULL, -- Semana del año (1-52)
  `Anio` INT(4) NOT NULL,
  `FechaCreacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `Activo` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unique_fecha_horario` (`Fecha`, `Horario`),
  INDEX `idx_fecha` (`Fecha`),
  INDEX `idx_semana_anio` (`NumeroSemana`, `Anio`),
  INDEX `idx_activo` (`Activo`),
  INDEX `idx_dia_horario` (`DiaSemana`, `Horario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `MenuSemanalSecciones` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `IDMenuSemanal` INT(11) NOT NULL, 
  `IDSeccion` INT(11) NOT NULL, 
  `Orden` INT(3), 
  `IDUsuarioAsigno` INT(11), 
  `FechaAsignacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDMenuSemanal`) REFERENCES `MenuSemanal`(`ID`) ON DELETE CASCADE,
  FOREIGN KEY (`IDSeccion`) REFERENCES `SeccionesMenu`(`ID`) ON DELETE CASCADE,
  FOREIGN KEY (`IDUsuarioAsigno`) REFERENCES `Usuarios`(`ID`) ON DELETE SET NULL,
  INDEX `idx_menu` (`IDMenuSemanal`),
  INDEX `idx_seccion` (`IDSeccion`),
  INDEX `idx_usuario` (`IDUsuarioAsigno`),
  UNIQUE KEY `unique_menu_seccion` (`IDMenuSemanal`, `IDSeccion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `ProductosEspeciales` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `IDProducto` INT(11) NOT NULL,
  `FechaInicio` DATE NOT NULL,
  `FechaFin` DATE NOT NULL,
  `Descripcion` TEXT,
  `PrecioEspecial` DECIMAL(10,2),
  `Activo` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDProducto`) REFERENCES `Productos`(`ID`) ON DELETE CASCADE,
  INDEX `idx_fechas` (`FechaInicio`, `FechaFin`),
  INDEX `idx_activo` (`Activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `Avisos` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Titulo` VARCHAR(255) NOT NULL,
  `Contenido` TEXT NOT NULL,
  `Establecimiento` ENUM('Cafeteria','Cafecito','Ambos') NOT NULL,
  `TipoAviso` ENUM('General','Horario','NoLaboral','Oferta','Evento') NOT NULL,
  `Prioridad` ENUM('Normal','Importante') DEFAULT 'Normal',
  `FechaPublicacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `FechaInicio` DATE NOT NULL,
  `FechaFin` DATE NOT NULL,
  `IDUsuarioCreador` INT(11),
  `Activo` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDUsuarioCreador`) REFERENCES `Usuarios`(`ID`) ON DELETE SET NULL,
  INDEX `idx_establecimiento` (`Establecimiento`),
  INDEX `idx_fechas` (`FechaInicio`, `FechaFin`),
  INDEX `idx_activo` (`Activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `HistorialCambios` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `Tabla` VARCHAR(100) NOT NULL,
  `IDRegistro` INT(11) NOT NULL,
  `Accion` ENUM('INSERT','UPDATE','DELETE') NOT NULL,
  `DatosAnteriores` JSON,
  `DatosNuevos` JSON,
  `IDUsuario` INT(11),
  `Fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDUsuario`) REFERENCES `Usuarios`(`ID`) ON DELETE SET NULL,
  INDEX `idx_tabla_registro` (`Tabla`, `IDRegistro`),
  INDEX `idx_fecha` (`Fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `Sesiones` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `IDUsuario` INT(11) NOT NULL,
  `Token` VARCHAR(255) NOT NULL,
  `FechaCreacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `FechaExpiracion` TIMESTAMP NOT NULL,
  `IPAddress` VARCHAR(45),
  `UserAgent` TEXT,
  `Activa` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`ID`),
  FOREIGN KEY (`IDUsuario`) REFERENCES `Usuarios`(`ID`) ON DELETE CASCADE,
  INDEX `idx_token` (`Token`),
  INDEX `idx_usuario` (`IDUsuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `MenuSemanal` 
ADD COLUMN `IDUsuarioCreador` INT(11) NULL AFTER `Activo`;


ALTER TABLE `MenuSemanal`
ADD CONSTRAINT `fk_menu_usuario_creador` 
FOREIGN KEY (`IDUsuarioCreador`) 
REFERENCES `Usuarios`(`ID`) 
ON DELETE SET NULL;


ALTER TABLE `MenuSemanal`
ADD COLUMN `IDUsuarioModificador` INT(11) NULL AFTER `IDUsuarioCreador`,
ADD COLUMN `FechaModificacion` TIMESTAMP NULL DEFAULT NULL 
    ON UPDATE CURRENT_TIMESTAMP AFTER `FechaCreacion`;


ALTER TABLE `MenuSemanal`
ADD CONSTRAINT `fk_menu_usuario_modificador` 
FOREIGN KEY (`IDUsuarioModificador`) 
REFERENCES `Usuarios`(`ID`) 
ON DELETE SET NULL;


ALTER TABLE `ProductosEspeciales` 
MODIFY COLUMN `FechaInicio` DATETIME NOT NULL,
MODIFY COLUMN `FechaFin` DATETIME NOT NULL;


ALTER TABLE `Avisos` 
MODIFY COLUMN `FechaInicio` DATETIME NOT NULL,
MODIFY COLUMN `FechaFin` DATETIME NOT NULL;


ALTER TABLE `Avisos` 
MODIFY COLUMN `FechaPublicacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;