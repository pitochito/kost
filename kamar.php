<?php
require 'koneksi.php';

// Pastikan selalu ada id_kost, jika tidak ada, kembalikan ke dashboard
if (!isset($_GET['id_kost'])) {
    header("Location: index.php");
    exit;
}

$id_kost = $_GET['id_kost'];
$pesan_sukses = '';

// PROSES HAPUS DATA KAMAR
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $stmt = $koneksi->prepare("DELETE FROM table_kamar WHERE id_kamar = ? AND id_kost = ?");
    $stmt->execute([$id_hapus, $id_kost]);
    header("Location: kamar.php?id_kost=$id_kost&pesan=sukses_hapus");
    exit;
}

if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses_hapus') {
    $pesan_sukses = "Data kamar berhasil dihapus.";
}

// AMBIL NAMA KOST UNTUK JUDUL
$stmt_kost = $koneksi->prepare("SELECT nama_kost FROM table_kost WHERE id_kost = ?");
$stmt_kost->execute([$id_kost]);
$kost = $stmt_kost->fetch(PDO::FETCH_ASSOC);

// AMBIL SEMUA DATA KAMAR BERDASARKAN ID KOST
$stmt_kamar = $koneksi->prepare("SELECT * FROM table_kamar WHERE id_kost = ? ORDER BY nomor_kamar ASC");
$stmt_kamar->execute([$id_kost]);
$data_kamar = $stmt_kamar->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kamar - Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <header class="bg-black shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <img src="logo.jpg" alt="Logo Kost Sun" class="h-12 object-contain rounded">
                <h1 class="text-2xl font-bold text-yellow-500 tracking-wide">KOST SUN</h1>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 w-full flex-1">
        
        <div class="mb-6">
            <a href="index.php" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Kembali ke Dashboard</a>
            <div class="flex justify-between items-center mt-2">
                <h2 class="text-2xl font-bold text-gray-800">Daftar Kamar - <span class="text-yellow-600"><?= htmlspecialchars($kost['nama_kost'] ?? 'Tidak Ditemukan') ?></span></h2>
                <a href="form_kamar.php?id_kost=<?= $id_kost ?>" class="bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-4 rounded transition-colors shadow-sm">
                    + Tambah Kamar
                </a>
            </div>
        </div>

        <?php if ($pesan_sukses): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_sukses ?></div>
        <?php endif; ?>

       <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead class="bg-gray-100 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-sm font-bold text-gray-600">No. Kamar</th>
                        <th class="py-3 px-4 text-sm font-bold text-gray-600">Fasilitas & Letak</th>
                        <th class="py-3 px-4 text-sm font-bold text-gray-600">Harga</th>
                        <th class="py-3 px-4 text-sm font-bold text-gray-600">Status</th>
                        <th class="py-3 px-4 text-sm font-bold text-gray-600 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($data_kamar as $kamar) : ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4">
                            <span class="font-bold text-lg text-gray-800"><?= htmlspecialchars($kamar['nomor_kamar']) ?></span>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <p class="font-semibold text-gray-700"><?= htmlspecialchars($kamar['jenis_kamar']) ?></p>
                            <p class="text-gray-500 text-xs"><?= htmlspecialchars($kamar['letak_kamar']) ?></p>
                        </td>
                        <td class="py-3 px-4 text-sm font-medium text-gray-700">
                            Rp <?= number_format($kamar['harga_kamar'], 0, ',', '.') ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php if (strtolower($kamar['status_kamar']) == 'kosong'): ?>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">Kosong</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">Terisi</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 flex justify-center gap-2 mt-1">
                            <a href="form_kamar.php?id_kost=<?= $id_kost ?>&edit=<?= $kamar['id_kamar'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Edit</a>
                            <a href="kamar.php?id_kost=<?= $id_kost ?>&hapus=<?= $kamar['id_kamar'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus kamar ini?');" class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($data_kamar)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">Belum ada kamar di lokasi ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="border-t border-gray-200 bg-white py-6 mt-8 text-center text-sm text-gray-500 w-full">
        <p>&copy; <?= date('Y') ?> Kost Sun Management. All rights reserved.</p>
    </footer>

</body>
</html>