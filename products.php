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

// --- 1. LOGIKA PROSES (TAMBAH & UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = strtoupper($_POST['nama_barang']);
    $harga = $_POST['harga_barang'];
    $stok = $_POST['stok_tersedia'];
    $satuan = $_POST['satuan'];

    if (isset($_POST['add_barang'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO barang (nama_barang, harga_barang, stok_tersedia, satuan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama, $harga, $stok, $satuan]);
            header("Location: products.php?msg=success&item=$nama&qty=$stok&prc=$harga");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif (isset($_POST['update_barang'])) {
        $id = $_POST['id_barang'];
        try {
            $stmt = $pdo->prepare("UPDATE barang SET nama_barang = ?, harga_barang = ?, stok_tersedia = ?, satuan = ? WHERE id_barang = ?");
            $stmt->execute([$nama, $harga, $stok, $satuan, $id]);
            header("Location: products.php?msg=updated&item=$nama&qty=$stok&prc=$harga");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// --- 2. LOGIKA PAGINASI & SEARCH ---
$limit = 5;
$page = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;
if ($page <= 0)
    $page = 1;
$offset = ($page - 1) * $limit;

$search = $_GET['q'] ?? '';
$whereClause = $search ? " WHERE nama_barang LIKE ? OR id_barang LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$totalData = $pdo->prepare("SELECT COUNT(*) FROM barang" . $whereClause);
$totalData->execute($params);
$totalRows = $totalData->fetchColumn();
$totalHalaman = ceil($totalRows / $limit);

$sql = "SELECT * FROM barang" . $whereClause . " ORDER BY id_barang DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$alertData = null;
if (isset($_GET['msg'])) {
    $item = $_GET['item'] ?? '';
    $qty = $_GET['qty'] ?? '';
    $prc = $_GET['prc'] ?? '';
    $status = $_GET['msg'];
    $title = $status === 'success' ? 'NEW ITEM ADDED' : 'STOCK UPDATED';
    $icon = $status === 'success' ? 'fas fa-plus-circle text-green-400' : 'fas fa-sync-alt text-blue-400';
    $formattedPrc = "Rp " . number_format((float) $prc, 0, ',', '.');
    $alertData = [
        'title' => $title,
        'msg' => "BARANG: <b class=\"text-white\">$item</b><br>HARGA: <b class=\"text-orange-500\">$formattedPrc</b><br>STOK: <b class=\"text-white\">$qty ITEMS</b>",
        'icon' => $icon
    ];
}

$username_display = $_SESSION['username'];
$role_display = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>SIM Pakle Sport - Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Inter:wght@400;600;700;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body class="flex h-screen overflow-hidden bg-black text-white">

    <?php require 'sidebar.php'; ?>

    <main class="flex-1 custom-dark p-12 overflow-y-auto">
        <header class="flex justify-between items-baseline mb-8 border-b border-gray-800/50 pb-6">
            <div>
                <h2 class="heading-font text-white text-4xl uppercase border-gray-800/50">Inventory</h2>
                <p class="text-gray-500 text-xl italic font-light tracking-wide">Design your vision. We craft
                    perfection.
            </div>
            <form action="" method="GET" class="relative w-80">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search items..."
                    class="w-full bg-white rounded-full py-2.5 px-6 text-sm text-black outline-none focus:ring-2 focus:ring-orange-500 transition-all">
                <button type="submit" class="absolute right-5 top-3 text-gray-400 hover:text-orange-500"><i
                        class="fas fa-search"></i></button>
            </form>
        </header>

        <div class="card-table p-8 border border-gray-800/50 shadow-2xl">
            <table class="w-full text-left">
                <thead>
                    <tr
                        class="text-gray-500 text-[13px] font-black uppercase tracking-[0.2em] border-b border-gray-800">
                        <th class="pb-5 px-4">Stock ID</th>
                        <th class="pb-5">Product Name</th>
                        <th class="pb-5">Unit Price</th>
                        <th class="pb-5 text-center">In Stock</th>
                        <th class="pb-5 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-[14px] font-semibold tracking-wide uppercase">
                    <?php foreach ($products as $row): ?>
                    <tr class="border-b border-gray-800/50 hover:bg-white/5 transition-all">
                        <td class="py-5 px-4 text-orange-500 font-mono font-bold">
                            #STK-<?= str_pad($row['id_barang'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td class="py-5 text-gray-100 font-black tracking-wider">
                            <?= htmlspecialchars($row['nama_barang']) ?>
                        </td>
                        <td class="py-5 text-white font-mono"><?= formatRupiah($row['harga_barang']) ?></td>
                        <td class="py-5 text-center">
                            <?php $isLow = ($row['stok_tersedia'] <= 10); ?>
                            <span
                                class="px-3 py-1 rounded-full border <?= $isLow ? 'text-red-500 border-red-500 animate-pulse' : 'text-white border-gray-700' ?>">
                                <?= $row['stok_tersedia'] ?> <?= $row['satuan'] ?>
                            </span>
                        </td>
                        <td class="py-5 text-center">
                            <button
                                onclick="openModal('edit', '<?= $row['id_barang'] ?>', '<?= addslashes($row['nama_barang']) ?>', '<?= $row['harga_barang'] ?>', '<?= $row['stok_tersedia'] ?>', '<?= $row['satuan'] ?>')"
                                class="bg-white/5 hover:bg-orange-500 hover:text-black px-4 py-2 rounded-xl border border-gray-800 transition-all text-[10px] font-bold uppercase">Update</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-8 flex justify-between items-center border-t border-gray-800 pt-6">
                <p class="text-[10px] text-gray-500 uppercase font-bold">Total Data: <?= $totalRows ?></p>
                <div class="flex space-x-2">
                    <?php for ($i = 1; $i <= $totalHalaman; $i++): ?>
                    <a href="?halaman=<?= $i ?>&q=<?= $search ?>"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all <?= $i == $page ? 'bg-orange-500 text-black' : 'bg-white/5 text-gray-400' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="mt-10 flex justify-end">
                <button onclick="openModal('add')"
                    class="bg-[#ff4d00] hover:bg-white text-black text-[12px] font-black px-10 py-4 rounded-2xl shadow-xl uppercase transition-all active:scale-95">Add
                    New Items</button>
            </div>
        </div>
    </main>

    <div id="inventoryModal"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm px-4">
        <div
            class="bg-[#1a1a1a] w-full max-w-md rounded-[30px] border border-gray-800 p-10 shadow-2xl relative overflow-hidden transition-all">
            <div class="flex justify-between items-start mb-8">
                <h3 id="modalTitle" class="heading-font text-2xl uppercase text-orange-500">Add New Item</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-white"><i
                        class="fas fa-times-circle text-xl"></i></button>
            </div>

            <form id="inventoryForm" action="products.php" method="POST" class="space-y-6"
                onsubmit="showConfirm(event)">
                <input type="hidden" name="id_barang" id="modal_id">
                <input type="hidden" id="submit_type" name="add_barang">
                <div>
                    <label class="text-[9px] font-black uppercase text-gray-400 mb-2 block ml-1">Product Name</label>
                    <input type="text" name="nama_barang" id="modal_nama" required
                        class="w-full input-dark p-4 rounded-2xl font-bold uppercase">
                </div>
                <div>
                    <label class="text-[9px] font-black uppercase text-gray-400 mb-2 block ml-1">Price (IDR)</label>
                    <input type="text" id="modal_harga_display" placeholder="0" required
                        class="w-full input-dark p-4 rounded-2xl font-mono text-orange-500 font-bold text-lg">
                    <input type="hidden" name="harga_barang" id="modal_harga">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[9px] font-black uppercase text-gray-400 mb-2 block ml-1">Stock</label>
                        <input type="number" name="stok_tersedia" id="modal_stok" required
                            class="w-full input-dark p-4 rounded-2xl font-mono text-center">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-gray-400 mb-2 block ml-1">Unit</label>
                        <select name="satuan" id="modal_satuan"
                            class="w-full input-dark p-4 rounded-2xl font-bold uppercase appearance-none text-center">
                            <option value="PCS">PCS</option>
                            <option value="METER">METER</option>
                        </select>
                    </div>
                </div>
                <button type="submit"
                    class="w-full bg-[#ff4d00] hover:bg-white text-black font-black py-5 rounded-2xl uppercase transition-all shadow-xl tracking-widest mt-4">Confirm
                    Action</button>
            </form>
        </div>
    </div>

    <div id="confirmModal"
        class="fixed inset-0 z-[60] hidden flex items-center justify-center bg-black/95 backdrop-blur-md px-4">
        <div
            class="bg-[#1a1a1a] w-full max-w-sm rounded-[40px] border-2 border-orange-500 p-10 text-center shadow-[0_0_50px_rgba(255,77,0,0.3)]">
            <div class="mb-6">
                <i class="fas fa-question-circle text-5xl text-orange-500 animate-bounce"></i>
            </div>
            <h4 class="heading-font text-xl text-white uppercase mb-4">Are you sure?</h4>

            <div class="bg-black/40 p-5 rounded-[25px] border border-gray-800 mb-8 text-left space-y-2">
                <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest">Detail Item:</p>
                <p id="conf_nama" class="text-white font-black uppercase text-sm leading-tight"></p>
                <div class="flex justify-between items-baseline pt-2">
                    <p id="conf_harga" class="text-orange-500 font-mono font-black text-xs"></p>
                    <p id="conf_stok" class="text-gray-400 font-bold text-[10px] uppercase"></p>
                </div>
            </div>

            <div class="flex flex-col space-y-3">
                <button onclick="executeSubmit()"
                    class="w-full bg-orange-500 text-black font-black py-4 rounded-2xl uppercase tracking-widest text-[11px] hover:bg-white transition-all shadow-lg shadow-orange-500/20">
                    YES, SAVE DATA
                </button>
                <button onclick="closeConfirm()"
                    class="w-full bg-transparent text-gray-500 font-black py-4 rounded-2xl uppercase tracking-widest text-[11px] hover:text-white transition-all">
                    CANCEL
                </button>
            </div>
        </div>
    </div>

    <div id="customAlert"
        class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/90 backdrop-blur-md transition-all">
        <div
            class="bg-[#1a1a1a] w-full max-w-sm rounded-[40px] border-2 border-orange-500/50 p-10 text-center shadow-[0_0_50px_rgba(255,77,0,0.2)]">
            <div id="alertIcon" class="mb-6"></div>
            <h4 id="alertTitle" class="heading-font text-xl text-white uppercase mb-4">SUCCESS</h4>
            <p id="alertMessage" class="text-gray-400 text-[11px] leading-relaxed uppercase tracking-widest mb-8"></p>
            <button onclick="hideAlert()"
                class="w-full bg-orange-500 text-black font-black py-4 rounded-2xl uppercase tracking-widest text-[11px] transition-all hover:bg-white">Dismiss</button>
        </div>
    </div>

    <script>
    function toggleDropdown() {
        document.getElementById('service-dropdown').classList.toggle('show');
    }

    function formatRupiah(angka) {
        let number_string = angka.toString().replace(/[^,\d]/g, ''),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        return rupiah;
    }

    const priceInput = document.getElementById('modal_harga_display');
    const hiddenPrice = document.getElementById('modal_harga');

    priceInput.addEventListener('keyup', function() {
        this.value = formatRupiah(this.value);
        hiddenPrice.value = this.value.replace(/\./g, '');
    });

    function openModal(mode, id = '', nama = '', harga = '', stok = '', satuan = 'PCS') {
        document.getElementById('inventoryForm').reset();
        const title = document.getElementById('modalTitle');
        const submitType = document.getElementById('submit_type');
        if (mode === 'add') {
            title.innerText = "Add New Item";
            submitType.name = "add_barang";
            priceInput.value = '';
            hiddenPrice.value = '';
        } else {
            title.innerText = "Edit Item";
            submitType.name = "update_barang";
            document.getElementById('modal_id').value = id;
            document.getElementById('modal_nama').value = nama;
            hiddenPrice.value = harga;
            priceInput.value = formatRupiah(harga);
            document.getElementById('modal_stok').value = stok;
            document.getElementById('modal_satuan').value = satuan;
        }
        document.getElementById('inventoryModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('inventoryModal').classList.add('hidden');
    }

    // Fungsi untuk memunculkan modal konfirmasi + Tampilkan Data
    function showConfirm(e) {
        e.preventDefault(); // Tahan form dulu

        // 1. Ambil data dari input field modal inventory
        const nama = document.getElementById('modal_nama').value;
        const hargaDisp = document.getElementById('modal_harga_display').value;
        const stok = document.getElementById('modal_stok').value;
        const satuan = document.getElementById('modal_satuan').value;

        // 2. Injek data ke dalam elemen di modal konfirmasi
        document.getElementById('conf_nama').innerText = nama;
        document.getElementById('conf_harga').innerText = "Rp " + hargaDisp;
        document.getElementById('conf_stok').innerText = stok + " " + satuan;

        // 3. Tampilkan modal konfirmasi
        document.getElementById('confirmModal').classList.remove('hidden');
    }

    // Fungsi jika user klik YES, SAVE DATA
    function executeSubmit() {
        document.getElementById('inventoryForm').submit();
    }

    // Fungsi tutup konfirmasi
    function closeConfirm() {
        document.getElementById('confirmModal').classList.add('hidden');
    }

    function hideAlert() {
        document.getElementById('customAlert').classList.add('hidden');
    }

    function showAlert(title, msg, icon) {
        document.getElementById('alertTitle').innerText = title;
        document.getElementById('alertMessage').innerHTML = msg;
        document.getElementById('alertIcon').innerHTML = `<i class="${icon} text-4xl"></i>`;
        document.getElementById('customAlert').classList.remove('hidden');
    }

    window.onload = () => {
        const queryString = window.location.search.substring(1);
        const params = {};
        if (queryString) {
            queryString.split('&').forEach(pair => {
                const [key, value] = pair.split('=');
                params[decodeURIComponent(key)] = decodeURIComponent(value || '');
            });
        }
        if ('msg' in params) {
            const item = params['item'];
            const qty = params['qty'];
            const status = params['msg'];
            // Pilih warna & icon berdasarkan status
            const title = status === 'success' ? 'NEW ITEM ADDED' : 'STOCK UPDATED';
            const icon = status === 'success' ? 'fas fa-plus-circle text-green-400' :
                'fas fa-sync-alt text-blue-400';
            // Tampilkan Alert Estetik kamu
            showAlert(
                title,
                `BARANG <b class="text-white">${item}</b><br>SEBANYAK <b class="text-orange-500">${qty}</b> BERHASIL DISIMPAN!`,
                icon
            );
            // --- INI KUNCINYA: BERSIHKAN URL ---
            // Menghapus ?msg=... dkk dari address bar biar jadi products.php aja
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
        // Fungsi untuk memunculkan modal konfirmasi
        function showConfirm(e) {
            e.preventDefault(); // Tahan form agar tidak langsung kirim
            document.getElementById('confirmModal').classList.remove('hidden');
            return false;
        }
        // Fungsi jika user klik CANCEL di konfirmasi
        function closeConfirm() {
            document.getElementById('confirmModal').classList.add('hidden');
        }
        // Fungsi jika user klik YES, SAVE DATA
        function executeSubmit() {
            // Jalankan submit form secara manual
            document.getElementById('inventoryForm').submit();
        }
        // Update fungsi closeModal agar ikut menutup konfirmasi jika terbuka
        function closeModal() {
            document.getElementById('inventoryModal').classList.add('hidden');
            closeConfirm();
        }
    }
    </script>

</html>