<?php
session_start();
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$pesan_sukses = '';
$id_user_aktif = $_SESSION['user_id'] ?? 1;

// ==========================================
// 1. PROSES POST (TERMINATE & PINDAH KAMAR)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_khusus'])) {
    $aksi = $_POST['aksi_khusus'];
    
    // --- AKSI: TERMINATE SEWA ---
    if ($aksi === 'terminate') {
        $id_cust = $_POST['term_id_customer'];
        $id_kamar = $_POST['term_id_kamar'];
        $tgl_terminate = date('Y-m-d');
        
        try {
            $koneksi->beginTransaction();
            // 1. Kosongkan Kamar
            $koneksi->prepare("UPDATE table_kamar SET status_kamar = 'Kosong' WHERE id_kamar = ?")->execute([$id_kamar]);
            // 2. Nonaktifkan Customer
            $koneksi->prepare("UPDATE table_customer SET statuscustomer = 'Tidak Aktif' WHERE id_customer = ?")->execute([$id_cust]);
            // 3. Update Transaksi (Tambahkan keterangan dan ubah habissewa ke hari ini)
            $stmt_trans = $koneksi->prepare("
                UPDATE table_transaksi 
                SET namatransaksi = CONCAT(namatransaksi, ' - Putus sewa tanggal ', ?), habissewa = ? 
                WHERE id_customer = ? AND id_kamar = ? 
                ORDER BY id_transaksi DESC LIMIT 1
            ");
            $stmt_trans->execute([$tgl_terminate, $tgl_terminate, $id_cust, $id_kamar]);
            
            $koneksi->commit();
            header("Location: customer.php?pesan=sukses_terminate");
            exit;
        } catch (Exception $e) {
            $koneksi->rollBack();
            $pesan_error = "Gagal memproses Terminate Sewa.";
        }
    } 
    // --- AKSI: PINDAH KAMAR ---
    elseif ($aksi === 'pindah') {
        $id_transaksi = $_POST['pindah_id_transaksi'];
        $id_kamar_lama = $_POST['pindah_id_kamar_lama'];
        $id_kamar_baru = $_POST['pindah_id_kamar_baru'];
        $tambahan = (int)$_POST['tambahan_biaya'];
        
        try {
            $koneksi->beginTransaction();
            // 1. Kamar Lama Jadi Kosong
            $koneksi->prepare("UPDATE table_kamar SET status_kamar = 'Kosong' WHERE id_kamar = ?")->execute([$id_kamar_lama]);
            // 2. Kamar Baru Jadi Terisi
            $koneksi->prepare("UPDATE table_kamar SET status_kamar = 'Terisi' WHERE id_kamar = ?")->execute([$id_kamar_baru]);
            // 3. Update Transaksi yang sedang berjalan
            $stmt_trans = $koneksi->prepare("
                UPDATE table_transaksi 
                SET id_kamar = ?, 
                    jumlah_charge = jumlah_charge + ?, 
                    jumlahtransaksi = jumlahtransaksi + ?, 
                    status_bayar = CASE WHEN ? > 0 THEN 'Belum Lunas' ELSE status_bayar END,
                    namatransaksi = CONCAT(namatransaksi, ' - Pindah Kamar')
                WHERE id_transaksi = ?
            ");
            $stmt_trans->execute([$id_kamar_baru, $tambahan, $tambahan, $tambahan, $id_transaksi]);
            
            $koneksi->commit();
            header("Location: customer.php?pesan=sukses_pindah");
            exit;
        } catch (Exception $e) {
            $koneksi->rollBack();
            $pesan_error = "Gagal memproses Pindah Kamar.";
        }
    }
}

// ==========================================
// PROSES HAPUS DATA CUSTOMER
// ==========================================
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $stmt_foto = $koneksi->prepare("SELECT fotoktpcustomer, fotoselfiecustomer FROM table_customer WHERE id_customer = ?");
    $stmt_foto->execute([$id_hapus]);
    $foto = $stmt_foto->fetch(PDO::FETCH_ASSOC);

    $stmt_hapus = $koneksi->prepare("DELETE FROM table_customer WHERE id_customer = ?");
    if ($stmt_hapus->execute([$id_hapus])) {
        if ($foto['fotoktpcustomer'] && file_exists('uploads/' . $foto['fotoktpcustomer'])) unlink('uploads/' . $foto['fotoktpcustomer']);
        if ($foto['fotoselfiecustomer'] && file_exists('uploads/' . $foto['fotoselfiecustomer'])) unlink('uploads/' . $foto['fotoselfiecustomer']);
        header("Location: customer.php?pesan=sukses_hapus");
        exit;
    }
}

// ==========================================
// TANGKAP PESAN SUKSES
// ==========================================
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'sukses_hapus') $pesan_sukses = "Data customer beserta fotonya berhasil dihapus.";
    if ($_GET['pesan'] == 'sukses_terminate') $pesan_sukses = "Terminate Sewa berhasil. Kamar kini kosong dan riwayat dihentikan.";
    if ($_GET['pesan'] == 'sukses_pindah') $pesan_sukses = "Proses Pindah Kamar berhasil diterapkan. Tagihan otomatis disesuaikan.";
}

// ==========================================
// DATA UNTUK MODAL (JSON INJECTION)
// ==========================================
// 1. Data Kost Global
$stmt_kost = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$list_kost_db = $stmt_kost->fetchAll(PDO::FETCH_ASSOC);

// 2. Data Customer Aktif (Sewa Berjalan)
$stmt_active = $koneksi->query("
    SELECT t.id_transaksi, c.id_customer, c.namacustomer, c.nikcustomer, k.id_kamar, k.nomor_kamar, k.harga_kamar, ko.id_kost, ko.nama_kost, t.habissewa
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE c.statuscustomer = 'Aktif'
    AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = c.id_customer)
");
$data_sewa_aktif = $stmt_active->fetchAll(PDO::FETCH_ASSOC);

// 3. Data Kamar Kosong
$stmt_empty = $koneksi->query("SELECT id_kamar, id_kost, nomor_kamar, harga_kamar FROM table_kamar WHERE status_kamar = 'Kosong'");
$data_kamar_kosong = $stmt_empty->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// LOGIKA PENCARIAN & PAGINATION
// ==========================================
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$filter_kost = $_GET['filter_kost'] ?? '';
$limit_filter = $_GET['limit_filter'] ?? '10';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where_arr = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_arr[] = "(c.namacustomer LIKE ? OR c.nikcustomer LIKE ? OR c.nohpcustomer LIKE ? OR c.alamatcustomer LIKE ? OR c.kotaasalcustomer LIKE ? OR c.namakontakdarurat LIKE ? OR c.kontakdarurat LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
}
if (!empty($status)) { $where_arr[] = "c.statuscustomer = ?"; $params[] = $status; }
if (!empty($filter_kost)) { $where_arr[] = "k.id_kost = ?"; $params[] = $filter_kost; }

$where_clause = "WHERE " . implode(" AND ", $where_arr);
$limit = ($limit_filter === 'Semua') ? 9999999 : (int)$limit_filter;
$offset = ($page - 1) * $limit;

$query_count = "SELECT COUNT(DISTINCT c.id_customer) FROM table_customer c LEFT JOIN table_transaksi t ON c.id_customer = t.id_customer AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = c.id_customer) LEFT JOIN table_kamar k ON t.id_kamar = k.id_kamar $where_clause";
$stmt_count = $koneksi->prepare($query_count);
$stmt_count->execute($params);
$total_data = $stmt_count->fetchColumn();
$total_pages = ($limit == 9999999) ? 1 : ceil($total_data / $limit);

function buildPaginateUrl($params_to_update) {
    $get = $_GET; foreach($params_to_update as $key => $value) { $get[$key] = $value; } return '?' . http_build_query($get);
}

// Data Table Utama
$query = "SELECT DISTINCT c.*, ko.nama_kost, k.nomor_kamar FROM table_customer c LEFT JOIN table_transaksi t ON c.id_customer = t.id_customer AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = c.id_customer) LEFT JOIN table_kamar k ON t.id_kamar = k.id_kamar LEFT JOIN table_kost ko ON k.id_kost = ko.id_kost $where_clause ORDER BY c.id_customer DESC LIMIT $limit OFFSET $offset";
$stmt = $koneksi->prepare($query);
$stmt->execute($params);
$data_customer = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="pb-32">

    <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Data Customer</h2>
            <p class="text-sm text-gray-500 mt-1">Kelola data riwayat penyewa dan profil customer kost Anda.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
            <button onclick="bukaModal('modal_terminate')" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition-colors shadow-sm whitespace-nowrap text-center">
                Terminate Sewa
            </button>
            <button onclick="bukaModal('modal_pindah')" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded transition-colors shadow-sm whitespace-nowrap text-center">
                Pindah Kamar
            </button>
            
            <a href="cetak_lampiran_customer.php" class="bg-gray-800 hover:bg-gray-900 text-white font-bold py-2 px-4 rounded transition-colors shadow-sm text-center flex items-center justify-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg> Cetak Lampiran
            </a>
            <a href="form_customer.php" class="bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-4 rounded transition-colors shadow-sm whitespace-nowrap text-center flex items-center justify-center">
                + Pendaftaran Baru
            </a>
        </div>
    </div>

    <?php if ($pesan_error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm font-semibold"><?= $pesan_error ?></div>
    <?php endif; ?>
    <?php if ($pesan_sukses): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-semibold"><?= $pesan_sukses ?></div>
    <?php endif; ?>

    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6">
        <form action="customer.php" method="GET" class="flex flex-col md:flex-row gap-4 items-end flex-wrap">
            <input type="hidden" name="page" value="1"> 
            <div class="flex-1 min-w-[200px]"><label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Pencarian Universal</label><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Nama, NIK, No. HP, Alamat..." class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500"></div>
            <div class="w-full md:w-40"><label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Status Sewa</label><select name="status" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white"><option value="">Semua Status</option><option value="Aktif" <?= $status == 'Aktif' ? 'selected' : '' ?>>Aktif</option><option value="Tidak Aktif" <?= $status == 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option></select></div>
            <div class="w-full md:w-48"><label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Lokasi Kost</label><select name="filter_kost" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white"><option value="">Semua Lokasi</option><?php foreach($list_kost_db as $k): ?><option value="<?= $k['id_kost'] ?>" <?= $filter_kost == $k['id_kost'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kost']) ?></option><?php endforeach; ?></select></div>
            <div class="w-full md:w-32"><label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Tampil Baris</label><select name="limit_filter" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white"><option value="10" <?= $limit_filter == '10' ? 'selected' : '' ?>>10</option><option value="15" <?= $limit_filter == '15' ? 'selected' : '' ?>>15</option><option value="20" <?= $limit_filter == '20' ? 'selected' : '' ?>>20</option><option value="25" <?= $limit_filter == '25' ? 'selected' : '' ?>>25</option><option value="50" <?= $limit_filter == '50' ? 'selected' : '' ?>>50</option><option value="Semua" <?= $limit_filter == 'Semua' ? 'selected' : '' ?>>Semua</option></select></div>
            <div class="flex gap-2 w-full md:w-auto"><button type="submit" class="flex-1 md:flex-none bg-black hover:bg-gray-800 text-white font-bold py-2 px-6 rounded transition-colors shadow-md">Terapkan</button><?php if(!empty($search) || !empty($status) || !empty($filter_kost) || $limit_filter != '10'): ?><a href="customer.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-center transition-colors border border-gray-300">Reset</a><?php endif; ?></div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50"><h3 class="font-bold text-gray-700 text-sm">Menampilkan <?= count($data_customer) ?> dari total <?= $total_data ?> customer</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-white border-b border-gray-200">
                    <tr><th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Profil & Identitas</th><th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Kontak Personal</th><th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Alamat Domisili</th><th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Status & Lokasi</th><th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase text-center">Tindakan</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($data_customer as $cust) : ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4"><p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($cust['namacustomer']) ?></p><p class="text-[11px] text-gray-500 font-mono mt-0.5">NIK: <?= htmlspecialchars($cust['nikcustomer']) ?></p></td>
                        <td class="py-3 px-4"><p class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($cust['nohpcustomer']) ?: '-' ?></p><p class="text-[11px] text-gray-500 mt-0.5">Darurat: <span class="font-semibold"><?= htmlspecialchars($cust['namakontakdarurat']) ?></span> (<?= htmlspecialchars($cust['kontakdarurat']) ?>)</p></td>
                        <td class="py-3 px-4"><p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($cust['kotaasalcustomer']) ?: '-' ?></p><p class="text-[11px] text-gray-500 mt-0.5 truncate max-w-[200px]" title="<?= htmlspecialchars($cust['alamatcustomer']) ?>"><?= htmlspecialchars($cust['alamatcustomer']) ?></p></td>
                        <td class="py-3 px-4">
                            <?php if (strtolower($cust['statuscustomer']) == 'aktif'): ?>
                                <span class="inline-block bg-green-100 text-green-800 border border-green-200 px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase mb-1">Aktif</span>
                                <p class="text-[11px] font-semibold text-gray-600"><?= htmlspecialchars($cust['nama_kost']) ?> - Kmr <?= htmlspecialchars($cust['nomor_kamar']) ?></p>
                            <?php else: ?>
                                <span class="inline-block bg-gray-100 text-gray-600 border border-gray-200 px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase">Tidak Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex flex-wrap justify-center gap-1.5">
                                <a href="profil_customer.php?id=<?= $cust['id_customer'] ?>" class="bg-blue-600 text-white hover:bg-blue-700 px-3 py-1.5 rounded text-xs font-bold transition-colors shadow-sm">Profil</a>
                                <?php if (strtolower($cust['statuscustomer']) == 'aktif'): ?><a href="perpanjang.php?id=<?= $cust['id_customer'] ?>" class="bg-black text-yellow-500 hover:bg-gray-800 px-3 py-1.5 rounded text-xs font-bold transition-colors shadow-sm">Perpanjang</a><?php endif; ?>
                                <a href="form_customer.php?edit=<?= $cust['id_customer'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-2 py-1.5 rounded text-xs font-semibold transition-colors">Edit</a>
                                <a href="customer.php?hapus=<?= $cust['id_customer'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus customer ini?');" class="border border-red-500 text-red-500 hover:bg-red-50 px-2 py-1.5 rounded text-xs font-semibold transition-colors">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($data_customer)): ?><tr><td colspan="5" class="text-center py-8 text-gray-500 font-medium">Tidak ada data customer.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center text-sm"><span class="text-gray-600 font-medium">Halaman <?= $page ?> dari <?= $total_pages ?></span><div class="flex gap-2"><?php if($page > 1): ?><a href="<?= buildPaginateUrl(['page' => $page - 1]) ?>" class="px-4 py-2 bg-white border border-gray-300 rounded hover:bg-gray-100 font-bold text-gray-700 shadow-sm">&larr; Sebelumnya</a><?php endif; ?><?php if($page < $total_pages): ?><a href="<?= buildPaginateUrl(['page' => $page + 1]) ?>" class="px-4 py-2 bg-white border border-gray-300 rounded hover:bg-gray-100 font-bold text-gray-700 shadow-sm">Selanjutnya &rarr;</a><?php endif; ?></div></div>
        <?php endif; ?>
    </div>
</div>

<div id="modal_terminate" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden border-t-4 border-red-600">
        <div class="bg-gray-50 px-6 py-4 flex justify-between items-center border-b border-gray-200">
            <h3 class="font-black text-gray-800 text-lg flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-600 inline-block"></span> Terminate Sewa</h3>
            <button type="button" onclick="tutupModal('modal_terminate')" class="text-gray-400 hover:text-red-500 font-bold text-2xl">&times;</button>
        </div>
        <form action="customer.php" method="POST" onsubmit="return confirmTerminate()" class="p-6">
            <input type="hidden" name="aksi_khusus" value="terminate">
            <input type="hidden" name="term_id_customer" id="term_id_customer">
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Lokasi Kost (Aktif)</label>
                <select id="term_kost" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500 font-semibold" onchange="renderKamarTerm()">
                    <option value="">-- Pilih Kost --</option>
                    <?php foreach($list_kost_db as $k): ?><option value="<?= $k['id_kost'] ?>"><?= htmlspecialchars($k['nama_kost']) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="mb-5">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Nomor Kamar</label>
                <select id="term_id_kamar" name="term_id_kamar" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500 font-semibold disabled:bg-gray-100" disabled onchange="showCustomerTerm()">
                    <option value="">-- Pilih Kamar --</option>
                </select>
            </div>

            <div id="term_box_customer" class="hidden bg-red-50 p-4 border border-red-200 rounded-lg mb-6 text-center">
                <p class="text-[10px] font-bold text-red-600 uppercase tracking-widest mb-1">Profil Target Terminate</p>
                <p id="term_nama_cust" class="text-xl font-black text-gray-800 mb-0.5"></p>
                <p id="term_nik_cust" class="text-xs text-gray-600 font-mono"></p>
                <p id="term_kamar_cust" class="mt-2 inline-block bg-red-200 text-red-800 px-3 py-1 rounded text-sm font-bold"></p>
            </div>
            
            <div class="flex gap-3 mt-4">
                <button type="button" onclick="tutupModal('modal_terminate')" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2.5 rounded transition-colors">Batal</button>
                <button type="submit" id="btn_submit_term" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded transition-colors shadow-md disabled:opacity-50" disabled>Terminate Sekarang</button>
            </div>
        </form>
    </div>
</div>

<div id="modal_pindah" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden border-t-4 border-orange-500">
        <div class="bg-gray-50 px-6 py-4 flex justify-between items-center border-b border-gray-200">
            <h3 class="font-black text-gray-800 text-lg flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-orange-500 inline-block"></span> Pindah Kamar</h3>
            <button type="button" onclick="tutupModal('modal_pindah')" class="text-gray-400 hover:text-orange-500 font-bold text-2xl">&times;</button>
        </div>
        <form action="customer.php" method="POST" onsubmit="return confirmPindah()" class="p-6">
            <input type="hidden" name="aksi_khusus" value="pindah">
            <input type="hidden" name="pindah_id_transaksi" id="pindah_id_transaksi">
            <input type="hidden" name="pindah_id_kamar_lama" id="pindah_id_kamar_lama">
            <input type="hidden" name="tambahan_biaya" id="input_tambahan_biaya" value="0">

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Kost Asal</label>
                    <select id="pindah_kost_asal" class="w-full border px-2 py-1.5 rounded text-sm font-semibold bg-white" onchange="renderCustPindah()">
                        <option value="">-- Kost --</option>
                        <?php foreach($list_kost_db as $k): ?><option value="<?= $k['id_kost'] ?>"><?= htmlspecialchars($k['nama_kost']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Penyewa (Aktif)</label>
                    <select id="pindah_customer" class="w-full border px-2 py-1.5 rounded text-sm font-semibold bg-white disabled:bg-gray-100" disabled onchange="showCustomerPindah()">
                        <option value="">-- Penyewa --</option>
                    </select>
                </div>
            </div>

            <div id="pindah_box_eksisting" class="hidden bg-orange-50 border border-orange-200 p-4 rounded-lg mb-6 flex flex-col items-center">
                <p class="text-[10px] font-bold text-orange-600 uppercase tracking-widest mb-1">Profil & Kamar Saat Ini</p>
                <p id="pindah_nama_cust" class="text-lg font-black text-gray-800"></p>
                <div class="flex gap-2 mt-2">
                    <span id="pindah_kamar_lama" class="bg-orange-500 text-white px-3 py-1 rounded text-sm font-bold shadow-sm"></span>
                    <span id="pindah_tarif_lama" class="bg-white border border-orange-200 text-orange-800 px-3 py-1 rounded text-sm font-bold shadow-sm"></span>
                </div>
            </div>

            <div class="border-t border-dashed border-gray-300 pt-4 mb-4">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3 text-center">Pindah Ke Tujuan</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Kost Tujuan</label>
                        <select id="pindah_kost_tujuan" class="w-full border px-2 py-1.5 rounded text-sm font-semibold bg-white" onchange="renderKamarTujuan()">
                            <option value="">-- Kost --</option>
                            <?php foreach($list_kost_db as $k): ?><option value="<?= $k['id_kost'] ?>"><?= htmlspecialchars($k['nama_kost']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Kamar (Kosong)</label>
                        <select id="pindah_id_kamar_baru" name="pindah_id_kamar_baru" class="w-full border px-2 py-1.5 rounded text-sm font-semibold bg-white disabled:bg-gray-100" disabled onchange="kalkulasiBiaya()">
                            <option value="">-- Kamar Baru --</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="pindah_box_kalkulasi" class="hidden bg-gray-800 p-4 rounded-lg text-white mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs text-gray-400 font-bold uppercase">Sisa Masa Sewa:</span>
                    <span id="calc_sisa_hari" class="text-sm font-bold text-yellow-400"></span>
                </div>
                <div class="flex justify-between items-center border-t border-gray-700 pt-2">
                    <span class="text-xs text-gray-400 font-bold uppercase">Proposional Kurang:</span>
                    <span id="calc_biaya_tambah" class="text-xl font-black text-white"></span>
                </div>
                <p id="calc_pesan" class="text-[10px] text-gray-400 mt-2 italic text-center"></p>
            </div>

            <div class="flex gap-3 mt-4">
                <button type="button" onclick="tutupModal('modal_pindah')" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2.5 rounded transition-colors">Batal</button>
                <button type="submit" id="btn_submit_pindah" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded transition-colors shadow-md disabled:opacity-50" disabled>Proses Pindah</button>
            </div>
        </form>
    </div>
</div>

<script>
// Data Injeksi dari PHP
const dbSewaAktif = <?= json_encode($data_sewa_aktif) ?>;
const dbKamarKosong = <?= json_encode($data_kamar_kosong) ?>;

// Format Rupiah
const formatRp = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);

// FUNGSI MODAL UMUM
function bukaModal(id) { document.getElementById(id).classList.remove('hidden'); }
function tutupModal(id) { document.getElementById(id).classList.add('hidden'); }

// =====================================
// LOGIKA TERMINATE
// =====================================
function renderKamarTerm() {
    const idKost = document.getElementById('term_kost').value;
    const selectKamar = document.getElementById('term_id_kamar');
    selectKamar.innerHTML = '<option value="">-- Pilih Kamar --</option>';
    document.getElementById('term_box_customer').classList.add('hidden');
    document.getElementById('btn_submit_term').disabled = true;

    if (!idKost) { selectKamar.disabled = true; return; }
    
    let adaKamar = false;
    dbSewaAktif.forEach(sewa => {
        if (sewa.id_kost == idKost) {
            selectKamar.innerHTML += `<option value="${sewa.id_kamar}">Kamar ${sewa.nomor_kamar}</option>`;
            adaKamar = true;
        }
    });
    selectKamar.disabled = !adaKamar;
}

function showCustomerTerm() {
    const idKamar = document.getElementById('term_id_kamar').value;
    const boxCust = document.getElementById('term_box_customer');
    const btnSubmit = document.getElementById('btn_submit_term');
    
    if (!idKamar) { boxCust.classList.add('hidden'); btnSubmit.disabled = true; return; }

    const sewa = dbSewaAktif.find(s => s.id_kamar == idKamar);
    if (sewa) {
        document.getElementById('term_id_customer').value = sewa.id_customer;
        document.getElementById('term_nama_cust').textContent = sewa.namacustomer;
        document.getElementById('term_nik_cust').textContent = 'NIK: ' + sewa.nikcustomer;
        document.getElementById('term_kamar_cust').textContent = 'Kamar ' + sewa.nomor_kamar;
        boxCust.classList.remove('hidden');
        btnSubmit.disabled = false;
    }
}

function confirmTerminate() {
    const nama = document.getElementById('term_nama_cust').textContent;
    const kamar = document.getElementById('term_kamar_cust').textContent;
    return confirm(`PERINGATAN TERMINATE SEWA!\n\nAnda akan memutus kontrak sewa untuk:\n- Nama: ${nama}\n- Kamar: ${kamar}\n\nKamar akan dikosongkan dan customer akan dinonaktifkan.\n\nLanjutkan?`);
}

// =====================================
// LOGIKA PINDAH KAMAR
// =====================================
let currentSewaGlobal = null;

function renderCustPindah() {
    const idKost = document.getElementById('pindah_kost_asal').value;
    const selectCust = document.getElementById('pindah_customer');
    selectCust.innerHTML = '<option value="">-- Penyewa --</option>';
    document.getElementById('pindah_box_eksisting').classList.add('hidden');
    document.getElementById('pindah_box_kalkulasi').classList.add('hidden');
    document.getElementById('pindah_kost_tujuan').value = "";
    document.getElementById('pindah_id_kamar_baru').disabled = true;
    document.getElementById('btn_submit_pindah').disabled = true;

    if (!idKost) { selectCust.disabled = true; return; }
    
    let adaCust = false;
    dbSewaAktif.forEach(sewa => {
        if (sewa.id_kost == idKost) {
            selectCust.innerHTML += `<option value="${sewa.id_transaksi}">${sewa.namacustomer} (Kmr ${sewa.nomor_kamar})</option>`;
            adaCust = true;
        }
    });
    selectCust.disabled = !adaCust;
}

function showCustomerPindah() {
    const idTrans = document.getElementById('pindah_customer').value;
    const boxEksisting = document.getElementById('pindah_box_eksisting');
    
    if (!idTrans) { boxEksisting.classList.add('hidden'); return; }

    const sewa = dbSewaAktif.find(s => s.id_transaksi == idTrans);
    if (sewa) {
        currentSewaGlobal = sewa;
        document.getElementById('pindah_id_transaksi').value = sewa.id_transaksi;
        document.getElementById('pindah_id_kamar_lama').value = sewa.id_kamar;
        document.getElementById('pindah_nama_cust').textContent = sewa.namacustomer;
        document.getElementById('pindah_kamar_lama').textContent = `Kmr ${sewa.nomor_kamar} (${sewa.nama_kost})`;
        document.getElementById('pindah_tarif_lama').textContent = formatRp(sewa.harga_kamar) + '/Bln';
        boxEksisting.classList.remove('hidden');
        kalkulasiBiaya(); // Hitung ulang jika kamar tujuan sudah dipilih
    }
}

function renderKamarTujuan() {
    const idKostTujuan = document.getElementById('pindah_kost_tujuan').value;
    const selectKamarBaru = document.getElementById('pindah_id_kamar_baru');
    selectKamarBaru.innerHTML = '<option value="">-- Kamar Baru --</option>';
    document.getElementById('pindah_box_kalkulasi').classList.add('hidden');
    document.getElementById('btn_submit_pindah').disabled = true;

    if (!idKostTujuan) { selectKamarBaru.disabled = true; return; }

    let adaKamar = false;
    dbKamarKosong.forEach(kamar => {
        if (kamar.id_kost == idKostTujuan) {
            selectKamarBaru.innerHTML += `<option value="${kamar.id_kamar}" data-harga="${kamar.harga_kamar}">Kmr ${kamar.nomor_kamar} - ${formatRp(kamar.harga_kamar)}</option>`;
            adaKamar = true;
        }
    });
    selectKamarBaru.disabled = !adaKamar;
}

function kalkulasiBiaya() {
    const idKamarBaru = document.getElementById('pindah_id_kamar_baru').value;
    const boxCalc = document.getElementById('pindah_box_kalkulasi');
    const btnSubmit = document.getElementById('btn_submit_pindah');

    if (!idKamarBaru || !currentSewaGlobal) { boxCalc.classList.add('hidden'); btnSubmit.disabled = true; return; }

    const optionBaru = document.querySelector(`#pindah_id_kamar_baru option[value='${idKamarBaru}']`);
    const hargaBaru = parseFloat(optionBaru.dataset.harga);
    const hargaLama = parseFloat(currentSewaGlobal.harga_kamar);

    // Hitung Sisa Hari Sewa
    const tglHabis = new Date(currentSewaGlobal.habissewa);
    const today = new Date();
    today.setHours(0,0,0,0);
    
    let sisaHari = Math.ceil((tglHabis - today) / (1000 * 60 * 60 * 24));
    if (sisaHari < 0) sisaHari = 0;

    let tambahanBiaya = 0;
    let pesan = "";

    if (hargaBaru > hargaLama) {
        // Proporsional: (Selisih Tarif / 30 Hari) * Sisa Hari
        tambahanBiaya = Math.round(((hargaBaru - hargaLama) / 30) * sisaHari);
        pesan = `Kamar baru lebih mahal. Ada tambahan biaya proporsional untuk sisa hari berjalan.`;
    } else {
        tambahanBiaya = 0;
        pesan = `Kamar baru sama/lebih murah. Tidak ada penambahan biaya atau pengembalian dana (Sesuai Syarat & Ketentuan).`;
    }

    document.getElementById('input_tambahan_biaya').value = tambahanBiaya;
    document.getElementById('calc_sisa_hari').textContent = sisaHari + " Hari";
    document.getElementById('calc_biaya_tambah').textContent = "+ " + formatRp(tambahanBiaya);
    document.getElementById('calc_biaya_tambah').className = tambahanBiaya > 0 ? 'text-xl font-black text-red-400' : 'text-xl font-black text-green-400';
    document.getElementById('calc_pesan').textContent = pesan;

    boxCalc.classList.remove('hidden');
    btnSubmit.disabled = false;
}

function confirmPindah() {
    const nama = document.getElementById('pindah_nama_cust').textContent;
    const kamarBaruText = document.getElementById('pindah_id_kamar_baru').options[document.getElementById('pindah_id_kamar_baru').selectedIndex].text;
    const biaya = formatRp(document.getElementById('input_tambahan_biaya').value);
    
    return confirm(`KONFIRMASI PINDAH KAMAR\n\n- Customer: ${nama}\n- Pindah ke: ${kamarBaruText}\n- Tambahan Tarif: ${biaya}\n\nStatus kamar lama akan dikosongkan dan kamar baru akan terisi.\nLanjutkan?`);
}
</script>

<?php require 'footer.php'; ?>