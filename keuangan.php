<?php
require 'koneksi.php';
require 'header.php';

$pesan_sukses = '';

// Ambil pesan sukses dari URL jika ada
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'sukses_hapus') {
        $pesan_sukses = "Data pengeluaran berhasil dihapus.";
    } elseif ($_GET['pesan'] == 'sukses_hapus_transaksi') {
        $pesan_sukses = "Riwayat transaksi pemasukan berhasil dihapus.";
    }
}

// ==========================================
// 1. PROSES HAPUS PENGELUARAN
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $stmt_hapus = $koneksi->prepare("DELETE FROM table_pengeluaran WHERE id_pengeluaran = ?");
    $stmt_hapus->execute([$id_hapus]);
    header("Location: keuangan.php?pesan=sukses_hapus");
    exit;
}

// ==========================================
// 2. PROSES HAPUS TRANSAKSI PEMASUKAN (KHUSUS SUPER ADMIN)
// ==========================================
if (isset($_GET['hapus_transaksi'])) {
    if ($role_aktif !== 'super admin') {
        echo "<script>alert('Akses Ditolak: Hanya Super Admin yang dapat menghapus riwayat transaksi.'); window.location.href='keuangan.php';</script>";
        exit;
    }
    
    $id_trans_hapus = $_GET['hapus_transaksi'];
    $stmt_hapus_trans = $koneksi->prepare("DELETE FROM table_transaksi WHERE id_transaksi = ?");
    $stmt_hapus_trans->execute([$id_trans_hapus]);
    header("Location: keuangan.php?pesan=sukses_hapus_transaksi");
    exit;
}

// ==========================================
// LOGIKA FILTER RENTANG WAKTU
// ==========================================
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_transaksi = "";
$where_pengeluaran = "";
$params = [];

if (!empty($start_date) && !empty($end_date)) {
    $where_transaksi = "WHERE t.tanggaltransaksi BETWEEN ? AND ?";
    $where_pengeluaran = "WHERE p.tanggalpengeluaran BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
}

// Kalkulasi Ringkasan Keuangan
$query_in = "SELECT SUM(t.jumlahtransaksi) FROM table_transaksi t $where_transaksi";
$stmt_in = $koneksi->prepare($query_in);
$stmt_in->execute($params);
$total_pemasukan = $stmt_in->fetchColumn() ?: 0;

$query_out = "SELECT SUM(p.jumlahpengeluaran) FROM table_pengeluaran p $where_pengeluaran";
$stmt_out = $koneksi->prepare($query_out);
$stmt_out->execute($params);
$total_pengeluaran = $stmt_out->fetchColumn() ?: 0;

$saldo_bersih = $total_pemasukan - $total_pengeluaran;

// Ambil Rincian Pemasukan
$query_list_in = "
    SELECT t.*, c.namacustomer, k.nomor_kamar, ko.nama_kost
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    $where_transaksi
    ORDER BY t.tanggaltransaksi DESC, t.id_transaksi DESC
";
$stmt_list_in = $koneksi->prepare($query_list_in);
$stmt_list_in->execute($params);
$data_pemasukan = $stmt_list_in->fetchAll(PDO::FETCH_ASSOC);

// Ambil Rincian Pengeluaran
$query_list_out = "
    SELECT p.*, ko.nama_kost 
    FROM table_pengeluaran p
    LEFT JOIN table_kost ko ON p.id_kost = ko.id_kost
    $where_pengeluaran
    ORDER BY p.tanggalpengeluaran DESC, p.id_pengeluaran DESC
";
$stmt_list_out = $koneksi->prepare($query_list_out);
$stmt_list_out->execute($params);
$data_pengeluaran = $stmt_list_out->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Buku Besar Keuangan</h2>
        <p class="text-sm text-gray-500 mt-1">Pantau arus kas, pendapatan sewa, dan pengeluaran operasional Anda.</p>
    </div>
    <a href="form_pengeluaran.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors shadow-sm whitespace-nowrap">
        - Catat Pengeluaran
    </a>
</div>

<?php if ($pesan_sukses): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_sukses ?></div>
<?php endif; ?>

<form action="keuangan.php" method="GET" class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row gap-4 items-end">
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Mulai Tanggal</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full md:w-auto border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-yellow-500 focus:outline-none" required>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Sampai Tanggal</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full md:w-auto border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-yellow-500 focus:outline-none" required>
    </div>
    <div class="flex gap-2 w-full md:w-auto">
        <button type="submit" class="flex-1 md:flex-none bg-black text-white px-6 py-2 rounded font-bold hover:bg-gray-800 transition-colors">Terapkan Filter</button>
        <?php if(!empty($start_date) || !empty($end_date)): ?>
            <a href="keuangan.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded font-bold hover:bg-gray-300 transition-colors text-center">Reset</a>
        <?php endif; ?>
    </div>
</form>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Pemasukan</p>
        <p class="text-2xl font-black text-green-600">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Pengeluaran</p>
        <p class="text-2xl font-black text-red-600">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></p>
    </div>
    <div class="bg-gray-900 p-6 rounded-xl shadow-md border border-gray-800 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-400 mb-1">Saldo Bersih (Laba/Rugi)</p>
        <p class="text-2xl font-black <?= $saldo_bersih >= 0 ? 'text-yellow-500' : 'text-red-500' ?>">
            Rp <?= number_format($saldo_bersih, 0, ',', '.') ?>
        </p>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-green-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h3 class="font-bold text-green-800 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Riwayat Pemasukan Sewa
        </h3>
        
        <?php if ($role_aktif === 'super admin'): ?>
            <a href="form_transaksi.php" class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-2 px-4 rounded shadow-sm transition-colors flex items-center gap-1">
                + Tambah Transaksi Manual
            </a>
        <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[950px]">
            <thead class="bg-white border-b border-gray-200">
                <tr>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Tanggal Transaksi</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Customer</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Properti & Rincian</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600 text-right">Nominal Masuk (Rp)</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600 text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data_pemasukan as $in) : ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="py-3 px-6 text-sm font-semibold text-gray-700">
                        <?= date('d M Y', strtotime($in['tanggaltransaksi'])) ?>
                    </td>
                    <td class="py-3 px-6 text-sm font-bold text-gray-800">
                        <?= htmlspecialchars($in['namacustomer']) ?>
                    </td>
                    <td class="py-3 px-6 text-sm">
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($in['nama_kost']) ?> - Kamar <?= htmlspecialchars($in['nomor_kamar']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($in['namatransaksi']) ?> (Diskon: Rp <?= number_format($in['diskontransaksi'], 0, ',', '.') ?>)</p>
                    </td>
                    <td class="py-3 px-6 text-sm font-black text-green-600 text-right">
                        + <?= number_format($in['jumlahtransaksi'], 0, ',', '.') ?>
                    </td>
                    <td class="py-3 px-6 flex justify-center items-center gap-2">
                        <a href="invoice.php?id=<?= $in['id_transaksi'] ?>" class="inline-block bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1.5 rounded text-xs font-bold transition-colors">
                            Lihat / Cetak
                        </a>
                        
                        <?php if ($role_aktif === 'super admin'): ?>
                            <a href="form_transaksi.php?edit=<?= $in['id_transaksi'] ?>" 
                               class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">
                                Edit
                            </a>
                            <a href="keuangan.php?hapus_transaksi=<?= $in['id_transaksi'] ?>" 
                               onclick="return confirm('Apakah Anda yakin ingin menghapus catatan riwayat transaksi ini? Tindakan ini akan mempengaruhi total kalkulator arus kas.');" 
                               class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">
                                Hapus
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($data_pemasukan)): ?>
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500">Tidak ada pemasukan pada rentang waktu ini.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-red-50">
        <h3 class="font-bold text-red-800 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
            Riwayat Pengeluaran Operasional
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[800px]">
            <thead class="bg-white border-b border-gray-200">
                <tr>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Tanggal Pengeluaran</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Lokasi Properti</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600">Kategori & Rincian</th>
                    <th class="py-3 px-6 text-sm font-bold text-gray-600 text-right">Nominal Keluar (Rp)</th>
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
                        <?= htmlspecialchars($out['nama_kost'] ?? 'Semua / Biaya Pusat') ?>
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
                    <td colspan="5" class="text-center py-8 text-gray-500">Tidak ada catatan pengeluaran pada rentang waktu ini.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>