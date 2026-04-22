<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_admin_sekarang = $_SESSION['id_user'];

// --- 1. LOGIKA UPDATE STATUS & UPLOAD DESAIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_prod = $_POST['id_produksi'];
    $status = $_POST['status_produksi'];
    
    try {
        $pdo->beginTransaction();

        // Cek Upload Desain Baru (Revisi)
        if (isset($_FILES['new_design']) && $_FILES['new_design']['error'] === 0) {
            $folder = "uploads/";
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $ext = pathinfo($_FILES['new_design']['name'], PATHINFO_EXTENSION);
            $fileName = "rev_" . time() . "_" . uniqid() . "." . $ext;
            
            if (move_uploaded_file($_FILES['new_design']['tmp_name'], $folder . $fileName)) {
                $stmtFile = $pdo->prepare("UPDATE produksi SET konfir_desain = ? WHERE id_produksi = ?");
                $stmtFile->execute([$fileName, $id_prod]);
            }
        }

        // Update Status & Pencatatan User
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

        $pdo->commit();
        header("Location: produksi.php?status=" . ($_GET['status'] ?? 'all'));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Gagal: " . $e->getMessage() . "');</script>";
    }
}

// --- 2. LOGIKA FILTER & PAGINASI ---
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['q'] ?? '';
$whereClause = " WHERE 1=1";
$params = [];

if ($status_filter !== 'all') { $whereClause .= " AND pr.status_produksi = ?"; $params[] = $status_filter; }
if ($search !== '') { $whereClause .= " AND (pl.nama_pelanggan LIKE ? OR p.id_pesanan LIKE ?)"; array_push($params, "%$search%", "%$search%"); }

$limit = 5; 
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$offset = ($page - 1) * $limit;

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM produksi pr JOIN pesanan p ON pr.id_pesanan = p.id_pesanan JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan" . $whereClause);
$stmtCount->execute($params);
$totalData = $stmtCount->fetchColumn();
$totalHalaman = ceil($totalData / $limit);

$sql = "SELECT pr.*, p.id_pesanan, p.tgl_pesanan, pl.nama_pelanggan, u1.username as nama_admin_order, u2.username as nama_admin_proses, u3.username as nama_admin_selesai,
        GROUP_CONCAT(CONCAT(b.nama_barang, ' [', IFNULL(dp.keterangan,'-'), '] (', dp.jumlah, ' PCS)') SEPARATOR '<br>') as daftar_barang
        FROM produksi pr
        JOIN pesanan p ON pr.id_pesanan = p.id_pesanan
        JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
        LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
        LEFT JOIN barang b ON dp.id_barang = b.id_barang
        LEFT JOIN user u1 ON pr.id_user = u1.id_user
        LEFT JOIN user u2 ON pr.id_user_proses = u2.id_user
        LEFT JOIN user u3 ON pr.id_user_selesai = u3.id_user" 
        . $whereClause . 
        " GROUP BY pr.id_produksi ORDER BY p.id_pesanan DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarProduksi = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Production Center - Pakle Sport</title>
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

    .select-status {
        background-color: #2a2a2a;
        font-size: 11px;
        font-weight: 800;
        padding: 8px 12px;
        border-radius: 10px;
        border: none;
        appearance: none;
        text-transform: uppercase;
        width: 140px;
    }

    input[type="file"]::file-selector-button {
        display: none;
    }
    </style>
</head>

<body class="flex h-screen overflow-hidden">
    <?php require 'sidebar.php'; ?>

    <main class="flex-1 custom-dark p-12 overflow-y-auto">
        <header class="flex justify-between items-baseline mb-10 border-b border-gray-800/50 pb-8">
            <h2 class="heading-font text-4xl uppercase text-orange-500">Production</h2>
            <form action="" method="GET" class="relative w-80">
                <input type="hidden" name="status" value="<?= $status_filter ?>">
                <input type="text" name="q" placeholder="Cari Nama/ID..." value="<?= htmlspecialchars($search) ?>"
                    class="w-full bg-white rounded-full py-2.5 px-6 text-sm text-black outline-none focus:ring-2 focus:ring-orange-500">
            </form>
        </header>

        <div class="flex space-x-3 mb-8">
            <?php 
            $tabs = ['all' => 'Semua', 'antrean' => 'Antrean', 'proses' => 'Proses', 'selesai' => 'Selesai', 'siap diambil' => 'Siap Ambil'];
            foreach ($tabs as $key => $label): 
                $isActive = ($status_filter === $key);
            ?>
            <a href="?status=<?= $key ?>&q=<?= $search ?>"
                class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all border <?= $isActive ? 'bg-orange-500 text-black border-orange-500' : 'bg-white/5 text-gray-500 border-gray-800 hover:border-gray-600' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="card-table p-10 border border-gray-800/50 shadow-2xl">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-800">
                        <th class="pb-6 px-4">Order ID</th>
                        <th class="pb-6">Customer / Items</th>
                        <th class="pb-6 text-center">Design</th>
                        <th class="pb-6 text-center">Status</th>
                        <th class="pb-6 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-sm font-medium uppercase">
                    <?php foreach ($daftarProduksi as $row): ?>
                    <tr class="border-b border-gray-800/40 hover:bg-white/5 transition-all">
                        <form action="produksi.php?status=<?= $status_filter ?>&q=<?= $search ?>" method="POST"
                            enctype="multipart/form-data">
                            <input type="hidden" name="id_produksi" value="<?= $row['id_produksi'] ?>">

                            <td class="py-8 px-4 font-mono text-orange-500 font-black text-xl italic">
                                #<?= $row['id_pesanan'] ?></td>

                            <td class="py-8">
                                <p class="font-black text-gray-100 mb-2"><?= htmlspecialchars($row['nama_pelanggan']) ?>
                                </p>
                                <div class="bg-black/30 p-4 rounded-2xl border border-gray-800/50 max-w-xs">
                                    <p class="text-[10px] text-gray-400 leading-relaxed font-bold">
                                        <?= $row['daftar_barang'] ?></p>
                                </div>
                            </td>

                            <td class="py-8 text-center">
                                <div class="flex flex-col items-center space-y-2">
                                    <div class="flex space-x-2">
                                        <?php if($row['konfir_desain']): ?>
                                        <button type="button"
                                            onclick="showDesign('uploads/<?= $row['konfir_desain'] ?>')"
                                            class="w-10 h-10 bg-orange-500 text-black rounded-xl flex items-center justify-center transition-all hover:bg-white"><i
                                                class="fas fa-eye"></i></button>
                                        <?php endif; ?>
                                        <label
                                            class="w-10 h-10 bg-white/5 hover:bg-blue-500 text-blue-500 hover:text-white rounded-xl border border-gray-800 flex items-center justify-center cursor-pointer transition-all">
                                            <i class="fas fa-upload text-xs"></i>
                                            <input type="file" name="new_design" class="hidden">
                                        </label>
                                    </div>
                                </div>
                            </td>

                            <td class="py-8 text-center">
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

                            <td class="py-8 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button type="submit" name="update_status"
                                        class="bg-white/5 hover:bg-orange-500 text-gray-400 hover:text-black w-10 h-10 rounded-xl transition-all border border-gray-800">
                                        <i class="fas fa-sync-alt text-sm"></i>
                                    </button>
                                    <button type="button"
                                        onclick="showDetail('<?= addslashes($row['nama_pelanggan']) ?>', '<?= $row['nama_admin_order'] ?? '-' ?>', '<?= $row['nama_admin_proses'] ?? '-' ?>', '<?= $row['nama_admin_selesai'] ?? '-' ?>', '<?= date('d/m/Y H:i', strtotime($row['tgl_pesanan'])) ?>', '<?= addslashes($row['daftar_barang']) ?>')"
                                        class="bg-white/5 hover:bg-white text-gray-500 hover:text-black w-10 h-10 rounded-xl transition-all border border-gray-800">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modal-detail"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm px-4">
        <div class="bg-[#1a1a1a] w-full max-w-md rounded-[40px] border border-gray-800 p-10 shadow-2xl">
            <h3 class="heading-font text-xl text-orange-500 uppercase mb-8">Workshop Detail</h3>
            <div class="space-y-6">
                <div class="bg-black/40 p-5 rounded-2xl border border-gray-800/50">
                    <p class="text-[9px] uppercase text-gray-500 mb-1 font-black">Customer</p>
                    <p id="det-nama" class="text-sm font-black text-white"></p>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="text-center">
                        <p class="text-[8px] text-gray-500 uppercase mb-1">Ordered</p>
                        <p id="det-admin-order" class="text-[10px] font-black text-blue-400"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-[8px] text-gray-500 uppercase mb-1">Processed</p>
                        <p id="det-admin-proses" class="text-[10px] font-black text-purple-400"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-[8px] text-gray-500 uppercase mb-1">Finished</p>
                        <p id="det-admin-selesai" class="text-[10px] font-black text-green-400"></p>
                    </div>
                </div>
                <div>
                    <p class="text-[9px] uppercase text-gray-500 mb-1 font-black">Timestamp</p>
                    <p id="det-tgl" class="text-[10px] font-mono text-gray-400"></p>
                </div>
                <div class="bg-black/40 p-5 rounded-2xl border border-gray-800/50">
                    <p id="det-items" class="text-[11px] font-bold text-gray-300 leading-relaxed"></p>
                </div>
            </div>
            <button onclick="closeModal()"
                class="w-full mt-10 py-5 bg-white/5 hover:bg-orange-500 hover:text-black rounded-2xl text-[10px] uppercase font-black transition-all border border-gray-800">Close
                Detail</button>
        </div>
    </div>

    <div id="modal-design"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/95 backdrop-blur-md p-10">
        <button onclick="closeDesign()" class="absolute top-10 right-10 text-white text-3xl hover:text-orange-500"><i
                class="fas fa-times-circle"></i></button>
        <img id="img-preview" src="" class="max-w-full max-h-[80vh] rounded-3xl shadow-2xl border border-gray-800">
    </div>

    <script>
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