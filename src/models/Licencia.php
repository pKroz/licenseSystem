<?php
// models/Licencia.php

require_once __DIR__ . '/../config/database.php';

class Licencia {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    // ---------------------------------------------------------
    //  Obtener todas las licencias (con datos de cliente y producto)
    // ---------------------------------------------------------
    public function obtenerTodas(array $filtros = []): array {
        $sql = "SELECT l.*, c.razon_social AS cliente, p.nombre AS producto,
                       DATEDIFF(l.fecha_expiracion, CURDATE()) AS dias_restantes
                FROM licencias l
                JOIN clientes  c ON l.cliente_id  = c.id
                JOIN productos p ON l.producto_id = p.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND l.estado = :estado";
            $params['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND l.cliente_id = :cliente_id";
            $params['cliente_id'] = $filtros['cliente_id'];
        }
        if (!empty($filtros['producto_id'])) {
            $sql .= " AND l.producto_id = :producto_id";
            $params['producto_id'] = $filtros['producto_id'];
        }

        $sql .= " ORDER BY l.fecha_expiracion ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ---------------------------------------------------------
    //  Obtener una licencia por ID
    // ---------------------------------------------------------
    public function obtenerPorId(string $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT l.*, c.razon_social AS cliente, p.nombre AS producto
             FROM licencias l
             JOIN clientes  c ON l.cliente_id  = c.id
             JOIN productos p ON l.producto_id = p.id
             WHERE l.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    // ---------------------------------------------------------
    //  Validar licencia por clave (para la API externa)
    // ---------------------------------------------------------
    public function validarClave(string $clave, array $contexto = []): array {
        $inicio = microtime(true);

        $stmt = $this->db->prepare(
            "SELECT l.*, p.nombre AS producto
             FROM licencias l
             JOIN productos p ON l.producto_id = p.id
             WHERE l.clave_unica = :clave LIMIT 1"
        );
        $stmt->execute(['clave' => $clave]);
        $licencia = $stmt->fetch();

        if (!$licencia) {
            $resultado = 'invalida';
        } elseif ($licencia['estado'] === 'suspendida') {
            $resultado = 'suspendida';
        } elseif ($licencia['estado'] === 'cancelada' || $licencia['fecha_expiracion'] < date('Y-m-d')) {
            $resultado = 'vencida';
        } elseif ($licencia['estado'] === 'activa') {
            $resultado = 'activa';
        } else {
            $resultado = 'invalida';
        }

        $latencia = (int)((microtime(true) - $inicio) * 1000);

        // Registrar intento de validación
        $this->registrarValidacion(
            $licencia['id'] ?? null,
            $contexto,
            $resultado,
            $latencia
        );

        return [
            'resultado'  => $resultado,
            'licencia'   => $resultado === 'activa' ? $licencia : null,
            'latencia_ms' => $latencia,
        ];
    }

    // ---------------------------------------------------------
    //  Crear licencia
    // ---------------------------------------------------------
    public function crear(array $datos, string $creado_por): string {
        $id    = $this->generarUUID();
        $clave = $this->generarClave();

        $stmt = $this->db->prepare(
            "INSERT INTO licencias
             (id, cliente_id, producto_id, clave_unica, estado, fecha_inicio,
              fecha_expiracion, max_usuarios, max_dispositivos, max_instalaciones, creado_por)
             VALUES
             (:id, :cliente_id, :producto_id, :clave_unica, 'pendiente', :fecha_inicio,
              :fecha_expiracion, :max_usuarios, :max_dispositivos, :max_instalaciones, :creado_por)"
        );
        $stmt->execute([
            'id'                => $id,
            'cliente_id'        => $datos['cliente_id'],
            'producto_id'       => $datos['producto_id'],
            'clave_unica'       => $clave,
            'fecha_inicio'      => $datos['fecha_inicio'],
            'fecha_expiracion'  => $datos['fecha_expiracion'],
            'max_usuarios'      => $datos['max_usuarios']      ?? null,
            'max_dispositivos'  => $datos['max_dispositivos']  ?? null,
            'max_instalaciones' => $datos['max_instalaciones'] ?? null,
            'creado_por'        => $creado_por,
        ]);

        $this->registrarHistorial($id, $creado_por, 'crear', null, 'pendiente');
        return $id;
    }

    // ---------------------------------------------------------
    //  Cambiar estado (activar, suspender, cancelar, renovar)
    // ---------------------------------------------------------
    public function cambiarEstado(string $id, string $nuevoEstado, string $usuario_id, string $observacion = ''): bool {
        $estados_validos = ['activa', 'suspendida', 'cancelada', 'vencida', 'pendiente'];
        if (!in_array($nuevoEstado, $estados_validos)) return false;

        $actual = $this->obtenerPorId($id);
        if (!$actual) return false;

        $stmt = $this->db->prepare(
            "UPDATE licencias SET estado = :estado WHERE id = :id"
        );
        $stmt->execute(['estado' => $nuevoEstado, 'id' => $id]);

        $this->registrarHistorial($id, $usuario_id, $nuevoEstado, $actual['estado'], $nuevoEstado, $observacion);
        return true;
    }

    // ---------------------------------------------------------
    //  Licencias próximas a vencer (para notificaciones)
    // ---------------------------------------------------------
    public function proximasAVencer(int $dias = 15): array {
        $stmt = $this->db->prepare(
            "SELECT l.*, c.razon_social AS cliente, c.correo AS correo_cliente,
                    p.nombre AS producto, DATEDIFF(l.fecha_expiracion, CURDATE()) AS dias_restantes
             FROM licencias l
             JOIN clientes  c ON l.cliente_id  = c.id
             JOIN productos p ON l.producto_id = p.id
             WHERE l.estado = 'activa'
               AND l.fecha_expiracion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
             ORDER BY l.fecha_expiracion ASC"
        );
        $stmt->execute(['dias' => $dias]);
        return $stmt->fetchAll();
    }

    // ---------------------------------------------------------
    //  Métodos privados de apoyo
    // ---------------------------------------------------------
    private function generarUUID(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function generarClave(): string {
        $segmentos = [];
        for ($i = 0; $i < 4; $i++) {
            $segmentos[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return implode('-', $segmentos);
    }

    private function registrarHistorial(string $licencia_id, ?string $usuario_id,
                                        string $accion, ?string $anterior, ?string $nuevo,
                                        string $observacion = ''): void {
        $stmt = $this->db->prepare(
            "INSERT INTO historial_licencias
             (id, licencia_id, usuario_id, accion, estado_anterior, estado_nuevo, observacion)
             VALUES (UUID(), :licencia_id, :usuario_id, :accion, :anterior, :nuevo, :obs)"
        );
        $stmt->execute([
            'licencia_id' => $licencia_id,
            'usuario_id'  => $usuario_id,
            'accion'      => $accion,
            'anterior'    => $anterior,
            'nuevo'       => $nuevo,
            'obs'         => $observacion,
        ]);
    }

    private function registrarValidacion(?string $licencia_id, array $ctx,
                                          string $resultado, int $latencia): void {
        $stmt = $this->db->prepare(
            "INSERT INTO validaciones_api
             (id, licencia_id, ip_origen, dominio, dispositivo_id, instalacion_id, resultado, latencia_ms)
             VALUES (UUID(), :lic, :ip, :dom, :dev, :inst, :res, :lat)"
        );
        $stmt->execute([
            'lic'  => $licencia_id,
            'ip'   => $ctx['ip']             ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'dom'  => $ctx['dominio']        ?? null,
            'dev'  => $ctx['dispositivo_id'] ?? null,
            'inst' => $ctx['instalacion_id'] ?? null,
            'res'  => $resultado,
            'lat'  => $latencia,
        ]);
    }
}
