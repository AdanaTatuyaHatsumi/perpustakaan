<?php
$host = 'localhost';
$dbname = 'perpustakaan_kampus';
$username = 'root';
$password = 'K.irun150201';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi untuk generate kode
function generateKode($prefix, $table, $field) {
    global $pdo;
    $query = $pdo->query("SELECT $field FROM $table ORDER BY $field DESC LIMIT 1");
    $row = $query->fetch();
    
    if($row) {
        $lastCode = $row[$field];
        $num = (int)substr($lastCode, strlen($prefix)) + 1;
    } else {
        $num = 1;
    }
    
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// Fungsi untuk cek session
function checkLogin() {
    session_start();
    if(!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
        header('Location: login.php');
        exit;
    }
}
?>