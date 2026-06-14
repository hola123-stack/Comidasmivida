<?php
date_default_timezone_set('America/Mexico_City');
// ============================================================
//  NutriTrack - API: Editar Registro de Comida
//  PUT /api/editar_registro.php
//
//  Body JSON esperado:
//  {
//    "registro_id" : 15,
//    "usuario_id"  : 2,
//    "nombre_libre": "Avena con fresas",   (opcional)
//    "tipo_comida" : "Cena"                (opcional)
//  }
//
//  NO se puede modificar: porcion, puntos, foto
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

$registroId  = isset($body['registro_id'])  ? (int)$body['registro_id']        : 0;
$usuarioId   = isset($body['usuario_id'])   ? (int)$body['usuario_id']          : 0;
$nombreLibre = isset($body['nombre_libre']) ? trim($body['nombre_libre'])        : null;
$tipoComida  = isset($body['tipo_comida'])  ? trim($body['tipo_comida'])         : null;

$tiposValidos = ['Desayuno', 'Comida', 'Cena', 'Merienda'];

if ($registroId <= 0 || $usuarioId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'registro_id y usuario_id son requeridos']);
    exit;
}

if ($tipoComida !== null && !in_array($tipoComida, $tiposValidos)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'tipo_comida inválido. Valores: ' . implode(', ', $tiposValidos)]);
    exit;
}

if ($nombreLibre !== null && $nombreLibre === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'El nombre del alimento no puede estar vacío']);
    exit;
}

try {
    $pdo = getDB();

    // Verificar que el registro pertenece al usuario
    $stmtCheck = $pdo->prepare("
        SELECT id, tipo_comida, nombre_libre FROM registros_comida
        WHERE id = :rid AND usuario_id = :uid
    ");
    $stmtCheck->execute([':rid' => $registroId, ':uid' => $usuarioId]);
    $registro = $stmtCheck->fetch();

    if (!$registro) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Registro no encontrado o no pertenece al usuario']);
        exit;
    }

    // Construir SET dinámico con solo los campos enviados
    $sets   = [];
    $params = [':rid' => $registroId, ':uid' => $usuarioId];

    if ($tipoComida !== null) {
        $sets[]             = 'tipo_comida = :tipo';
        $params[':tipo']    = $tipoComida;
    }
    if ($nombreLibre !== null) {
        $sets[]              = 'nombre_libre = :nombre';
        $params[':nombre']   = $nombreLibre;
    }

    if (empty($sets)) {
        echo json_encode(['ok' => false, 'error' => 'Nada que actualizar']);
        exit;
    }

    $sql = 'UPDATE registros_comida SET ' . implode(', ', $sets) . '
            WHERE id = :rid AND usuario_id = :uid';
    $pdo->prepare($sql)->execute($params);

    echo json_encode([
        'ok'      => true,
        'mensaje' => 'Registro actualizado correctamente',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}