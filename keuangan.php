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
    } elseif ($_GET['pesan'] == 'sukses_bayar') {
        $pesan_sukses = "Pembayaran cicilan berhasil diperbarui dan dicatat.";
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
// 3. PROSES UPDATE PEMBAYARAN CICILAN (MANUAL)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_bayar'])) {
    $id_trans_bayar = (int)$_POST['id_transaksi_bayar'];
    $nominal_tambah = (int)$_POST['nominal_bayar_baru'];
    $tanggal_bayar_baru = $_POST['tanggal_bayar_baru'];
    
    // Perlindungan Audit Trail dengan Ternary Operator
    $id_user_aktif = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Ambil data lama untuk kalkulasi
    $stmt_cek = $koneksi->prepare("SELECT jumlahtransaksi, diskontransaksi, jumlah_charge, jumlah_bayar FROM table_transaksi WHERE id_transaksi = ?");
    $stmt_cek->execute([$id_trans_bayar]);
    $tr = $stmt_cek->fetch(PDO::FETCH_ASSOC);

    if ($tr) {
        $total_tagihan = $tr['jumlahtransaksi'] - $tr['diskontransaksi'] + $tr['jumlah_charge'];
        $total_bayar_baru = $tr['jumlah_bayar'] + $nominal_tambah;
        
        // Tentukan status lunas/belum
        $status_baru = ($total_bayar_baru >= $total_tagihan) ? 'Lunas' : 'Belum Lunas';

        // Update database: tanggal_bayar digunakan sebagai tanggal terakhir pembayaran masuk
        $stmt_upd = $koneksi->prepare("UPDATE table_transaksi SET jumlah_bayar = ?, status_bayar = ?, tanggal_bayar = ?, id = ? WHERE id_transaksi = ?");
        $stmt_upd->execute([$total_bayar_baru, $status_baru, $tanggal_bayar_baru, $id_user_aktif, $id_trans_bayar]);

        header("Location: keuangan.php?pesan=sukses_bayar");
        exit;
    }
}

// ==========================================
// 4. LOGIKA FILTER PENCARIAN & RENTANG WAKTU
// ==========================================
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

$where_transaksi_arr = [];
$params_in = [];

$where_pengeluaran_arr = [];
$params_out = [];

// Filter Tanggal
if (!empty($start_date) && !empty($end_date)) {
    $where_transaksi_arr[] = "t.tanggaltransaksi BETWEEN ? AND ?";
    $params_in[] = $start_date;
    $params_in[] = $end_date;
    
    $where_pengeluaran_arr[] = "p.tanggalpengeluaran BETWEEN ? AND ?";
    $params_out[] = $start_date;
    $params_out[] = $end_date;
}

// Filter Status Pembayaran (Khusus Pemasukan)
if (!empty($status_filter)) {
    $where_transaksi_arr[] = "t.status_bayar = ?";
    $params_in[] = $status_filter;
}

$where_transaksi = !empty($where_transaksi_arr) ? "WHERE " . implode(" AND ", $where_transaksi_arr) : "";
$where_pengeluaran = !empty($where_pengeluaran_arr) ? "WHERE " . implode(" AND ", $where_pengeluaran_arr) : "";

// ==========================================
// 5. KALKULASI ARUS KAS (DIHITUNG DARI UANG YG SUDAH DIBAYAR)
// ==========================================
// Menggunakan jumlah_bayar agar Cashflow merepresentasikan uang riil yang sudah diterima
$query_in = "SELECT SUM(t.jumlah_bayar) FROM table_transaksi t $where_transaksi";
$stmt_in = $koneksi->prepare($query_in);
$stmt_in->execute($params_in);
$total_pemasukan = $stmt_in->fetchColumn() ?: 0;

$query_out = "SELECT SUM(p.jumlahpengeluaran) FROM table_pengeluaran p $where_pengeluaran";
$stmt_out = $koneksi->prepare($query_out);
$stmt_out->execute($params_out);
$total_pengeluaran = $stmt_out->fetchColumn() ?: 0;

$saldo_bersih = $total_pemasukan - $total_pengeluaran;

// ==========================================
// 6. AMBIL DATA TABEL
// ==========================================
// Rincian Pemasukan: Diurutkan berdasarkan Prioritas Belum Lunas terlebih dahulu
$query_list_in = "
    SELECT t.*, c.namacustomer, k.nomor_kamar, ko.nama_kost
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    $where_transaksi
    ORDER BY FIELD(t.status_bayar, 'Belum Lunas', 'Lunas'), t.tanggaltransaksi DESC, t.id_transaksi DESC
";
$stmt_list_in = $koneksi->prepare($query_list_in);
$stmt_list_in->execute($params_in);
$data_pemasukan = $stmt_list_in->fetchAll(PDO::FETCH_ASSOC);

// Rincian Pengeluaran
$query_list_out = "
    SELECT p.*, ko.nama_kost 
    FROM table_pengeluaran p
    LEFT JOIN table_kost ko ON p.id_kost = ko.id_kost
    $where_pengeluaran
    ORDER BY p.tanggalpengeluaran DESC, p.id_pengeluaran DESC
";
$stmt_list_out = $koneksi->prepare($query_list_out);
$stmt_list_out->execute($params_out);
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
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-semibold"><?= $pesan_sukses ?></div>
<?php endif; ?>

<!-- FORM FILTER -->
<form action="keuangan.php" method="GET" class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row gap-4 items-end flex-wrap">
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Status Pembayaran</label>
        <select name="status_filter" class="w-full md:w-auto border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-yellow-500 focus:outline-none bg-white">
            <option value="">Semua Status</option>
            <option value="Lunas" <?= $status_filter == 'Lunas' ? 'selected' : '' ?>>Lunas</option>
            <option value="Belum Lunas" <?= $status_filter == 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Mulai Tanggal</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full md:w-auto border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-yellow-500 focus:outline-none">
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Sampai Tanggal</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full md:w-auto border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-yellow-500 focus:outline-none">
    </div>
    <div class="flex gap-2 w-full md:w-auto">
        <button type="submit" class="flex-1 md:flex-none bg-black text-white px-6 py-2 rounded font-bold hover:bg-gray-800 transition-colors">Filter</button>
        <?php if(!empty($start_date) || !empty($end_date) || !empty($status_filter)): ?>
            <a href="keuangan.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded font-bold hover:bg-gray-300 transition-colors text-center">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- RINGKASAN KARTU KEUANGAN -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500 flex flex-col justify-center relative overflow-hidden">
        <p class="text-sm font-semibold text-gray-500 mb-1">Dana Masuk (Telah Dibayar)</p>
        <p class="text-2xl font-black text-green-600">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Pengeluaran</p>
        <p class="text-2xl font-black text-red-600">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></p>
    </div>
    <div class="bg-gray-900 p-6 rounded-xl shadow-md border border-gray-800 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-400 mb-1">Saldo Bersih (Kas Riil)</p>
        <p class="text-2xl font-black <?= $saldo_bersih >= 0 ? 'text-yellow-500' : 'text-red-500' ?>">
            Rp <?= number_format($saldo_bersih, 0, ',', '.') ?>
        </p>
    </div>
</div>

<!-- TABEL PEMASUKAN -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-green-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h3 class="font-bold text-green-800 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Riwayat Tagihan & Pemasukan Sewa
        </h3>
        
        <?php if ($role_aktif === 'super admin'): ?>
            <a href="form_transaksi.php" class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-2 px-4 rounded shadow-sm transition-colors flex items-center gap-1">
                + Tambah Transaksi Manual
            </a>
        <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[1000px]">
            <thead class="bg-white border-b border-gray-200">
                <tr>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Tanggal</th>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Customer & Properti</th>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Tagihan & Status</th>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase text-right">Pembayaran Masuk</th>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data_pemasukan as $in) : 
                    // Kalkulasi Logika Tagihan
                    $total_tagihan = $in['jumlahtransaksi'] - $in['diskontransaksi'] + $in['jumlah_charge'];
                    $kurang_bayar = $total_tagihan - $in['jumlah_bayar'];
                    $status_badge = ($in['status_bayar'] === 'Lunas') ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200';
                ?>
                <tr class="hover:bg-gray-50 transition-colors <?= $in['status_bayar'] === 'Belum Lunas' ? 'bg-red-50/30' : '' ?>">
                    <td class="py-3 px-4">
                        <p class="text-sm font-bold text-gray-800"><?= date('d M Y', strtotime($in['tanggaltransaksi'])) ?></p>
                        <p class="text-xs text-gray-500 mt-1">Trx ID: #<?= $in['id_transaksi'] ?></p>
                    </td>
                    <td class="py-3 px-4">
                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($in['namacustomer']) ?></p>
                        <p class="text-xs font-semibold text-gray-600 mt-0.5"><?= htmlspecialchars($in['nama_kost']) ?> - Kamar <?= htmlspecialchars($in['nomor_kamar']) ?></p>
                        <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($in['namatransaksi']) ?></p>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="border px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $status_badge ?>">
                                <?= htmlspecialchars($in['status_bayar']) ?>
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-gray-700">Total: Rp <?= number_format($total_tagihan, 0, ',', '.') ?></p>
                        <?php if($in['status_bayar'] === 'Belum Lunas'): ?>
                            <p class="text-xs font-bold text-red-500 mt-0.5">Kurang: Rp <?= number_format($kurang_bayar, 0, ',', '.') ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <p class="text-sm font-black text-green-600">+ Rp <?= number_format($in['jumlah_bayar'], 0, ',', '.') ?></p>
                        <p class="text-[10px] text-gray-400 font-semibold mt-1">Last Update: <?= date('d/m/Y', strtotime($in['tanggal_bayar'])) ?></p>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex flex-col sm:flex-row justify-center items-center gap-2">
                            <?php if($in['status_bayar'] === 'Belum Lunas'): ?>
                                <button type="button" 
                                    onclick="bukaModalBayar(<?= $in['id_transaksi'] ?>, '<?= htmlspecialchars($in['namacustomer']) ?>', <?= $kurang_bayar ?>)" 
                                    class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-bold transition-colors shadow-sm">
                                    Bayar
                                </button>
                            <?php endif; ?>
                            
                            <a href="invoice.php?id=<?= $in['id_transaksi'] ?>" class="w-full sm:w-auto text-center border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded text-xs font-bold transition-colors">
                                Cetak
                            </a>
                            
                            <?php if ($role_aktif === 'super admin'): ?>
                                <a href="form_transaksi.php?edit=<?= $in['id_transaksi'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-2 py-1.5 rounded text-xs font-semibold transition-colors" title="Edit">
                                    Edit
                                </a>
                                <a href="keuangan.php?hapus_transaksi=<?= $in['id_transaksi'] ?>" onclick="return confirm('Yakin hapus transaksi ini?');" class="border border-red-500 text-red-500 hover:bg-red-50 px-2 py-1.5 rounded text-xs font-semibold transition-colors" title="Hapus">
                                    X
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($data_pemasukan)): ?>
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500 font-medium">Tidak ada catatan tagihan/pemasukan pada rentang waktu ini.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- TABEL PENGELUARAN -->
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
                    <td colspan="5" class="text-center py-8 text-gray-500 font-medium">Tidak ada catatan pengeluaran pada rentang waktu ini.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL UPDATE PEMBAYARAN -->
<div id="modal_bayar" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-green-600 px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-white text-lg">Update Pembayaran / Cicilan</h3>
            <button type="button" onclick="tutupModalBayar()" class="text-white hover:text-green-200 font-bold text-xl">&times;</button>
        </div>
        <form action="keuangan.php" method="POST" onsubmit="return validasiModalBayar(event)" class="p-6">
            <input type="hidden" name="id_transaksi_bayar" id="input_id_transaksi">
            
            <div class="mb-4 bg-gray-50 p-3 rounded border border-gray-200">
                <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">Customer</p>
                <p class="font-bold text-gray-800" id="display_nama_cust"></p>
            </div>
            
            <div class="mb-5 bg-red-50 p-3 rounded border border-red-200">
                <p class="text-xs text-red-500 font-bold uppercase tracking-wider mb-1">Sisa Kurang Bayar</p>
                <p class="text-xl font-black text-red-600" id="display_sisa_bayar"></p>
                <p class="text-[10px] text-red-400 mt-1">*Hati-hati! Jangan input melebihi nominal ini.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Tanggal Transfer/Bayar <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal_bayar_baru" value="<?= date('Y-m-d') ?>" required class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-green-500 focus:outline-none">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 mb-1">Nominal Pembayaran (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="nominal_bayar_baru" id="input_nominal_bayar" required min="1" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-green-500 focus:outline-none font-bold text-lg text-gray-800">
            </div>

            <div class="flex gap-3 justify-end mt-2">
                <button type="button" onclick="tutupModalBayar()" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded font-bold hover:bg-gray-300 transition-colors">Batal</button>
                <button type="submit" name="proses_bayar" class="px-5 py-2.5 bg-green-600 text-white rounded font-bold hover:bg-green-700 transition-colors shadow-md">Simpan Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<script>
// Format Rupiah untuk tampilan JS
const formatRupiahJs = (angka) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
}

// Fungsi Buka Modal
function bukaModalBayar(id, nama, kurang) {
    document.getElementById('input_id_transaksi').value = id;
    document.getElementById('display_nama_cust').textContent = nama;
    document.getElementById('display_sisa_bayar').textContent = formatRupiahJs(kurang);
    
    const inputNominal = document.getElementById('input_nominal_bayar');
    inputNominal.value = kurang; // Set default full sisa bayar
    inputNominal.max = kurang; // HTML5 Validation protection
    inputNominal.dataset.maksimum = kurang; // Data set untuk JS manual validation
    
    document.getElementById('modal_bayar').classList.remove('hidden');
}

// Fungsi Tutup Modal
function tutupModalBayar() {
    document.getElementById('modal_bayar').classList.add('hidden');
}

// Validasi saat form di-submit
function validasiModalBayar(e) {
    const inputNominal = document.getElementById('input_nominal_bayar');
    const nominal = parseInt(inputNominal.value);
    const batasMaksimum = parseInt(inputNominal.dataset.maksimum);
    
    if (nominal > batasMaksimum) {
        alert('PERINGATAN SISTEM!\n\nNominal yang Anda masukkan (' + formatRupiahJs(nominal) + ') LEBIH BESAR dari sisa tagihan customer (' + formatRupiahJs(batasMaksimum) + ').\n\nSilakan periksa kembali bukti transfer dan perbaiki nominal input.');
        e.preventDefault();
        return false;
    }
    
    if (!confirm('Anda yakin ingin menyimpan data pembayaran ini?\nPastikan nominal transfer sesuai dengan mutasi bank Anda.')) {
        e.preventDefault();
        return false;
    }
    
    return true;
}
</script>

<?php require 'footer.php'; ?>