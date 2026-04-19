<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_admin_sekarang = $_SESSION['id_user'];

// --- LOGIKA UPDATE STATUS PEMBAYARAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $id_pay = $_POST['id_pembayaran'];
    $status_bayar = $_POST['status_bayar'];

    try {
        if ($status_bayar == 'lunas') {
            $metode_lunas = $_POST['metode_lunas'] ?? 'tunai';
            
            // LOGIKA SAKTI: Set jumlah_bayar = total_harga saat lunas
            $stmtPay = $pdo->prepare("
                UPDATE pembayaran pem
                JOIN pesanan p ON pem.id_pembayaran = p.id_pembayaran
                SET pem.status_bayar = 'lunas',
                    pem.jumlah_bayar = p.total_harga, -- Ini kuncinya agar tidak Rp 0
                    pem.tgl_lunas = NOW(),
                    pem.id_admin_lunas = ?,
                    pem.metode_lunas = ?
                WHERE pem.id_pembayaran = ?
            ");
            $stmtPay->execute([$id_admin_sekarang, $metode_lunas, $id_pay]);
        } else {
            $stmtPay = $pdo->prepare("UPDATE pembayaran SET status_bayar = ? WHERE id_pembayaran = ?");
            $stmtPay->execute([$status_bayar, $id_pay]);
        }
        echo "<script>alert('Status & Nominal Berhasil Diperbarui!'); window.location='pembayaran.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Gagal: " . $e->getMessage() . "');</script>";
    }
}

// --- LOGIKA PAGINASI & SEARCH ---
$limit = 5; 
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($page <= 0) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['q']) ? $_GET['q'] : '';
$whereClause = "";
$params = [];

if ($search != '') {
    $whereClause = " WHERE pl.nama_pelanggan LIKE ? OR pl.id_pelanggan LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$sqlCount = "SELECT COUNT(*) FROM pesanan p JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalData = $stmtCount->fetchColumn();
$totalHalaman = ceil($totalData / $limit);

// Ambil Data Utama dengan JOIN Admin & Metode
$sql = "SELECT 
            p.id_pesanan, p.id_pembayaran,
            pl.id_pelanggan, pl.nama_pelanggan,
            p.total_harga, pem.jumlah_bayar, pem.status_bayar,
            pem.tgl_dp, pem.tgl_lunas, pem.metode_dp, pem.metode_lunas,
            u1.username as nama_admin_dp, 
            u2.username as nama_admin_lunas,
            (p.total_harga - pem.jumlah_bayar) as sisa_tagihan
        FROM pesanan p
        JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
        JOIN pembayaran pem ON p.id_pembayaran = pem.id_pembayaran
        LEFT JOIN user u1 ON pem.id_admin_dp = u1.id_user
        LEFT JOIN user u2 ON pem.id_admin_lunas = u2.id_user" 
        . $whereClause . 
        " ORDER BY p.id_pesanan DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarKeuangan = $stmt->fetchAll();

function formatPL($id) { return "PL" . sprintf('%04d', $id); }
function formatRupiah($angka) { return "Rp " . number_format($angka, 0, ',', '.'); }

$username_display = $_SESSION['username'];
$role_display = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIM Pakle Sport - Financial Records</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Inter:wght@400;600;700;900&display=swap"
        rel="stylesheet">
    <style>
    body {
        font-family: 'Inter', sans-serif;
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
        outline: none;
        cursor: pointer;
        appearance: none;
        text-transform: uppercase;
        width: 140px;
    }

    #service-dropdown {
        transition: all 0.3s ease-in-out;
        max-height: 0;
        overflow: hidden;
    }

    #service-dropdown.show {
        max-height: 200px;
        margin-top: 0.5rem;
    }
    </style>
</head>

<body class="bg-black flex h-screen overflow-hidden text-white">

    <div class="flex h-screen overflow-hidden"> <?php require 'sidebar.php'; ?>
        <main class="flex-1 overflow-y-auto">
    </div>

    <main class="flex-1 custom-dark p-12 overflow-y-auto">
        <header class="flex justify-between items-baseline mb-10 border-b border-gray-800/50 pb-8">
            <h2 class="heading-font text-4xl italic uppercase tracking-tighter text-orange-500">Finance</h2>
            <form action="" method="GET" class="relative w-80">
                <input type="text" name="q" placeholder="Cari Pelanggan..." value="<?= htmlspecialchars($search) ?>"
                    class="w-full bg-white rounded-full py-2.5 px-6 text-sm text-black outline-none focus:ring-2 focus:ring-orange-500">
                <button type="submit"
                    class="absolute right-5 top-2.5 text-gray-400 hover:text-orange-500 transition-colors"><i
                        class="fas fa-search"></i></button>
            </form>
        </header>

        <div class="card-table p-10 border border-gray-800/50 shadow-2xl">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-gray-500 text-xs uppercase tracking-[0.2em] border-b border-gray-800">
                        <th class="pb-6 px-4">ID</th>
                        <th class="pb-6">Pelanggan</th>
                        <th class="pb-6">Total Tagihan</th>
                        <th class="pb-6">Sisa Bayar</th>
                        <th class="pb-6 text-center">Status</th>
                        <th class="pb-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-sm font-medium">
                    <?php if (empty($daftarKeuangan)): ?>
                    <tr>
                        <td colspan="6" class="py-10 text-center text-gray-600 italic">Data tidak ditemukan.</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($daftarKeuangan as $row): 
                        $isLunas = ($row['status_bayar'] == 'lunas');
                        $tampilanSisa = $isLunas ? 0 : $row['sisa_tagihan'];
                    ?>
                    <tr class="border-b border-gray-800/40 hover:bg-white/5 transition-all">
                        <form action="pembayaran.php" method="POST">
                            <input type="hidden" name="id_pembayaran" value="<?= $row['id_pembayaran'] ?>">

                            <td class="py-6 px-4 font-mono text-orange-500 italic font-bold">
                                <?= formatPL($row['id_pelanggan']) ?></td>
                            <td class="py-6 font-black uppercase tracking-wide text-gray-100 text-base">
                                <?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                            <td class="py-6 font-mono text-gray-400"><?= formatRupiah($row['total_harga']) ?></td>
                            <td class="py-6 font-mono font-bold <?= $isLunas ? 'text-green-400' : 'text-red-500' ?>">
                                <?= formatRupiah($tampilanSisa) ?></td>

                            <td class="py-6 text-center">
                                <div class="relative inline-block">
                                    <select name="<?= $isLunas ? '' : 'status_bayar' ?>"
                                        <?= $isLunas ? 'disabled' : '' ?>
                                        onchange="this.value=='lunas' ? document.getElementById('metode-lunas-<?= $row['id_pembayaran'] ?>').classList.remove('hidden') : document.getElementById('metode-lunas-<?= $row['id_pembayaran'] ?>').classList.add('hidden')"
                                        class="select-status <?= $isLunas ? 'text-green-400 opacity-50 cursor-not-allowed' : ($row['status_bayar'] == 'dp' ? 'text-orange-400' : 'text-red-500') ?>">
                                        <option value="lunas" <?= $row['status_bayar'] == 'lunas' ? 'selected' : '' ?>>
                                            Lunas</option>
                                        <option value="dp" <?= $row['status_bayar'] == 'dp' ? 'selected' : '' ?>>DP
                                            (Sebagian)</option>
                                        <option value="belum" <?= $row['status_bayar'] == 'belum' ? 'selected' : '' ?>>
                                            Belum Bayar</option>
                                    </select>

                                    <select id="metode-lunas-<?= $row['id_pembayaran'] ?>" name="metode_lunas"
                                        class="hidden select-status mt-2 !bg-gray-800 !text-[9px] !w-full">
                                        <option value="tunai">Lunas via TUNAI</option>
                                        <option value="transfer">Lunas via TF</option>
                                        <option value="e-wallet">Lunas via QRIS</option>
                                    </select>

                                    <i
                                        class="fas fa-lock absolute right-3 top-3 text-[8px] <?= $isLunas ? 'text-green-400' : 'hidden' ?>"></i>
                                </div>
                            </td>

                            <td class="py-6 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <?php if (!$isLunas): ?>
                                    <button type="submit" name="update_payment"
                                        class="bg-orange-500/10 hover:bg-orange-500 text-orange-500 hover:text-black w-10 h-10 rounded-xl transition-all shadow-lg flex items-center justify-center">
                                        <i class="fas fa-save text-sm"></i>
                                    </button>
                                    <?php endif; ?>

                                    <button type="button"
                                        onclick="showDetail('<?= addslashes($row['nama_pelanggan']) ?>', '<?= $row['tgl_dp'] ?>', '<?= $row['nama_admin_dp'] ?? '-' ?>', '<?= $row['metode_dp'] ?? '-' ?>', '<?= $row['tgl_lunas'] ?>', '<?= $row['nama_admin_lunas'] ?? '-' ?>', '<?= $row['metode_lunas'] ?? '-' ?>', '<?= formatRupiah($row['total_harga']) ?>')"
                                        class="bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white w-10 h-10 rounded-xl transition-all flex items-center justify-center">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-8 flex justify-between items-center border-t border-gray-800 pt-6">
                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Showing <span
                        class="text-white"><?= count($daftarKeuangan) ?></span> of <?= $totalData ?> Records</p>
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
        <div
            class="bg-[#1a1a1a] w-full max-w-sm rounded-[30px] border border-gray-800 p-8 shadow-2xl scale-95 transition-all">
            <div class="flex justify-between items-start mb-6">
                <h3 class="heading-font text-xl uppercase italic text-orange-500">Payment Logs</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-white transition-colors"><i
                        class="fas fa-times"></i></button>
            </div>

            <div class="space-y-4">
                <div>
                    <p class="text-[9px] uppercase tracking-widest text-gray-500 mb-1">Customer Name</p>
                    <p id="det-nama" class="font-bold text-gray-100 uppercase tracking-wide">---</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-black/20 p-3 rounded-2xl border border-gray-800/50">
                        <p class="text-[9px] uppercase tracking-widest text-gray-500 mb-1">DP Record</p>
                        <p id="det-admin-dp" class="text-xs font-bold text-orange-400 uppercase">---</p>
                        <p id="det-metode-dp" class="text-[9px] text-white font-black uppercase mt-1">---</p>
                        <p id="det-tgl-dp" class="text-[8px] font-mono text-gray-500 mt-1">---</p>
                    </div>
                    <div class="bg-black/20 p-3 rounded-2xl border border-gray-800/50">
                        <p class="text-[9px] uppercase tracking-widest text-gray-500 mb-1">Lunas Record</p>
                        <p id="det-admin-lunas" class="text-xs font-bold text-green-400 uppercase">---</p>
                        <p id="det-metode-lunas" class="text-[9px] text-white font-black uppercase mt-1">---</p>
                        <p id="det-tgl-lunas" class="text-[8px] font-mono text-gray-500 mt-1">---</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-800">
                    <p class="text-[9px] uppercase tracking-widest text-gray-500 mb-1">Total Billing</p>
                    <p id="det-total" class="text-2xl font-black text-white">---</p>
                </div>
            </div>

            <button onclick="closeModal()"
                class="w-full mt-8 py-4 bg-white/5 hover:bg-white/10 rounded-2xl text-[10px] uppercase font-bold tracking-widest transition-all border border-gray-800/50">
                Close Detail
            </button>
        </div>
    </div>

    <script>
    function toggleDropdown() {
        const dropdown = document.getElementById('service-dropdown');
        const icon = document.getElementById('chevron-icon');
        dropdown.classList.toggle('show');
        icon.style.transform = dropdown.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    function showDetail(nama, tglDp, adminDp, metDp, tglLunas, adminLunas, metLunas, total) {
        document.getElementById('det-nama').innerText = nama;
        document.getElementById('det-total').innerText = total;

        // Data DP
        document.getElementById('det-admin-dp').innerText = adminDp;
        document.getElementById('det-metode-dp').innerText = "Via " + metDp;
        document.getElementById('det-tgl-dp').innerText = tglDp && tglDp !== '0000-00-00 00:00:00' ? tglDp :
            'Belum Terinput';

        // Data Lunas
        document.getElementById('det-admin-lunas').innerText = adminLunas;
        document.getElementById('det-metode-lunas').innerText = metLunas !== '-' ? "Via " + metLunas : "Belum Lunas";
        document.getElementById('det-tgl-lunas').innerText = tglLunas && tglLunas !== '0000-00-00 00:00:00' ? tglLunas :
            'Hutang Belum Lunas';

        const modal = document.getElementById('modal-detail');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('modal-detail');
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    </script>
</body>

</html>