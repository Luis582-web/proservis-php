<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'proservis';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        $options
    );
} catch (PDOException $e) {
    die("Error de conexión con la base de datos: " . htmlspecialchars($e->getMessage()));
}

function e(string $valor): string {
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function badgeClass(string $estado): string {
    return match ($estado) {
        'Recibida', 'En espera de repuesto' => 'badge-amber',
        'Asignada', 'En reparación'         => 'badge-blue',
        'Lista para entrega', 'Entregada'   => 'badge-green',
        'Cancelada'                         => 'badge-red',
        default                             => 'badge-blue',
    };
}

function registrarBitacora(PDO $pdo, int $ordenId, int $usuarioId, string $accion, string $detalle): void {
    $stmt = $pdo->prepare("INSERT INTO bitacora_orden (orden_id, usuario_id, accion, detalle) VALUES (?, ?, ?, ?)");
    $stmt->execute([$ordenId, $usuarioId, $accion, $detalle]);
}