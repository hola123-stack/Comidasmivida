<?php
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'db.php';

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int) ($body['id'] ?? 0);

if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'ID requerido']); exit;
}

$db = getDB();

// Leer estado actual
$row = $db->prepare('SELECT activo FROM tienda_articulos WHERE id = ?');
$row->execute([$id]);
$item = $row->fetch();

if (!$item) {
    echo json_encode(['ok' => false, 'error' => 'Artículo no encontrado']); exit;
}

$nuevoEstado = $item['activo'] ? 0 : 1;
$db->prepare('UPDATE tienda_articulos SET activo = ? WHERE id = ?')
   ->execute([$nuevoEstado, $id]);

echo json_encode(['ok' => true, 'activo' => (bool) $nuevoEstado]);