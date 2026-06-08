<?php
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'db.php'; // tu archivo con getDB()

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: lista de artículos ──────────────────────────────────────────────────
if ($method === 'GET') {
    $soloActivos = isset($_GET['activos']) && $_GET['activos'] == '1';
    $sql = $soloActivos
        ? 'SELECT * FROM tienda_articulos WHERE activo = 1 ORDER BY id ASC'
        : 'SELECT * FROM tienda_articulos ORDER BY id ASC';
    $items = $db->query($sql)->fetchAll();

    // Normalizar: la columna en BD se llama 'icono', en JS se usa 'emoji'
    foreach ($items as &$i) {
        $i['emoji']  = $i['icono'];
        $i['desc']   = $i['descripcion'];
        $i['costo']  = (int) $i['costo_pts'];
        $i['activo'] = (bool) $i['activo'];
    }
    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
}

// ── POST: crear artículo ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($body['nombre'] ?? '');
    $desc   = trim($body['desc']   ?? '');
    $costo  = (int) ($body['costo'] ?? 0);
    $emoji  = trim($body['emoji']  ?? '🎁');
    $tipo   = in_array($body['tipo'] ?? '', ['consumible','cosmético','especial'])
              ? $body['tipo'] : 'consumible';

    if (!$nombre || $costo <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nombre y costo son obligatorios']); exit;
    }

    $stmt = $db->prepare(
        'INSERT INTO tienda_articulos (nombre, descripcion, icono, costo_pts, tipo, activo)
         VALUES (?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([$nombre, $desc, $emoji, $costo, $tipo]);
    $id = (int) $db->lastInsertId();

    echo json_encode([
        'ok'   => true,
        'item' => ['id' => $id, 'nombre' => $nombre, 'desc' => $desc,
                   'emoji' => $emoji, 'costo' => $costo, 'activo' => true]
    ]);
    exit;
}

// ── PUT: editar artículo ─────────────────────────────────────────────────────
if ($method === 'PUT') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $id    = (int) ($body['id'] ?? 0);
    $nombre = trim($body['nombre'] ?? '');
    $desc   = trim($body['desc']   ?? '');
    $costo  = (int) ($body['costo'] ?? 0);
    $emoji  = trim($body['emoji']  ?? '🎁');

    if (!$id || !$nombre || $costo <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Datos incompletos']); exit;
    }

    $stmt = $db->prepare(
        'UPDATE tienda_articulos SET nombre=?, descripcion=?, icono=?, costo_pts=? WHERE id=?'
    );
    $stmt->execute([$nombre, $desc, $emoji, $costo, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE: eliminar artículo ────────────────────────────────────────────────
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = (int) ($body['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'ID requerido']); exit; }

    $db->prepare('DELETE FROM tienda_articulos WHERE id=?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Método no soportado']);