<?php
// ============================================================
//  NutriTrack - API: Resumen del día actual
//  GET /api/resumen_dia.php?usuario_id=2
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;

if ($usuarioId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'usuario_id requerido']);
    exit;
}

try {
    $pdo = getDB();

    $stmtHoy = $pdo->prepare("
        SELECT
            COALESCE(SUM(puntos_asignados), 0)                          AS puntos_hoy,
            MAX(CASE WHEN tipo_comida = 'Desayuno' THEN 1 ELSE 0 END)   AS tiene_desayuno,
            MAX(CASE WHEN tipo_comida = 'Comida'   THEN 1 ELSE 0 END)   AS tiene_comida,
            MAX(CASE WHEN tipo_comida = 'Cena'     THEN 1 ELSE 0 END)   AS tiene_cena
        FROM registros_comida
        WHERE usuario_id = :uid
          AND fecha_registro = CURDATE()
    ");
    $stmtHoy->execute([':uid' => $usuarioId]);
    $hoy = $stmtHoy->fetch();

    $stmtTotal = $pdo->prepare("
        SELECT puntos, racha_dias, meta_diaria FROM usuarios WHERE id = :uid AND activo = 1
    ");
    $stmtTotal->execute([':uid' => $usuarioId]);
    $usuario = $stmtTotal->fetch();

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    echo json_encode([
        'ok'           => true,
        'puntos_hoy'   => (int)$hoy['puntos_hoy'],
        'puntos_total' => (int)$usuario['puntos'],
        'meta_diaria'  => (int)$usuario['meta_diaria'],
        'racha_dias'   => (int)$usuario['racha_dias'],
        'comidas_hoy'  => [
            'Desayuno' => (bool)$hoy['tiene_desayuno'],
            'Comida'   => (bool)$hoy['tiene_comida'],
            'Cena'     => (bool)$hoy['tiene_cena'],
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}