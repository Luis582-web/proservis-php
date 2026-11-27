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
    <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">
</head>
<body class="login-body">
    <a href="#contenido-principal" class="skip-link">Saltar al contenido principal</a>

    <div class="login-split">
        <aside class="login-hero">
            <div class="hero-brand">
                <span class="hero-brand-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </span>
                ProServis
            </div>

            <div class="hero-content">
                <div class="hero-intro">
                    <p class="hero-kicker">Gestión de servicio técnico</p>
                    <h2 class="hero-title">Cada equipo.<br>Cada reparación.<br>Todo bajo control.</h2>
                    <p class="hero-text">Un solo espacio para recibir, coordinar y dar seguimiento a laptops y celulares.</p>
                </div>

                <div class="hero-illustration" aria-hidden="true">
                    <svg viewBox="0 0 432 198" fill="none" stroke="#8CAAC5" stroke-width="1.8" stroke-linejoin="round">
                        <path d="M59 152V39q0-7 7-7h220q7 0 7 7v113z"/>
                        <path d="M73 47h206v87H73zM59 152l-24 20q0 6 8 6h278l-28-26zM145 152v9h64v-9"/>
                        <circle cx="175" cy="96" r="28.5" fill="#233F60" stroke="none"/>
                        <path d="m166 96 5 5 13-13" stroke="#BCD1E2" stroke-width="1"/>
                        <rect x="307" y="65" width="62" height="118" rx="7" fill="#0B1D32"/>
                        <path d="M327 73h21m-18 102h17m-24-51 5 5 9-10"/>
                    </svg>
                </div>

                <ol class="hero-steps">
                    <li><span>01</span>Recepción</li>
                    <li><span>02</span>Reparación</li>
                    <li><span>03</span>Retiro</li>
                </ol>
            </div>

            <p class="hero-note">
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/></svg>
                <?php $avisoLocal = 'Recepción y retiro exclusivamente en el local.'; ?>
                <?= e($avisoLocal) ?>
            </p>
        </aside>

        <main id="contenido-principal" class="login-main">
            <p class="login-top-help">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                ¿Necesitas ayuda? Contacta al taller
            </p>

            <section class="login-form-box" aria-labelledby="titulo-login">
                <p class="login-kicker">Bienvenido a Proservis</p>
                <h1 id="titulo-login">Iniciar sesión</h1>
                <p class="login-sub">Ingresa a tu espacio de trabajo para continuar.</p>

                <?php if ($error): ?>
                    <div class="alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="index.php">
                    <div class="form-group">
                        <label for="correo">Correo electrónico</label>
                        <input type="email" id="correo" name="correo" required autocomplete="email"
                               placeholder="usuario@proservis.local" value="<?= e($_POST['correo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="password-wrap">
                            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                            <button type="button" class="toggle-pass" aria-label="Mostrar contraseña" aria-controls="password">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="login-row">
                        <label class="check"><input type="checkbox" name="recordar"> Recordar mi sesión</label>
                        <a href="#ayuda-acceso">Olvidé mi contraseña</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-login">Ingresar <span aria-hidden="true">→</span></button>
                </form>

                <div class="login-info" id="ayuda-acceso" role="note">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Tu cuenta muestra las herramientas de tu rol. Si eres cliente, el taller te proporcionará tus datos de acceso al recibir tu equipo. <span class="password-help">¿Olvidaste tu contraseña? Solicítala en el mostrador.</span><span class="login-mobile-note"><?= e($avisoLocal) ?></span></span>
                </div>
            </section>

            <footer class="login-foot">
                <span>© <?= date('Y') ?> Proservis</span>
                <a href="#">Aviso de privacidad</a>
            </footer>
        </main>
    </div>
    <script>
        const passwordField = document.getElementById('password');
        const togglePassword = document.querySelector('.toggle-pass');
        togglePassword.addEventListener('click', () => {
            const mostrar = passwordField.type === 'password';
            passwordField.type = mostrar ? 'text' : 'password';
            togglePassword.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    </script>
</body>
</html>
