<?php
require 'koneksi.php';

$id_pelanggan = $_GET['id_pelanggan'] ?? 0;

$sql = "SELECT p.id_pesanan, p.tgl_pesanan, p.total_harga, pem.status_bayar 
        FROM pesanan p 
        JOIN pembayaran pem ON p.id_pembayaran = pem.id_pembayaran 
        WHERE p.id_pelanggan = ? 
        ORDER BY p.tgl_pesanan DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_pelanggan]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format data sebelum dikirim ke JavaScript
$result = [];
foreach($orders as $o) {
    $o['total_harga_formatted'] = "Rp " . number_format($o['total_harga'], 0, ',', '.');
    $o['tgl_pesanan'] = date('d/m/Y H:i', strtotime($o['tgl_pesanan']));
    $result[] = $o;
}

header('Content-Type: application/json');
echo json_encode($result);