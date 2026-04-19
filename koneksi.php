<?php
// Pengaturan Database
$host = 'localhost';
$db = 'sim_pakle';
$user = 'root';
$pass = ''; // Kosongkan jika menggunakan XAMPP standar
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opsi = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Membuat instance PDO untuk koneksi yang lebih aman
    $pdo = new PDO($dsn, $user, $pass, $opsi);
} catch (\PDOException $e) {
    // Jika gagal, tampilkan pesan kesalahan
    die("Koneksi database gagal: " . $e->getMessage());
}
?>