<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_admin_sekarang = $_SESSION['id_user'];

// --- LOGIKA ID OTOMATIS PL0001 ---
$queryNextID = $pdo->query("SELECT MAX(id_pelanggan) as last_id FROM pelanggan")->fetch();
$nextIDValue = ($queryNextID['last_id'] ?? 0) + 1;
$autoID = "PL" . sprintf('%04d', $nextIDValue);

// --- AMBIL DATA VARIAN BARANG (JOIN 3 TABEL) ---
$queryBarang = $pdo->query("SELECT v.id_varian, v.id_barang, v.ukuran, v.warna, v.harga, v.stok_tersedia, b.nama_barang, k.nama_kategori 
                            FROM varian_barang v 
                            JOIN barang b ON v.id_barang = b.id_barang 
                            JOIN kategori k ON b.id_kategori = k.id_kategori 
                            ORDER BY k.nama_kategori ASC, b.nama_barang ASC, v.ukuran ASC");
$daftarBarang = $queryBarang->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Logika Pelanggan
        $no_telp = $_POST['no_telp'];
        $nama_pelanggan = $_POST['nama_pelanggan'];
        $alamat = $_POST['alamat'];

        $stmtCekPlg = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE no_telp = ?");
        $stmtCekPlg->execute([$no_telp]);
        $pelangganLama = $stmtCekPlg->fetch();

        if ($pelangganLama) {
            $id_pelanggan = $pelangganLama['id_pelanggan'];
            $stmtUpdatePlg = $pdo->prepare("UPDATE pelanggan SET nama_pelanggan = ?, alamat = ? WHERE id_pelanggan = ?");
            $stmtUpdatePlg->execute([$nama_pelanggan, $alamat, $id_pelanggan]);
        } else {
            $stmtInsertPlg = $pdo->prepare("INSERT INTO pelanggan (nama_pelanggan, no_telp, alamat) VALUES (?, ?, ?)");
            $stmtInsertPlg->execute([$nama_pelanggan, $no_telp, $alamat]);
            $id_pelanggan = $pdo->lastInsertId();
        }

        // 2. Simpan Pembayaran
        $total_harga = (float) $_POST['total_hidden'];
        $jumlah_bayar = (float) $_POST['dp_input'];
        $metode_awal = $_POST['metode_bayar'];

        $status_bayar = ($jumlah_bayar >= $total_harga) ? 'lunas' : (($jumlah_bayar > 0) ? 'dp' : 'belum');

        $tgl_sekarang = date('Y-m-d H:i:s');
        $tgl_dp = ($jumlah_bayar > 0) ? $tgl_sekarang : null;
        $tgl_lunas = ($status_bayar == 'lunas') ? $tgl_sekarang : null;
        $admin_dp = ($jumlah_bayar > 0) ? $id_admin_sekarang : null;
        $admin_lunas = ($status_bayar == 'lunas') ? $id_admin_sekarang : null;
        $m_dp = ($jumlah_bayar > 0) ? $metode_awal : null;
        $m_lunas = ($status_bayar == 'lunas') ? $metode_awal : null;

        $stmtPay = $pdo->prepare("INSERT INTO pembayaran (status_bayar, jumlah_bayar, tgl_dp, tgl_lunas, id_admin_dp, id_admin_lunas, metode_dp, metode_lunas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtPay->execute([$status_bayar, $jumlah_bayar, $tgl_dp, $tgl_lunas, $admin_dp, $admin_lunas, $m_dp, $m_lunas]);
        $id_pembayaran = $pdo->lastInsertId();

        // 3. SIMPAN PESANAN UTAMA
        $stmtOrder = $pdo->prepare("INSERT INTO pesanan (id_pelanggan, id_pembayaran, total_harga, tgl_pesanan, status_acc) VALUES (?, ?, ?, NOW(), 'pending')");
        $stmtOrder->execute([$id_pelanggan, $id_pembayaran, $total_harga]);
        $id_pesanan = $pdo->lastInsertId();

        // 4. Detail Barang & Update Stok Varian
        // Memasukkan id_barang dan id_varian agar relasi tetap aman
        $stmtDetail = $pdo->prepare("INSERT INTO detail_pesanan (id_pesanan, id_barang, id_varian, jumlah, harga_satuan, subtotal, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtUpdateStok = $pdo->prepare("UPDATE varian_barang SET stok_tersedia = stok_tersedia - ? WHERE id_varian = ?");
        $stmtCekStok = $pdo->prepare("SELECT b.id_barang, b.nama_barang, v.ukuran, v.warna, v.stok_tersedia FROM varian_barang v JOIN barang b ON v.id_barang = b.id_barang WHERE v.id_varian = ?");

        // Perhatikan loop ini sekarang menggunakan id_varian[]
        foreach ($_POST['id_varian'] as $key => $id_varian) {
            if ($id_varian == "0") continue;

            $qty_input = (int) $_POST['qty'][$key];
            $harga_satuan = (float) $_POST['harga_satuan_hidden'][$key];
            $subtotal_item = $qty_input * $harga_satuan;
            $ket_item = $_POST['keterangan_item'][$key];

            $stmtCekStok->execute([$id_varian]);
            $dataVarian = $stmtCekStok->fetch();

            if ($qty_input > $dataVarian['stok_tersedia']) {
                $nama_lengkap = $dataVarian['nama_barang'] . " [" . $dataVarian['ukuran'] . "/" . $dataVarian['warna'] . "]";
                throw new Exception("Stok " . $nama_lengkap . " tidak mencukupi!");
            }

            // Execute insert detail & potong stok
            $stmtDetail->execute([$id_pesanan, $dataVarian['id_barang'], $id_varian, $qty_input, $harga_satuan, $subtotal_item, $ket_item]);
            $stmtUpdateStok->execute([$qty_input, $id_varian]);
        }

        // 5. Inisialisasi Produksi & Upload File
        $newFile = null;
        if (isset($_FILES['desain']) && !empty($_FILES['desain']['name'][0])) {
            $folder = "uploads/";
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            if ($_FILES['desain']['error'][0] === 0) {
                $ext = pathinfo($_FILES['desain']['name'][0], PATHINFO_EXTENSION);
                $newFile = "desain_" . time() . "_" . uniqid() . "." . $ext;
                move_uploaded_file($_FILES['desain']['tmp_name'][0], $folder . $newFile);
            }
        }

        $stmtProd = $pdo->prepare("INSERT INTO produksi (status_produksi, konfir_desain, id_user, id_pesanan) VALUES ('antrean', ?, ?, ?)");
        $stmtProd->execute([$newFile, $id_admin_sekarang, $id_pesanan]);

        $pdo->commit();
        echo "<script>alert('SUKSES! Pesanan Baru Berhasil Disimpan.'); window.location='index.php';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('GAGAL! Terjadi kesalahan: " . addslashes($e->getMessage()) . "');</script>";
    }
}

$username_display = $_SESSION['username'];
$role_display = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIM Pakle Production - New Order</title>
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
        <header class="flex justify-between items-baseline mb-8 border-b border-gray-800/50 pb-6">
            <div>
                <h2 class="heading-font text-4xl italic uppercase">New Order</h2>
                <p class="text-gray-500 text-xl italic font-light tracking-wide">Design your vision. We craft
                    perfection.</p>
            </div>
        </header>

        <form id="orderForm" action="pesanan.php" method="POST" enctype="multipart/form-data"
            class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <div class="space-y-8">
                <div class="space-y-4">
                    <label class="heading-font text-lg border-gray-800/50 uppercase tracking-widest">1.
                        Pelanggan</label>
                    <div class="flex gap-4">
                        <div class="w-1/3">
                            <p class="text-gray-500 text-[10px] mb-1 uppercase font-bold">ID :</p>
                            <input type="text" value="<?= $autoID ?>" readonly
                                class="w-full input-dark p-4 rounded-xl opacity-50 cursor-not-allowed">
                        </div>
                        <div class="w-2/3">
                            <p class="text-gray-500 text-[10px] mb-1 uppercase font-bold">Nama :</p>
                            <input type="text" name="nama_pelanggan" placeholder="Nama Lengkap" required
                                class="w-full input-dark p-4 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none">
                        </div>
                    </div>
                    <input type="text" name="no_telp" placeholder="Nomor Telepon" required
                        class="w-full input-dark p-4 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none">
                    <textarea name="alamat" placeholder="Alamat Lengkap" required rows="2"
                        class="w-full input-dark p-4 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none"></textarea>
                </div>

                <div class="space-y-4">
                    <label class="heading-font text-lg border-gray-800/50 uppercase tracking-widest">2. Item
                        Pesanan</label>
                    <div id="items-container" class="space-y-6">
                        <div class="item-row bg-[#1a1a1a]/50 p-6 rounded-[32px] border border-gray-800 space-y-4">
                            <div class="flex gap-3 items-center">
                                <select name="id_varian[]" onchange="calculate()" required
                                    class="flex-1 input-dark p-3 rounded-xl text-sm product-select outline-none">
                                    <option value="0" data-price="0" data-stock="0">Pilih Produk & Varian</option>
                                    <?php foreach ($daftarBarang as $b): ?>
                                    <option value="<?= $b['id_varian'] ?>" data-price="<?= $b['harga'] ?>"
                                        data-stock="<?= $b['stok_tersedia'] ?>">
                                        <?= htmlspecialchars($b['nama_kategori'] . ' - ' . $b['nama_barang'] . ' [' . $b['ukuran'] . ' / ' . $b['warna'] . ']') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="harga_satuan_hidden[]" class="price-hidden" value="0">
                                <input type="number" name="qty[]" oninput="calculate()" placeholder="QTY" required
                                    class="w-20 input-dark p-3 rounded-xl text-center text-sm qty-input outline-none">
                                <button type="button" onclick="removeItem(this)"
                                    class="text-gray-600 hover:text-red-500 p-2 transition-colors"><i
                                        class="fas fa-trash-alt"></i></button>
                            </div>
                            <textarea name="keterangan_item[]" rows="2"
                                placeholder="Detail Item (Nama, No Punggung, Catatan Khusus...)"
                                class="w-full bg-black/40 p-4 rounded-2xl text-xs italic text-gray-300 outline-none focus:ring-1 focus:ring-orange-500 resize-none"></textarea>
                        </div>
                    </div>
                    <button type="button" onclick="addItem()"
                        class="border-gray-800/50 text-[10px] font-bold uppercase tracking-widest"><i
                            class="fas fa-plus-circle mr-1"></i> Tambah Item Lain</button>
                </div>

                <div class="space-y-4">
                    <label class="heading-font text-lg border-gray-800/50 uppercase tracking-widest">3. Desain</label>
                    <input type="file" name="desain[]" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.pdf"
                        multiple>
                    <div onclick="document.getElementById('fileInput').click()"
                        class="border-2 border-dashed border-gray-700 rounded-[32px] p-10 flex flex-col items-center justify-center space-y-3 hover:border-orange-500 transition-all duration-300 cursor-pointer bg-[#1a1a1a]/50 group">
                        <i
                            class="fas fa-cloud-upload-alt text-4xl text-gray-600 group-hover:border-gray-800/50 transition-colors"></i>
                        <div id="fileList" class="text-center">
                            <p class="text-gray-500 text-[10px] font-bold uppercase tracking-[0.2em]">Klik untuk Upload
                                Banyak Desain</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <div class="bg-[#1a1a1a] p-8 rounded-[40px] border border-gray-800 shadow-xl">
                    <h3
                        class="heading-font text-white text-xl uppercase border-b border-gray-800 pb-4 mb-4 tracking-widest">
                        Ringkasan Pesanan</h3>
                    <div class="max-h-60 overflow-y-auto pr-2">
                        <table class="w-full text-left">
                            <thead class="text-gray-500 text-[14px] uppercase font-bold border-b border-gray-800/30">
                                <tr>
                                    <th class="pb-3">Item</th>
                                    <th class="pb-3 text-center">QTY</th>
                                    <th class="pb-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="summary-body" class="text-gray-400 space-y-2"></tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="bg-gradient-to-br from-[#1a1a1a] to-black p-10 rounded-[50px] border border-gray-800 shadow-2xl">
                    <h3
                        class="heading-font text-white text-xl uppercase border-b border-gray-800 pb-4 mb-8 tracking-widest">
                        Biaya</h3>
                    <div class="space-y-6">
                        <div class="flex justify-between items-center">
                            <p class="text-gray-500 text-sm italic">TOTAL HARGA :</p>
                            <input type="hidden" name="total_hidden" id="total-hidden" value="0">
                            <p id="total-price" class="text-white text-5xl font-black">Rp 0</p>
                        </div>
                        <div
                            class="flex justify-between items-center bg-black/50 p-5 rounded-3xl border border-gray-800/50">
                            <div>
                                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest">DP (50%) :</p>
                                <p class="text-[9px] text-orange-500 italic">*Minimal pembayaran awal</p>
                            </div>
                            <div class="flex items-center">
                                <span class="text-white text-2xl font-black mr-1">Rp</span>
                                <input type="text" id="dp-display" oninput="manualDP()"
                                    class="w-40 bg-transparent text-right text-2xl font-black text-white outline-none">
                                <input type="hidden" name="dp_input" id="dp-input" required>
                            </div>
                        </div>
                        <div class="flex justify-between items-center border-t border-gray-800 pt-8">
                            <p class="text-gray-500 text-sm font-bold uppercase tracking-widest">Sisa Tagihan :</p>
                            <p id="balance-due" class="border-gray-800/50 text-3xl font-black">Rp 0</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <select name="metode_bayar"
                        class="w-full input-dark p-5 rounded-3xl uppercase text-xs font-bold tracking-widest focus:ring-2 focus:ring-orange-500 outline-none">
                        <option value="tunai">TUNAI (CASH)</option>
                        <option value="transfer">TRANSFER BANK</option>
                        <option value="e-wallet">E-WALLET (QRIS)</option>
                    </select>
                    <button type="submit"
                        class="w-full bg-[#ff4d00] hover:bg-[#ff6a00] text-black text-2xl font-black py-7 rounded-[40px] uppercase transition-all">Simpan
                        Pesanan</button>
                </div>
            </div>
        </form>
    </main>

    <script>
    function toggleDropdown() {
        document.getElementById('service-dropdown').classList.toggle('show');
        document.getElementById('chevron-icon').classList.toggle('rotate-180');
    }

    function addItem() {
        const container = document.getElementById('items-container');
        const rows = document.querySelectorAll('.item-row');
        const newRow = rows[0].cloneNode(true);
        newRow.querySelector('.qty-input').value = "";
        newRow.querySelector('.product-select').selectedIndex = 0;
        newRow.querySelector('textarea').value = "";
        container.appendChild(newRow);
        calculate();
    }

    function removeItem(btn) {
        if (document.querySelectorAll('.item-row').length > 1) {
            btn.closest('.item-row').remove();
            calculate();
        }
    }

    function formatNumber(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function calculate() {
        let grandTotal = 0;
        const summaryBody = document.getElementById('summary-body');
        summaryBody.innerHTML = "";

        document.querySelectorAll('.item-row').forEach(row => {
            const select = row.querySelector('.product-select');
            const qtyInput = row.querySelector('.qty-input');
            const selectedOption = select.options[select.selectedIndex];

            const price = parseFloat(selectedOption.dataset.price) || 0;
            row.querySelector('.price-hidden').value = price;

            const stock = parseInt(selectedOption.dataset.stock) || 0;
            let qty = parseInt(qtyInput.value) || 0;

            if (select.value !== "0" && qty > 0) {
                if (qty > stock) {
                    alert(`Stok ${selectedOption.text} cuma ada ${stock}!`);
                    qty = stock;
                    qtyInput.value = stock;
                }
                const subtotal = qty * price;
                grandTotal += subtotal;
                summaryBody.innerHTML += `
            <tr class="border-b border-gray-800/20">
                <td class="py-3 uppercase font-bold text-[12px] leading-tight">${selectedOption.text}</td>
                <td class="py-3 text-center text-[12px]">${qty}x</td>
                <td class="py-3 text-right text-gray-200 font-mono text-[12px]">Rp ${formatNumber(subtotal)}</td>
            </tr>`;
            }
        });

        const dpDisplay = document.getElementById('dp-display');
        const dpHidden = document.getElementById('dp-input');
        const autoDP = Math.ceil(grandTotal * 0.50);

        dpHidden.value = autoDP;
        dpDisplay.value = formatNumber(autoDP);
        document.getElementById('total-price').innerText = `Rp ${formatNumber(grandTotal)}`;
        document.getElementById('total-hidden').value = grandTotal;

        const sisa = grandTotal - (parseInt(dpHidden.value) || 0);
        document.getElementById('balance-due').innerText = `Rp ${formatNumber(sisa)}`;
    }

    function manualDP() {
        const dpDisplay = document.getElementById('dp-display');
        const dpHidden = document.getElementById('dp-input');
        let value = dpDisplay.value.replace(/\D/g, "");
        if (value !== "") {
            dpHidden.value = value;
            dpDisplay.value = formatNumber(value);
        } else {
            dpHidden.value = 0;
            dpDisplay.value = "";
        }
        const total = parseInt(document.getElementById('total-hidden').value) || 0;
        const sisa = total - (parseInt(dpHidden.value) || 0);
        document.getElementById('balance-due').innerText = `Rp ${formatNumber(sisa)}`;
    }

    document.getElementById('orderForm').addEventListener('submit', function(e) {
        const dp = document.getElementById('dp-input').value;
        if (dp === "" || dp === "0") {
            e.preventDefault();
            alert("Nominal DP tidak boleh kosong!");
        }
    });

    document.getElementById('fileInput').addEventListener('change', function() {
        const list = document.getElementById('fileList');
        list.innerHTML = "";
        for (let i = 0; i < this.files.length; i++) {
            const p = document.createElement('p');
            p.className = "border-gray-800/50 text-[10px] font-bold uppercase mt-1 animate-pulse";
            p.innerHTML = `<i class="fas fa-check-circle mr-1"></i> ${this.files[i].name}`;
            list.appendChild(p);
        }
    });
    </script>
</body>

</html>