-- ============================================================
--  SISTEMA DE GESTIÓN DE LICENCIAS
--  Compatible con MySQL 8+ / phpMyAdmin
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

CREATE DATABASE IF NOT EXISTS db_licencias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_licencias;

-- ============================================================
--  1. ROLES Y PERMISOS
-- ============================================================

CREATE TABLE roles (
    id          CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    nombre      VARCHAR(50)  NOT NULL UNIQUE,
    descripcion TEXT,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permisos (
    id      CHAR(36)    PRIMARY KEY DEFAULT (UUID()),
    rol_id  CHAR(36)    NOT NULL,
    modulo  VARCHAR(100) NOT NULL,
    accion  VARCHAR(50)  NOT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  2. USUARIOS
-- ============================================================

CREATE TABLE usuarios (
    id              CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    nombre          VARCHAR(150) NOT NULL,
    correo          VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    rol_id          CHAR(36)     NOT NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_acceso   TIMESTAMP    NULL,
    token_reset     VARCHAR(255) NULL,
    token_reset_exp TIMESTAMP    NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE historial_accesos (
    id          CHAR(36)    PRIMARY KEY DEFAULT (UUID()),
    usuario_id  CHAR(36)    NOT NULL,
    ip          VARCHAR(45),
    dispositivo VARCHAR(255),
    exitoso     TINYINT(1)  NOT NULL DEFAULT 1,
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  3. CLIENTES
-- ============================================================

CREATE TABLE clientes (
    id            CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    tipo          ENUM('empresa','persona') NOT NULL DEFAULT 'empresa',
    ruc_dni       VARCHAR(20)  NOT NULL UNIQUE,
    razon_social  VARCHAR(255) NOT NULL,
    correo        VARCHAR(255) NOT NULL,
    telefono      VARCHAR(30)  NULL,
    direccion     TEXT         NULL,
    representante VARCHAR(150) NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuarios_cliente (
    id          CHAR(36)    PRIMARY KEY DEFAULT (UUID()),
    usuario_id  CHAR(36)    NOT NULL,
    cliente_id  CHAR(36)    NOT NULL,
    cargo       VARCHAR(100) NULL,
    UNIQUE KEY uq_usuario_cliente (usuario_id, cliente_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  4. PRODUCTOS Y MÓDULOS
-- ============================================================

CREATE TABLE productos (
    id          CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT         NULL,
    version     VARCHAR(30)  NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE modulos_producto (
    id          CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    producto_id CHAR(36)     NOT NULL,
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT         NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  5. LICENCIAS
-- ============================================================

CREATE TABLE licencias (
    id                CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    cliente_id        CHAR(36)     NOT NULL,
    producto_id       CHAR(36)     NOT NULL,
    clave_unica       VARCHAR(255) NOT NULL UNIQUE,
    estado            ENUM('activa','suspendida','cancelada','vencida','pendiente') NOT NULL DEFAULT 'pendiente',
    fecha_inicio      DATE         NOT NULL,
    fecha_expiracion  DATE         NOT NULL,
    max_usuarios      INT          NULL,
    max_dispositivos  INT          NULL,
    max_instalaciones INT          NULL,
    creado_por        CHAR(36)     NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id)  REFERENCES clientes(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (creado_por)  REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE modulos_licencia (
    id          CHAR(36)   PRIMARY KEY DEFAULT (UUID()),
    licencia_id CHAR(36)   NOT NULL,
    modulo_id   CHAR(36)   NOT NULL,
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_lic_modulo (licencia_id, modulo_id),
    FOREIGN KEY (licencia_id) REFERENCES licencias(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id)   REFERENCES modulos_producto(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE historial_licencias (
    id               CHAR(36)    PRIMARY KEY DEFAULT (UUID()),
    licencia_id      CHAR(36)    NOT NULL,
    usuario_id       CHAR(36)    NULL,
    accion           VARCHAR(50) NOT NULL,
    estado_anterior  VARCHAR(50) NULL,
    estado_nuevo     VARCHAR(50) NULL,
    observacion      TEXT        NULL,
    created_at       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (licencia_id) REFERENCES licencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  6. VALIDACIÓN DE LICENCIAS (API)
-- ============================================================

CREATE TABLE validaciones_api (
    id             CHAR(36)    PRIMARY KEY DEFAULT (UUID()),
    licencia_id    CHAR(36)    NULL,
    ip_origen      VARCHAR(45) NULL,
    dominio        VARCHAR(255) NULL,
    dispositivo_id VARCHAR(255) NULL,
    instalacion_id VARCHAR(255) NULL,
    resultado      ENUM('activa','vencida','suspendida','invalida','no_autorizada') NOT NULL,
    latencia_ms    INT         NULL,
    created_at     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (licencia_id) REFERENCES licencias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  7. NOTIFICACIONES
-- ============================================================

CREATE TABLE plantillas_correo (
    id      CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    nombre  VARCHAR(100) NOT NULL UNIQUE,
    asunto  VARCHAR(255) NOT NULL,
    cuerpo  TEXT         NOT NULL,
    activo  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE notificaciones (
    id           CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    licencia_id  CHAR(36)     NULL,
    plantilla_id CHAR(36)     NULL,
    tipo         VARCHAR(50)  NOT NULL,
    destinatario VARCHAR(255) NOT NULL,
    estado       ENUM('pendiente','enviada','fallida') NOT NULL DEFAULT 'pendiente',
    contenido    TEXT         NULL,
    enviado_at   TIMESTAMP    NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (licencia_id)  REFERENCES licencias(id)        ON DELETE SET NULL,
    FOREIGN KEY (plantilla_id) REFERENCES plantillas_correo(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  8. AUDITORÍA
-- ============================================================

CREATE TABLE auditoria (
    id               CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    usuario_id       CHAR(36)     NULL,
    entidad          VARCHAR(100) NOT NULL,
    entidad_id       CHAR(36)     NULL,
    accion           VARCHAR(50)  NOT NULL,
    datos_anteriores JSON         NULL,
    datos_nuevos     JSON         NULL,
    ip               VARCHAR(45)  NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  ÍNDICES
-- ============================================================

CREATE INDEX idx_licencias_cliente     ON licencias(cliente_id);
CREATE INDEX idx_licencias_estado      ON licencias(estado);
CREATE INDEX idx_licencias_expiracion  ON licencias(fecha_expiracion);
CREATE INDEX idx_validaciones_licencia ON validaciones_api(licencia_id);
CREATE INDEX idx_validaciones_fecha    ON validaciones_api(created_at);
CREATE INDEX idx_auditoria_entidad     ON auditoria(entidad, entidad_id);
CREATE INDEX idx_historial_licencia    ON historial_licencias(licencia_id);
CREATE INDEX idx_notif_estado          ON notificaciones(estado);

-- ============================================================
--  DATOS INICIALES
-- ============================================================

INSERT INTO roles (id, nombre, descripcion) VALUES
  (UUID(), 'administrador', 'Acceso total al sistema'),
  (UUID(), 'vendedor',      'Gestión de clientes y licencias'),
  (UUID(), 'soporte',       'Consulta y soporte de licencias'),
  (UUID(), 'auditor',       'Solo lectura y reportes'),
  (UUID(), 'cliente',       'Acceso al portal de cliente');

INSERT INTO plantillas_correo (id, nombre, asunto, cuerpo) VALUES
  (UUID(), 'licencia_proxima_vencer',
   'Tu licencia de {{producto}} vence pronto',
   'Estimado {{cliente}},\n\nTu licencia {{clave}} vence el {{fecha_expiracion}}.\nPor favor renueva a tiempo.'),
  (UUID(), 'licencia_vencida',
   'Tu licencia de {{producto}} ha vencido',
   'Estimado {{cliente}},\n\nTu licencia {{clave}} venció el {{fecha_expiracion}}.\nContacta a soporte para renovar.'),
  (UUID(), 'licencia_activada',
   'Tu licencia de {{producto}} ha sido activada',
   'Estimado {{cliente}},\n\nTu licencia {{clave}} está activa desde {{fecha_inicio}} hasta {{fecha_expiracion}}.');

-- ============================================================
--  VISTA: LICENCIAS CON DÍAS RESTANTES
-- ============================================================

CREATE VIEW v_licencias_resumen AS
SELECT
    l.id,
    l.clave_unica,
    l.estado,
    l.fecha_inicio,
    l.fecha_expiracion,
    DATEDIFF(l.fecha_expiracion, CURDATE()) AS dias_restantes,
    c.razon_social   AS cliente,
    c.correo         AS correo_cliente,
    p.nombre         AS producto,
    p.version        AS version_producto,
    l.max_usuarios,
    l.max_dispositivos,
    l.max_instalaciones
FROM licencias l
JOIN clientes  c ON l.cliente_id  = c.id
JOIN productos p ON l.producto_id = p.id;

SET FOREIGN_KEY_CHECKS = 1;
