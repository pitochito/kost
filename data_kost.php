<?php
// ==========================================
// BAGIAN 1: LOGIKA SISTEM & KEAMANAN
// ==========================================
session_start();
require 'koneksi.php';

// Proteksi Login: Jika belum login, tendang ke login.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pesan_error = '';
$pesan_sukses = '';

// Proses Hapus Data Kost
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $cek_kamar = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar WHERE id_kost = ?");
    $cek_kamar->execute([$id_hapus]);
    $jumlah = $cek_kamar->fetchColumn();

    if ($jumlah > 0) {
        $pesan_error = "Gagal menghapus: Kost ini masih memiliki $jumlah kamar aktif. Hapus kamar terlebih dahulu.";
    } else {
        $stmt = $koneksi->prepare("DELETE FROM table_kost WHERE id_kost = ?");
        $stmt->execute([$id_hapus]);
        header("Location: data_kost.php?pesan=sukses_hapus");
        exit;
    }
}

if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses_hapus') {
    $pesan_sukses = "Data kost berhasil dihapus.";
}

// Ambil Semua Data Kost
$query = "SELECT * FROM table_kost ORDER BY id_kost DESC";
$stmt = $koneksi->prepare($query);
$stmt->execute();
$data_kost = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// BAGIAN 2: MENAMPILKAN VISUAL (HTML)
// ==========================================

// Panggil bagian atas web (Navbar & Logo)
require 'header.php'; 
?>

<main class="max-w-7xl mx-auto px-4 py-8 w-full flex-1">
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-700">Daftar Lokasi Kost</h2>
        <a href="form_kost.php" class="bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-4 rounded transition-colors">
            + Tambah Kost Baru
        </a>
    </div>

    <?php if ($pesan_error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_error ?></div>
    <?php endif; ?>
    <?php if ($pesan_sukses): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_sukses ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
    <table class="w-full text-left border-collapse min-w-[600px]">
            <thead class="bg-gray-100 border-b border-gray-200">
                <tr>
                    <th class="py-3 px-4 text-sm font-bold text-gray-600">Nama Kost</th>
                    <th class="py-3 px-4 text-sm font-bold text-gray-600">Alamat</th>
                    <th class="py-3 px-4 text-sm font-bold text-gray-600 text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data_kost as $kost) : ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="py-3 px-4">
                        <p class="font-bold text-gray-800"><?= htmlspecialchars($kost['nama_kost']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($kost['kota_kost']) ?></p>
                    </td>
                    <td class="py-3 px-4 text-sm text-gray-600">
                        <?= htmlspecialchars($kost['alamat_kost']) ?>
                    </td>
                    <td class="py-3 px-4 flex justify-center gap-2">
                        <a href="kamar.php?id_kost=<?= $kost['id_kost'] ?>" class="bg-black text-white hover:bg-gray-800 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Kelola Kamar</a>
                        <a href="form_kost.php?edit=<?= $kost['id_kost'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Edit</a>
                        <a href="data_kost.php?hapus=<?= $kost['id_kost'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?');" class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php 
// Panggil bagian bawah web (Copyright)
require 'footer.php'; 
?>