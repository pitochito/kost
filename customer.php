<?php
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$pesan_sukses = '';

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
        if ($foto['fotoktpcustomer'] && file_exists('uploads/' . $foto['fotoktpcustomer'])) {
            unlink('uploads/' . $foto['fotoktpcustomer']);
        }
        if ($foto['fotoselfiecustomer'] && file_exists('uploads/' . $foto['fotoselfiecustomer'])) {
            unlink('uploads/' . $foto['fotoselfiecustomer']);
        }
        header("Location: customer.php?pesan=sukses_hapus");
        exit;
    }
}

if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses_hapus') {
    $pesan_sukses = "Data customer beserta fotonya berhasil dihapus.";
}

// ==========================================
// DATA REFERENSI KOST UNTUK FILTER
// ==========================================
$stmt_kost = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$list_kost_db = $stmt_kost->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// LOGIKA PENCARIAN OMNI-SEARCH & FILTER
// ==========================================
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$filter_kost = $_GET['filter_kost'] ?? '';
$limit_filter = $_GET['limit_filter'] ?? '10';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where_arr = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_arr[] = "(
        c.namacustomer LIKE ? OR 
        c.nikcustomer LIKE ? OR 
        c.nohpcustomer LIKE ? OR 
        c.alamatcustomer LIKE ? OR 
        c.kotaasalcustomer LIKE ? OR 
        c.namakontakdarurat LIKE ? OR 
        c.kontakdarurat LIKE ?
    )";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
}

if (!empty($status)) {
    $where_arr[] = "c.statuscustomer = ?";
    $params[] = $status;
}

if (!empty($filter_kost)) {
    $where_arr[] = "k.id_kost = ?";
    $params[] = $filter_kost;
}

$where_clause = "WHERE " . implode(" AND ", $where_arr);

// ==========================================
// LOGIKA PAGINATION
// ==========================================
$limit = ($limit_filter === 'Semua') ? 9999999 : (int)$limit_filter;
$offset = ($page - 1) * $limit;

// Menghitung Total Data 
$query_count = "
    SELECT COUNT(DISTINCT c.id_customer) 
    FROM table_customer c
    LEFT JOIN table_transaksi t ON c.id_customer = t.id_customer AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = c.id_customer)
    LEFT JOIN table_kamar k ON t.id_kamar = k.id_kamar
    $where_clause
";
$stmt_count = $koneksi->prepare($query_count);
$stmt_count->execute($params);
$total_data = $stmt_count->fetchColumn();
$total_pages = ($limit == 9999999) ? 1 : ceil($total_data / $limit);

// Helper fungsi Pagination (Menahan form filter agar tidak hilang saat pindah halaman)
function buildPaginateUrl($params_to_update) {
    $get = $_GET;
    foreach($params_to_update as $key => $value) { $get[$key] = $value; }
    return '?' . http_build_query($get);
}

// ==========================================
// AMBIL DATA KESELURUHAN (DENGAN LIMIT)
// ==========================================
$query = "
    SELECT DISTINCT c.*, ko.nama_kost, k.nomor_kamar 
    FROM table_customer c
    LEFT JOIN table_transaksi t ON c.id_customer = t.id_customer AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = c.id_customer)
    LEFT JOIN table_kamar k ON t.id_kamar = k.id_kamar
    LEFT JOIN table_kost ko ON k.id_kost = ko.id_kost
    $where_clause
    ORDER BY c.id_customer DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $koneksi->prepare($query);
$stmt->execute($params);
$data_customer = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Data Customer</h2>
        <p class="text-sm text-gray-500 mt-1">Kelola data riwayat penyewa dan profil customer kost Anda.</p>
    </div>
    
    <!-- Area Tombol Diperbarui -->
    <div class="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
        <a href="laporan_tamu.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded transition-colors shadow-sm text-center flex items-center justify-center gap-2 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak Laporan Tamu
        </a>
        <a href="form_customer.php" class="bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-5 rounded transition-colors shadow-sm whitespace-nowrap text-center">
            + Pendaftaran Baru
        </a>
    </div>
</div>

<?php if ($pesan_sukses): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-semibold"><?= $pesan_sukses ?></div>
<?php endif; ?>

<div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6">
    <form action="customer.php" method="GET" class="flex flex-col md:flex-row gap-4 items-end flex-wrap">
        
        <input type="hidden" name="page" value="1"> 
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Pencarian Universal</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Nama, NIK, No. HP, Alamat..." 
                   class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
        </div>
        
        <div class="w-full md:w-40">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Status Sewa</label>
            <select name="status" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                <option value="">Semua Status</option>
                <option value="Aktif" <?= $status == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="Tidak Aktif" <?= $status == 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
            </select>
        </div>

        <div class="w-full md:w-48">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Lokasi Kost</label>
            <select name="filter_kost" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                <option value="">Semua Lokasi</option>
                <?php foreach($list_kost_db as $k): ?>
                    <option value="<?= $k['id_kost'] ?>" <?= $filter_kost == $k['id_kost'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kost']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="w-full md:w-32">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Tampil Baris</label>
            <select name="limit_filter" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                <option value="10" <?= $limit_filter == '10' ? 'selected' : '' ?>>10</option>
                <option value="15" <?= $limit_filter == '15' ? 'selected' : '' ?>>15</option>
                <option value="20" <?= $limit_filter == '20' ? 'selected' : '' ?>>20</option>
                <option value="25" <?= $limit_filter == '25' ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $limit_filter == '50' ? 'selected' : '' ?>>50</option>
                <option value="Semua" <?= $limit_filter == 'Semua' ? 'selected' : '' ?>>Semua</option>
            </select>
        </div>

        <div class="flex gap-2 w-full md:w-auto">
            <button type="submit" class="flex-1 md:flex-none bg-black hover:bg-gray-800 text-white font-bold py-2 px-6 rounded transition-colors shadow-md">
                Terapkan
            </button>
            <?php if(!empty($search) || !empty($status) || !empty($filter_kost) || $limit_filter != '10'): ?>
                <a href="customer.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-center transition-colors border border-gray-300">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="font-bold text-gray-700 text-sm">Menampilkan <?= count($data_customer) ?> dari total <?= $total_data ?> customer</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[1000px]">
            <thead class="bg-white border-b border-gray-200">
                <tr>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Profil & Identitas</th>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Kontak Personal</th>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Alamat Domisili</th>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase">Status & Lokasi</th>
                    <th class="py-3 px-4 text-xs font-bold text-gray-600 uppercase text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data_customer as $cust) : ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="py-3 px-4">
                        <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($cust['namacustomer']) ?></p>
                        <p class="text-[11px] text-gray-500 font-mono mt-0.5">NIK: <?= htmlspecialchars($cust['nikcustomer']) ?></p>
                    </td>
                    <td class="py-3 px-4">
                        <p class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($cust['nohpcustomer']) ?: '-' ?></p>
                        <p class="text-[11px] text-gray-500 mt-0.5">Darurat: <span class="font-semibold"><?= htmlspecialchars($cust['namakontakdarurat']) ?></span> (<?= htmlspecialchars($cust['kontakdarurat']) ?>)</p>
                    </td>
                    <td class="py-3 px-4">
                        <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($cust['kotaasalcustomer']) ?: '-' ?></p>
                        <p class="text-[11px] text-gray-500 mt-0.5 truncate max-w-[200px]" title="<?= htmlspecialchars($cust['alamatcustomer']) ?>">
                            <?= htmlspecialchars($cust['alamatcustomer']) ?>
                        </p>
                    </td>
                    <td class="py-3 px-4">
                        <?php if (strtolower($cust['statuscustomer']) == 'aktif'): ?>
                            <span class="inline-block bg-green-100 text-green-800 border border-green-200 px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase mb-1">Aktif</span>
                            <p class="text-[11px] font-semibold text-gray-600">
                                <?= htmlspecialchars($cust['nama_kost']) ?> - Kmr <?= htmlspecialchars($cust['nomor_kamar']) ?>
                            </p>
                        <?php else: ?>
                            <span class="inline-block bg-gray-100 text-gray-600 border border-gray-200 px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase">Tidak Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex flex-wrap justify-center gap-1.5">
                            <a href="profil_customer.php?id=<?= $cust['id_customer'] ?>" class="bg-blue-600 text-white hover:bg-blue-700 px-3 py-1.5 rounded text-xs font-bold transition-colors shadow-sm">Profil</a>

                            <?php if (strtolower($cust['statuscustomer']) == 'aktif'): ?>
                                <a href="perpanjang.php?id=<?= $cust['id_customer'] ?>" class="bg-black text-yellow-500 hover:bg-gray-800 px-3 py-1.5 rounded text-xs font-bold transition-colors shadow-sm">Perpanjang</a>
                            <?php endif; ?>
                            
                            <a href="form_customer.php?edit=<?= $cust['id_customer'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-2 py-1.5 rounded text-xs font-semibold transition-colors">Edit</a>
                            
                            <a href="customer.php?hapus=<?= $cust['id_customer'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus customer ini? Semua foto terkait juga akan ikut terhapus.');" class="border border-red-500 text-red-500 hover:bg-red-50 px-2 py-1.5 rounded text-xs font-semibold transition-colors">Hapus</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($data_customer)): ?>
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500 font-medium">Tidak ada data riwayat customer yang sesuai dengan filter/pencarian Anda.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if($total_pages > 1): ?>
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center text-sm">
        <span class="text-gray-600 font-medium">Halaman <?= $page ?> dari <?= $total_pages ?></span>
        <div class="flex gap-2">
            <?php if($page > 1): ?>
                <a href="<?= buildPaginateUrl(['page' => $page - 1]) ?>" class="px-4 py-2 bg-white border border-gray-300 rounded hover:bg-gray-100 font-bold text-gray-700 shadow-sm">&larr; Sebelumnya</a>
            <?php endif; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="<?= buildPaginateUrl(['page' => $page + 1]) ?>" class="px-4 py-2 bg-white border border-gray-300 rounded hover:bg-gray-100 font-bold text-gray-700 shadow-sm">Selanjutnya &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<?php require 'footer.php'; ?>