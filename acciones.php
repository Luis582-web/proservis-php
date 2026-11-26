<?php
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$usuarioId = (int)$_SESSION['usuario_id'];
$rol       = $_SESSION['rol'];
$accion    = $_POST['accion'] ?? '';

try {
    // 1. COORDINADOR: Registrar nueva orden en mostrador (2.3) + Alta automática de cliente
    if ($accion === 'crear_orden' && $rol === 'coordinador') {
        $nombre   = trim($_POST['nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo   = trim($_POST['correo'] ?? '');
        $tipo     = $_POST['tipo_equipo'] ?? 'Otro';
        $modelo   = trim($_POST['marca_modelo'] ?? '');
        $falla    = trim($_POST['falla_reportada'] ?? '');

        if (!$nombre || !$telefono || !$correo || !$modelo || !$falla) {
            throw new Exception('Todos los campos obligatorios deben completarse.');
        }

        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? LIMIT 1");
        $stmt->execute([$correo]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            $clienteId = (int)$cliente['id'];
            $notaCliente = "Cliente existente vinculado.";
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO usuarios (nombre, telefono, correo, password_hash, rol) VALUES (?, ?, ?, SHA2('123456', 256), 'cliente')");
            $stmtIns->execute([$nombre, $telefono, $correo]);
            $clienteId = (int)$pdo->lastInsertId();
            $notaCliente = "Cuenta Cliente generada automáticamente (clave inicial: 123456).";
        }

        $stmtOrd = $pdo->prepare("INSERT INTO ordenes (folio, cliente_id, tipo_equipo, marca_modelo, falla_reportada, estado) VALUES ('TEMP', ?, ?, ?, ?, 'Recibida')");
        $stmtOrd->execute([$clienteId, $tipo, $modelo, $falla]);
        $ordenId = (int)$pdo->lastInsertId();

        $folio = sprintf('PS-%s-%04d', date('ym'), $ordenId);
        $pdo->prepare("UPDATE ordenes SET folio = ? WHERE id = ?")->execute([$folio, $ordenId]);

        registrarBitacora($pdo, $ordenId, $usuarioId, 'Recepción en mostrador', "Equipo recibido en mostrador. $notaCliente");

        $_SESSION['flash'] = "Orden $folio registrada correctamente. $notaCliente";
        header("Location: panel.php?vista=detalle&id=$ordenId");
        exit;
    }

    // 2. COORDINADOR: Actualizar orden, entregar o cancelar (3.0)
    if ($accion === 'actualizar_orden' && $rol === 'coordinador') {
        $ordenId    = (int)($_POST['orden_id'] ?? 0);
        $subaccion  = $_POST['subaccion'] ?? 'guardar';
        $tecnicoId  = !empty($_POST['tecnico_id']) ? (int)$_POST['tecnico_id'] : null;
        $estado     = $_POST['estado'] ?? 'Recibida';
        $comentario = trim($_POST['comentario'] ?? '');
        $motivo     = trim($_POST['motivo_cancelacion'] ?? '');

        if ($subaccion === 'cancelar') {
            if ($motivo === '') {
                throw new Exception('RF-08 / RNF-02: Debe indicar un motivo obligatorio para cancelar la orden.');
            }
            $stmt = $pdo->prepare("UPDATE ordenes SET estado = 'Cancelada', motivo_cancelacion = ? WHERE id = ?");
            $stmt->execute([$motivo, $ordenId]);
            registrarBitacora($pdo, $ordenId, $usuarioId, 'Orden cancelada', "Motivo: $motivo");
            $_SESSION['flash'] = 'La orden fue cancelada y registrada en bitácora.';
        } elseif ($subaccion === 'entregar') {
            $stmt = $pdo->prepare("UPDATE ordenes SET estado = 'Entregada', fecha_entrega = NOW() WHERE id = ?");
            $stmt->execute([$ordenId]);
            registrarBitacora($pdo, $ordenId, $usuarioId, 'Entrega en mostrador', 'Equipo retirado físicamente por el cliente en local.');
            $_SESSION['flash'] = 'Orden marcada como Entregada en mostrador.';
        } else {
            if ($tecnicoId && $estado === 'Recibida') {
                $estado = 'Asignada';
            }
            $stmt = $pdo->prepare("UPDATE ordenes SET tecnico_id = ?, estado = ? WHERE id = ?");
            $stmt->execute([$tecnicoId, $estado, $ordenId]);
            $detalle = "Estado actualizado a '$estado'." . ($comentario ? " Nota: $comentario" : '');
            registrarBitacora($pdo, $ordenId, $usuarioId, 'Actualización de coordinación', $detalle);
            $_SESSION['flash'] = 'Cambios de coordinación guardados.';
        }

        header("Location: panel.php?vista=detalle&id=$ordenId");
        exit;
    }

    // 3. TÉCNICO: Guardar intervención técnica (2.4 - RF-06)
    if ($accion === 'guardar_intervencion' && $rol === 'tecnico') {
        $ordenId     = (int)($_POST['orden_id'] ?? 0);
        $estado      = $_POST['estado'] ?? 'En reparación';
        $diagnostico = trim($_POST['diagnostico'] ?? '');
        $repuesto    = trim($_POST['repuesto'] ?? 'Ninguno') ?: 'Ninguno';
        $tiempo      = max(5, (int)($_POST['tiempo_minutos'] ?? 30));

        if ($diagnostico === '') {
            throw new Exception('Debe describir el diagnóstico y trabajo realizado.');
        }

        $stmt = $pdo->prepare("INSERT INTO intervenciones (orden_id, tecnico_id, diagnostico, repuesto, tiempo_minutos) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ordenId, $usuarioId, $diagnostico, $repuesto, $tiempo]);

        $pdo->prepare("UPDATE ordenes SET estado = ? WHERE id = ?")->execute([$estado, $ordenId]);
        registrarBitacora($pdo, $ordenId, $usuarioId, "Intervención técnica ($estado)", "$diagnostico (Repuesto: $repuesto | Tiempo: {$tiempo} min)");

        $_SESSION['flash'] = 'Intervención técnica registrada correctamente.';
        header("Location: panel.php?vista=cola_tecnico&orden_id=$ordenId");
        exit;
    }

    // 4. ADMINISTRADOR: Alta o activación/desactivación de personal (2.5)
    if ($accion === 'crear_usuario' && $rol === 'admin') {
        $nombre   = trim($_POST['nombre'] ?? '');
        $correo   = trim($_POST['correo'] ?? '');
        $rolNuevo = $_POST['rol_nuevo'] ?? 'tecnico';

        if (!in_array($rolNuevo, ['coordinador', 'tecnico', 'admin'], true)) {
            throw new Exception('El administrador solo gestiona cuentas internas del personal.');
        }

        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, correo, password_hash, rol, activo) VALUES (?, ?, SHA2('123456', 256), ?, 1)");
        $stmt->execute([$nombre, $correo, $rolNuevo]);

        $_SESSION['flash'] = "Usuario interno $nombre creado con clave inicial 123456.";
        header('Location: panel.php?vista=usuarios');
        exit;
    }

    if ($accion === 'toggle_usuario' && $rol === 'admin') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId === $usuarioId) {
            throw new Exception('No puede desactivar su propia cuenta de administrador.');
        }
        $pdo->prepare("UPDATE usuarios SET activo = IF(activo = 1, 0, 1) WHERE id = ? AND rol != 'cliente'")->execute([$targetId]);
        $_SESSION['flash'] = 'Estado del miembro del personal actualizado.';
        header('Location: panel.php?vista=usuarios');
        exit;
    }

} catch (Exception $ex) {
    $_SESSION['flash_error'] = $ex->getMessage();
    $referer = $_SERVER['HTTP_REFERER'] ?? 'panel.php';
    header("Location: $referer");
    exit;
}