<?php
session_start();
require 'koneksi.php';

// Proteksi Halaman
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

// --- 1. DATA STATISTIK BERDASARKAN ROLE ---
if ($role_display === 'admin') {
    // Admin lihat Duit
    $sqlPemasukan = "SELECT SUM(CASE WHEN pem.status_bayar = 'lunas' THEN p.total_harga ELSE pem.jumlah_bayar END) as total 
                     FROM pembayaran pem JOIN pesanan p ON pem.id_pembayaran = p.id_pembayaran";
    $stat_utama = $pdo->query($sqlPemasukan)->fetch()['total'] ?? 0;
    $label_utama = "Total Pemasukan";
} else {
    // Karyawan lihat Stok (Penting buat Produksi) - UPDATE KE TABEL varian_barang
    $stat_utama = $pdo->query("SELECT SUM(stok_tersedia) FROM varian_barang")->fetchColumn() ?? 0;
    $label_utama = "Total Stok Bahan";
}

// Statistik Umum (Keduanya bisa lihat)
$pesananBaru = $pdo->query("SELECT COUNT(*) FROM produksi WHERE status_produksi = 'antrean'")->fetchColumn();
$dalamProduksi = $pdo->query("SELECT COUNT(*) FROM produksi WHERE status_produksi = 'proses'")->fetchColumn();
$siapAmbil = $pdo->query("SELECT COUNT(*) FROM produksi WHERE status_produksi = 'siap diambil'")->fetchColumn();

// --- 2. DATA GRAFIK (7 HARI TERAKHIR) ---
$dailySales = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayName = date('D', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE DATE(tgl_pesanan) = ?");
    $stmt->execute([$date]);
    $dailySales[] = ['day' => $dayName, 'count' => $stmt->fetchColumn(), 'isToday' => ($i == 0)];
}

// --- 3. TOP PRODUCTS & RECENT TRANSACTIONS ---
// UPDATE: JOIN ke tabel kategori karena tabel barang sudah tidak ada
$topProducts = $pdo->query("SELECT k.nama_kategori as nama_barang, SUM(dp.jumlah) as total 
                            FROM detail_pesanan dp 
                            JOIN kategori k ON dp.id_kategori = k.id_kategori 
                            GROUP BY k.id_kategori 
                            ORDER BY total DESC LIMIT 3")->fetchAll();

$limit = 5;
$page = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;
$offset = ($page - 1) * $limit;
$recentOrders = $pdo->query("SELECT p.id_pesanan, pl.nama_pelanggan, p.total_harga, p.tgl_pesanan, pem.status_bayar 
                             FROM pesanan p 
                             JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                             JOIN pembayaran pem ON p.id_pembayaran = pem.id_pembayaran 
                             ORDER BY p.tgl_pesanan DESC LIMIT $limit OFFSET $offset")->fetchAll();
$totalHalaman = ceil($pdo->query("SELECT COUNT(*) FROM pesanan")->fetchColumn() / $limit);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pakle Production</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Inter:wght@400;600;700;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body class="flex h-screen overflow-hidden">

    <?php require 'sidebar.php'; ?>

    <main class="flex-1 custom-dark p-12 overflow-y-auto">
        <header class="flex justify-between items-baseline mb-12 border-b border-gray-800/50 pb-8">
            <div>
                <h2 class="heading-font text-white text-4xl uppercase border-gray-800/50">Dashboard</h2>
                <p class="text-gray-500 text-xl italic font-light tracking-wide">Design your vision. We
                    craft perfection. Leave it to Pakle Production.</p>
            </div>
            <div class="text-right">
                <p class="text-white font-bold text-sm uppercase"><?= date('l, d F Y') ?></p>
                <p class="text-gray-600 text-[10px] font-black uppercase tracking-widest"><?= $role_display ?> Session
                    Active</p>
            </div>
        </header>

        <div class="grid grid-cols-4 gap-6 mb-16">
            <div class="card-table p-8 border border-gray-800 shadow-2xl relative overflow-hidden group">
                <p
                    class="<?= $role_display === 'admin' ? 'text-orange-500' : 'text-yellow-500' ?> font-black text-[10px] mb-4 uppercase tracking-[0.3em] heading-font">
                    <?= $label_utama ?>
                </p>
                <p class="text-white text-2xl font-black">
                    <?= $role_display === 'admin' ? formatRupiah($stat_utama) : $stat_utama . " <span class='text-xs'>Units</span>" ?>
                </p>
                <div
                    class="absolute -right-4 -top-4 w-20 h-20 <?= $role_display === 'admin' ? 'bg-orange-500/5' : 'bg-yellow-500/5' ?> rounded-full blur-2xl">
                </div>
            </div>

            <div class="card-table p-8 border border-gray-800 shadow-2xl">
                <p class="text-blue-400 font-black text-[10px] mb-4 uppercase tracking-[0.3em] heading-font">Antrean
                    Baru</p>
                <p class="text-white text-4xl font-black"><?= $pesananBaru ?> <span
                        class="text-[10px] text-gray-600 font-bold uppercase">Order</span></p>
            </div>

            <div class="card-table p-8 border border-gray-800 shadow-2xl">
                <p class="text-purple-400 font-black text-[10px] mb-4 uppercase tracking-[0.3em] heading-font">Sedang
                    Proses</p>
                <p class="text-white text-4xl font-black"><?= $dalamProduksi ?></p>
            </div>

            <div class="card-table p-8 border border-gray-800 shadow-2xl">
                <p class="text-green-400 font-black text-[10px] mb-4 uppercase tracking-[0.3em] heading-font">Siap
                    Diambil</p>
                <p class="text-white text-4xl font-black"><?= $siapAmbil ?></p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-8">
            <div class="col-span-2 card-table p-10 border border-gray-800/50 shadow-2xl">
                <div class="flex justify-between items-center mb-10">
                    <h3 class="heading-font text-white text-2xl uppercase tracking-widest">7 Days Performance</h3>
                    <span
                        class="text-[11px] text-gray-500 font-bold uppercase tracking-widest border border-gray-800 px-3 py-1 rounded-full">Order
                        Frequency</span>
                </div>
                <div class="flex items-end justify-between h-64 px-4">
                    <?php foreach ($dailySales as $data):
                        $maxHeight = max($data['count'] * 20, 10); ?>
                        <div class="flex flex-col items-center group w-full">
                            <div class="relative w-12 flex flex-col justify-end h-48">
                                <div
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 bg-white text-black text-[9px] font-black px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity uppercase">
                                    <?= $data['count'] ?> Order
                                </div>
                                <div class="chart-bar w-full rounded-t-xl <?= $data['isToday'] ? 'bg-orange-500' : 'bg-gray-800' ?>"
                                    style="height: <?= $maxHeight ?>px"></div>
                            </div>
                            <span
                                class="mt-4 text-[10px] font-black uppercase <?= $data['isToday'] ? 'text-orange-500' : 'text-gray-600' ?>"><?= $data['day'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card-table p-8 border border-gray-800/50 shadow-2xl">
                    <h3 class="heading-font text-xl text-white text-sm uppercase mb-6 tracking-widest">Best Selling
                    </h3>
                    <div class="space-y-4">
                        <?php foreach ($topProducts as $top): ?>
                            <div
                                class="flex justify-between items-center bg-black/40 p-4 rounded-2xl border border-gray-800/50">
                                <p class="text-[11px] font-black uppercase text-gray-300"><?= $top['nama_barang'] ?></p>
                                <p class="text-orange-500 font-mono font-bold text-xs"><?= $top['total'] ?> PCS</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div
                    class="bg-gradient-to-br from-orange-600 to-orange-900 p-8 rounded-[30px] shadow-2xl relative overflow-hidden group">
                    <i
                        class="fas fa-bolt absolute -right-4 -bottom-4 text-7xl text-white/10 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-black uppercase text-white text-lg leading-tight mb-2">Ready to work?</h4>
                    <p class="text-white/70 text-[12px] uppercase font-bold tracking-widest">Check the production line
                        now.</p>
                    <a href="produksi.php"
                        class="mt-6 inline-block bg-white text-black text-[12px] font-black px-6 py-3 rounded-full uppercase tracking-tighter hover:bg-black hover:text-white transition-all">Go
                        to Production</a>
                </div>
            </div>
        </div>

        <div class="card-table p-10 border border-gray-800/50 shadow-2xl mt-12">
            <h3 class="heading-font text-white text-2xl uppercase mb-8 tracking-widest">Recent Activity</h3>
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-500 text-[13px] font-black uppercase tracking-widest border-b border-gray-800">
                        <th class="pb-5 px-4">Order ID</th>
                        <th class="pb-5">Customer</th>
                        <th class="pb-5">Total</th>
                        <th class="pb-5">Payment</th>
                        <th class="pb-5 text-center">Date</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-[13px] font-bold tracking-wide uppercase">
                    <?php foreach ($recentOrders as $order): ?>
                        <tr class="border-b border-gray-800/40 hover:bg-white/5 transition-all">
                            <td class="py-5 px-4 text-orange-500 font-mono font-black">#<?= $order['id_pesanan'] ?></td>
                            <td class="py-5 text-white"><?= htmlspecialchars($order['nama_pelanggan']) ?></td>
                            <td class="py-5 font-mono"><?= formatRupiah($order['total_harga']) ?></td>
                            <td class="py-5">
                                <span
                                    class="px-3 py-1 rounded-full text-[11px] font-black <?= $order['status_bayar'] == 'lunas' ? 'text-green-500' : 'text-red-500' ?>">
                                    <?= $order['status_bayar'] ?>
                                </span>
                            </td>
                            <td class="py-5 text-center text-gray-600 font-mono text-[12px]">
                                <?= date('d/m/y H:i', strtotime($order['tgl_pesanan'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>

</html>