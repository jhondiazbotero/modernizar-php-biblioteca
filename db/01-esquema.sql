SET NAMES utf8mb4;

CREATE TABLE bibliotecarios (
    id INT NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(40) NOT NULL,
    clave VARCHAR(255) NOT NULL, -- password_hash, o md5 (32 hex) en cuentas viejas
    nombre VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE socios (
    id INT NOT NULL AUTO_INCREMENT,
    documento VARCHAR(20) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    fecha_alta DATE NOT NULL,
    activo TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY documento (documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE libros (
    id INT NOT NULL AUTO_INCREMENT,
    isbn VARCHAR(20) DEFAULT NULL,
    titulo VARCHAR(200) NOT NULL,
    autor VARCHAR(120) NOT NULL,
    anio INT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ejemplares (
    id INT NOT NULL AUTO_INCREMENT,
    libro_id INT NOT NULL,
    codigo VARCHAR(20) NOT NULL,
    estado ENUM('disponible','prestado','reservado','baja') NOT NULL DEFAULT 'disponible',
    PRIMARY KEY (id),
    UNIQUE KEY codigo (codigo),
    KEY libro_id (libro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prestamos (
    id INT NOT NULL AUTO_INCREMENT,
    ejemplar_id INT NOT NULL,
    socio_id INT NOT NULL,
    fecha_prestamo DATE NOT NULL,
    fecha_vence DATE NOT NULL,
    fecha_devolucion DATE DEFAULT NULL,
    multa DECIMAL(10,2) NOT NULL DEFAULT 0,
    multa_pagada TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ejemplar_id (ejemplar_id),
    KEY socio_id (socio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservas (
    id INT NOT NULL AUTO_INCREMENT,
    libro_id INT NOT NULL,
    socio_id INT NOT NULL,
    fecha_reserva DATETIME NOT NULL,
    estado ENUM('pendiente','asignada','atendida','cancelada') NOT NULL DEFAULT 'pendiente',
    ejemplar_id INT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY libro_id (libro_id),
    KEY socio_id (socio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
