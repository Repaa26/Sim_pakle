<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

// --- HELPER ---
function formatRupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
}

$username_display = $_SESSION['username'];
$role_display = $_SESSION['role'];

// --- LOGIKA FILTER TANGGAL ---
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// --- 1. RINGKASAN EKSEKUTIF ---
$sqlSummary = "SELECT 
                SUM(total_harga) as total_omset, 
                COUNT(id_pesanan) as total_pesanan,
                AVG(total_harga) as rata_rata_pesanan
               FROM pesanan 
               WHERE DATE(tgl_pesanan) BETWEEN ? AND ?";
$stmtSum = $pdo->prepare($sqlSummary);
$stmtSum->execute([$tgl_awal, $tgl_akhir]);
$summary = $stmtSum->fetch();

// --- 2. PRODUK PALING LARIS ---
$sqlLaris = "SELECT b.nama_barang, SUM(dp.jumlah) as total_terjual
             FROM detail_pesanan dp
             JOIN barang b ON dp.id_barang = b.id_barang
             JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
             WHERE DATE(p.tgl_pesanan) BETWEEN ? AND ?
             GROUP BY b.id_barang 
             ORDER BY total_terjual DESC LIMIT 1";
$stmtLaris = $pdo->prepare($sqlLaris);
$stmtLaris->execute([$tgl_awal, $tgl_akhir]);
$produkTerlaris = $stmtLaris->fetch();

// --- 3. DATA TABEL DETAIL ---
$sqlDetail = "SELECT p.id_pesanan, p.tgl_pesanan, pl.nama_pelanggan, p.total_harga, pem.status_bayar 
              FROM pesanan p 
              JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
              JOIN pembayaran pem ON p.id_pembayaran = pem.id_pembayaran 
              WHERE DATE(p.tgl_pesanan) BETWEEN ? AND ?
              ORDER BY p.tgl_pesanan DESC";
$stmtDetail = $pdo->prepare($sqlDetail);
$stmtDetail->execute([$tgl_awal, $tgl_akhir]);
$laporanData = $stmtDetail->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pemilik - Pakle Sport</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Inter:wght@400;600;700;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-black flex h-screen overflow-hidden text-white">
    <div class="flex h-screen overflow-hidden"> <?php require 'sidebar.php'; ?>
        <main class="flex-1 overflow-y-auto">
    </div>

    <main class="flex-1 custom-dark p-12 overflow-y-auto">
        <header class="flex justify-between items-end mb-12 border-b border-gray-800/50 pb-8 no-print">
            <div>
                <h2 class="heading-font text-white text-4xl uppercase border-gray-800/50">Laporan Pemilik</h2>
                <p class="text-gray-500 text-xl italic font-light tracking-wide">Design your vision. We craft
                    perfection.
            </div>

            <form action="" method="GET"
                class="flex items-center space-x-4 bg-[#1a1a1a] p-4 rounded-3xl border border-gray-800">
                <div class="flex flex-col">
                    <label class="text-[9px] font-black text-gray-500 uppercase mb-1 ml-1">Dari Tanggal</label>
                    <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>"
                        class="bg-black text-white border border-gray-800 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                </div>
                <div class="flex flex-col">
                    <label class="text-[9px] font-black text-gray-500 uppercase mb-1 ml-1">Sampai Tanggal</label>
                    <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>"
                        class="bg-black text-white border border-gray-800 rounded-xl px-4 py-2 text-xs outline-none focus:border-orange-500">
                </div>
                <button type="submit"
                    class="mt-4 bg-orange-500 text-black px-8 py-2.5 rounded-xl font-black text-[10px] uppercase transition-all shadow-lg shadow-orange-900/20">Analisa</button>
            </form>
        </header>

        <div class="grid grid-cols-4 gap-6 mb-12 no-print">
            <div class="card-table p-8 border border-gray-800 relative overflow-hidden group">
                <p class="text-orange-500 font-black text-[10px] mb-4 uppercase tracking-[0.3em] heading-font">Total
                    Omset Kotor</p>
                <p class="text-white text-xl font-black"><?= formatRupiah($summary['total_omset'] ?? 0) ?></p>
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-orange-500/10 rounded-full blur-2xl"></div>
            </div>
            <div class="card-table p-8 border border-gray-800 shadow-2xl">
                <p class="text-blue-400 font-black text-[10px] mb-4 uppercase tracking-[0.3em] heading-font">Total
                    Pesanan</p>
                <p class="text-white text-4xl font-black"><?= $summary['total_pesanan'] ?> <span
                        class="text-[10px] text-gray-600 font-bold uppercase">Volume</span></p>
            </div>
            <div class="card-table p-8 border border-gray-800 shadow-2xl">
                <p class="text-green-400 font-black text-[10px] mb-4 uppercase tracking-[0.3em] heading-font">Rata-Rata
                    Pesanan</p>
                <p class="text-white text-xl font-black"><?= formatRupiah($summary['rata_rata_pesanan'] ?? 0) ?></p>
            </div>
            <div class="card-table p-8 border border-gray-800 shadow-2xl">
                <p class="text-purple-400 font-black text-[10px] mb-4 uppercase tracking-[0.3em] heading-font">Produk
                    Terlaris</p>
                <p class="text-white text-xs font-black uppercase leading-tight mt-2">
                    <?= $produkTerlaris['nama_barang'] ?? 'Tidak Ada Data' ?>
                </p>
                <p class="text-[9px] text-gray-500 font-bold mt-1 uppercase">
                    <?= $produkTerlaris['total_terjual'] ?? 0 ?> Item Terjual
                </p>
            </div>
        </div>

        <div id="area-cetak" class="card-table p-10 border border-gray-800/50 shadow-2xl mb-12">
            <div class="flex justify-between items-center mb-10 no-print">
                <h3 class="heading-font text-white text-3xl uppercase tracking-widest">Catatan Transaksi</h3>
                <button onclick="window.print()"
                    class="text-[12px] font-black uppercase tracking-widest bg-white text-black px-6 py-2.5 rounded-full hover:bg-orange-500 hover:text-white transition-all">
                    <i class="fas fa-print mr-2"></i> Cetak Laporan
                </button>
            </div>

            <div class="hidden print:block mb-6 text-center">
                <h1 class="text-2xl font-bold uppercase">Laporan Penjualan Pakle Sport</h1>
                <p class="text-sm">Periode: <?= date('d/m/Y', strtotime($tgl_awal)) ?> -
                    <?= date('d/m/Y', strtotime($tgl_akhir)) ?>
                </p>
                <hr class="my-4 border-black">
            </div>

            <table class="w-full text-left">
                <thead>
                    <tr
                        class="text-gray-500 print:text-black text-[13px] font-black uppercase tracking-widest border-b border-gray-800 print:border-black">
                        <th class="pb-5 px-4">Tanggal</th>
                        <th class="pb-5">ID Pesanan</th>
                        <th class="pb-5">Nama Pelanggan</th>
                        <th class="pb-5">Nilai Transaksi</th>
                        <th class="pb-5 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 print:text-black text-[14px] font-bold tracking-wide uppercase">
                    <?php foreach ($laporanData as $row): ?>
                    <tr class="border-b border-gray-800/40 print:border-black/20">
                        <td class="py-5 px-4 font-mono text-[12px]"><?= date('d/m/Y', strtotime($row['tgl_pesanan'])) ?>
                        </td>
                        <td class="py-5 font-black tracking-widest">#<?= $row['id_pesanan'] ?></td>
                        <td class="py-5"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                        <td class="py-5 font-mono"><?= formatRupiah($row['total_harga']) ?></td>
                        <td class="py-5 text-center">
                            <span class="font-black"><?= $row['status_bayar'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
    function toggleDropdown() {
        document.getElementById('service-dropdown').classList.toggle('show');
    }
    </script>
</body>

</html>