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
// 1. PROSES AKSI DATABASE (HAPUS & BAYAR)
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $stmt_hapus = $koneksi->prepare("DELETE FROM table_pengeluaran WHERE id_pengeluaran = ?");
    $stmt_hapus->execute([$id_hapus]);
    header("Location: keuangan.php?pesan=sukses_hapus");
    exit;
}

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_bayar'])) {
    $id_trans_bayar = (int)$_POST['id_transaksi_bayar'];
    $nominal_tambah = (int)$_POST['nominal_bayar_baru'];
    $tanggal_bayar_baru = $_POST['tanggal_bayar_baru'];
    
    $id_user_aktif = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    $stmt_cek = $koneksi->prepare("SELECT jumlahtransaksi, diskontransaksi, jumlah_charge, jumlah_bayar FROM table_transaksi WHERE id_transaksi = ?");
    $stmt_cek->execute([$id_trans_bayar]);
    $tr = $stmt_cek->fetch(PDO::FETCH_ASSOC);

    if ($tr) {
        $total_tagihan = $tr['jumlahtransaksi'] - $tr['diskontransaksi'] + $tr['jumlah_charge'];
        $total_bayar_baru = $tr['jumlah_bayar'] + $nominal_tambah;
        $status_baru = ($total_bayar_baru >= $total_tagihan) ? 'Lunas' : 'Belum Lunas';

        $stmt_upd = $koneksi->prepare("UPDATE table_transaksi SET jumlah_bayar = ?, status_bayar = ?, tanggal_bayar = ?, id = ? WHERE id_transaksi = ?");
        $stmt_upd->execute([$total_bayar_baru, $status_baru, $tanggal_bayar_baru, $id_user_aktif, $id_trans_bayar]);

        header("Location: keuangan.php?pesan=sukses_bayar");
        exit;
    }
}

// ==========================================
// 2. DATA REFERENSI UNTUK DROPDOWN
// ==========================================
$stmt_kost = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$list_kost_db = $stmt_kost->fetchAll(PDO::FETCH_ASSOC);

$stmt_kat = $koneksi->query("SELECT DISTINCT kategoripengeluaran FROM table_pengeluaran ORDER BY kategoripengeluaran ASC");
$list_kategori_db = $stmt_kat->fetchAll(PDO::FETCH_COLUMN);

// HELPER: Pertahankan Parameter URL lainnya saat filter form disubmit
function hiddenParamsHtml($exclude_prefix) {
    $html = '';
    foreach ($_GET as $key => $value) {
        if (strpos($key, $exclude_prefix) !== 0 && $key !== 'pesan' && $key !== 'hapus' && $key !== 'hapus_transaksi') {
            $html .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'">';
        }
    }
    return $html;
}

function buildPaginateUrl($params_to_update) {
    $get = $_GET;
    foreach($params_to_update as $key => $value) { $get[$key] = $value; }
    return '?' . http_build_query($get);
}

// ==========================================
// BAGIAN 1: LOGIKA RINGKASAN ARUS KAS
// ==========================================
$sum_tipe = $_GET['sum_tipe'] ?? 'bulan';
$sum_bulan = $_GET['sum_bulan'] ?? date('Y-m');
$sum_start = $_GET['sum_start'] ?? date('Y-m-01');
$sum_end = $_GET['sum_end'] ?? date('Y-m-t');

if ($sum_tipe === 'bulan') {
    $date_start_sum = $sum_bulan . '-01';
    $date_end_sum = date('Y-m-t', strtotime($date_start_sum));
} else {
    $date_start_sum = $sum_start;
    $date_end_sum = $sum_end;
}

$stmt_sum_in = $koneksi->prepare("SELECT SUM(jumlah_bayar) FROM table_transaksi WHERE tanggal_bayar BETWEEN ? AND ?");
$stmt_sum_in->execute([$date_start_sum, $date_end_sum]);
$total_pemasukan_sum = $stmt_sum_in->fetchColumn() ?: 0;

$stmt_sum_out = $koneksi->prepare("SELECT SUM(jumlahpengeluaran) FROM table_pengeluaran WHERE tanggalpengeluaran BETWEEN ? AND ?");
$stmt_sum_out->execute([$date_start_sum, $date_end_sum]);
$total_pengeluaran_sum = $stmt_sum_out->fetchColumn() ?: 0;

$saldo_bersih_sum = $total_pemasukan_sum - $total_pengeluaran_sum;


// ==========================================
// BAGIAN 2: LOGIKA TABEL PEMASUKAN
// ==========================================
$in_tipe = $_GET['in_tipe'] ?? 'bulan';
$in_bulan = $_GET['in_bulan'] ?? date('Y-m');
$in_start = $_GET['in_start'] ?? date('Y-m-01');
$in_end = $_GET['in_end'] ?? date('Y-m-t');
$in_kost = $_GET['in_kost'] ?? '';
$in_status = $_GET['in_status'] ?? '';
$in_limit = $_GET['in_limit'] ?? '10';
$page_in = isset($_GET['page_in']) ? max(1, (int)$_GET['page_in']) : 1;

if ($in_tipe === 'bulan') {
    $date_start_in = $in_bulan . '-01';
    $date_end_in = date('Y-m-t', strtotime($date_start_in));
} else {
    $date_start_in = $in_start;
    $date_end_in = $in_end;
}

$where_in_arr = ["t.tanggaltransaksi BETWEEN ? AND ?"];
$params_in = [$date_start_in, $date_end_in];

if (!empty($in_kost)) { $where_in_arr[] = "k.id_kost = ?"; $params_in[] = $in_kost; }
if (!empty($in_status)) { $where_in_arr[] = "t.status_bayar = ?"; $params_in[] = $in_status; }

$where_in = "WHERE " . implode(" AND ", $where_in_arr);
$limit_val_in = ($in_limit === 'Semua') ? 999999 : (int)$in_limit;
$offset_in = ($page_in - 1) * $limit_val_in;

$stmt_count_in = $koneksi->prepare("SELECT COUNT(*) FROM table_transaksi t JOIN table_kamar k ON t.id_kamar = k.id_kamar $where_in");
$stmt_count_in->execute($params_in);
$total_data_in = $stmt_count_in->fetchColumn();
$total_pages_in = ($limit_val_in == 999999) ? 1 : ceil($total_data_in / $limit_val_in);

$query_list_in = "
    SELECT t.*, c.namacustomer, k.nomor_kamar, ko.nama_kost
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    $where_in
    ORDER BY FIELD(t.status_bayar, 'Belum Lunas', 'Lunas'), t.tanggaltransaksi DESC, t.id_transaksi DESC
    LIMIT $limit_val_in OFFSET $offset_in
";
$stmt_list_in = $koneksi->prepare($query_list_in);
$stmt_list_in->execute($params_in);
$data_pemasukan = $stmt_list_in->fetchAll(PDO::FETCH_ASSOC);

$show_in = isset($_GET['filter_in']) || isset($_GET['page_in']);


// ==========================================
// BAGIAN 3: LOGIKA TABEL PENGELUARAN
// ==========================================
$out_tipe = $_GET['out_tipe'] ?? 'bulan';
$out_bulan = $_GET['out_bulan'] ?? date('Y-m');
$out_start = $_GET['out_start'] ?? date('Y-m-01');
$out_end = $_GET['out_end'] ?? date('Y-m-t');
$out_kost = $_GET['out_kost'] ?? '';
$out_kat = $_GET['out_kat'] ?? '';
$out_limit = $_GET['out_limit'] ?? '10';
$page_out = isset($_GET['page_out']) ? max(1, (int)$_GET['page_out']) : 1;

if ($out_tipe === 'bulan') {
    $date_start_out = $out_bulan . '-01';
    $date_end_out = date('Y-m-t', strtotime($date_start_out));
} else {
    $date_start_out = $out_start;
    $date_end_out = $out_end;
}

$where_out_arr = ["p.tanggalpengeluaran BETWEEN ? AND ?"];
$params_out = [$date_start_out, $date_end_out];

if (!empty($out_kost)) { $where_out_arr[] = "p.id_kost = ?"; $params_out[] = $out_kost; }
if (!empty($out_kat)) { $where_out_arr[] = "p.kategoripengeluaran = ?"; $params_out[] = $out_kat; }

$where_out = "WHERE " . implode(" AND ", $where_out_arr);
$limit_val_out = ($out_limit === 'Semua') ? 999999 : (int)$out_limit;
$offset_out = ($page_out - 1) * $limit_val_out;

$stmt_count_out = $koneksi->prepare("SELECT COUNT(*) FROM table_pengeluaran p $where_out");
$stmt_count_out->execute($params_out);
$total_data_out = $stmt_count_out->fetchColumn();
$total_pages_out = ($limit_val_out == 999999) ? 1 : ceil($total_data_out / $limit_val_out);

$query_list_out = "
    SELECT p.*, ko.nama_kost 
    FROM table_pengeluaran p
    LEFT JOIN table_kost ko ON p.id_kost = ko.id_kost
    $where_out
    ORDER BY p.tanggalpengeluaran DESC, p.id_pengeluaran DESC
    LIMIT $limit_val_out OFFSET $offset_out
";
$stmt_list_out = $koneksi->prepare($query_list_out);
$stmt_list_out->execute($params_out);
$data_pengeluaran = $stmt_list_out->fetchAll(PDO::FETCH_ASSOC);

$show_out = isset($_GET['filter_out']) || isset($_GET['page_out']);
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Buku Besar Keuangan</h2>
        <p class="text-sm text-gray-500 mt-1">Pantau arus kas, pendapatan sewa, dan pengeluaran operasional Anda.</p>
    </div>
    <div class="flex gap-2">
        <a href="form_pengeluaran.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors shadow-sm whitespace-nowrap">
            - Catat Pengeluaran
        </a>
    </div>
</div>

<?php if ($pesan_sukses): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-semibold"><?= $pesan_sukses ?></div>
<?php endif; ?>

<div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6">
    <div class="flex justify-between items-center mb-4 border-b pb-2">
        <h3 class="font-bold text-gray-800 text-lg">Ringkasan Arus Kas</h3>
    </div>
    
    <form action="keuangan.php" method="GET" class="flex flex-col md:flex-row gap-4 items-end mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
        <?= hiddenParamsHtml('sum_') ?>
        <input type="hidden" name="filter_sum" value="1">
        
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Metode Waktu</label>
            <select name="sum_tipe" id="sum_tipe" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white" onchange="toggleDateSum()">
                <option value="bulan" <?= $sum_tipe == 'bulan' ? 'selected' : '' ?>>Per Bulan</option>
                <option value="rentang" <?= $sum_tipe == 'rentang' ? 'selected' : '' ?>>Rentang Tanggal</option>
            </select>
        </div>
        
        <div id="sum_wrap_bulan" class="<?= $sum_tipe == 'bulan' ? 'block' : 'hidden' ?>">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Pilih Bulan</label>
            <input type="month" name="sum_bulan" value="<?= $sum_bulan ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div id="sum_wrap_rentang" class="<?= $sum_tipe == 'rentang' ? 'flex' : 'hidden' ?> gap-2">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Dari</label>
                <input type="date" name="sum_start" value="<?= $sum_start ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Sampai</label>
                <input type="date" name="sum_end" value="<?= $sum_end ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-bold transition-colors">Lihat Ringkasan</button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-green-50 p-6 rounded-xl border border-green-200 border-l-4 border-l-green-500 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-500 mb-1">Pemasukan (Telah Dibayar)</p>
            <p class="text-2xl font-black text-green-600">Rp <?= number_format($total_pemasukan_sum, 0, ',', '.') ?></p>
        </div>
        <div class="bg-red-50 p-6 rounded-xl border border-red-200 border-l-4 border-l-red-500 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-500 mb-1">Total Pengeluaran</p>
            <p class="text-2xl font-black text-red-600">Rp <?= number_format($total_pengeluaran_sum, 0, ',', '.') ?></p>
        </div>
        <div class="bg-gray-900 p-6 rounded-xl shadow-md border border-gray-800 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-400 mb-1">Saldo Bersih (Kas Riil)</p>
            <p class="text-2xl font-black <?= $saldo_bersih_sum >= 0 ? 'text-yellow-500' : 'text-red-500' ?>">
                Rp <?= number_format($saldo_bersih_sum, 0, ',', '.') ?>
            </p>
        </div>
    </div>
</div>


<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 transition-all">
    <div class="px-6 py-4 border-b border-gray-200 bg-green-50 flex justify-between items-center cursor-pointer select-none" onclick="toggleSection('wrapper_pemasukan', 'icon_pemasukan')">
        <h3 class="font-bold text-green-800 flex items-center gap-2 text-lg">
            <svg id="icon_pemasukan" class="w-6 h-6 transition-transform duration-300 <?= $show_in ? 'rotate-0' : '-rotate-90' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
            2. Pemasukan Sewa (<?= $total_data_in ?> Data Ditemukan)
        </h3>
        <?php if ($role_aktif === 'super admin'): ?>
            <a href="form_transaksi.php" onclick="event.stopPropagation();" class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-2 px-4 rounded shadow-sm transition-colors flex items-center gap-1">
                + Tambah Manual
            </a>
        <?php endif; ?>
    </div>
    
    <div id="wrapper_pemasukan" class="<?= $show_in ? 'block' : 'hidden' ?>">
        
        <form action="keuangan.php" method="GET" class="p-5 border-b border-gray-200 bg-white space-y-4">
            <?= hiddenParamsHtml('in_') ?>
            <input type="hidden" name="filter_in" value="1">
            <input type="hidden" name="page_in" value="1"> <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Metode</label>
                    <select name="in_tipe" id="in_tipe" class="border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-green-500 text-sm" onchange="toggleDateIn()">
                        <option value="bulan" <?= $in_tipe == 'bulan' ? 'selected' : '' ?>>Bulan</option>
                        <option value="rentang" <?= $in_tipe == 'rentang' ? 'selected' : '' ?>>Rentang</option>
                    </select>
                </div>
                <div id="in_wrap_bulan" class="<?= $in_tipe == 'bulan' ? 'block' : 'hidden' ?>">
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Bulan</label>
                    <input type="month" name="in_bulan" value="<?= $in_bulan ?>" class="border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-green-500 text-sm">
                </div>
                <div id="in_wrap_rentang" class="<?= $in_tipe == 'rentang' ? 'flex' : 'hidden' ?> gap-2">
                    <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Dari</label><input type="date" name="in_start" value="<?= $in_start ?>" class="border px-3 py-2 rounded text-sm"></div>
                    <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Sampai</label><input type="date" name="in_end" value="<?= $in_end ?>" class="border px-3 py-2 rounded text-sm"></div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Lokasi Kost</label>
                    <select name="in_kost" class="border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-green-500 text-sm">
                        <option value="">Semua Lokasi</option>
                        <?php foreach($list_kost_db as $k): ?>
                            <option value="<?= $k['id_kost'] ?>" <?= $in_kost == $k['id_kost'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kost']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                    <select name="in_status" class="border border-gray-300 px-3 py-2 rounded text-sm">
                        <option value="">Semua Status</option>
                        <option value="Lunas" <?= $in_status == 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                        <option value="Belum Lunas" <?= $in_status == 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Tampil</label>
                    <select name="in_limit" class="border border-gray-300 px-3 py-2 rounded text-sm">
                        <option value="10" <?= $in_limit == '10' ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $in_limit == '25' ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $in_limit == '50' ? 'selected' : '' ?>>50</option>
                        <option value="Semua" <?= $in_limit == 'Semua' ? 'selected' : '' ?>>Semua</option>
                    </select>
                </div>
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded font-bold text-sm shadow-sm hover:bg-green-700">Filter Data</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Tanggal</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Customer & Properti</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Tagihan & Status</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase text-right">Telah Dibayar</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($data_pemasukan as $in) : 
                        $total_tagihan = $in['jumlahtransaksi'] - $in['diskontransaksi'] + $in['jumlah_charge'];
                        $kurang_bayar = $total_tagihan - $in['jumlah_bayar'];
                        $status_badge = ($in['status_bayar'] === 'Lunas') ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200 animate-pulse';
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors <?= $in['status_bayar'] === 'Belum Lunas' ? 'bg-red-50/40' : '' ?>">
                        <td class="py-3 px-4">
                            <p class="text-sm font-bold text-gray-800"><?= date('d M Y', strtotime($in['tanggaltransaksi'])) ?></p>
                            <p class="text-xs text-gray-500 mt-1">Trx: #<?= $in['id_transaksi'] ?></p>
                        </td>
                        <td class="py-3 px-4">
                            <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($in['namacustomer']) ?></p>
                            <p class="text-xs font-semibold text-gray-600 mt-0.5"><?= htmlspecialchars($in['nama_kost']) ?> - Kmr <?= htmlspecialchars($in['nomor_kamar']) ?></p>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="border px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $status_badge ?>"><?= htmlspecialchars($in['status_bayar']) ?></span>
                            </div>
                            <p class="text-sm font-semibold text-gray-700">Rp <?= number_format($total_tagihan, 0, ',', '.') ?></p>
                            <?php if($in['status_bayar'] === 'Belum Lunas'): ?><p class="text-xs font-bold text-red-500 mt-0.5">Kurang: Rp <?= number_format($kurang_bayar, 0, ',', '.') ?></p><?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <p class="text-sm font-black text-green-600">Rp <?= number_format($in['jumlah_bayar'], 0, ',', '.') ?></p>
                            <p class="text-[10px] text-gray-400 font-semibold mt-1">Update: <?= date('d/m/Y', strtotime($in['tanggal_bayar'])) ?></p>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex justify-center items-center gap-2">
                                <?php if($in['status_bayar'] === 'Belum Lunas'): ?>
                                    <button onclick="bukaModalBayar(<?= $in['id_transaksi'] ?>, '<?= htmlspecialchars($in['namacustomer']) ?>', <?= $kurang_bayar ?>)" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-bold shadow-sm">Bayar</button>
                                <?php endif; ?>
                                <a href="invoice.php?id=<?= $in['id_transaksi'] ?>" class="border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded text-xs font-bold">Cetak</a>
                                <?php if ($role_aktif === 'super admin'): ?>
                                    <a href="form_transaksi.php?edit=<?= $in['id_transaksi'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-2 py-1.5 rounded text-xs font-semibold">Edit</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($data_pemasukan)): ?>
                    <tr><td colspan="5" class="text-center py-8 text-gray-500 font-medium">Tidak ada data pemasukan pada filter ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages_in > 1): ?>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center text-sm">
            <span class="text-gray-600 font-medium">Halaman <?= $page_in ?> dari <?= $total_pages_in ?></span>
            <div class="flex gap-2">
                <?php if($page_in > 1): ?>
                    <a href="<?= buildPaginateUrl(['page_in' => $page_in - 1]) ?>" class="px-3 py-1 bg-white border border-gray-300 rounded hover:bg-gray-100 font-semibold text-gray-700">&larr; Prev</a>
                <?php endif; ?>
                <?php if($page_in < $total_pages_in): ?>
                    <a href="<?= buildPaginateUrl(['page_in' => $page_in + 1]) ?>" class="px-3 py-1 bg-white border border-gray-300 rounded hover:bg-gray-100 font-semibold text-gray-700">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>


<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-red-50 flex justify-between items-center cursor-pointer select-none" onclick="toggleSection('wrapper_pengeluaran', 'icon_pengeluaran')">
        <h3 class="font-bold text-red-800 flex items-center gap-2 text-lg">
            <svg id="icon_pengeluaran" class="w-6 h-6 transition-transform duration-300 <?= $show_out ? 'rotate-0' : '-rotate-90' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
            3. Pengeluaran Operasional (<?= $total_data_out ?> Data)
        </h3>
    </div>
    
    <div id="wrapper_pengeluaran" class="<?= $show_out ? 'block' : 'hidden' ?>">
        
        <form action="keuangan.php" method="GET" class="p-5 border-b border-gray-200 bg-white space-y-4">
            <?= hiddenParamsHtml('out_') ?>
            <input type="hidden" name="filter_out" value="1">
            <input type="hidden" name="page_out" value="1">
            
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Metode</label>
                    <select name="out_tipe" id="out_tipe" class="border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-red-500 text-sm" onchange="toggleDateOut()">
                        <option value="bulan" <?= $out_tipe == 'bulan' ? 'selected' : '' ?>>Bulan</option>
                        <option value="rentang" <?= $out_tipe == 'rentang' ? 'selected' : '' ?>>Rentang</option>
                    </select>
                </div>
                <div id="out_wrap_bulan" class="<?= $out_tipe == 'bulan' ? 'block' : 'hidden' ?>">
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Bulan</label>
                    <input type="month" name="out_bulan" value="<?= $out_bulan ?>" class="border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-red-500 text-sm">
                </div>
                <div id="out_wrap_rentang" class="<?= $out_tipe == 'rentang' ? 'flex' : 'hidden' ?> gap-2">
                    <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Dari</label><input type="date" name="out_start" value="<?= $out_start ?>" class="border px-3 py-2 rounded text-sm"></div>
                    <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Sampai</label><input type="date" name="out_end" value="<?= $out_end ?>" class="border px-3 py-2 rounded text-sm"></div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Lokasi Kost</label>
                    <select name="out_kost" class="border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-red-500 text-sm">
                        <option value="">Semua Lokasi</option>
                        <?php foreach($list_kost_db as $k): ?>
                            <option value="<?= $k['id_kost'] ?>" <?= $out_kost == $k['id_kost'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kost']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Kategori Biaya</label>
                    <select name="out_kat" class="border border-gray-300 px-3 py-2 rounded text-sm">
                        <option value="">Semua Kategori</option>
                        <?php foreach($list_kategori_db as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>" <?= $out_kat == $kat ? 'selected' : '' ?>><?= htmlspecialchars($kat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Tampil</label>
                    <select name="out_limit" class="border border-gray-300 px-3 py-2 rounded text-sm">
                        <option value="10" <?= $out_limit == '10' ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $out_limit == '25' ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $out_limit == '50' ? 'selected' : '' ?>>50</option>
                        <option value="Semua" <?= $out_limit == 'Semua' ? 'selected' : '' ?>>Semua</option>
                    </select>
                </div>
                <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded font-bold text-sm shadow-sm hover:bg-red-700">Filter Data</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-6 text-xs font-bold text-gray-600 uppercase">Tgl Pengeluaran</th>
                        <th class="py-3 px-6 text-xs font-bold text-gray-600 uppercase">Lokasi Properti</th>
                        <th class="py-3 px-6 text-xs font-bold text-gray-600 uppercase">Kategori & Rincian</th>
                        <th class="py-3 px-6 text-xs font-bold text-gray-600 uppercase text-right">Nominal Keluar (Rp)</th>
                        <th class="py-3 px-6 text-xs font-bold text-gray-600 uppercase text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($data_pengeluaran as $out) : ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-6 text-sm font-semibold text-gray-700"><?= date('d M Y', strtotime($out['tanggalpengeluaran'])) ?></td>
                        <td class="py-3 px-6 text-sm font-bold text-gray-800"><?= htmlspecialchars($out['nama_kost'] ?? 'Semua / Biaya Pusat') ?></td>
                        <td class="py-3 px-6 text-sm">
                            <span class="bg-gray-200 text-gray-700 px-2 py-0.5 rounded text-xs font-bold mr-2"><?= htmlspecialchars($out['kategoripengeluaran']) ?></span>
                            <span class="text-gray-600"><?= htmlspecialchars($out['namapengeluaran']) ?></span>
                        </td>
                        <td class="py-3 px-6 text-sm font-black text-red-600 text-right">- <?= number_format($out['jumlahpengeluaran'], 0, ',', '.') ?></td>
                        <td class="py-3 px-6 flex justify-center gap-2">
                            <a href="form_pengeluaran.php?edit=<?= $out['id_pengeluaran'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-3 py-1.5 rounded text-xs font-semibold">Edit</a>
                            <a href="keuangan.php?hapus=<?= $out['id_pengeluaran'] ?>" onclick="return confirm('Hapus catatan pengeluaran ini?');" class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded text-xs font-semibold">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($data_pengeluaran)): ?>
                    <tr><td colspan="5" class="text-center py-8 text-gray-500 font-medium">Tidak ada data pengeluaran pada filter ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages_out > 1): ?>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center text-sm">
            <span class="text-gray-600 font-medium">Halaman <?= $page_out ?> dari <?= $total_pages_out ?></span>
            <div class="flex gap-2">
                <?php if($page_out > 1): ?>
                    <a href="<?= buildPaginateUrl(['page_out' => $page_out - 1]) ?>" class="px-3 py-1 bg-white border border-gray-300 rounded hover:bg-gray-100 font-semibold text-gray-700">&larr; Prev</a>
                <?php endif; ?>
                <?php if($page_out < $total_pages_out): ?>
                    <a href="<?= buildPaginateUrl(['page_out' => $page_out + 1]) ?>" class="px-3 py-1 bg-white border border-gray-300 rounded hover:bg-gray-100 font-semibold text-gray-700">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>


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
// Logika Tampil Sembunyi Input Tanggal (Ringkasan)
function toggleDateSum() {
    const tipe = document.getElementById('sum_tipe').value;
    document.getElementById('sum_wrap_bulan').className = (tipe === 'bulan') ? 'block' : 'hidden';
    document.getElementById('sum_wrap_rentang').className = (tipe === 'rentang') ? 'flex gap-2' : 'hidden';
}

// Logika Tampil Sembunyi Input Tanggal (Pemasukan)
function toggleDateIn() {
    const tipe = document.getElementById('in_tipe').value;
    document.getElementById('in_wrap_bulan').className = (tipe === 'bulan') ? 'block' : 'hidden';
    document.getElementById('in_wrap_rentang').className = (tipe === 'rentang') ? 'flex gap-2' : 'hidden';
}

// Logika Tampil Sembunyi Input Tanggal (Pengeluaran)
function toggleDateOut() {
    const tipe = document.getElementById('out_tipe').value;
    document.getElementById('out_wrap_bulan').className = (tipe === 'bulan') ? 'block' : 'hidden';
    document.getElementById('out_wrap_rentang').className = (tipe === 'rentang') ? 'flex gap-2' : 'hidden';
}

// Logika Accordion
function toggleSection(wrapperId, iconId) {
    const wrapper = document.getElementById(wrapperId);
    const icon = document.getElementById(iconId);
    if (wrapper.classList.contains('hidden')) {
        wrapper.classList.remove('hidden');
        icon.classList.remove('-rotate-90');
    } else {
        wrapper.classList.add('hidden');
        icon.classList.add('-rotate-90');
    }
}

// Format Rupiah untuk JS
const formatRupiahJs = (angka) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
}

function bukaModalBayar(id, nama, kurang) {
    document.getElementById('input_id_transaksi').value = id;
    document.getElementById('display_nama_cust').textContent = nama;
    document.getElementById('display_sisa_bayar').textContent = formatRupiahJs(kurang);
    
    const inputNominal = document.getElementById('input_nominal_bayar');
    inputNominal.value = kurang; 
    inputNominal.max = kurang; 
    inputNominal.dataset.maksimum = kurang; 
    
    document.getElementById('modal_bayar').classList.remove('hidden');
}

function tutupModalBayar() {
    document.getElementById('modal_bayar').classList.add('hidden');
}

function validasiModalBayar(e) {
    const inputNominal = document.getElementById('input_nominal_bayar');
    const nominal = parseInt(inputNominal.value);
    const batasMaksimum = parseInt(inputNominal.dataset.maksimum);
    
    if (nominal > batasMaksimum) {
        alert('PERINGATAN SISTEM!\n\nNominal yang Anda masukkan LEBIH BESAR dari sisa tagihan.\n\nSilakan periksa kembali bukti transfer.');
        e.preventDefault();
        return false;
    }
    if (!confirm('Anda yakin ingin menyimpan data pembayaran ini?')) {
        e.preventDefault();
        return false;
    }
    return true;
}
</script>

<?php require 'footer.php'; ?>