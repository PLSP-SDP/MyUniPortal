<?php 
header("Content-Type: text/html; charset=UTF-8");
$env = parse_ini_file(__DIR__ . "/.env");

$host = $env['DB_HOST'];
$dbname = $env['DB_NAME'];
$dbuser = $env['DB_USER'];
$dbpass = $env['DB_PASS'];
try {
    $pdo = new PDO ("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
$success = "Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>