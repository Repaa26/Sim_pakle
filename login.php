<?php
session_start();
require 'koneksi.php';

// Jika sudah login, langsung lempar ke dashboard
if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        // Cari user berdasarkan username
        $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Verifikasi (Untuk tahap belajar kita pakai perbandingan langsung, 
        // tapi di dunia nyata wajib pakai password_hash)
        if ($user && $password === $user['password']) {
            // Set Session
            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header("Location: index.php");
            exit;
        } else {
            $error = 'Username atau Password salah!';
        }
    } else {
        $error = 'Harap isi semua bidang!';
    }

}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Login - SIM Pakle Production</title>
</head>

<body class="bg-gray-500 flex items-center justify-center min-h-screen">

    <div class="flex flex-col md:flex-row w-full max-w-4xl bg-[#333333] rounded-3xl overflow-hidden shadow-2xl mx-4">

        <div class="w-full md:w-1/2 p-12 flex flex-col justify-center text-white">
            <h2 class="text-3xl font-semibold mb-2 text-orange-500">Welcome back!</h2>
            <p class="text-gray-400 mb-8">Masukkan kredensial Anda untuk mengakses sistem.</p>

            <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'logout_berhasil'): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-200 px-4 py-2 rounded-lg mb-6 text-sm">
                <i class="fas fa-check-circle mr-2"></i> Anda telah berhasil keluar dari sistem.
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-200 px-4 py-2 rounded-lg mb-6 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
            </div>
            <?php endif; ?>
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm mb-1">Username</label>
                    <input type="text" name="username" placeholder="Masukkan username" required
                        class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500 transition text-white">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-sm">Password</label>
                        <a href="#" class="text-blue-500 text-xs hover:underline">Lupa password?</a>
                    </div>
                    <input type="password" name="password" placeholder="••••••••" required
                        class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500 transition text-white">
                </div>

                <div class="flex items-center space-x-2 py-2">
                    <input type="checkbox" id="remember" class="accent-orange-500">
                    <label for="remember" class="text-xs text-gray-400">Ingat saya selama 20 hari</label>
                </div>

                <button type="submit"
                    class="w-full bg-[#FF6B35] hover:bg-orange-600 text-white font-bold py-2 rounded-xl transition shadow-lg mt-4">
                    Login
                </button>
            </form>

            <p class="text-center text-sm text-gray-400 mt-8">
                Belum punya akun? <a href="#" class="text-blue-500 hover:underline text-orange-400">Hubungi Admin</a>
            </p>
        </div>

        <div class="hidden md:block md:w-1/2 relative bg-white">
            <img src="https://images.unsplash.com/photo-1525498128493-380d1990a112?fm=jpg&q=60&w=3000&ixlib=rb-4.1.0"
                alt="Background" class="absolute inset-0 w-full h-full object-cover rounded-l-[60px]">
            <div class="absolute inset-0 bg-black/20 rounded-l-[60px]"></div>
        </div>
    </div>

</body>

</html>