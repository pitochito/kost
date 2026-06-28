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
    <title>Kost Sun | Secure Terminal</title>
    <!-- Tambahkan baris ini -->
    <link rel="icon" type="image/jpeg" href="logo.jpg?v=<?= time(); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Highlight selection untuk efek terminal */
        ::selection { background: #eab308; color: #000; }
        ::-moz-selection { background: #eab308; color: #000; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen font-mono relative overflow-hidden">
    
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#e5e7eb_1px,transparent_1px),linear-gradient(to_bottom,#e5e7eb_1px,transparent_1px)] bg-[size:40px_40px] opacity-70 pointer-events-none"></div>

    <div class="relative z-10 w-full max-w-sm mx-4 p-6 sm:p-8 bg-[#0a0a0a] border border-gray-800 border-t-4 border-t-yellow-500 rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.15)] flex flex-col">
        
        <div class="flex flex-col items-center mb-6 text-center">
            <div class="bg-black p-2 rounded-lg border border-gray-800 mb-4 inline-block">
                <img src="logo.jpg" alt="Logo Kost Sun" class="h-16 w-16 sm:h-20 sm:w-20 object-contain">
            </div>
            <h2 class="text-xl sm:text-2xl font-bold tracking-widest text-white uppercase">SYS_LOGIN</h2>
            <p class="text-[10px] sm:text-xs text-yellow-500 mt-1 tracking-widest">AUTHORIZED PERSONNEL ONLY</p>
        </div>
        
        <?php if ($pesan_error): ?>
            <div class="bg-red-900/40 border border-red-500/50 text-red-400 p-3 rounded mb-6 text-xs sm:text-sm text-center tracking-wide flex items-center justify-center gap-2 font-sans font-semibold">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= $pesan_error ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="flex flex-col gap-5 w-full">
            <div class="w-full">
                <label class="block text-xs sm:text-sm font-semibold text-gray-400 mb-2 tracking-wider uppercase">User_ID</label>
                <input type="text" name="username" 
                       class="w-full bg-[#121212] border border-gray-700 text-white text-base sm:text-lg px-4 py-3 rounded focus:outline-none focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 transition-all placeholder-gray-600 block" 
                       placeholder="admin" required autocomplete="off">
            </div>
            <div class="w-full">
                <label class="block text-xs sm:text-sm font-semibold text-gray-400 mb-2 tracking-wider uppercase">Pass_Key</label>
                <input type="password" name="password" 
                       class="w-full bg-[#121212] border border-gray-700 text-white text-base sm:text-lg px-4 py-3 rounded focus:outline-none focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 transition-all placeholder-gray-600 block" 
                       placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-400 text-black font-bold tracking-widest uppercase py-3.5 px-4 rounded mt-2 transition-all shadow-[0_0_15px_rgba(234,179,8,0.2)] hover:shadow-[0_0_25px_rgba(234,179,8,0.4)] text-base sm:text-lg block">
                Authenticate
            </button>
        </form>
        
        <div class="mt-8 pt-4 border-t border-gray-800 text-center w-full">
            <p class="text-[9px] sm:text-[10px] text-gray-500 tracking-widest break-words">CONNECTION: SECURE // KOST_SUN_DB</p>
        </div>
    </div>
</body>
</html>
