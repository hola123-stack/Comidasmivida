<?php
// ============================================================
//  NutriTrack - API: Historial de registros
//  GET /api/historial.php?usuario_id=2&semana=1
//
//  Parámetros:
//    usuario_id  (requerido) - ID del usuario
//    semana      (opcional)  - 1 = solo esta semana, 0 = todos
//    tipo        (opcional)  - Desayuno | Comida | Cena | Merienda
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$usuarioId  = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$soloSemana = isset($_GET['semana'])     ? (int)$_GET['semana']     : 0;
$tipo       = isset($_GET['tipo'])       ? trim($_GET['tipo'])       : '';

if ($usuarioId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'usuario_id requerido']);
    exit;
}

try {
    $pdo = getDB();

    $where  = ['rc.usuario_id = :uid'];
    $params = [':uid' => $usuarioId];

    if ($soloSemana) {
        $where[] = 'rc.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
    }
    if ($tipo) {
        $where[] = 'rc.tipo_comida = :tipo';
        $params[':tipo'] = $tipo;
    }

    $whereSQL = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            rc.id,
            rc.nombre_libre,
            rc.tipo_comida,
            rc.porcion,
            TIME_FORMAT(rc.hora_registro, '%h:%i %p')  AS hora,
            DATE_FORMAT(rc.fecha_registro, '%d/%m/%Y') AS fecha,
            DAYNAME(rc.fecha_registro)                  AS dia_semana,
            rc.puntos_asignados,
            rc.foto_url,
            rc.es_cheat_meal,
            a.nombre   AS nombre_catalogo,
            a.categoria
        FROM registros_comida rc
        LEFT JOIN alimentos a ON a.id = rc.alimento_id
        WHERE {$whereSQL}
        ORDER BY rc.fecha_registro DESC, rc.hora_registro DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $registros = $stmt->fetchAll();

    $stmtTotales = $pdo->prepare("
        SELECT
            SUM(CASE WHEN puntos_asignados > 0 THEN puntos_asignados ELSE 0 END) AS ganados,
            SUM(CASE WHEN puntos_asignados < 0 THEN puntos_asignados ELSE 0 END) AS perdidos,
            COUNT(*) AS total
        FROM registros_comida
        WHERE usuario_id = :uid
          AND fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmtTotales->execute([':uid' => $usuarioId]);
    $totales = $stmtTotales->fetch();

    $diasES = [
        'Monday'    => 'Lunes',    'Tuesday'  => 'Martes',
        'Wednesday' => 'Miércoles','Thursday' => 'Jueves',
        'Friday'    => 'Viernes',  'Saturday' => 'Sábado',
        'Sunday'    => 'Domingo',
    ];

    foreach ($registros as &$r) {
        $r['dia_semana']       = $diasES[$r['dia_semana']] ?? $r['dia_semana'];
        $r['puntos_asignados'] = (int)$r['puntos_asignados'];
        $r['es_cheat_meal']    = (bool)$r['es_cheat_meal'];
        $r['emoji'] = match($r['tipo_comida']) {
            'Desayuno' => '🌅',
            'Comida'   => '🍽️',
            'Cena'     => '🌙',
            'Merienda' => '🍎',
            default    => '🍴',
        };
    }

    echo json_encode([
        'ok'        => true,
        'registros' => $registros,
        'totales'   => [
            'ganados'  => (int)($totales['ganados']  ?? 0),
            'perdidos' => (int)($totales['perdidos'] ?? 0),
            'total'    => (int)($totales['total']    ?? 0),
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}