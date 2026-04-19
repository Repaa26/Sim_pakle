<?php
$current_page = basename($_SERVER['PHP_SELF']);
// Daftar halaman yang masuk kategori Service
$service_pages = ['pesanan.php', 'pembayaran.php', 'produksi.php', 'products.php', 'laporan.php', 'pelanggan.php'];
$is_service_active = in_array($current_page, $service_pages);
?>

<aside
    class="w-72 bg-white h-full rounded-r-[40px] flex flex-col justify-between py-10 px-6 z-10 shadow-2xl text-black">
    <div>
        <div class="mb-3 flex flex-col items-center">
            <img src="images/Logo.png" alt="Logo" class="w-42 mb-1">
            <h1 class="font-bold text-xl text-center leading-tight uppercase">SIM<br>Pakle Production</h1>
        </div>

        <nav class="space-y-3 text-lg">
            <a href="index.php"
                class="flex items-center space-x-4 px-6 py-3 <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-[#1a1a1a] text-white rounded-full shadow-lg' : 'text-gray-600 hover:text-black font-semibold' ?> transition-all">
                <i class="fas fa-home"></i><span>Home</span>
            </a>

            <div class="relative">
                <button onclick="toggleDropdown()"
                    class="w-full flex items-center justify-between px-6 py-3 transition-all <?= $is_service_active ? 'bg-[#1a1a1a] text-white rounded-full shadow-lg' : 'text-gray-600 hover:text-black font-semibold' ?>">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-puzzle-piece"></i>
                        <span class="font-bold">Service</span>
                    </div>
                    <i id="chevron-icon"
                        class="fas fa-chevron-down text-xs <?= $is_service_active ? 'rotate-180' : '' ?> transition-transform"></i>
                </button>

                <div id="service-dropdown"
                    class="ml-14 space-y-3 <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? '' : 'show' ?>">

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="pesanan.php"
                            class="flex items-center space-x-3 <?= basename($_SERVER['PHP_SELF']) == 'pesanan.php' ? 'text-black font-bold' : 'text-gray-400 hover:text-black' ?> mt-2">
                            <div
                                class="<?= basename($_SERVER['PHP_SELF']) == 'pesanan.php' ? 'w-2 h-2 bg-orange-500' : 'w-1.5 h-1.5 bg-gray-300' ?> rounded-full">
                            </div>
                            <span>Orders</span>
                        </a>

                        <a href="pembayaran.php"
                            class="flex items-center space-x-3 <?= basename($_SERVER['PHP_SELF']) == 'pembayaran.php' ? 'text-black font-bold' : 'text-gray-400 hover:text-black' ?>">
                            <div
                                class="<?= basename($_SERVER['PHP_SELF']) == 'pembayaran.php' ? 'w-2 h-2 bg-orange-500' : 'w-1.5 h-1.5 bg-gray-300' ?> rounded-full">
                            </div>
                            <span>Payments</span>
                        </a>
                    <?php endif; ?>

                    <a href="produksi.php"
                        class="flex items-center space-x-3 <?= basename($_SERVER['PHP_SELF']) == 'produksi.php' ? 'text-black font-bold' : 'text-gray-400 hover:text-black' ?>">
                        <div
                            class="<?= basename($_SERVER['PHP_SELF']) == 'produksi.php' ? 'w-2 h-2 bg-orange-500' : 'w-1.5 h-1.5 bg-gray-300' ?> rounded-full">
                        </div>
                        <span>Production</span>
                    </a>

                    <a href="products.php"
                        class="flex items-center space-x-3 <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'text-black font-bold' : 'text-gray-400 hover:text-black' ?>">
                        <div
                            class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'w-2 h-2 bg-orange-500' : 'w-1.5 h-1.5 bg-gray-300' ?> rounded-full">
                        </div>
                        <span>Products</span>
                    </a>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="laporan.php"
                            class="flex items-center space-x-3 <?= basename($_SERVER['PHP_SELF']) == 'laporan.php' ? 'text-black font-bold' : 'text-gray-400 hover:text-black' ?>">
                            <div
                                class="<?= basename($_SERVER['PHP_SELF']) == 'laporan.php' ? 'w-2 h-2 bg-orange-500' : 'w-1.5 h-1.5 bg-gray-300' ?> rounded-full">
                            </div>
                            <span>Reports</span>
                        </a>
                        <a href="pelanggan.php"
                            class="flex items-center space-x-3 <?= basename($_SERVER['PHP_SELF']) == 'pelanggan.php' ? 'text-black font-bold' : 'text-gray-400 hover:text-black' ?>">
                            <div
                                class="<?= basename($_SERVER['PHP_SELF']) == 'pelanggan.php' ? 'w-2 h-2 bg-orange-500' : 'w-1.5 h-1.5 bg-gray-300' ?> rounded-full">
                            </div>
                            <span>Customers</span>
                        </a>
                    <?php endif; ?>

                </div>
            </div>
        </nav>
    </div>

    <div class="flex items-center justify-between pt-6 border-t border-gray-100">
        <div class="flex items-center space-x-3">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=FF6B35&color=fff"
                class="w-11 h-11 rounded-full border-2 border-white shadow-sm">
            <div>
                <p class="font-extrabold text-sm uppercase leading-tight"><?= htmlspecialchars($_SESSION['username']) ?>
                </p>
                <p class="text-[10px] text-gray-400 font-bold uppercase"><?= $_SESSION['role'] ?></p>
            </div>
        </div>
        <a href="logout.php" onclick="return confirm('Keluar?')"
            class="text-gray-400 hover:text-red-500 transition-colors">
            <i class="fas fa-sign-out-alt text-xl"></i>
        </a>
    </div>
</aside>

<script>
    function toggleDropdown() {
        const dropdown = document.getElementById('service-dropdown');
        const icon = document.getElementById('chevron-icon');
        dropdown.classList.toggle('show');
        icon.style.transform = dropdown.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
    }
</script>

<style>
    #service-dropdown {
        transition: all 0.3s ease-in-out;
        max-height: 0;
        overflow: hidden;
    }

    #service-dropdown.show {
        max-height: 500px;
        margin-top: 0.5rem;
    }
</style>