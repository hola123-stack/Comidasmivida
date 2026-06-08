<?php
// ============================================================
//  NutriTrack - Actualizador de Rachas
//  Llamar cada noche con un cron job, ej:
//    0 23 * * * php /ruta/al/proyecto/api/actualizar_racha.php
//
//  También se puede llamar manualmente vía HTTP con ?secret=TU_CLAVE
// ============================================================

if (php_sapi_name() !== 'cli') {
    $secret = getenv('RACHA_SECRET') ?: 'nutritrack_racha_2024';
    if (($_GET['secret'] ?? '') !== $secret) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json');
}

require_once __DIR__ . '/db.php';

try {
    $pdo = getDB();

    $testUsuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;

    if ($testUsuarioId > 0) {
        $stmt = $pdo->prepare("SELECT id, username, racha_dias FROM usuarios WHERE id = :uid AND activo = 1");
        $stmt->execute([':uid' => $testUsuarioId]);
    } else {
        $stmt = $pdo->query("SELECT id, username, racha_dias FROM usuarios WHERE activo = 1");
    }

    $usuarios  = $stmt->fetchAll();
    $hoy       = date('Y-m-d');
    $resultado = [];

    foreach ($usuarios as $u) {
        $uid         = $u['id'];
        $rachaActual = (int) $u['racha_dias'];

        $stmtHoy = $pdo->prepare("
            SELECT
                MAX(CASE WHEN tipo_comida = 'Desayuno' THEN 1 ELSE 0 END) AS desayuno,
                MAX(CASE WHEN tipo_comida = 'Comida'   THEN 1 ELSE 0 END) AS comida,
                MAX(CASE WHEN tipo_comida = 'Cena'     THEN 1 ELSE 0 END) AS cena,
                COUNT(*) AS total_registros
            FROM registros_comida
            WHERE usuario_id = :uid AND fecha_registro = :hoy
        ");
        $stmtHoy->execute([':uid' => $uid, ':hoy' => $hoy]);
        $registrosHoy = $stmtHoy->fetch();

        $tieneDesayuno = (bool) $registrosHoy['desayuno'];
        $tieneComida   = (bool) $registrosHoy['comida'];
        $tieneCena     = (bool) $registrosHoy['cena'];
        $totalHoy      = (int)  $registrosHoy['total_registros'];
        $completoHoy   = $tieneDesayuno && $tieneComida && $tieneCena;

        if ($completoHoy) {
            $nuevaRacha = $rachaActual + 1;
            $accion     = 'incrementada';
        } elseif ($totalHoy === 0) {
            $nuevaRacha = 0;
            $accion     = 'reiniciada';
        } else {
            $nuevaRacha = $rachaActual;
            $accion     = 'mantenida';
        }

        $nivelRacha         = (int) floor($nuevaRacha / 10);
        $multiplicadorRacha = min(2.0, 1.0 + $nivelRacha * 0.1);

        $pdo->prepare("
            UPDATE usuarios SET racha_dias = :racha WHERE id = :uid
        ")->execute([':racha' => $nuevaRacha, ':uid' => $uid]);

        $pdo->prepare("
            INSERT INTO resumen_diario (usuario_id, fecha, puntos_totales)
            VALUES (:uid, :hoy, 0)
            ON DUPLICATE KEY UPDATE usuario_id = usuario_id
        ")->execute([':uid' => $uid, ':hoy' => $hoy]);

        $resultado[] = [
            'usuario'        => $u['username'],
            'racha_anterior' => $rachaActual,
            'racha_nueva'    => $nuevaRacha,
            'multiplicador'  => $multiplicadorRacha,
            'accion'         => $accion,
            'comidas_hoy'    => [
                'desayuno' => $tieneDesayuno,
                'comida'   => $tieneComida,
                'cena'     => $tieneCena,
            ],
        ];

        $log = "[{$u['username']}] Racha: $rachaActual → $nuevaRacha (×$multiplicadorRacha) — $accion";
        if (php_sapi_name() === 'cli') {
            echo $log . PHP_EOL;
        }
    }

    if (php_sapi_name() !== 'cli') {
        echo json_encode(['ok' => true, 'fecha' => $hoy, 'usuarios' => $resultado], JSON_PRETTY_PRINT);
    }

} catch (PDOException $e) {
    $msg = 'Error de base de datos: ' . $e->getMessage();
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $msg]);
    }
}