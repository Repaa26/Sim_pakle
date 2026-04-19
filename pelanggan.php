<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

// --- HELPER ---
function formatRupiah($angka) { return "Rp " . number_format($angka, 0, ',', '.'); }

// --- LOGIKA SEARCH & PAGINASI ---
$search = $_GET['q'] ?? '';
$limit = 10;
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$offset = ($page - 1) * $limit;

$whereClause = $search ? " WHERE nama_pelanggan LIKE ? OR no_telp LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

// Ambil Data Pelanggan + Hitung jumlah order mereka
$sql = "SELECT pl.*, COUNT(ps.id_pesanan) as total_order 
        FROM pelanggan pl 
        LEFT JOIN pesanan ps ON pl.id_pelanggan = ps.id_pelanggan 
        $whereClause 
        GROUP BY pl.id_pelanggan 
        ORDER BY nama_pelanggan ASC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pelanggan = $stmt->fetchAll();

// Hitung Total Data untuk Paginasi
$sqlCount = "SELECT COUNT(*) FROM pelanggan" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalHalaman = ceil($stmtCount->fetchColumn() / $limit);

$username_display = $_SESSION['username'];
$role_display = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Customer Database - Pakle Sport</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Inter:wght@400;600;700;900&display=swap"
        rel="stylesheet">
    <style>
    body {
        font-family: 'Inter', sans-serif;
        background: black;
        color: white;
    }

    .heading-font {
        font-family: 'Orbitron', sans-serif;
        letter-spacing: 0.05em;
    }

    .custom-dark {
        background-color: #121212;
    }

    .card-table {
        background-color: #1a1a1a;
        border-radius: 30px;
    }

    .input-dark {
        background-color: #2a2a2a;
        border: 1px solid #333;
        color: white;
    }
    </style>
</head>

<body class="flex h-screen overflow-hidden">

    <?php require 'sidebar.php'; ?>

    <main class="flex-1 custom-dark p-12 overflow-y-auto">
        <header class="flex justify-between items-baseline mb-12 border-b border-gray-800/50 pb-8">
            <div>
                <h2 class="heading-font text-white text-4xl uppercase text-orange-500">Pelanggan</h2>
                <p class="text-gray-500 text-[10px] font-black tracking-[0.4em] mt-2 uppercase">Customer Identity &
                    Order History</p>
            </div>

            <form action="" method="GET" class="relative w-80">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Nama/Telepon..."
                    class="w-full bg-white rounded-full py-3 px-6 text-sm text-black outline-none focus:ring-2 focus:ring-orange-500 transition-all">
                <button type="submit" class="absolute right-5 top-3.5 text-gray-400 hover:text-orange-500"><i
                        class="fas fa-search"></i></button>
            </form>
        </header>

        <div class="card-table p-10 border border-gray-800/50 shadow-2xl">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-800">
                        <th class="pb-5 px-4">Identitas</th>
                        <th class="pb-5">Kontak & Alamat</th>
                        <th class="pb-5 text-center">Total Order</th>
                        <th class="pb-5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-[11px] font-bold tracking-wide uppercase">
                    <?php foreach($pelanggan as $row): ?>
                    <tr class="border-b border-gray-800/40 hover:bg-white/5 transition-all group">
                        <td class="py-6 px-4">
                            <p class="text-white font-black text-sm tracking-tighter">
                                <?= htmlspecialchars($row['nama_pelanggan']) ?></p>
                            <p class="text-orange-500 text-[9px] font-mono">ID:
                                PL-<?= str_pad($row['id_pelanggan'], 4, '0', STR_PAD_LEFT) ?></p>
                        </td>
                        <td class="py-6">
                            <p class="text-gray-100 italic mb-1"><i
                                    class="fas fa-phone text-[9px] mr-2 text-gray-600"></i><?= $row['no_telp'] ?></p>
                            <p class="text-gray-500 text-[10px] lowercase leading-relaxed"><i
                                    class="fas fa-map-marker-alt text-[9px] mr-2"></i><?= $row['alamat'] ?></p>
                        </td>
                        <td class="py-6 text-center">
                            <span class="bg-white/5 border border-gray-800 px-4 py-1.5 rounded-full text-white">
                                <?= $row['total_order'] ?> PESANAN
                            </span>
                        </td>
                        <td class="py-6 text-center">
                            <button
                                onclick="showHistory(<?= $row['id_pelanggan'] ?>, '<?= addslashes($row['nama_pelanggan']) ?>')"
                                class="bg-orange-500 hover:bg-white text-black px-6 py-2.5 rounded-xl font-black text-[9px] uppercase transition-all">
                                RIWAYAT ORDER
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-10 flex justify-center space-x-2">
                <?php for ($i = 1; $i <= $totalHalaman; $i++): ?>
                <a href="?halaman=<?= $i ?>&q=<?= $search ?>"
                    class="w-10 h-10 flex items-center justify-center rounded-xl text-[10px] font-black transition-all <?= $i == $page ? 'bg-orange-500 text-black' : 'bg-white/5 text-gray-500 hover:text-white' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
    </main>

    <div id="modalHistory"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/90 backdrop-blur-sm px-4">
        <div
            class="bg-[#1a1a1a] w-full max-w-4xl rounded-[40px] border border-gray-800 p-10 shadow-2xl relative overflow-hidden">
            <div class="flex justify-between items-center mb-10 border-b border-gray-800 pb-6">
                <div>
                    <h3 id="historyTitle" class="heading-font text-white text-2xl uppercase text-orange-500">Order
                        History</h3>
                    <p id="customerName" class="text-gray-500 text-[10px] font-black tracking-widest uppercase mt-1">
                    </p>
                </div>
                <button onclick="closeModal()" class="text-gray-500 hover:text-white transition-colors text-2xl">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>

            <div class="max-h-[500px] overflow-y-auto pr-4 custom-scrollbar">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-600 text-[9px] font-black uppercase border-b border-gray-800">
                            <th class="pb-4">No. Order</th>
                            <th class="pb-4">Tanggal</th>
                            <th class="pb-4">Total Bayar</th>
                            <th class="pb-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="historyContent" class="text-[11px] font-bold uppercase">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function showHistory(id, name) {
        document.getElementById('customerName').innerText = "PELANGGAN: " + name;
        const content = document.getElementById('historyContent');
        content.innerHTML = "<tr><td colspan='4' class='py-10 text-center animate-pulse'>MENGAMBIL DATA...</td></tr>";

        document.getElementById('modalHistory').classList.remove('hidden');

        // Fetch data orderan via Ajax (Fetch API)
        fetch(`get_order_history.php?id_pelanggan=${id}`)
            .then(response => response.json())
            .then(data => {
                content.innerHTML = "";
                if (data.length === 0) {
                    content.innerHTML =
                        "<tr><td colspan='4' class='py-10 text-center text-gray-600'>BELUM ADA TRANSAKSI</td></tr>";
                } else {
                    data.forEach(order => {
                        content.innerHTML += `
                                <tr class="border-b border-gray-800/30">
                                    <td class="py-5 font-mono text-orange-500 font-black">#ORD-${order.id_pesanan}</td>
                                    <td class="py-5 text-gray-400">${order.tgl_pesanan}</td>
                                    <td class="py-5 text-white font-mono">${order.total_harga_formatted}</td>
                                    <td class="py-5 text-center">
                                        <span class="px-3 py-1 rounded-full text-[9px] font-black ${order.status_bayar === 'lunas' ? 'text-green-500 bg-green-500/10' : 'text-red-500 bg-red-500/10'}">
                                            ${order.status_bayar}
                                        </span>
                                    </td>
                                </tr>
                            `;
                    });
                }
            });
    }

    function closeModal() {
        document.getElementById('modalHistory').classList.add('hidden');
    }
    </script>
</body>

</html>