<?php
// 1. Mulai session untuk mengenali siapa yang mau logout
session_start();

// 2. Hapus semua variabel session (username, role, id_user, dll)
session_unset();

// 3. Hancurkan session dari server
session_destroy();

// 4. Arahkan kembali ke halaman login agar user tidak bisa masuk lagi tanpa autentikasi
header("Location: login.php?pesan=logout_berhasil");
exit;
?>