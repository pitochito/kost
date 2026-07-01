<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// PENGECEKAN HAK AKSES (ROLE-BASED ACCESS CONTROL)
// Mengambil data role dari database berdasarkan user yang sedang login
$stmt_role_header = $koneksi->prepare("SELECT role FROM table_user WHERE id = ?");
$stmt_role_header->execute([$_SESSION['user_id']]);
$role_aktif = strtolower($stmt_role_header->fetchColumn());
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen - Kost Sun</title>
    <!-- Tambahkan baris ini -->
    <link rel="icon" type="image/jpeg" href="ikon-sun.jpg">
    <link rel="apple-touch-icon" href="ikon-sun.jpg">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kost Sun">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden text-gray-800 font-sans relative">

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden md:hidden transition-opacity"></div>

    <aside id="sidebar" class="w-64 bg-black text-white flex flex-col fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 z-30 transition-transform duration-300 ease-in-out shadow-xl">
        <div class="p-5 flex items-center justify-between border-b border-gray-800">
            <div class="flex items-center gap-3">
                <img src="logo.jpg" alt="Logo" class="h-10 object-contain rounded bg-white p-0.5">
                <span class="text-yellow-500 font-bold text-xl tracking-wider">KOST SUN</span>
            </div>
            <button id="close-sidebar-btn" class="md:hidden text-gray-400 hover:text-white focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto py-4">
            <ul class="space-y-1 text-sm font-medium">
                <li><a href="index.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Dasbor Utama</a></li>
                <li><a href="data_kost.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Kost & Kamar</a></li>
                <li><a href="customer.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Data Customer</a></li>
                <li><a href="keuangan.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Keuangan</a></li>
                
                <!-- INI BARIS YANG SUDAH DIPERBAIKI -->
                <li><a href="laporan_tahunan.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Laporan Tahunan</a></li>
                
                <?php if ($role_aktif === 'super admin'): ?>
                <li><a href="user.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Manajemen User</a></li>
                <?php endif; ?>

                <li><a href="ubah_password.php" class="block px-6 py-3 hover:bg-gray-800 hover:text-yellow-500 transition-colors">Ubah Password</a></li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari sistem?')" class="block w-full bg-red-600 text-center py-2 rounded font-bold text-sm hover:bg-red-700 transition-colors">
                Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden w-full relative">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-6 py-4 flex justify-between items-center z-10">
            <div class="flex items-center gap-3">
                <button id="open-sidebar-btn" class="md:hidden text-gray-600 hover:text-black focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h2 class="font-bold text-lg text-gray-700 truncate">Workspace</h2>
            </div>
            
            <div class="text-xs md:text-sm font-bold text-yellow-600 bg-yellow-50 px-3 md:px-4 py-1.5 rounded-full border border-yellow-100 truncate" id="live-clock">
                Memuat waktu...
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 relative bg-[linear-gradient(to_right,#e5e7eb_1px,transparent_1px),linear-gradient(to_bottom,#e5e7eb_1px,transparent_1px)] bg-[size:40px_40px]">