<?php
session_start();
require 'koneksi.php';

// Jika sudah login, langsung tendang ke index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$pesan_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $koneksi->prepare("SELECT * FROM table_user WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cek apakah user ada DAN password cocok dengan hash di database
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: index.php");
        exit;
    } else {
        $pesan_error = "Autentikasi Gagal: Username atau Password tidak valid.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerbang Akses - Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Aksen seleksi teks berwarna kuning seperti di dalam sistem */
        ::selection { background: #eab308; color: #000; }
        ::-moz-selection { background: #eab308; color: #000; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans relative">
    
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none opacity-50">
        <div class="absolute -top-[20%] -left-[10%] w-[50%] h-[50%] rounded-full bg-gradient-to-br from-gray-200 to-transparent blur-3xl"></div>
        <div class="absolute -bottom-[20%] -right-[10%] w-[50%] h-[50%] rounded-full bg-gradient-to-tl from-gray-200 to-transparent blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] overflow-hidden border border-gray-200">
        
        <div class="bg-black p-8 text-center relative border-b-4 border-yellow-500">
            <div class="flex justify-center mb-4">
                <img src="logo.jpg" alt="Logo Kost Sun" class="h-20 w-20 object-contain rounded-lg bg-white p-1 shadow-md">
            </div>
            <h1 class="text-2xl font-black text-yellow-500 tracking-widest uppercase">Kost Sun</h1>
            <p class="text-xs text-gray-400 mt-1 font-mono tracking-widest uppercase">Manajemen Database Terpusat</p>
        </div>
        
        <div class="p-8">
            
            <?php if ($pesan_error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm font-semibold shadow-sm flex items-start gap-2">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?= $pesan_error ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="flex flex-col gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1.5 tracking-wider uppercase">User ID</label>
                    <input type="text" name="username" 
                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-lg px-4 py-3 rounded-lg focus:outline-none focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition-all font-mono font-semibold" 
                           placeholder="Masukkan ID..." required autocomplete="off" autofocus>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1.5 tracking-wider uppercase">Kata Sandi</label>
                    <input type="password" name="password" 
                           class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-lg px-4 py-3 rounded-lg focus:outline-none focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition-all font-mono font-semibold" 
                           placeholder="••••••••" required>
                </div>
                
                <button type="submit" class="w-full bg-black hover:bg-gray-800 text-yellow-500 font-bold tracking-wider uppercase py-3.5 px-4 rounded-lg mt-4 transition-all shadow-lg hover:shadow-xl text-sm flex justify-center items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    Akses Sistem
                </button>
            </form>
            
        </div>
    </div>
    
    <div class="fixed bottom-4 text-center w-full z-0 text-xs font-semibold text-gray-400">
        &copy; <?= date('Y') ?> Kost Sun Management. Hak Cipta Dilindungi.
    </div>

</body>
</html>