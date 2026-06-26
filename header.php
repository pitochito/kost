<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen - Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden text-gray-800 font-sans">

    <aside class="w-64 bg-black text-white flex flex-col hidden md:flex shadow-xl z-20">
        <div class="p-5 flex items-center gap-3 border-b border-gray-800">
            <img src="logo.jpg" alt="Logo" class="h-10 object-contain rounded">
            <span class="text-yellow-500 font-bold text-xl tracking-wider">KOST SUN</span>
        </div>
        <nav class="flex-1 overflow-y-auto py-4">
            <ul class="space-y-1 text-sm font-medium">
                <li><a href="index.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Dasbor Utama</a></li>
                <li><a href="data_kost.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Kost & Kamar</a></li>
                <li><a href="#" class="block px-6 py-3 text-gray-500 cursor-not-allowed">Data Customer (Segera)</a></li>
                <li><a href="#" class="block px-6 py-3 text-gray-500 cursor-not-allowed">Keuangan (Segera)</a></li>
                <li><a href="#" class="block px-6 py-3 text-gray-500 cursor-not-allowed">Manajemen User (Segera)</a></li>
                <li><a href="ubah_password.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Ubah Password</a></li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari sistem?')" class="block w-full bg-red-600 text-center py-2 rounded font-bold text-sm hover:bg-red-700 transition-colors">
                Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4 flex justify-between items-center z-10">
            <h2 class="font-bold text-lg text-gray-700">Workspace</h2>
            <div class="text-sm font-bold text-yellow-600 bg-yellow-50 px-4 py-1.5 rounded-full border border-yellow-100" id="live-clock">
                Memuat waktu...
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8 relative">