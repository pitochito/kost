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
        $pesan_error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kost Sun | Secure Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Highlight selection untuk efek terminal */
        ::selection { background: #eab308; color: #000; }
        ::-moz-selection { background: #eab308; color: #000; }
    </style>
</head>
<body class="bg-[#0a0a0a] text-gray-300 flex items-center justify-center min-h-screen font-mono relative overflow-hidden">
    
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#27272a_1px,transparent_1px),linear-gradient(to_bottom,#27272a_1px,transparent_1px)] bg-[size:40px_40px] opacity-20"></div>

    <div class="relative z-10 w-full max-w-md p-8 bg-black/80 backdrop-blur-sm border border-gray-800 rounded-lg shadow-2xl">
        
        <div class="flex flex-col items-center mb-8">
            <img src="logo.jpg" alt="Logo Kost Sun" class="h-24 w-24 object-contain rounded mb-4 border border-gray-800 p-1">
            <h2 class="text-xl font-bold tracking-widest text-gray-100 uppercase">SYS_LOGIN</h2>
            <p class="text-[10px] text-gray-500 mt-1 tracking-widest">AUTHORIZED PERSONNEL ONLY</p>
        </div>
        
        <?php if ($pesan_error): ?>
            <div class="bg-red-900/40 border border-red-500/50 text-red-400 p-3 rounded mb-6 text-xs text-center tracking-wide flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?= $pesan_error ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="flex flex-col gap-5">
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-2 tracking-wider uppercase">User_ID</label>
                <input type="text" name="username" class="w-full bg-gray-900/50 border border-gray-800 text-yellow-500 px-4 py-2.5 rounded focus:outline-none focus:border-yellow-600 focus:ring-1 focus:ring-yellow-600 transition-all placeholder-gray-800" placeholder="admin" required autocomplete="off">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-2 tracking-wider uppercase">Pass_Key</label>
                <input type="password" name="password" class="w-full bg-gray-900/50 border border-gray-800 text-yellow-500 px-4 py-2.5 rounded focus:outline-none focus:border-yellow-600 focus:ring-1 focus:ring-yellow-600 transition-all placeholder-gray-800" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-500 text-black font-bold tracking-widest uppercase py-3 px-4 rounded mt-4 transition-all shadow-[0_0_15px_rgba(202,138,4,0.15)] hover:shadow-[0_0_25px_rgba(234,179,8,0.3)]">
                Authenticate
            </button>
        </form>
        
        <div class="mt-8 pt-4 border-t border-gray-900 text-center">
            <p class="text-[9px] text-gray-600 tracking-widest">CONNECTION: SECURE // KOST_SUN_DB</p>
        </div>
    </div>
</body>
</html>