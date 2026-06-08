<?php
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'db.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: historial de canjes ─────────────────────────────────────────────────
// ?usuario_id=2          → canjes de ese usuario
// ?todos=1               → todos los canjes (admin)
if ($method === 'GET') {
    if (isset($_GET['todos']) && $_GET['todos'] == '1') {
        $sql = '
            SELECT c.id, c.usuario_id, u.username, c.articulo_id,
                   a.nombre, a.icono AS emoji, c.puntos_gastados AS costo,
                   c.estado, c.fecha_canje
            FROM canjes c
            JOIN tienda_articulos a ON a.id = c.articulo_id
            JOIN usuarios u ON u.id = c.usuario_id
            ORDER BY c.fecha_canje DESC
        ';
        $rows = $db->query($sql)->fetchAll();
    } else {
        $uid = (int) ($_GET['usuario_id'] ?? 0);
        if (!$uid) { echo json_encode(['ok' => false, 'error' => 'usuario_id requerido']); exit; }
        $stmt = $db->prepare('
            SELECT c.id, c.usuario_id, c.articulo_id,
                   a.nombre, a.icono AS emoji, c.puntos_gastados AS costo,
                   c.estado, c.fecha_canje
            FROM canjes c
            JOIN tienda_articulos a ON a.id = c.articulo_id
            WHERE c.usuario_id = ?
            ORDER BY c.fecha_canje DESC
        ');
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll();
    }

    // Formatear fechas legibles en español
    foreach ($rows as &$r) {
        $dt = new DateTime($r['fecha_canje'], new DateTimeZone('America/Mexico_City'));
        $r['fecha'] = $dt->format('d/m/Y');
        $r['hora']  = $dt->format('H:i');
        $r['costo'] = (int) $r['costo'];
    }

    echo json_encode(['ok' => true, 'canjes' => $rows]);
    exit;
}

// ── POST: realizar un canje ──────────────────────────────────────────────────
if ($method === 'POST') {
    $body       = json_decode(file_get_contents('php://input'), true);
    $uid        = (int) ($body['usuario_id']  ?? 0);
    $articulo_id = (int) ($body['articulo_id'] ?? 0);

    if (!$uid || !$articulo_id) {
        echo json_encode(['ok' => false, 'error' => 'usuario_id y articulo_id son obligatorios']); exit;
    }

    // Verificar artículo activo
    $stmtA = $db->prepare('SELECT * FROM tienda_articulos WHERE id = ? AND activo = 1');
    $stmtA->execute([$articulo_id]);
    $articulo = $stmtA->fetch();
    if (!$articulo) {
        echo json_encode(['ok' => false, 'error' => 'Artículo no disponible']); exit;
    }

    // Verificar puntos del usuario
    $stmtU = $db->prepare('SELECT puntos FROM usuarios WHERE id = ?');
    $stmtU->execute([$uid]);
    $usuario = $stmtU->fetch();
    if (!$usuario) {
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']); exit;
    }

    $costo = (int) $articulo['costo_pts'];
    if ($usuario['puntos'] < $costo) {
        echo json_encode(['ok' => false, 'error' => 'Puntos insuficientes']); exit;
    }

    // Transacción: descontar puntos + insertar canje
    $db->beginTransaction();
    try {
        $db->prepare('UPDATE usuarios SET puntos = puntos - ? WHERE id = ?')
           ->execute([$costo, $uid]);

        $db->prepare(
            'INSERT INTO canjes (usuario_id, articulo_id, puntos_gastados, estado)
             VALUES (?, ?, ?, "pendiente")'
        )->execute([$uid, $articulo_id, $costo]);

        $canjeId = (int) $db->lastInsertId();
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Error al procesar el canje: ' . $e->getMessage()]); exit;
    }

    // Leer puntos actualizados
    $stmtU->execute([$uid]);
    $ptsNuevos = (int) $stmtU->fetch()['puntos'];

    $ahora = new DateTime('now', new DateTimeZone('America/Mexico_City'));

    echo json_encode([
        'ok'     => true,
        'puntos' => $ptsNuevos,
        'canje'  => [
            'id'     => $canjeId,
            'nombre' => $articulo['nombre'],
            'emoji'  => $articulo['icono'],
            'costo'  => $costo,
            'estado' => 'pendiente',
            'fecha'  => $ahora->format('d/m/Y'),
            'hora'   => $ahora->format('H:i'),
        ],
    ]);
    exit;
}

// ── PUT: marcar canje como entregado ─────────────────────────────────────────
if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = (int) ($body['id'] ?? 0);

    if (!$id) { echo json_encode(['ok' => false, 'error' => 'ID requerido']); exit; }

    $db->prepare(
        'UPDATE canjes SET estado = "aprobado", fecha_resolucion = NOW() WHERE id = ?'
    )->execute([$id]);

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Método no soportado']);