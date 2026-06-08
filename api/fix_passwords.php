<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Test</h2>";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=nutritrack;charset=utf8mb4','root','',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<p style='color:green'>Conexion OK</p>";
    $hash1 = password_hash('admin123', PASSWORD_BCRYPT);
    $hash2 = password_hash('nano', PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE usuarios SET password_hash=:h WHERE username='admin'")->execute([':h'=>$hash1]);
    $pdo->prepare("UPDATE usuarios SET password_hash=:h WHERE username='mivida'")->execute([':h'=>$hash2]);
    echo "<p style='color:green'>Passwords actualizados. Ya puedes login con admin/admin123 y mivida/nano</p>";
    $users = $pdo->query("SELECT id,username,rol FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    foreach($users as $u) echo "<p>{$u['id']} - {$u['username']} - {$u['rol']}</p>";
} catch(Exception $e) {
    echo "<p style='color:red'>Error: ".$e->getMessage()."</p>";
}