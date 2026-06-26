<?php
require 'koneksi.php';
require 'header.php';

// 1. Ambil Nama Username yang sedang login
$stmt_user = $koneksi->prepare("SELECT username FROM table_user WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$user_aktif = $stmt_user->fetchColumn();

// 2. Kalkulasi Statistik Properti
$total_kost = $koneksi->query("SELECT COUNT(*) FROM table_kost")->fetchColumn();
$total_kamar = $koneksi->query("SELECT COUNT(*) FROM table_kamar")->fetchColumn();
$kamar_isi = $koneksi->query("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) = 'isi' OR LOWER(status_kamar) = 'terisi'")->fetchColumn();
$kamar_kosong = $koneksi->query("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) = 'kosong'")->fetchColumn();

// Nilai keuangan dikosongkan sementara sampai tabelnya dibuat
$total_pengeluaran = 0; 
?>

<div class="mb-8">
    <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight">Selamat datang, <span class="text-yellow-600 capitalize"><?= htmlspecialchars($user_aktif) ?></span>!</h1>
    <p class="text-gray-500 mt-2 text-sm">Berikut adalah ringkasan performa dan statistik properti Kost Sun Anda saat ini.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Lokasi Kost</p>
        <p class="text-3xl font-black text-gray-800"><?= $total_kost ?> <span class="text-sm font-medium text-gray-400">Properti</span></p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Keseluruhan Kamar</p>
        <p class="text-3xl font-black text-gray-800"><?= $total_kamar ?> <span class="text-sm font-medium text-gray-400">Pintu</span></p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Kamar Terisi</p>
        <p class="text-3xl font-black text-green-600"><?= $kamar_isi ?> <span class="text-sm font-medium text-green-400">Pintu</span></p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Kamar Kosong</p>
        <p class="text-3xl font-black text-red-600"><?= $kamar_kosong ?> <span class="text-sm font-medium text-red-400">Pintu</span></p>
    </div>

</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-400 mt-12 border-dashed">
    <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <h3 class="text-lg font-bold text-gray-600 mb-1">Modul Keuangan & Operasional</h3>
    <p class="text-sm">Tabel statistik beban biaya (PDAM, Listrik, Internet) dan pemasukan sewa akan muncul di sini setelah tabel database disiapkan.</p>
</div>

<?php require 'footer.php'; ?>