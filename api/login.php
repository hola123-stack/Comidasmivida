<?php
// ============================================================
//  NutriTrack - API: Login
//  POST /api/login.php
//
//  Body JSON esperado:
//  {
//    "username": "Mivida",
//    "password": "nano"
//  }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db.php';

$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Usuario y contraseña requeridos']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "SELECT id, username, nombre_real, rol, puntos, racha_dias, meta_diaria, password_hash
         FROM usuarios
         WHERE username = :u AND activo = 1
         LIMIT 1"
    );
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Usuario o contraseña incorrectos']);
        exit;
    }

    // Nunca enviar el hash al cliente
    unset($user['password_hash']);

    // Convertir tipos numéricos
    $user['id']          = (int) $user['id'];
    $user['puntos']      = (int) $user['puntos'];
    $user['racha_dias']  = (int) $user['racha_dias'];
    $user['meta_diaria'] = (int) $user['meta_diaria'];

    echo json_encode(['ok' => true, 'usuario' => $user]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}