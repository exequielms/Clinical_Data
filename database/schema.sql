-- =====================================================
-- Clinical_Data - Hospital de Clínicas
-- Base de datos (importar desde phpMyAdmin)
-- =====================================================

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS clinical_data CHARACTER SET utf8mb4;
USE clinical_data;

-- ---------- Usuarios del sistema ----------
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    rol ENUM('Administrador', 'Recepcionista', 'Coordinador') NOT NULL
);

-- ---------- Módulo 1: documentos y encuestas ----------
CREATE TABLE documentos (
    id_documento INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    categoria VARCHAR(100) NOT NULL,
    archivo VARCHAR(255) NOT NULL,
    fecha_alta DATETIME NOT NULL,
    id_usuario INT NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE encuestas (
    id_encuesta INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    estado ENUM('Activa', 'Inactiva') NOT NULL DEFAULT 'Activa',
    fecha_creacion DATETIME NOT NULL
);

CREATE TABLE preguntas (
    id_pregunta INT AUTO_INCREMENT PRIMARY KEY,
    id_encuesta INT NOT NULL,
    texto VARCHAR(300) NOT NULL,
    tipo ENUM('cerrada', 'abierta') NOT NULL,
    FOREIGN KEY (id_encuesta) REFERENCES encuestas(id_encuesta) ON DELETE CASCADE
);

CREATE TABLE opciones (
    id_opcion INT AUTO_INCREMENT PRIMARY KEY,
    id_pregunta INT NOT NULL,
    texto VARCHAR(150) NOT NULL,
    FOREIGN KEY (id_pregunta) REFERENCES preguntas(id_pregunta) ON DELETE CASCADE
);

-- las respuestas son anónimas a propósito: no guardan datos del paciente
CREATE TABLE respuestas (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_encuesta INT NOT NULL,
    fecha_hora DATETIME NOT NULL,
    FOREIGN KEY (id_encuesta) REFERENCES encuestas(id_encuesta) ON DELETE CASCADE
);

CREATE TABLE respuestas_detalle (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_respuesta INT NOT NULL,
    id_pregunta INT NOT NULL,
    id_opcion INT NULL,
    texto_libre VARCHAR(500) NULL,
    FOREIGN KEY (id_respuesta) REFERENCES respuestas(id_respuesta) ON DELETE CASCADE,
    FOREIGN KEY (id_pregunta) REFERENCES preguntas(id_pregunta) ON DELETE CASCADE,
    FOREIGN KEY (id_opcion) REFERENCES opciones(id_opcion) ON DELETE CASCADE
);

-- ---------- Módulo 2: traslados de ambulancias ----------
CREATE TABLE ambulancias (
    id_ambulancia INT AUTO_INCREMENT PRIMARY KEY,
    patente VARCHAR(20) NOT NULL UNIQUE,
    modelo VARCHAR(100) NOT NULL,
    estado ENUM('Disponible', 'En servicio', 'Mantenimiento') NOT NULL DEFAULT 'Disponible'
);

CREATE TABLE personal (
    id_personal INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    documento VARCHAR(20) NOT NULL UNIQUE,
    cargo ENUM('Conductor', 'Enfermera', 'Auxiliar') NOT NULL,
    telefono VARCHAR(20)
);

CREATE TABLE rutas (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    origen VARCHAR(150) NOT NULL,
    destino VARCHAR(150) NOT NULL,
    distancia_km DECIMAL(6,1) NOT NULL,
    tiempo_min INT NOT NULL
);

-- Un traslado sale del hospital, llega al destino y vuelve al hospital.
-- Estados: Pendiente -> En viaje -> En destino -> Regresando -> Finalizado
-- (o Cancelado). Las horas reales las pone el sistema al cambiar de estado.
CREATE TABLE traslados (
    id_traslado INT AUTO_INCREMENT PRIMARY KEY,
    id_ambulancia INT NOT NULL,
    id_conductor INT NOT NULL,
    id_copiloto INT NULL,
    id_ruta INT NULL,
    tipo_elemento VARCHAR(30) NOT NULL,
    elemento VARCHAR(300) NOT NULL,
    origen VARCHAR(200) NOT NULL,
    destino VARCHAR(200) NOT NULL,
    estado ENUM('Pendiente', 'En viaje', 'En destino', 'Regresando', 'Finalizado', 'Cancelado') NOT NULL DEFAULT 'Pendiente',
    hora_salida_prevista DATETIME NOT NULL,
    hora_llegada_estimada DATETIME NULL,
    hora_salida_real DATETIME NULL,
    hora_llegada_real DATETIME NULL,
    hora_regreso DATETIME NULL,
    observaciones TEXT,
    fecha_creacion DATETIME NOT NULL,
    id_usuario INT NOT NULL,
    FOREIGN KEY (id_ambulancia) REFERENCES ambulancias(id_ambulancia),
    FOREIGN KEY (id_conductor) REFERENCES personal(id_personal),
    FOREIGN KEY (id_copiloto) REFERENCES personal(id_personal),
    FOREIGN KEY (id_ruta) REFERENCES rutas(id_ruta) ON DELETE SET NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- historial: una fila por cada cambio de estado
CREATE TABLE seguimiento (
    id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_traslado INT NOT NULL,
    estado VARCHAR(20) NOT NULL,
    fecha_hora DATETIME NOT NULL,
    comentario VARCHAR(300),
    id_usuario INT NOT NULL,
    FOREIGN KEY (id_traslado) REFERENCES traslados(id_traslado) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- ---------- Datos de prueba ----------
-- mrodriguez = direccion2024 / vfernandez = recepcion2024 / jalvarez = coordina2024
INSERT INTO usuarios (nombre, apellido, usuario, clave, rol) VALUES
('Mariana', 'Rodríguez', 'mrodriguez', '$2b$12$G4RmUNwugVCs08lcaqsJKewmT7Zm6E5kF2HunS0hMXgI/i.61EqkO', 'Administrador'),
('Valentina', 'Fernández', 'vfernandez', '$2b$12$0Yhy6UfIYJ6SAOHkSFHzA.5MDIBSpwW0gYSehI7WAA4iZqz50wqvm', 'Recepcionista'),
('Javier', 'Álvarez', 'jalvarez', '$2b$12$fdzmwea.SRCZLlaiTMyMROuCgI5Awf6rWye5Kho/KmN2IWnZ7ezeO', 'Coordinador');

INSERT INTO ambulancias (patente, modelo) VALUES
('SBA 1234', 'Mercedes Sprinter'),
('SBB 5678', 'Fiat Ducato');

INSERT INTO personal (nombre, apellido, documento, cargo, telefono) VALUES
('Marcos', 'Giménez', '12345678', 'Conductor', '099111222'),
('Lucía', 'Pereira', '34567890', 'Conductor', '099333444'),
('Rosa', 'Ibarra', '23456789', 'Enfermera', '099222333'),
('Diego', 'Suárez', '45678901', 'Auxiliar', '099444555');

-- rutas de ejemplo (distancias y tiempos aproximados)
INSERT INTO rutas (nombre, origen, destino, distancia_km, tiempo_min) VALUES
('Hospital - Aeropuerto de Carrasco', 'Hospital de Clínicas', 'Aeropuerto de Carrasco', 18.5, 30),
('Hospital - Terminal Tres Cruces', 'Hospital de Clínicas', 'Terminal Tres Cruces', 6.2, 15),
('Hospital - Maldonado', 'Hospital de Clínicas', 'Hospital de Maldonado', 135, 120),
('Hospital - Salto', 'Hospital de Clínicas', 'Hospital de Salto', 500, 390);

-- encuesta de ejemplo para probar la parte pública
INSERT INTO encuestas (titulo, descripcion, estado, fecha_creacion) VALUES
('Encuesta de satisfacción del usuario', 'Su opinión nos ayuda a mejorar la atención del hospital.', 'Activa', NOW());

INSERT INTO preguntas (id_encuesta, texto, tipo) VALUES
(1, '¿Cómo calificaría la atención recibida?', 'cerrada'),
(1, '¿La información que recibió fue clara?', 'cerrada'),
(1, '¿Quiere dejarnos algún comentario?', 'abierta');

INSERT INTO opciones (id_pregunta, texto) VALUES
(1, 'Muy buena'), (1, 'Buena'), (1, 'Regular'), (1, 'Mala'),
(2, 'Sí, muy clara'), (2, 'Más o menos'), (2, 'No fue clara');
