<?php
// ============================================================
//  NutriTrack - API: Obtener usuario por username
//  GET /api/usuario.php?username=Mivida
//  Devuelve id, nombre_real, puntos, racha_dias
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if (!$username) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'username requerido']);
    exit;
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, username, nombre_real, rol, puntos, racha_dias, meta_diaria
         FROM usuarios WHERE username = :u AND activo = 1 LIMIT 1"
    );
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    $user['id']          = (int) $user['id'];
    $user['puntos']      = (int) $user['puntos'];
    $user['racha_dias']  = (int) $user['racha_dias'];
    $user['meta_diaria'] = (int) $user['meta_diaria'];

    echo json_encode(['ok' => true, 'usuario' => $user]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}