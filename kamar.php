<?php
require 'koneksi.php';
require 'header.php';

if (!isset($_GET['id_kost'])) {
    header("Location: data_kost.php");
    exit;
}

$id_kost = $_GET['id_kost'];
$pesan_sukses = '';

// PROSES HAPUS DATA KAMAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id_hapus = $_POST['hapus'];
    $stmt = $koneksi->prepare("DELETE FROM table_kamar WHERE id_kamar = ? AND id_kost = ?");
    $stmt->execute([$id_hapus, $id_kost]);
    header("Location: kamar.php?id_kost=$id_kost&pesan=sukses_hapus");
    exit;
}

if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses_hapus') {
    $pesan_sukses = "Data kamar berhasil dihapus.";
}

// AMBIL NAMA KOST
$stmt_kost = $koneksi->prepare("SELECT nama_kost FROM table_kost WHERE id_kost = ?");
$stmt_kost->execute([$id_kost]);
$kost = $stmt_kost->fetch(PDO::FETCH_ASSOC);

// AMBIL DATA KAMAR
$stmt_kamar = $koneksi->prepare("SELECT * FROM table_kamar WHERE id_kost = ? ORDER BY nomor_kamar ASC");
$stmt_kamar->execute([$id_kost]);
$data_kamar = $stmt_kamar->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="mb-6">
    <a href="data_kost.php" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Kembali ke Daftar Kost</a>
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
    <table class="w-full text-left border-collapse min-w-[900px]">
        <thead class="bg-gray-100 border-b border-gray-200">
            <tr>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">No. Kamar</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">Fasilitas & Letak</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">Tarif (Bulan / Minggu / Hari)</th>
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
                <td class="py-3 px-4 text-xs font-medium text-gray-700 space-y-1">
                    <p>Bln: Rp <?= number_format($kamar['harga_kamar'] ?: 0, 0, ',', '.') ?></p>
                    <p>Mgg: Rp <?= number_format($kamar['harga_minggu'] ?: 0, 0, ',', '.') ?></p>
                    <p>Hri: Rp <?= number_format($kamar['harga_hari'] ?: 0, 0, ',', '.') ?></p>
                </td>
                <td class="py-3 px-4 text-sm">
                    <?php if (strtolower($kamar['status_kamar']) == 'kosong'): ?>
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">Kosong</span>
                    <?php else: ?>
                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">Terisi</span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4 flex justify-center gap-2 mt-2">
                    <a href="form_kamar.php?id_kost=<?= $id_kost ?>&edit=<?= $kamar['id_kamar'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Edit</a>
                    <form method="POST" action="kamar.php?id_kost=<?= $id_kost ?>" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kamar ini?');" style="display:inline;">
                        <input type="hidden" name="hapus" value="<?= $kamar['id_kamar'] ?>">
                        <button type="submit" class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require 'footer.php'; ?>