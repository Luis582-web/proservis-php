<?php
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: panel.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? LIMIT 1");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    if ($usuario && hash('sha256', $password) === $usuario['password_hash']) {
        if ((int)$usuario['activo'] !== 1) {
            $error = 'Esta cuenta se encuentra desactivada. Contacte al administrador del taller.';
        } else {
            $_SESSION['usuario_id'] = (int)$usuario['id'];
            $_SESSION['nombre']     = $usuario['nombre'];
            $_SESSION['correo']     = $usuario['correo'];
            $_SESSION['rol']        = $usuario['rol'];
            header('Location: panel.php');
            exit;
        }
    } else {
        $error = 'Correo electrónico o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · Proservis</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-body">
    <a href="#contenido-principal" class="skip-link">Saltar al contenido principal</a>

    <main id="contenido-principal" class="login-wrapper">
        <section class="login-card" aria-labelledby="titulo-login">
            <header class="login-header">
                <span class="pill-local">ATENCIÓN EXCLUSIVA EN MOSTRADOR LOCAL</span>
                <h1 id="titulo-login">Acceso a Proservis</h1>
                <p>Sistema de Gestión de Servicio Técnico y Reparación</p>
            </header>

            <div class="login-body-content">
                <div class="alert-info" role="note">
                    <strong>Acceso para clientes y personal:</strong> Las credenciales del cliente se generan automáticamente al recibir su equipo en mostrador.
                </div>

                <?php if ($error): ?>
                    <div class="alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="index.php">
                    <div class="form-group">
                        <label for="correo">Correo electrónico *</label>
                        <input type="email" id="correo" name="correo" required autocomplete="email"
                               placeholder="usuario@proservis.local" value="<?= e($_POST['correo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña *</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Ingresar al sistema</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>