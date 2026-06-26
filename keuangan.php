<?php
require 'koneksi.php';
require 'header.php';

$pesan_sukses = '';
if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses_hapus') {
    $pesan_sukses = "Data pengeluaran berhasil dihapus.";
}

// PROSES HAPUS PENGELUARAN
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $stmt_hapus = $koneksi->prepare("DELETE FROM table_pengeluaran WHERE id_pengeluaran = ?");
    $stmt_hapus->execute([$id_hapus]);
    header("Location: keuangan.php?pesan=sukses_hapus");
    exit;
}

// 1. KALKULASI TOTAL PEMASUKAN (Dari Tabel Transaksi)
$stmt_in = $koneksi->query("SELECT SUM(jumlahtransaksi) FROM table_transaksi");
$total_pemasukan = $stmt_in->fetchColumn() ?: 0;

// 2. KALKULASI TOTAL PENGELUARAN (Dari Tabel Pengeluaran)
$stmt_out = $koneksi->query("SELECT SUM(jumlahpengeluaran) FROM table_pengeluaran");
$total_pengeluaran = $stmt_out->fetchColumn() ?: 0;

// 3. SALDO BERSIH
$saldo_bersih = $total_pemasukan - $total_pengeluaran;

// 4. AMBIL DATA RIWAYAT PENGELUARAN
$query_pengeluaran = "
    SELECT p.*, k.nama_kost 
    FROM table_pengeluaran p
    LEFT JOIN table_kost k ON p.id_kost = k.id_kost
    ORDER BY p.tanggalpengeluaran DESC, p.id_pengeluaran DESC
";
$data_pengeluaran = $koneksi->query($query_pengeluaran)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Modul Keuangan</h2>
        <p class="text-sm text-gray-500 mt-1">Pantau arus kas, pendapatan sewa, dan pengeluaran operasional.</p>
    </div>
    <a href="form_pengeluaran.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors shadow-sm whitespace-nowrap">
        - Catat Pengeluaran
    </a>
</div>

<?php if ($pesan_sukses): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_sukses ?></div>
<?php endif; ?>

<!-- WIDGET STATISTIK KEUANGAN -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Pemasukan Sewa</p>
        <p class="text-2xl font-black text-green-600">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Pengeluaran</p>
        <p class="text-2xl font-black text-red-600">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></p>
    </div>
    <div class="bg-gray-900 p-6 rounded-xl shadow-md border border-gray-800">
        <p class="text-sm font-semibold text-gray-400 mb-1">Saldo Kas Bersih</p>
        <p class="text-2xl font-black text-yellow-500">Rp <?= number_format($saldo_bersih, 0, ',', '.') ?></p>
    </div>
</div>

<!-- TABEL RIWAYAT PENGELUARAN -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="font-bold text-gray-700">Riwayat Pengeluaran Operasional</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[800px]">
            <thead class="bg-white border-b border-gray-200">
                <tr>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Tanggal</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Lokasi Properti</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Kategori & Rincian</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600 text-right">Nominal (Rp)</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600 text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data_pengeluaran as $out) : ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="py-3 px-6 text-sm font-semibold text-gray-700">
                        <?= date('d M Y', strtotime($out['tanggalpengeluaran'])) ?>
                    </td>
                    <td class="py-3 px-6 text-sm font-bold text-gray-800">
                        <?= htmlspecialchars($out['nama_kost'] ?? 'Semua/Pusat') ?>
                    </td>
                    <td class="py-3 px-6 text-sm">
                        <span class="bg-gray-200 text-gray-700 px-2 py-0.5 rounded text-xs font-bold mr-2"><?= htmlspecialchars($out['kategoripengeluaran']) ?></span>
                        <span class="text-gray-600"><?= htmlspecialchars($out['namapengeluaran']) ?></span>
                    </td>
                    <td class="py-3 px-6 text-sm font-black text-red-600 text-right">
                        - <?= number_format($out['jumlahpengeluaran'], 0, ',', '.') ?>
                    </td>
                    <td class="py-3 px-6 flex justify-center gap-2">
                        <a href="form_pengeluaran.php?edit=<?= $out['id_pengeluaran'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Edit</a>
                        <a href="keuangan.php?hapus=<?= $out['id_pengeluaran'] ?>" onclick="return confirm('Hapus catatan pengeluaran ini?');" class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($data_pengeluaran)): ?>
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500">Belum ada catatan pengeluaran.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>