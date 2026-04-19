<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_admin_sekarang = $_SESSION['id_user'];

// --- LOGIKA UPDATE STATUS PRODUKSI DENGAN PENCATATAN USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_prod = $_POST['id_produksi'];
    $status = $_POST['status_produksi'];

    try {
        if ($status == 'proses') {
            $stmt = $pdo->prepare("UPDATE produksi SET status_produksi = ?, id_user_proses = ? WHERE id_produksi = ?");
            $stmt->execute([$status, $id_admin_sekarang, $id_prod]);
        } elseif ($status == 'selesai' || $status == 'siap diambil') {
            $stmt = $pdo->prepare("UPDATE produksi SET status_produksi = ?, id_user_selesai = ? WHERE id_produksi = ?");
            $stmt->execute([$status, $id_admin_sekarang, $id_prod]);
        } else {
            $stmt = $pdo->prepare("UPDATE produksi SET status_produksi = ? WHERE id_produksi = ?");
            $stmt->execute([$status, $id_prod]);
        }
        echo "<script>alert('Status Produksi Diperbarui!'); window.location='produksi.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Gagal: " . $e->getMessage() . "');</script>";
    }
}

// --- LOGIKA PAGINASI & SEARCH (DIBERESKAN DI SINI) ---
$limit = 5; 
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($page <= 0) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['q']) ? $_GET['q'] : '';
$whereClause = "";
$params = [];

if ($search != '') {
    $whereClause = " WHERE pl.nama_pelanggan LIKE ? OR p.id_pesanan LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// 1. HITUNG TOTAL DATA (SOLUSI WARNING)
$sqlCount = "SELECT COUNT(*) FROM produksi pr 
             JOIN pesanan p ON pr.id_pesanan = p.id_pesanan 
             JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalData = $stmtCount->fetchColumn(); // Sekarang $totalData sudah ada isinya
$totalHalaman = ceil($totalData / $limit); // Sekarang $totalHalaman sudah ada isinya

// 2. AMBIL DATA UTAMA
$sql = "SELECT 
            pr.id_produksi, pr.status_produksi, pr.konfir_desain,
            p.id_pesanan, p.tgl_pesanan,
            pl.nama_pelanggan, pl.no_telp, pl.alamat,
            u1.username as nama_admin_order,
            u2.username as nama_admin_proses,
            u3.username as nama_admin_selesai,
            GROUP_CONCAT(CONCAT(b.nama_barang, ' [', IFNULL(dp.keterangan, '-'), '] (', dp.jumlah, ' PCS)') SEPARATOR '<br>') as daftar_barang
        FROM produksi pr
        JOIN pesanan p ON pr.id_pesanan = p.id_pesanan
        JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
        LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
        LEFT JOIN barang b ON dp.id_barang = b.id_barang
        LEFT JOIN user u1 ON pr.id_user = u1.id_user
        LEFT JOIN user u2 ON pr.id_user_proses = u2.id_user
        LEFT JOIN user u3 ON pr.id_user_selesai = u3.id_user" 
        . $whereClause . 
        " GROUP BY pr.id_produksi
          ORDER BY p.id_pesanan DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarProduksi = $stmt->fetchAll();

$username_display = $_SESSION['username'];
$role_display = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIM Pakle Sport - Production Log</title>
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

    #service-dropdown {
        transition: all 0.3s ease-in-out;
        max-height: 0;
        overflow: hidden;
    }

    #service-dropdown.show {
        max-height: 300px;
        margin-top: 0.5rem;
    }

    /* STYLE KHUSUS CETAK */
    @media print {

        /* Sembunyikan semua elemen kecuali area tabel */
        body * {
            visibility: hidden;
        }

        /* Tampilkan area tabel dan isinya */
        #area-cetak,
        #area-cetak * {
            visibility: visible;
        }

        /* Posisikan tabel di paling atas kertas */
        #area-cetak {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            background: white !important;
            color: black !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
        }

        /* Rapikan tampilan tabel untuk kertas */
        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }

        th,
        td {
            border: 1px solid #ddd !important;
            color: black !important;
            padding: 12px !important;
            text-transform: uppercase !important;
        }

        th {
            background-color: #f2f2f2 !important;
        }

        .no-print {
            display: none !important;
        }
    }
    </style>
</head>

<body class="bg-black flex h-screen overflow-hidden text-white">

    <div class="flex h-screen overflow-hidden"> <?php require 'sidebar.php'; ?>
        <main class="flex-1 overflow-y-auto">
    </div>
    <main class="flex-1 custom-dark p-12 overflow-y-auto">
        <header class="flex justify-between items-baseline mb-10 border-b border-gray-800/50 pb-8">
            <h2 class="heading-font text-4xl uppercase text-orange-500">Production</h2>
            <form action="" method="GET" class="relative w-80">
                <input type="text" name="q" placeholder="Cari Nama/ID..." value="<?= htmlspecialchars($search) ?>"
                    class="w-full bg-white rounded-full py-2.5 px-6 text-sm text-black outline-none focus:ring-2 focus:ring-orange-500">
                <button type="submit" class="absolute right-5 top-2.5 text-gray-400 hover:text-orange-500"><i
                        class="fas fa-search"></i></button>
            </form>
        </header>

        <div class="card-table p-10 border border-gray-800/50 shadow-2xl">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-500 text-xs uppercase tracking-[0.2em] border-b border-gray-800">
                        <th class="pb-6 px-4">Order ID</th>
                        <th class="pb-6">Customer / Items</th>
                        <th class="pb-6 text-center">Design</th>
                        <th class="pb-6 text-center">Status</th>
                        <th class="pb-6 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-sm font-medium">
                    <?php if (empty($daftarProduksi)): ?>
                    <tr>
                        <td colspan="5" class="py-10 text-center text-gray-600">Data Produksi Kosong</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($daftarProduksi as $row): ?>
                    <tr class="border-b border-gray-800/40 hover:bg-white/5 transition-all">
                        <form action="produksi.php" method="POST">
                            <input type="hidden" name="id_produksi" value="<?= $row['id_produksi'] ?>">
                            <td class="py-6 px-4 font-mono text-orange-500 font-bold">#<?= $row['id_pesanan'] ?></td>
                            <td class="py-6">
                                <p class="font-black uppercase text-gray-100 mb-2">
                                    <?= htmlspecialchars($row['nama_pelanggan']) ?></p>
                                <div class="bg-black/30 p-3 rounded-xl border border-gray-800/50 max-w-xs">
                                    <p class="text-[10px] text-gray-400 uppercase leading-relaxed font-medium">
                                        <?= $row['daftar_barang'] ?></p>
                                </div>
                            </td>
                            <td class="py-6 text-center">
                                <?php if($row['konfir_desain']): ?>
                                <button type="button" onclick="showDesign('uploads/<?= $row['konfir_desain'] ?>')"
                                    class="w-10 h-10 bg-white/5 hover:bg-orange-500/20 text-gray-400 hover:text-orange-500 rounded-xl border border-gray-800 flex items-center justify-center mx-auto transition-all group">
                                    <i class="fas fa-eye text-sm group-hover:scale-110"></i>
                                </button>
                                <?php else: ?>
                                <i class="fas fa-eye-slash text-gray-700"></i>
                                <?php endif; ?>
                            </td>
                            <td class="py-6 text-center">
                                <select name="status_produksi"
                                    class="select-status <?= $row['status_produksi'] == 'antrean' ? 'text-blue-400' : ($row['status_produksi'] == 'proses' ? 'text-purple-400' : 'text-green-400') ?>">
                                    <option value="antrean"
                                        <?= $row['status_produksi'] == 'antrean' ? 'selected' : '' ?>>Antrean</option>
                                    <option value="proses" <?= $row['status_produksi'] == 'proses' ? 'selected' : '' ?>>
                                        Proses</option>
                                    <option value="selesai"
                                        <?= $row['status_produksi'] == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                    <option value="siap diambil"
                                        <?= $row['status_produksi'] == 'siap diambil' ? 'selected' : '' ?>>Siap Ambil
                                    </option>
                                </select>
                            </td>
                            <td class="py-6 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button type="submit" name="update_status"
                                        class="bg-white/5 hover:bg-orange-500 text-gray-400 hover:text-black w-10 h-10 rounded-xl transition-all flex items-center justify-center border border-gray-800"><i
                                            class="fas fa-sync-alt text-sm"></i></button>
                                    <button type="button"
                                        onclick="showDetail('<?= addslashes($row['nama_pelanggan']) ?>', '<?= $row['nama_admin_order'] ?? '-' ?>', '<?= $row['nama_admin_proses'] ?? '-' ?>', '<?= $row['nama_admin_selesai'] ?? '-' ?>', '<?= $row['tgl_pesanan'] ?>', '<?= addslashes($row['daftar_barang']) ?>')"
                                        class="bg-white/5 hover:bg-white/10 text-gray-500 hover:text-white w-10 h-10 rounded-xl transition-all flex items-center justify-center border border-gray-800"><i
                                            class="fas fa-info-circle text-sm"></i></button>
                                </div>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-8 flex justify-between items-center border-t border-gray-800 pt-6">
                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Showing <span
                        class="text-white"><?= count($daftarProduksi) ?></span> of <?= $totalData ?> Workload</p>
                <div class="flex space-x-2">
                    <?php if($page > 1): ?>
                    <a href="?halaman=<?= $page - 1 ?>&q=<?= $search ?>"
                        class="w-8 h-8 flex items-center justify-center bg-white/5 rounded-lg hover:bg-orange-500 hover:text-black transition-all text-xs"><i
                            class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php for($i=1; $i<=$totalHalaman; $i++): ?>
                    <a href="?halaman=<?= $i ?>&q=<?= $search ?>"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all <?= $i == $page ? 'bg-orange-500 text-black shadow-lg shadow-orange-500/20' : 'bg-white/5 text-gray-400 hover:bg-white/10' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if($page < $totalHalaman): ?>
                    <a href="?halaman=<?= $page + 1 ?>&q=<?= $search ?>"
                        class="w-8 h-8 flex items-center justify-center bg-white/5 rounded-lg hover:bg-orange-500 hover:text-black transition-all text-xs"><i
                            class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <div id="modal-detail"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm px-4">
        <div class="bg-[#1a1a1a] w-full max-w-md rounded-[30px] border border-gray-800 p-8 shadow-2xl transition-all">
            <div class="flex justify-between items-start mb-6">
                <h3 class="heading-font text-xl uppercase text-orange-500">Workshop Info</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-white"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="space-y-5">
                <div>
                    <p class="text-[9px] uppercase tracking-[0.2em] text-gray-500 mb-1 font-bold">Customer Name</p>
                    <p id="det-nama" class="text-sm font-black uppercase text-white tracking-wide">---</p>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-black/40 p-3 rounded-2xl border border-gray-800/50 text-center">
                        <p class="text-[8px] uppercase tracking-widest text-gray-500 mb-1">Ordered</p>
                        <p id="det-admin-order" class="text-[10px] font-bold text-blue-400 uppercase">---</p>
                    </div>
                    <div class="bg-black/40 p-3 rounded-2xl border border-gray-800/50 text-center">
                        <p class="text-[8px] uppercase tracking-widest text-gray-500 mb-1">Processed</p>
                        <p id="det-admin-proses" class="text-[10px] font-bold text-purple-400 uppercase">---</p>
                    </div>
                    <div class="bg-black/40 p-3 rounded-2xl border border-gray-800/50 text-center">
                        <p class="text-[8px] uppercase tracking-widest text-gray-500 mb-1">Finished</p>
                        <p id="det-admin-selesai" class="text-[10px] font-bold text-green-400 uppercase">---</p>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-800">
                    <p class="text-[9px] uppercase tracking-[0.2em] text-gray-500 mb-1 font-bold">Order Timestamp</p>
                    <p id="det-tgl" class="text-xs font-mono text-gray-400">---</p>
                </div>
                <div class="pt-4 border-t border-gray-800">
                    <p class="text-[9px] uppercase tracking-[0.2em] text-gray-500 mb-2 font-bold">Job Instructions</p>
                    <div id="det-items"
                        class="bg-black/40 p-5 rounded-2xl border border-gray-800 text-[11px] text-gray-300 leading-relaxed uppercase font-medium">
                        ---</div>
                </div>
            </div>
            <button onclick="closeModal()"
                class="w-full mt-8 py-4 bg-white/5 hover:bg-white/10 rounded-2xl text-[10px] uppercase font-bold tracking-widest transition-all border border-gray-800/50">Close
                Detail</button>
        </div>
    </div>

    <div id="modal-design"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/90 backdrop-blur-md p-10">
        <div class="relative max-w-4xl w-full flex flex-col items-center">
            <button onclick="closeDesign()"
                class="absolute -top-10 right-0 text-white text-3xl hover:text-orange-500"><i
                    class="fas fa-times-circle"></i></button>
            <img id="img-preview" src="" class="max-w-full max-h-[80vh] rounded-2xl shadow-2xl border border-gray-800">
            <p class="mt-6 text-orange-500 text-[10px] font-black uppercase tracking-[0.5em] heading-font">Blueprint
                Preview</p>
        </div>
    </div>

    <script>
    function toggleDropdown() {
        const dropdown = document.getElementById('service-dropdown');
        const icon = document.getElementById('chevron-icon');
        dropdown.classList.toggle('show');
        icon.style.transform = dropdown.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    function showDesign(src) {
        document.getElementById('img-preview').src = src;
        document.getElementById('modal-design').classList.remove('hidden');
    }

    function closeDesign() {
        document.getElementById('modal-design').classList.add('hidden');
    }

    function showDetail(nama, adminOrder, adminProses, adminSelesai, tgl, items) {
        document.getElementById('det-nama').innerText = nama;
        document.getElementById('det-admin-order').innerText = adminOrder;
        document.getElementById('det-admin-proses').innerText = adminProses;
        document.getElementById('det-admin-selesai').innerText = adminSelesai;
        document.getElementById('det-tgl').innerText = tgl;
        document.getElementById('det-items').innerHTML = items;
        document.getElementById('modal-detail').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modal-detail').classList.add('hidden');
    }
    </script>
</body>

</html>