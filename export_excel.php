<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    exit("Akses ditolak");
}

// Ambil parameter tanggal dari URL
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// Perintah Header agar file didownload sebagai Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Penjualan_Pakle_Sport_" . $tgl_awal . "_ke_" . $tgl_akhir . ".xls");

// Ambil Data Transaksi
$sqlDetail = "SELECT p.id_pesanan, p.tgl_pesanan, pl.nama_pelanggan, p.total_harga, pem.status_bayar,
              GROUP_CONCAT(CONCAT(k.nama_kategori, ' [', v.jenis_lengan, '/', v.ukuran, '/', v.warna, '] (', dp.jumlah, 'x)') SEPARATOR ', ') as keterangan_barang
              FROM pesanan p 
              JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
              JOIN pembayaran pem ON p.id_pembayaran = pem.id_pembayaran 
              LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
              LEFT JOIN kategori k ON dp.id_kategori = k.id_kategori
              LEFT JOIN varian_barang v ON dp.id_varian = v.id_varian
              WHERE DATE(p.tgl_pesanan) BETWEEN ? AND ?
              GROUP BY p.id_pesanan
              ORDER BY p.tgl_pesanan DESC";
$stmtDetail = $pdo->prepare($sqlDetail);
$stmtDetail->execute([$tgl_awal, $tgl_akhir]);
$laporanData = $stmtDetail->fetchAll();

// Ambil Ringkasan Omset
$sqlSummary = "SELECT SUM(total_harga) as total_omset FROM pesanan WHERE DATE(tgl_pesanan) BETWEEN ? AND ?";
$stmtSum = $pdo->prepare($sqlSummary);
$stmtSum->execute([$tgl_awal, $tgl_akhir]);
$totalOmset = $stmtSum->fetch()['total_omset'] ?? 0;
?>

<center>
    <h2>LAPORAN PENJUALAN PAKLE SPORT</h2>
    <h4>Periode: <?= date('d/m/Y', strtotime($tgl_awal)) ?> - <?= date('d/m/Y', strtotime($tgl_akhir)) ?></h4>
</center>

<table border="1">
    <thead>
        <tr style="background-color: #f2f2f2; font-weight: bold; text-align: center;">
            <th style="vertical-align: middle; padding: 5px;">No</th>
            <th style="vertical-align: middle; padding: 5px;">Tanggal</th>
            <th style="vertical-align: middle; padding: 5px;">ID Pesanan</th>
            <th style="vertical-align: middle; padding: 5px;">Nama Pelanggan</th>
            <th style="vertical-align: middle; padding: 5px;">Keterangan Barang</th>
            <th style="vertical-align: middle; padding: 5px;">Nilai Transaksi (IDR)</th>
            <th style="vertical-align: middle; padding: 5px;">Status Pembayaran</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        foreach ($laporanData as $row):
            ?>
            <tr style="text-align: center; vertical-align: middle;">
                <td style="padding: 5px;"><?= $no++ ?></td>
                <td style="padding: 5px;"><?= date('d/m/Y', strtotime($row['tgl_pesanan'])) ?></td>
                <td style="padding: 5px;">#<?= $row['id_pesanan'] ?></td>
                <td style="padding: 5px;"><?= strtoupper($row['nama_pelanggan']) ?></td>
                <td style="padding: 5px;"><?= $row['keterangan_barang'] ?></td>
                <td style="padding: 5px;"><?= $row['total_harga'] ?></td>
                <td style="padding: 5px;"><?= strtoupper($row['status_bayar']) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr style="font-weight: bold; background-color: #eeeeee;">
            <td colspan="5" align="right" style="padding: 5px 15px;">TOTAL OMSET :</td>
            <td colspan="2" align="center" style="padding: 5px;"><?= $totalOmset ?></td>
        </tr>
    </tbody>
</table>