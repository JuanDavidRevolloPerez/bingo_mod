-- ============================================================
-- BINGO ONLINE - Esquema de Base de Datos
-- Motor: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 (soporte completo Unicode y emojis)
-- ============================================================

CREATE DATABASE IF NOT EXISTS bingo_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bingo_db;

-- ------------------------------------------------------------
-- Tabla: usuarios
-- Almacena todos los jugadores y administradores del sistema.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nickname            VARCHAR(30)  NOT NULL,
    email               VARCHAR(255) NOT NULL UNIQUE,
    password_hash       VARCHAR(255) NOT NULL,
    nivel_verificacion  TINYINT      DEFAULT 0,
    tipo_documento      ENUM('CC', 'CE', 'PASAPORTE') NULL,
    numero_documento    VARCHAR(50)  UNIQUE NULL,
    rol                 ENUM('JUGADOR', 'ADMIN') DEFAULT 'JUGADOR',
    estado              ENUM('ACTIVO', 'INACTIVO', 'BLOQUEADO') DEFAULT 'ACTIVO',
    creado_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    actualizado_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabla: verificaciones_kyc
-- Control de proceso Know Your Customer por usuario.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS verificaciones_kyc (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id              INT          NOT NULL,
    token_otp               VARCHAR(6)   NULL,
    ruta_documento_id       VARCHAR(255) NULL,
    ruta_certificado_banco  VARCHAR(255) NULL,
    estado                  ENUM('PENDIENTE', 'APROBADO', 'RECHAZADO') DEFAULT 'PENDIENTE',
    comentarios_admin       TEXT         NULL,
    creado_at               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_verif_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabla: billeteras
-- Saldo disponible y retenido (escrow) por usuario.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS billeteras (
    id                  INT AUTO_INCREMENT  PRIMARY KEY,
    usuario_id          INT          NOT NULL UNIQUE,
    saldo_disponible    DECIMAL(12,2) DEFAULT 0.00,
    saldo_retenido      DECIMAL(12,2) DEFAULT 0.00,
    actualizado_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_billetera_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabla: transacciones
-- Registro inmutable de todos los movimientos financieros.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transacciones (
    id                      BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id              INT          NOT NULL,
    tipo                    ENUM('RECARGA', 'RETIRO', 'BUY_IN', 'PREMIO', 'REEMBOLSO', 'AJUSTE') NOT NULL,
    monto                   DECIMAL(12,2) NOT NULL,
    saldo_resultante        DECIMAL(12,2) NOT NULL,
    referencia_pasarela     VARCHAR(255) UNIQUE NULL,
    descripcion             VARCHAR(255) NULL,
    creado_at               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transaccion_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabla: salas_payplay
-- Salas de juego con modalidad Pay-to-Play (buy-in real).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS salas_payplay (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    codigo_sala         VARCHAR(10)   NOT NULL UNIQUE,
    host_id             INT           NOT NULL,
    buy_in              DECIMAL(10,2) NOT NULL,
    comision_pct        DECIMAL(5,2)  DEFAULT 0.10,
    pozo_total          DECIMAL(12,2) DEFAULT 0.00,
    estado_financiero   ENUM('ABIERTA', 'COBRADA', 'REPARTIDA', 'REEMBOLSADA', 'CANCELADA') DEFAULT 'ABIERTA',
    creado_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    finalizado_at       TIMESTAMP     NULL,
    CONSTRAINT fk_sala_host
        FOREIGN KEY (host_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabla: participantes_sala
-- Jugadores inscritos en cada sala Payplay y su estado de pago.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS participantes_sala (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    sala_id         BIGINT        NOT NULL,
    usuario_id      INT           NOT NULL,
    estado_pago     ENUM('RETENIDO', 'COBRADO', 'REEMBOLSADO') DEFAULT 'RETENIDO',
    es_ganador      BOOLEAN       DEFAULT FALSE,
    premio_obtenido DECIMAL(12,2) DEFAULT 0.00,
    unido_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sala_usuario (sala_id, usuario_id),
    CONSTRAINT fk_participante_sala
        FOREIGN KEY (sala_id) REFERENCES salas_payplay(id) ON DELETE RESTRICT,
    CONSTRAINT fk_participante_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabla: solicitudes_retiro
-- Gestion manual de retiros a cuentas bancarias.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS solicitudes_retiro (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id              INT           NOT NULL,
    monto                   DECIMAL(12,2) NOT NULL,
    datos_bancarios_json    JSON          NOT NULL,
    estado                  ENUM('PENDIENTE', 'PROCESANDO', 'COMPLETADO', 'RECHAZADO') DEFAULT 'PENDIENTE',
    admin_procesador_id     INT           NULL,
    comprobante_url         VARCHAR(255)  NULL,
    creado_at               TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    actualizado_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_retiro_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_retiro_admin
        FOREIGN KEY (admin_procesador_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Indices adicionales para optimizar consultas frecuentes
-- ------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_transacciones_usuario ON transacciones(usuario_id);
CREATE INDEX IF NOT EXISTS idx_transacciones_fecha   ON transacciones(creado_at);
CREATE INDEX IF NOT EXISTS idx_salas_estado          ON salas_payplay(estado_financiero);
