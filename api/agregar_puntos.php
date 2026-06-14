<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$usuarioId = isset($data['usuario_id']) ? (int)$data['usuario_id'] : 0;
$puntos    = isset($data['puntos'])     ? (int)$data['puntos']     : 0;
$motivo    = isset($data['motivo'])     ? trim($data['motivo'])     : 'Puntos agregados por admin';

if ($usuarioId <= 0 || $puntos <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $pdo = getDB();

    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT id, puntos FROM usuarios WHERE id = :uid AND activo = 1");
    $stmt->execute([':uid' => $usuarioId]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    // Sumar los puntos al usuario
    $stmtUpdate = $pdo->prepare("UPDATE usuarios SET puntos = puntos + :pts WHERE id = :uid");
    $stmtUpdate->execute([':pts' => $puntos, ':uid' => $usuarioId]);

    // (Opcional) Registrar en un log si tienes tabla de movimientos
    // Si tienes tabla `movimientos_puntos` o similar, puedes insertar aquí:
    // $stmtLog = $pdo->prepare("INSERT INTO movimientos_puntos (usuario_id, puntos, motivo, fecha) VALUES (:uid, :pts, :motivo, NOW())");
    // $stmtLog->execute([':uid' => $usuarioId, ':pts' => $puntos, ':motivo' => $motivo]);

    $puntosNuevos = $usuario['puntos'] + $puntos;

    echo json_encode([
        'ok'           => true,
        'puntos_antes' => (int)$usuario['puntos'],
        'puntos_added' => $puntos,
        'puntos_total' => $puntosNuevos,
        'motivo'       => $motivo,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}