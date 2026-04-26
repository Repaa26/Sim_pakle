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

// --- AMBIL DAFTAR KATEGORI UNTUK DATALIST ---
$kategoriList = $pdo->query("SELECT nama_kategori FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// --- 1. LOGIKA PROSES (TAMBAH & UPDATE VARIAN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kategori = strtoupper($_POST['nama_kategori']);
    $ukuran = strtoupper($_POST['ukuran']);
    $warna = strtoupper($_POST['warna']);
    $harga = $_POST['harga_barang'];
    $stok = $_POST['stok_tersedia'];
    $satuan = $_POST['satuan'];

    // Cerdas: Cari nama_kategori, kalau belum ada di database, buat kategori baru otomatis
    $stmtK = $pdo->prepare("SELECT id_kategori FROM kategori WHERE nama_kategori = ?");
    $stmtK->execute([$nama_kategori]);
    $k = $stmtK->fetch();

    if ($k) {
        $id_kategori = $k['id_kategori'];
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $stmtIns->execute([$nama_kategori]);
        $id_kategori = $pdo->lastInsertId();
    }

    if (isset($_POST['add_barang'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO varian_barang (id_kategori, ukuran, warna, harga, stok_tersedia, satuan) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_kategori, $ukuran, $warna, $harga, $stok, $satuan]);
            header("Location: products.php?msg=success&item=$nama_kategori [$ukuran / $warna]&qty=$stok&prc=$harga");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif (isset($_POST['update_barang'])) {
        $id_varian = $_POST['id_varian'];
        try {
            $stmt = $pdo->prepare("UPDATE varian_barang SET id_kategori = ?, ukuran = ?, warna = ?, harga = ?, stok_tersedia = ?, satuan = ? WHERE id_varian = ?");
            $stmt->execute([$id_kategori, $ukuran, $warna, $harga, $stok, $satuan, $id_varian]);
            header("Location: products.php?msg=updated&item=$nama_kategori [$ukuran / $warna]&qty=$stok&prc=$harga");
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
$whereClause = $search ? " WHERE k.nama_kategori LIKE ? OR v.id_varian LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$totalData = $pdo->prepare("SELECT COUNT(*) FROM varian_barang v JOIN kategori k ON v.id_kategori = k.id_kategori" . $whereClause);
$totalData->execute($params);
$totalRows = $totalData->fetchColumn();
$totalHalaman = ceil($totalRows / $limit);

$sql = "SELECT v.*, k.nama_kategori 
        FROM varian_barang v 
        JOIN kategori k ON v.id_kategori = k.id_kategori 
        $whereClause ORDER BY v.id_varian DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

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
            color: white;
            border: 1px solid #333;
        }

        .input-dark:focus {
            border-color: #ff4d00;
            outline: none;
        }

        #service-dropdown {
            transition: all 0.3s ease-in-out;
            max-height: 0;
            overflow: hidden;
        }

        #service-dropdown.show {
            max-height: 400px;
            margin-top: 0.5rem;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 10px;
        }

        select.input-dark {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23ffffff%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 1rem top 50%;
            background-size: 0.65rem auto;
        }
    </style>
</head>

<body class="flex h-screen overflow-hidden bg-black text-white">

    <?php require 'sidebar.php'; ?>

    <main class="flex-1 custom-dark p-12 overflow-y-auto">
        <header class="flex justify-between items-baseline mb-8 border-b border-gray-800/50 pb-6">
            <div>
                <h2 class="heading-font text-white text-4xl uppercase text-orange-500">Inventory</h2>
                <p class="text-gray-500 text-[10px] font-black uppercase tracking-[0.4em] mt-2">Manage Categories &
                    Stock</p>
            </div>
            <form action="" method="GET" class="relative w-80">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search items or SKU..."
                    class="w-full bg-white rounded-full py-2.5 px-6 text-sm text-black outline-none focus:ring-2 focus:ring-orange-500 transition-all">
                <button type="submit" class="absolute right-5 top-3 text-gray-400 hover:text-orange-500"><i
                        class="fas fa-search"></i></button>
            </form>
        </header>

        <div class="card-table p-8 border border-gray-800/50 shadow-2xl">
            <table class="w-full text-left">
                <thead>
                    <tr
                        class="text-gray-500 text-[11px] font-black uppercase tracking-[0.2em] border-b border-gray-800">
                        <th class="pb-5 px-4">SKU / ID</th>
                        <th class="pb-5">Category & Detail</th>
                        <th class="pb-5">Unit Price</th>
                        <th class="pb-5 text-center">In Stock</th>
                        <th class="pb-5 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-[12px] font-semibold tracking-wide uppercase">
                    <?php foreach ($products as $row): ?>
                        <tr class="border-b border-gray-800/50 hover:bg-white/5 transition-all">
                            <td class="py-5 px-4 text-orange-500 font-mono font-bold">
                                #SKU-<?= str_pad($row['id_varian'], 4, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td class="py-5">
                                <p class="text-gray-100 font-black tracking-wider text-sm">
                                    <?= htmlspecialchars($row['nama_kategori']) ?>
                                </p>
                                <p class="text-[9px] text-orange-500 font-bold uppercase mt-1">
                                    Size: <span class="text-white mr-2"><?= htmlspecialchars($row['ukuran']) ?></span>
                                    Color: <span class="text-white"><?= htmlspecialchars($row['warna']) ?></span>
                                </p>
                            </td>
                            <td class="py-5 text-white font-mono"><?= formatRupiah($row['harga']) ?></td>
                            <td class="py-5 text-center">
                                <?php $isLow = ($row['stok_tersedia'] <= 10); ?>
                                <span
                                    class="px-3 py-1 rounded-full border <?= $isLow ? 'text-red-500 border-red-500 animate-pulse' : 'text-white border-gray-700' ?>">
                                    <?= $row['stok_tersedia'] ?>     <?= $row['satuan'] ?>
                                </span>
                            </td>
                            <td class="py-5 text-center">
                                <button
                                    onclick="openModal('edit', '<?= $row['id_varian'] ?>', '<?= addslashes($row['nama_kategori']) ?>', '<?= addslashes($row['ukuran']) ?>', '<?= addslashes($row['warna']) ?>', '<?= $row['harga'] ?>', '<?= $row['stok_tersedia'] ?>', '<?= $row['satuan'] ?>')"
                                    class="bg-white/5 hover:bg-orange-500 hover:text-black px-4 py-2 rounded-xl border border-gray-800 transition-all text-[10px] font-bold uppercase">Update</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-8 flex justify-between items-center border-t border-gray-800 pt-6">
                <p class="text-[10px] text-gray-500 uppercase font-bold">Total Variants: <?= $totalRows ?></p>
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
                    New Variant</button>
            </div>
        </div>
    </main>

    <div id="inventoryModal"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm px-4">
        <div
            class="bg-[#1a1a1a] w-full max-w-lg rounded-[30px] border border-gray-800 p-10 shadow-2xl relative overflow-hidden transition-all">
            <div class="flex justify-between items-start mb-8">
                <h3 id="modalTitle" class="heading-font text-2xl uppercase text-orange-500">Add New Variant</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-white"><i
                        class="fas fa-times-circle text-xl"></i></button>
            </div>

            <form id="inventoryForm" action="products.php" method="POST" class="space-y-5"
                onsubmit="return showConfirm(event)">
                <input type="hidden" name="id_varian" id="modal_id">
                <input type="hidden" id="submit_type" name="add_barang">

                <div>
                    <label class="text-[9px] font-black uppercase text-gray-400 mb-2 block ml-1">Category Name</label>
                    <input type="text" name="nama_kategori" id="modal_kategori" list="daftar_kategori" required
                        placeholder="E.G. BAJU BOXY..."
                        class="w-full input-dark p-4 rounded-2xl font-bold uppercase text-xs">
                    <datalist id="daftar_kategori">
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= htmlspecialchars($k['nama_kategori']) ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[9px] font-black uppercase text-gray-400 mb-2 block ml-1">Size</label>
                        <input type="text" name="ukuran" id="modal_ukuran" required placeholder="S, M, L, XL, etc"
                            class="w-full input-dark p-4 rounded-2xl font-bold uppercase text-xs">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-gray-400 mb-2 block ml-1">Color</label>
                        <input type="text" name="warna" id="modal_warna" required placeholder="Black, White, etc"
                            class="w-full input-dark p-4 rounded-2xl font-bold uppercase text-xs">
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-gray-400 mb-2 block ml-1">Variant Price
                        (IDR)</label>
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
                            class="w-full input-dark p-4 rounded-2xl font-bold uppercase text-center">
                            <option value="PCS">PCS</option>
                            <option value="METER">METER</option>
                        </select>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-[#ff4d00] hover:bg-white text-black font-black py-5 rounded-2xl uppercase transition-all shadow-xl tracking-widest mt-6">Confirm
                    Action</button>
            </form>
        </div>
    </div>

    <div id="confirmModal"
        class="fixed inset-0 z-[60] hidden flex items-center justify-center bg-black/95 backdrop-blur-md px-4">
        <div
            class="bg-[#1a1a1a] w-full max-w-sm rounded-[40px] border-2 border-orange-500 p-10 text-center shadow-[0_0_50px_rgba(255,77,0,0.3)]">
            <div class="mb-6"><i class="fas fa-question-circle text-5xl text-orange-500 animate-bounce"></i></div>
            <h4 class="heading-font text-xl text-white uppercase mb-4">Are you sure?</h4>

            <div class="bg-black/40 p-5 rounded-[25px] border border-gray-800 mb-8 text-left space-y-2">
                <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest">Detail Variant:</p>
                <p id="conf_nama" class="text-white font-black uppercase text-sm leading-tight"></p>
                <p id="conf_varian" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"></p>
                <div class="flex justify-between items-baseline pt-2 border-t border-gray-800 mt-2">
                    <p id="conf_harga" class="text-orange-500 font-mono font-black text-xs"></p>
                    <p id="conf_stok" class="text-white font-bold text-[10px] uppercase"></p>
                </div>
            </div>

            <div class="flex flex-col space-y-3">
                <button onclick="executeSubmit()"
                    class="w-full bg-orange-500 text-black font-black py-4 rounded-2xl uppercase tracking-widest text-[11px] hover:bg-white transition-all shadow-lg shadow-orange-500/20">YES,
                    SAVE DATA</button>
                <button onclick="closeConfirm()"
                    class="w-full bg-transparent text-gray-500 font-black py-4 rounded-2xl uppercase tracking-widest text-[11px] hover:text-white transition-all">CANCEL</button>
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

        priceInput.addEventListener('keyup', function () {
            this.value = formatRupiah(this.value);
            hiddenPrice.value = this.value.replace(/\./g, '');
        });

        function openModal(mode, id = '', kategori = '', ukuran = '', warna = '', harga = '', stok = '', satuan = 'PCS') {
            document.getElementById('inventoryForm').reset();
            const title = document.getElementById('modalTitle');
            const submitType = document.getElementById('submit_type');

            if (mode === 'add') {
                title.innerText = "Add New Variant";
                submitType.name = "add_barang";
                priceInput.value = '';
                hiddenPrice.value = '';
            } else {
                title.innerText = "Edit Variant";
                submitType.name = "update_barang";
                document.getElementById('modal_id').value = id;
                document.getElementById('modal_kategori').value = kategori;
                document.getElementById('modal_ukuran').value = ukuran;
                document.getElementById('modal_warna').value = warna;
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

        function showConfirm(e) {
            e.preventDefault();
            const kategori = document.getElementById('modal_kategori').value;
            const size = document.getElementById('modal_ukuran').value;
            const color = document.getElementById('modal_warna').value;
            const hargaDisp = document.getElementById('modal_harga_display').value;
            const stok = document.getElementById('modal_stok').value;
            const satuan = document.getElementById('modal_satuan').value;

            document.getElementById('conf_nama').innerText = kategori;
            document.getElementById('conf_varian').innerText = "SIZE: " + size + " | COLOR: " + color;
            document.getElementById('conf_harga').innerText = "Rp " + hargaDisp;
            document.getElementById('conf_stok').innerText = stok + " " + satuan;

            document.getElementById('confirmModal').classList.remove('hidden');
            return false;
        }

        function executeSubmit() {
            document.getElementById('inventoryForm').submit();
        }

        function closeConfirm() {
            document.getElementById('confirmModal').classList.add('hidden');
        }

        function hideAlert() {
            document.getElementById('customAlert').classList.add('hidden');
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
</body>

</html>