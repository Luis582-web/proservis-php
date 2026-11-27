<?php
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$usuarioId = (int)$_SESSION['usuario_id'];
$nombreUsr = $_SESSION['nombre'];
$correoUsr = $_SESSION['correo'];
$rol       = $_SESSION['rol'];

$vistaDefault = match ($rol) {
    'coordinador' => 'bandeja',
    'tecnico'     => 'cola_tecnico',
    'cliente'     => 'mis_ordenes',
    'admin'       => 'usuarios',
    default       => 'bandeja',
};
$vista = $_GET['vista'] ?? $vistaDefault;

$flash      = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proservis · Panel Operativo</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <a href="#contenido-principal" class="skip-link">Saltar al contenido principal</a>

    <div class="app-layout">
        <aside class="sidebar" aria-label="Menú principal">
            <div class="sidebar-top">
                <div class="brand">
                    <span class="brand-badge" aria-hidden="true">PS</span>
                    <div>
                        <strong>Proservis</strong>
                        <small>Servicio Técnico Local</small>
                    </div>
                </div>
                <button type="button" id="btn-menu-movil" class="btn-menu-movil"
                        aria-expanded="false" aria-controls="nav-principal pie-menu">
                    ☰ Menú
                </button>
            </div>

            <div class="user-box">
                <span class="user-role"><?= e(strtoupper($rol)) ?></span>
                <strong><?= e($nombreUsr) ?></strong>
                <small><?= e($correoUsr) ?></small>
            </div>

            <nav id="nav-principal" class="sidebar-nav">
                <ul>
                    <?php if ($rol === 'coordinador'): ?>
                        <li><a href="panel.php?vista=bandeja" class="<?= $vista === 'bandeja' ? 'active' : '' ?>">Bandeja de órdenes (2.2)</a></li>
                        <li><a href="panel.php?vista=nueva_orden" class="<?= $vista === 'nueva_orden' ? 'active' : '' ?>">+ Nueva orden (2.3)</a></li>
                    <?php elseif ($rol === 'tecnico'): ?>
                        <li><a href="panel.php?vista=cola_tecnico" class="<?= $vista === 'cola_tecnico' ? 'active' : '' ?>">Cola del técnico (2.4)</a></li>
                    <?php elseif ($rol === 'cliente'): ?>
                        <li><a href="panel.php?vista=mis_ordenes" class="<?= $vista === 'mis_ordenes' ? 'active' : '' ?>">Mis órdenes (2.1)</a></li>
                    <?php elseif ($rol === 'admin'): ?>
                        <li><a href="panel.php?vista=usuarios" class="<?= $vista === 'usuarios' ? 'active' : '' ?>">Usuarios del personal (2.5)</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="sidebar-footer" id="pie-menu">
                <small>Modalidad: Solo mostrador local</small>
                <a href="logout.php" class="btn btn-danger btn-block">Cerrar sesión (1.0)</a>
            </div>
        </aside>

        <main id="contenido-principal" class="main-content">
            <header class="topbar">
                <span>Proservis / <strong><?= e(ucfirst(str_replace('_', ' ', $vista))) ?></strong></span>
                <span class="pill-local-light">Atención exclusiva en mostrador (Sin envíos)</span>
            </header>

            <?php if ($flash): ?>
                <div class="alert-success" role="status" aria-live="polite"><?= e($flash) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert-danger" role="alert" aria-live="assertive"><?= e($flashError) ?></div>
            <?php endif; ?>

            <?php
            // VISTA 2.2: BANDEJA DEL COORDINADOR
            if ($vista === 'bandeja' && $rol === 'coordinador'):
                $filtroEstado = $_GET['estado'] ?? '';
                $busqueda     = trim($_GET['q'] ?? '');

                $sql = "SELECT o.*, c.nombre AS cliente_nombre, c.telefono AS cliente_tel, t.nombre AS tecnico_nombre
                        FROM ordenes o
                        JOIN usuarios c ON o.cliente_id = c.id
                        LEFT JOIN usuarios t ON o.tecnico_id = t.id
                        WHERE 1=1";
                $params = [];
                if ($filtroEstado !== '') {
                    $sql .= " AND o.estado = ?";
                    $params[] = $filtroEstado;
                }
                if ($busqueda !== '') {
                    $sql .= " AND (o.folio LIKE ? OR c.nombre LIKE ?)";
                    $params[] = "%$busqueda%";
                    $params[] = "%$busqueda%";
                }
                $sql .= " ORDER BY o.id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $ordenes = $stmt->fetchAll();

                $totAbiertas   = $pdo->query("SELECT COUNT(*) FROM ordenes WHERE estado NOT IN ('Entregada','Cancelada')")->fetchColumn();
                $totSinTecnico = $pdo->query("SELECT COUNT(*) FROM ordenes WHERE tecnico_id IS NULL AND estado = 'Recibida'")->fetchColumn();
                $totReparacion = $pdo->query("SELECT COUNT(*) FROM ordenes WHERE estado = 'En reparación'")->fetchColumn();
                $totListas     = $pdo->query("SELECT COUNT(*) FROM ordenes WHERE estado = 'Lista para entrega'")->fetchColumn();
            ?>
                <div class="page-header">
                    <div>
                        <h1>Bandeja general de órdenes (2.2)</h1>
                        <p>Supervisión de órdenes en taller, asignación de técnicos y entregas en mostrador.</p>
                    </div>
                    <a href="panel.php?vista=nueva_orden" class="btn btn-primary">+ Registrar nueva orden (2.3)</a>
                </div>

                <section class="grid-4" aria-label="Indicadores del taller">
                    <article class="stat-card"><h2>Órdenes abiertas</h2><p class="stat-num"><?= $totAbiertas ?></p><small>Activas en taller</small></article>
                    <article class="stat-card"><h2>Recibidas sin técnico</h2><p class="stat-num"><?= $totSinTecnico ?></p><small>Pendientes de asignar</small></article>
                    <article class="stat-card"><h2>En reparación</h2><p class="stat-num"><?= $totReparacion ?></p><small>En banco de trabajo</small></article>
                    <article class="stat-card"><h2>Listas para entrega</h2><p class="stat-num"><?= $totListas ?></p><small>Esperando retiro</small></article>
                </section>

                <section class="card">
                    <h2>Listado de órdenes en taller</h2>
                    <form method="GET" action="panel.php" class="filter-bar">
                        <input type="hidden" name="vista" value="bandeja">
                        <div class="form-group">
                            <label for="q">Buscar por folio o cliente</label>
                            <input type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Ej. PS-2611-0001 o María">
                        </div>
                        <div class="form-group">
                            <label for="estado">Filtrar por estado</label>
                            <select id="estado" name="estado">
                                <option value="">Todos los estados</option>
                                <?php foreach (['Recibida','Asignada','En espera de repuesto','En reparación','Lista para entrega','Entregada','Cancelada'] as $est): ?>
                                    <option value="<?= $est ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= $est ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group filter-actions">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a href="panel.php?vista=bandeja" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table>
                            <caption class="sr-only">Órdenes registradas en taller</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Folio</th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Equipo</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Técnico asignado</th>
                                    <th scope="col">Recepción</th>
                                    <th scope="col">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordenes as $o): ?>
                                    <tr>
                                        <th scope="row"><span class="folio-tag"><?= e($o['folio']) ?></span></th>
                                        <td><strong><?= e($o['cliente_nombre']) ?></strong><br><small><?= e($o['cliente_tel']) ?></small></td>
                                        <td><?= e($o['tipo_equipo']) ?> · <?= e($o['marca_modelo']) ?></td>
                                        <td><span class="badge <?= badgeClass($o['estado']) ?>">● <?= e($o['estado']) ?></span></td>
                                        <td><?= e($o['tecnico_nombre'] ?? 'Sin asignar') ?></td>
                                        <td><?= e(date('d/m/Y H:i', strtotime($o['fecha_recepcion']))) ?></td>
                                        <td>
                                            <a href="panel.php?vista=detalle&id=<?= (int)$o['id'] ?>"
                                               aria-label="Ver detalle de la orden <?= e($o['folio']) ?>">Ver orden →</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            <?php
            // VISTA 2.3: FORMULARIO DE RECEPCIÓN (NUEVA ORDEN - RNF-01)
            elseif ($vista === 'nueva_orden' && $rol === 'coordinador'):
                $siguienteId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM ordenes")->fetchColumn();
                $proximoFolio = sprintf('PS-%s-%04d', date('ym'), $siguienteId);
            ?>
                <div class="page-header">
                    <div>
                        <h1>Formulario de Recepción · Nueva orden (2.3)</h1>
                        <p>Captura ágil en mostrador con los 6 campos esenciales (RNF-01).</p>
                    </div>
                    <a href="panel.php?vista=bandeja" class="btn btn-secondary">← Volver a Bandeja</a>
                </div>

                <div class="grid-main-aside">
                    <section class="card">
                        <h2>Datos esenciales de recepción en mostrador</h2>
                        <form method="POST" action="acciones.php">
                            <input type="hidden" name="accion" value="crear_orden">

                            <div class="grid-2">
                                <div class="form-group">
                                    <label for="nombre">1. Nombre completo del cliente *</label>
                                    <input type="text" id="nombre" name="nombre" required placeholder="Ej. Roberto Gómez Vega">
                                </div>
                                <div class="form-group">
                                    <label for="telefono">2. Teléfono *</label>
                                    <input type="tel" id="telefono" name="telefono" required placeholder="Ej. 555-0198">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="correo">3. Correo electrónico *</label>
                                <input type="email" id="correo" name="correo" required aria-describedby="ayuda-correo" placeholder="Ej. roberto.gomez@correo.com">
                                <small id="ayuda-correo">Si el correo no existe, el sistema crea automáticamente su cuenta rol Cliente.</small>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label for="tipo_equipo">4. Tipo de equipo *</label>
                                    <select id="tipo_equipo" name="tipo_equipo" required>
                                        <option value="Laptop">Laptop</option>
                                        <option value="PC">PC de escritorio</option>
                                        <option value="Celular">Celular</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="marca_modelo">5. Marca y Modelo *</label>
                                    <input type="text" id="marca_modelo" name="marca_modelo" required placeholder="Ej. HP Pavilion 15-eg2000">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="falla_reportada">6. Falla reportada *</label>
                                <textarea id="falla_reportada" name="falla_reportada" rows="3" required placeholder="Describa los síntomas indicados por el cliente..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Registrar orden en mostrador</button>
                        </form>
                    </section>

                    <aside class="card">
                        <h2>Resumen de ingreso</h2>
                        <div class="summary-box">
                            <small>PRÓXIMO FOLIO AUTOGENERADO</small>
                            <p class="folio-big"><?= e($proximoFolio) ?></p>
                            <small>ESTADO INICIAL</small>
                            <div><span class="badge badge-amber">● Recibida</span></div>
                        </div>
                        <div class="alert-warning" role="note">
                            <strong>Modalidad presencial:</strong> El retiro del equipo se realiza exclusivamente en este mostrador presentando el folio.
                        </div>
                    </aside>
                </div>

            <?php
            // VISTA 3.0: DETALLE DE ORDEN
            elseif ($vista === 'detalle' && $rol === 'coordinador'):
                $ordenId = (int)($_GET['id'] ?? 0);
                $stmt = $pdo->prepare("SELECT o.*, c.nombre AS cliente_nombre, c.telefono AS cliente_tel, c.correo AS cliente_correo, t.nombre AS tecnico_nombre
                                       FROM ordenes o
                                       JOIN usuarios c ON o.cliente_id = c.id
                                       LEFT JOIN usuarios t ON o.tecnico_id = t.id
                                       WHERE o.id = ?");
                $stmt->execute([$ordenId]);
                $orden = $stmt->fetch();

                if (!$orden) {
                    echo '<p class="alert-danger">Orden no encontrada.</p>';
                } else {
                    $tecnicos = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol = 'tecnico' AND activo = 1")->fetchAll();
                    $stmtBit = $pdo->prepare("SELECT b.*, u.nombre AS autor, u.rol AS autor_rol
                                              FROM bitacora_orden b JOIN usuarios u ON b.usuario_id = u.id
                                              WHERE b.orden_id = ? ORDER BY b.id DESC");
                    $stmtBit->execute([$ordenId]);
                    $bitacora = $stmtBit->fetchAll();
            ?>
                <div class="page-header">
                    <div>
                        <h1>Detalle de orden <?= e($orden['folio']) ?> (3.0)</h1>
                        <p>Trazabilidad completa, asignación técnica, entrega y cancelación auditada.</p>
                    </div>
                    <a href="panel.php?vista=bandeja" class="btn btn-secondary">← Volver a Bandeja</a>
                </div>

                <div class="grid-main-aside">
                    <div>
                        <section class="card">
                            <div class="card-header-flex">
                                <h2>Resumen del caso y equipo</h2>
                                <span class="badge <?= badgeClass($orden['estado']) ?>">● <?= e($orden['estado']) ?></span>
                            </div>
                            <dl class="detail-grid">
                                <div><dt>FOLIO</dt><dd><?= e($orden['folio']) ?></dd></div>
                                <div><dt>RECEPCIÓN</dt><dd><?= e(date('d/m/Y H:i', strtotime($orden['fecha_recepcion']))) ?></dd></div>
                                <div><dt>CLIENTE</dt><dd><?= e($orden['cliente_nombre']) ?></dd></div>
                                <div><dt>CONTACTO</dt><dd><?= e($orden['cliente_tel']) ?> · <?= e($orden['cliente_correo']) ?></dd></div>
                                <div><dt>EQUIPO</dt><dd><?= e($orden['tipo_equipo']) ?> · <?= e($orden['marca_modelo']) ?></dd></div>
                                <div><dt>TÉCNICO ASIGNADO</dt><dd><?= e($orden['tecnico_nombre'] ?? 'Sin asignar') ?></dd></div>
                            </dl>
                            <div class="alert-warning">
                                <strong>Falla reportada:</strong> <?= e($orden['falla_reportada']) ?>
                            </div>
                        </section>

                        <section class="card">
                            <h2>Actividad de la orden (Bitácora de historial)</h2>
                            <ul class="timeline">
                                <?php foreach ($bitacora as $item): ?>
                                    <li>
                                        <strong><?= e($item['autor']) ?> (<?= e($item['autor_rol']) ?>)</strong>
                                        <small> — <?= e(date('d/m/Y H:i', strtotime($item['fecha']))) ?></small>
                                        <p><?= e($item['detalle']) ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    </div>

                    <aside class="card">
                        <h2>Gestión de la orden</h2>
                        <form method="POST" action="acciones.php">
                            <input type="hidden" name="accion" value="actualizar_orden">
                            <input type="hidden" name="orden_id" value="<?= (int)$orden['id'] ?>">

                            <div class="form-group">
                                <label for="tecnico_id">Reasignar técnico</label>
                                <select id="tecnico_id" name="tecnico_id">
                                    <option value="">-- Sin asignar --</option>
                                    <?php foreach ($tecnicos as $tec): ?>
                                        <option value="<?= (int)$tec['id'] ?>" <?= (int)$orden['tecnico_id'] === (int)$tec['id'] ? 'selected' : '' ?>>
                                            <?= e($tec['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="estado_orden">Estado de la orden</label>
                                <select id="estado_orden" name="estado">
                                    <?php foreach (['Recibida','Asignada','En espera de repuesto','En reparación','Lista para entrega'] as $est): ?>
                                        <option value="<?= $est ?>" <?= $orden['estado'] === $est ? 'selected' : '' ?>><?= $est ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="comentario">Comentario para la bitácora</label>
                                <textarea id="comentario" name="comentario" rows="2" placeholder="Observación opcional..."></textarea>
                            </div>

                            <div class="btn-stack">
                                <button type="submit" name="subaccion" value="guardar" class="btn btn-primary">Guardar cambios</button>
                                <button type="submit" name="subaccion" value="entregar" class="btn btn-success">✓ Marcar entregada en mostrador</button>
                                <button type="button" id="btn-abrir-cancelar" class="btn btn-danger-outline">✕ Cancelar orden</button>
                            </div>

                            <div id="panel-cancelar" class="cancel-box hidden" role="region" aria-label="Confirmación de cancelación">
                                <h3>Confirmación obligatoria (RF-08)</h3>
                                <div class="form-group">
                                    <label for="motivo_cancelacion">Motivo obligatorio de cancelación *</label>
                                    <textarea id="motivo_cancelacion" name="motivo_cancelacion" rows="2"
                                              placeholder="Ej. Cliente decide retirar el equipo sin reparar..."></textarea>
                                </div>
                                <div class="grid-2">
                                    <button type="submit" name="subaccion" value="cancelar" class="btn btn-danger">Confirmar cancelación</button>
                                    <button type="button" id="btn-cerrar-cancelar" class="btn btn-secondary">Volver</button>
                                </div>
                            </div>
                        </form>
                    </aside>
                </div>

            <?php
            }
            // VISTA 2.4: COLA DEL TÉCNICO + INTERVENCIÓN (RF-06)
            elseif ($vista === 'cola_tecnico' && $rol === 'tecnico'):
                $stmt = $pdo->prepare("SELECT o.*, c.nombre AS cliente_nombre
                                       FROM ordenes o JOIN usuarios c ON o.cliente_id = c.id
                                       WHERE o.tecnico_id = ? AND o.estado NOT IN ('Entregada','Cancelada')
                                       ORDER BY o.id DESC");
                $stmt->execute([$usuarioId]);
                $misAsignadas = $stmt->fetchAll();

                $ordenSelId = (int)($_GET['orden_id'] ?? ($misAsignadas[0]['id'] ?? 0));
                $ordenActiva = null;
                foreach ($misAsignadas as $item) {
                    if ((int)$item['id'] === $ordenSelId) {
                        $ordenActiva = $item;
                        break;
                    }
                }
            ?>
                <div class="page-header">
                    <div>
                        <h1>Cola de trabajo del Técnico (2.4)</h1>
                        <p>Diagnóstico y registro de intervenciones técnicas (RF-06).</p>
                    </div>
                </div>

                <div class="grid-main-aside">
                    <section class="card">
                        <h2>Mis órdenes asignadas (<?= count($misAsignadas) ?>)</h2>
                        <div class="table-responsive">
                            <table>
                                <caption class="sr-only">Órdenes asignadas al técnico</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Folio</th>
                                        <th scope="col">Equipo</th>
                                        <th scope="col">Falla reportada</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($misAsignadas as $o): ?>
                                        <tr>
                                            <th scope="row"><span class="folio-tag"><?= e($o['folio']) ?></span></th>
                                            <td><?= e($o['tipo_equipo']) ?> · <?= e($o['marca_modelo']) ?></td>
                                            <td><?= e($o['falla_reportada']) ?></td>
                                            <td><span class="badge <?= badgeClass($o['estado']) ?>">● <?= e($o['estado']) ?></span></td>
                                            <td>
                                                <a href="panel.php?vista=cola_tecnico&orden_id=<?= (int)$o['id'] ?>"
                                                   aria-label="Intervenir orden <?= e($o['folio']) ?>">Intervenir →</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <aside class="card">
                        <h2>Registro de intervención (RF-06)</h2>
                        <?php if ($ordenActiva): ?>
                            <p><strong><?= e($ordenActiva['folio']) ?></strong> · <?= e($ordenActiva['marca_modelo']) ?> (Cliente: <?= e($ordenActiva['cliente_nombre']) ?>)</p>
                            <form method="POST" action="acciones.php">
                                <input type="hidden" name="accion" value="guardar_intervencion">
                                <input type="hidden" name="orden_id" value="<?= (int)$ordenActiva['id'] ?>">

                                <div class="form-group">
                                    <label for="estado_tec">Estado técnico *</label>
                                    <select id="estado_tec" name="estado" required>
                                        <option value="En reparación" <?= $ordenActiva['estado'] === 'En reparación' ? 'selected' : '' ?>>En reparación</option>
                                        <option value="En espera de repuesto" <?= $ordenActiva['estado'] === 'En espera de repuesto' ? 'selected' : '' ?>>En espera de repuesto</option>
                                        <option value="Lista para entrega" <?= $ordenActiva['estado'] === 'Lista para entrega' ? 'selected' : '' ?>>Lista para entrega</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="diagnostico">Diagnóstico y trabajo realizado *</label>
                                    <textarea id="diagnostico" name="diagnostico" rows="3" required placeholder="Detalle la revisión técnica efectuada..."></textarea>
                                </div>

                                <div class="grid-2">
                                    <div class="form-group">
                                        <label for="repuesto">Repuesto requerido</label>
                                        <input type="text" id="repuesto" name="repuesto" placeholder="Ej. Batería OEM">
                                    </div>
                                    <div class="form-group">
                                        <label for="tiempo_minutos">Tiempo (minutos)</label>
                                        <input type="number" id="tiempo_minutos" name="tiempo_minutos" value="60" min="5">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block">Guardar intervención</button>
                            </form>
                        <?php else: ?>
                            <p>No tiene órdenes activas asignadas en este momento.</p>
                        <?php endif; ?>
                    </aside>
                </div>

            <?php
            // VISTA 2.1: MIS ÓRDENES (CLIENTE - SOLO LECTURA)
            elseif ($vista === 'mis_ordenes' && $rol === 'cliente'):
                $stmt = $pdo->prepare("SELECT o.*, t.nombre AS tecnico_nombre
                                       FROM ordenes o LEFT JOIN usuarios t ON o.tecnico_id = t.id
                                       WHERE o.cliente_id = ? ORDER BY o.id DESC");
                $stmt->execute([$usuarioId]);
                $misOrdenes = $stmt->fetchAll();

                $selId = (int)($_GET['id'] ?? ($misOrdenes[0]['id'] ?? 0));
                $ordenActual = null;
                foreach ($misOrdenes as $item) {
                    if ((int)$item['id'] === $selId) {
                        $ordenActual = $item;
                        break;
                    }
                }

                $ultimoAvance = 'Equipo registrado en mostrador. En espera de diagnóstico.';
                if ($ordenActual) {
                    $stmtBit = $pdo->prepare("SELECT detalle FROM bitacora_orden WHERE orden_id = ? ORDER BY id DESC LIMIT 1");
                    $stmtBit->execute([$ordenActual['id']]);
                    $ultimoAvance = $stmtBit->fetchColumn() ?: $ultimoAvance;
                }

                $pasoActual = match ($ordenActual['estado'] ?? '') {
                    'Recibida' => 1,
                    'Asignada' => 2,
                    'En reparación', 'En espera de repuesto' => 3,
                    'Lista para entrega', 'Entregada' => 4,
                    default => 1,
                };
            ?>
                <div class="page-header">
                    <div>
                        <h1>Mis órdenes de servicio (2.1)</h1>
                        <p>Consulta de estado en tiempo real (Vista de solo lectura).</p>
                    </div>
                </div>

                <?php if ($ordenActual): ?>
                    <div class="grid-main-aside">
                        <section class="card">
                            <div class="card-header-flex">
                                <h2>Seguimiento del Folio <?= e($ordenActual['folio']) ?></h2>
                                <span class="badge <?= badgeClass($ordenActual['estado']) ?>">● <?= e($ordenActual['estado']) ?></span>
                            </div>
                            <p><?= e($ordenActual['tipo_equipo']) ?> · <?= e($ordenActual['marca_modelo']) ?></p>

                            <ol class="stepper" aria-label="Progreso de reparación">
                                <li class="<?= $pasoActual >= 1 ? 'step-done' : '' ?>">1. Recibida</li>
                                <li class="<?= $pasoActual >= 2 ? 'step-done' : '' ?>">2. Asignada</li>
                                <li class="<?= $pasoActual >= 3 ? 'step-done' : '' ?>">3. En reparación</li>
                                <li class="<?= $pasoActual >= 4 ? 'step-done' : '' ?>">4. Lista para entrega</li>
                            </ol>

                            <div class="alert-success">
                                <strong>Último avance del taller:</strong>
                                <p><?= e($ultimoAvance) ?></p>
                            </div>
                        </section>

                        <aside class="card">
                            <h2>Información para retiro en mostrador</h2>
                            <p>Proservis opera exclusivamente en sucursal física (sin envíos a domicilio).</p>
                            <ul class="info-list">
                                <li><strong>Horario:</strong> Lunes a Viernes 09:00 – 18:30 hrs</li>
                                <li><strong>Sábados:</strong> 09:30 – 14:00 hrs</li>
                                <li><strong>Teléfono local:</strong> (55) 4820-9100</li>
                                <li><strong>Dirección:</strong> Av. Tecnológico #415, Local B</li>
                            </ul>
                        </aside>
                    </div>
                <?php endif; ?>

                <section class="card">
                    <h2>Historial de folios</h2>
                    <div class="table-responsive">
                        <table>
                            <caption class="sr-only">Historial de órdenes del cliente</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Folio</th>
                                    <th scope="col">Equipo</th>
                                    <th scope="col">Falla</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Consulta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($misOrdenes as $o): ?>
                                    <tr>
                                        <th scope="row"><span class="folio-tag"><?= e($o['folio']) ?></span></th>
                                        <td><?= e($o['marca_modelo']) ?></td>
                                        <td><?= e($o['falla_reportada']) ?></td>
                                        <td><span class="badge <?= badgeClass($o['estado']) ?>">● <?= e($o['estado']) ?></span></td>
                                        <td><a href="panel.php?vista=mis_ordenes&id=<?= (int)$o['id'] ?>">Ver progreso</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            <?php
            // VISTA 2.5: USUARIOS DEL PERSONAL (ADMINISTRADOR)
            elseif ($vista === 'usuarios' && $rol === 'admin'):
                $personal = $pdo->query("SELECT * FROM usuarios WHERE rol != 'cliente' ORDER BY id ASC")->fetchAll();
            ?>
                <div class="page-header">
                    <div>
                        <h1>Usuarios del personal del taller (2.5)</h1>
                        <p>Administración de cuentas internas de Técnicos, Coordinadores y Administradores.</p>
                    </div>
                </div>

                <div class="alert-warning" role="note">
                    <strong>Alcance exclusivo de personal interno:</strong> Este módulo no gestiona clientes (las cuentas Cliente nacen automáticamente en Recepción).
                </div>

                <div class="grid-main-aside">
                    <section class="card">
                        <h2>Listado del personal interno</h2>
                        <div class="table-responsive">
                            <table>
                                <caption class="sr-only">Personal interno de Proservis</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Usuario</th>
                                        <th scope="col">Correo</th>
                                        <th scope="col">Rol</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($personal as $p): ?>
                                        <tr>
                                            <th scope="row"><?= e($p['nombre']) ?></th>
                                            <td><?= e($p['correo']) ?></td>
                                            <td><?= e(ucfirst($p['rol'])) ?></td>
                                            <td>
                                                <?php if ((int)$p['activo'] === 1): ?>
                                                    <span class="badge badge-green">● Activo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-red">● Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="acciones.php">
                                                    <input type="hidden" name="accion" value="toggle_usuario">
                                                    <input type="hidden" name="target_id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary">
                                                        <?= (int)$p['activo'] === 1 ? 'Desactivar' : 'Reactivar' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <aside class="card">
                        <h2>+ Dar de alta personal</h2>
                        <form method="POST" action="acciones.php">
                            <input type="hidden" name="accion" value="crear_usuario">
                            <div class="form-group">
                                <label for="nombre_usr">Nombre completo *</label>
                                <input type="text" id="nombre_usr" name="nombre" required placeholder="Ej. Sofía Navarro">
                            </div>
                            <div class="form-group">
                                <label for="correo_usr">Correo institucional *</label>
                                <input type="email" id="correo_usr" name="correo" required placeholder="sofia@proservis.local">
                            </div>
                            <div class="form-group">
                                <label for="rol_nuevo">Rol asignado *</label>
                                <select id="rol_nuevo" name="rol_nuevo">
                                    <option value="tecnico">Técnico</option>
                                    <option value="coordinador">Coordinador</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Crear cuenta interna</button>
                        </form>
                    </aside>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const btnMenu = document.getElementById('btn-menu-movil');
        const nav = document.getElementById('nav-principal');
        if (btnMenu && nav) {
            btnMenu.addEventListener('click', () => {
                const abierto = nav.classList.toggle('open');
                btnMenu.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            });
        }

        const btnCancelar = document.getElementById('btn-abrir-cancelar');
        const btnCerrarCancelar = document.getElementById('btn-cerrar-cancelar');
        const panelCancelar = document.getElementById('panel-cancelar');
        if (btnCancelar && panelCancelar) {
            btnCancelar.addEventListener('click', () => panelCancelar.classList.remove('hidden'));
        }
        if (btnCerrarCancelar && panelCancelar) {
            btnCerrarCancelar.addEventListener('click', () => panelCancelar.classList.add('hidden'));
        }
    </script>
</body>
</html>
