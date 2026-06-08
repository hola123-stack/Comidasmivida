<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

    $stmtUser = $pdo->prepare("SELECT id, username, nombre_real, puntos, racha_dias, meta_diaria FROM usuarios WHERE id = :uid AND activo = 1");
    $stmtUser->execute([':uid' => $usuarioId]);
    $usuario = $stmtUser->fetch();

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    $usuario['id']          = (int) $usuario['id'];
    $usuario['puntos']      = (int) $usuario['puntos'];
    $usuario['racha_dias']  = (int) $usuario['racha_dias'];
    $usuario['meta_diaria'] = (int) $usuario['meta_diaria'];

    $stmtSemana = $pdo->prepare("SELECT rc.tipo_comida, rc.nombre_libre, rc.porcion, rc.puntos_asignados, rc.foto_url, DATE_FORMAT(rc.fecha_registro, '%Y-%m-%d') AS fecha, DAYNAME(rc.fecha_registro) AS dia_en, TIME_FORMAT(rc.hora_registro, '%h:%i %p') AS hora FROM registros_comida rc WHERE rc.usuario_id = :uid AND rc.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) ORDER BY rc.fecha_registro ASC, rc.hora_registro ASC");
    $stmtSemana->execute([':uid' => $usuarioId]);
    $registrosSemana = $stmtSemana->fetchAll();

    $stmtPuntosDia = $pdo->prepare("SELECT DATE_FORMAT(fecha_registro, '%Y-%m-%d') AS fecha, DAYNAME(fecha_registro) AS dia_en, SUM(puntos_asignados) AS puntos FROM registros_comida WHERE usuario_id = :uid AND fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY fecha_registro ORDER BY fecha_registro ASC");
    $stmtPuntosDia->execute([':uid' => $usuarioId]);
    $puntosPorDia = $stmtPuntosDia->fetchAll();

    $diasES = [
        'Monday'    => 'Lunes',
        'Tuesday'   => 'Martes',
        'Wednesday' => 'Miercoles',
        'Thursday'  => 'Jueves',
        'Friday'    => 'Viernes',
        'Saturday'  => 'Sabado',
        'Sunday'    => 'Domingo',
    ];

    $matrizIndex = [];
    foreach ($registrosSemana as $r) {
        $key = $r['fecha'] . '_' . $r['tipo_comida'];
        $matrizIndex[$key] = [
            'nombre'     => $r['nombre_libre'],
            'porcion'    => $r['porcion'],
            'puntos'     => (int)$r['puntos_asignados'],
            'hora'       => $r['hora'],
            'foto'       => $r['foto_url'],
            'dia'        => $diasES[$r['dia_en']] ?? $r['dia_en'],
        ];
    }

    $semana = [];
    for ($i = 6; $i >= 0; $i--) {
        $fecha = date('Y-m-d', strtotime("-$i days"));
        $diaEN = date('l', strtotime("-$i days"));
        $diaES = $diasES[$diaEN] ?? $diaEN;

        $comidas = [];
        foreach (['Desayuno', 'Comida', 'Cena'] as $tipo) {
            $key = $fecha . '_' . $tipo;
            $comidas[$tipo] = isset($matrizIndex[$key])
                ? array_merge($matrizIndex[$key], ['registrado' => true])
                : ['registrado' => false, 'nombre' => 'No registrado', 'puntos' => 0];
        }

        $semana[] = [
            'fecha'   => $fecha,
            'dia'     => $diaES,
            'comidas' => $comidas,
        ];
    }

    $puntosIndex = [];
    foreach ($puntosPorDia as $p) {
        $puntosIndex[$p['fecha']] = (int)$p['puntos'];
    }

    $puntosGrafica = [];
    for ($i = 6; $i >= 0; $i--) {
        $fecha = date('Y-m-d', strtotime("-$i days"));
        $diaEN = date('l', strtotime("-$i days"));
        $puntosGrafica[] = [
            'dia'    => substr($diasES[$diaEN] ?? $diaEN, 0, 3),
            'puntos' => $puntosIndex[$fecha] ?? 0,
        ];
    }

    echo json_encode([
        'ok'      => true,
        'usuario' => $usuario,
        'semana'  => $semana,
        'grafica' => $puntosGrafica,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
