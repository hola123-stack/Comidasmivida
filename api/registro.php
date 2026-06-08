<?php
date_default_timezone_set('America/Mexico_City');
// ============================================================
//  NutriTrack - API: Guardar Registro de Comida
//  POST /api/registro.php
//
//  Body JSON esperado:
//  {
//    "usuario_id"  : 2,
//    "nombre_libre": "Avena con fresas",
//    "tipo_comida" : "Desayuno",
//    "porcion"     : "Normal",
//    "hora"        : "08:30",
//    "foto_base64" : "data:image/jpeg;base64,..."  (opcional)
//  }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db.php';

// ── 1. Leer y validar el cuerpo ──────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$usuarioId   = isset($body['usuario_id'])   ? (int) $body['usuario_id']   : 0;
$nombreLibre = isset($body['nombre_libre']) ? trim($body['nombre_libre']) : '';
$tipoComida  = isset($body['tipo_comida'])  ? trim($body['tipo_comida'])  : '';
$porcion     = isset($body['porcion'])      ? trim($body['porcion'])      : 'Normal';
$hora        = isset($body['hora'])         ? trim($body['hora'])         : date('H:i');
$fotoBase64  = isset($body['foto_base64'])  ? $body['foto_base64']        : null;

$tiposValidos     = ['Desayuno', 'Comida', 'Cena', 'Merienda'];
$porcionesValidas = ['Ligera', 'Normal', 'Abundante'];

if ($usuarioId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'usuario_id requerido']);
    exit;
}
if (!$nombreLibre) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'El nombre del alimento es requerido']);
    exit;
}
if (!in_array($tipoComida, $tiposValidos)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'tipo_comida inválido. Valores: ' . implode(', ', $tiposValidos)]);
    exit;
}
if (!in_array($porcion, $porcionesValidas)) {
    $porcion = 'Normal';
}

try {
    $pdo = getDB();

    // ── 2. Obtener racha actual del usuario ───────────────────
    $stmtUser = $pdo->prepare("SELECT racha_dias FROM usuarios WHERE id = :uid AND activo = 1");
    $stmtUser->execute([':uid' => $usuarioId]);
    $userData = $stmtUser->fetch();

    if (!$userData) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    $rachaDias = (int) $userData['racha_dias'];

    // ── 3. Calcular multiplicador de racha ────────────────────
    $nivelRacha         = (int) floor($rachaDias / 10);
    $multiplicadorRacha = min(2.0, 1.0 + $nivelRacha * 0.1);

    // ── 4. Buscar alimento en catálogo ────────────────────────
    $stmtAlimento = $pdo->prepare(
        "SELECT id, puntos_base, categoria FROM alimentos
         WHERE LOWER(nombre) LIKE LOWER(:nombre) LIMIT 1"
    );
    $stmtAlimento->execute([':nombre' => '%' . $nombreLibre . '%']);
    $alimentoCat = $stmtAlimento->fetch();

    if ($alimentoCat) {
        $alimentoId = $alimentoCat['id'];
        $puntosBase = $alimentoCat['puntos_base'];
    } else {
        $alimentoId = null;
        $puntosBase = match($porcion) {
            'Ligera'    => 15,
            'Normal'    => 25,
            'Abundante' => 30,
            default     => 20,
        };
    }

    // ── 5. Calcular puntos finales ────────────────────────────
$multiplicadorPorcion = match($porcion) {
    'Ligera'    => 0.8,
    'Abundante' => 1.2,
    default     => 1.0,
};

$puntosConPorcion = $puntosBase * $multiplicadorPorcion;

if ($puntosConPorcion >= 0) {
    $puntosFinales = (int) round($puntosConPorcion * $multiplicadorRacha);
} else {
    $puntosFinales = (int) round($puntosConPorcion);
}

// ── 5b. Override de puntos (para canjes) ─────────────────
if (isset($body['puntos_override'])) {
    $puntosFinales = (int) $body['puntos_override'];
}

    // ── 6. Guardar foto si viene en base64 ────────────────────
    $fotoUrl = null;
    if ($fotoBase64 && str_starts_with($fotoBase64, 'data:image/')) {
        $uploadDir = __DIR__ . '/../uploads/fotos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        [$meta, $datos] = explode(',', $fotoBase64, 2);
        preg_match('/data:image\/(\w+);base64/', $meta, $m);
        $ext           = $m[1] ?? 'jpg';
        $nombreArchivo = 'foto_' . $usuarioId . '_' . time() . '.' . $ext;
        file_put_contents($uploadDir . $nombreArchivo, base64_decode($datos));
        $fotoUrl = 'uploads/fotos/' . $nombreArchivo;
    }

    // ── 7. Insertar registro en BD ────────────────────────────
    $fecha = date('Y-m-d');

    $stmt = $pdo->prepare("
        INSERT INTO registros_comida
            (usuario_id, alimento_id, nombre_libre, tipo_comida, porcion,
             hora_registro, fecha_registro, foto_url, puntos_asignados, es_cheat_meal)
        VALUES
            (:uid, :aid, :nombre, :tipo, :porcion,
             :hora, :fecha, :foto, :puntos, :cheat)
    ");
    $stmt->execute([
        ':uid'    => $usuarioId,
        ':aid'    => $alimentoId,
        ':nombre' => $nombreLibre,
        ':tipo'   => $tipoComida,
        ':porcion'=> $porcion,
        ':hora'   => $hora . ':00',
        ':fecha'  => $fecha,
        ':foto'   => $fotoUrl,
        ':puntos' => $puntosFinales,
        ':cheat'  => ($puntosFinales < 0) ? 1 : 0,
    ]);
    $nuevoRegistroId = $pdo->lastInsertId();

    // ── 8. Actualizar puntos totales del usuario ──────────────
    $pdo->prepare("
        UPDATE usuarios SET puntos = puntos + :pts WHERE id = :uid
    ")->execute([':pts' => $puntosFinales, ':uid' => $usuarioId]);

    // ── 9. Actualizar / crear resumen diario ──────────────────
    $campoOk = match($tipoComida) {
        'Desayuno' => 'desayuno_ok',
        'Comida'   => 'comida_ok',
        'Cena'     => 'cena_ok',
        default    => null,
    };

    $sqlResumen = "
        INSERT INTO resumen_diario
            (usuario_id, fecha, puntos_totales" . ($campoOk ? ", $campoOk" : "") . ")
        VALUES
            (:uid, :fecha, :pts" . ($campoOk ? ", 1" : "") . ")
        ON DUPLICATE KEY UPDATE
            puntos_totales = puntos_totales + :pts2
            " . ($campoOk ? ", $campoOk = 1" : "") . "
    ";
    $pdo->prepare($sqlResumen)->execute([
        ':uid'   => $usuarioId,
        ':fecha' => $fecha,
        ':pts'   => $puntosFinales,
        ':pts2'  => $puntosFinales,
    ]);

    // ── 10. Respuesta exitosa ─────────────────────────────────
    $mensajeRacha = '';
    if ($multiplicadorRacha > 1.0 && $puntosFinales > 0) {
        $mensajeRacha = ' (×' . number_format($multiplicadorRacha, 1) . ' por racha de ' . $rachaDias . ' días 🔥)';
    }

    echo json_encode([
        'ok'                  => true,
        'registro_id'         => (int) $nuevoRegistroId,
        'puntos'              => $puntosFinales,
        'racha_dias'          => $rachaDias,
        'multiplicador_racha' => $multiplicadorRacha,
        'mensaje'             => '¡Registro guardado! ' . ($puntosFinales >= 0 ? "+$puntosFinales pts 🎉" : "$puntosFinales pts") . $mensajeRacha,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error de base de datos: ' . $e->getMessage(),
    ]);
}