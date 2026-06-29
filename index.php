<?php
require 'koneksi.php';
require 'header.php';

// ==============================================================================
// 1. PROSES POST: TAGIHAN RUTIN (INPUT / EDIT / BAYAR)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_tagihan'])) {
    $aksi = $_POST['aksi_tagihan'];
    $id_user_aktif = $_SESSION['user_id'] ?? 1;

    if ($aksi === 'simpan_tagihan') {
        $id_kost_tagihan = (int)$_POST['id_kost'];
        $jenis = $_POST['jenis_tagihan'];
        $nominal = (int)$_POST['nominal_tagihan'];
        $status_bayar = $_POST['status_bayar']; // 'Belum Bayar' atau 'Lunas'
        $tanggal_bayar = $_POST['tanggal_bayar'] ?: date('Y-m-d');
        
        $bulan = $_POST['bulan_tagihan'];
        $tahun = $_POST['tahun_tagihan'];
        $catat_pengeluaran = isset($_POST['catat_pengeluaran']) ? true : false;

        // Cek apakah data bulan ini sudah ada
        $cek = $koneksi->prepare("SELECT * FROM table_tagihan_rutin WHERE id_kost=? AND jenis_tagihan=? AND bulan_tagihan=? AND tahun_tagihan=?");
        $cek->execute([$id_kost_tagihan, $jenis, $bulan, $tahun]);
        $tag = $cek->fetch(PDO::FETCH_ASSOC);

        $id_ada = $tag['id_tagihan'] ?? null;
        $status_sebelumnya = $tag['status_bayar'] ?? 'Belum Bayar';

        try {
            $koneksi->beginTransaction();

            if ($id_ada) {
                // Cegah edit jika di database sebenarnya sudah lunas
                if ($status_sebelumnya !== 'Lunas') {
                    $tgl_db = ($status_bayar === 'Lunas') ? $tanggal_bayar : null;
                    $upd = $koneksi->prepare("UPDATE table_tagihan_rutin SET nominal=?, status_bayar=?, tanggal_bayar=? WHERE id_tagihan=?");
                    $upd->execute([$nominal, $status_bayar, $tgl_db, $id_ada]);
                }
            } else {
                $tgl_db = ($status_bayar === 'Lunas') ? $tanggal_bayar : null;
                $ins = $koneksi->prepare("INSERT INTO table_tagihan_rutin (id_kost, jenis_tagihan, bulan_tagihan, tahun_tagihan, nominal, status_bayar, tanggal_bayar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$id_kost_tagihan, $jenis, $bulan, $tahun, $nominal, $status_bayar, $tgl_db]);
            }

            // CROSS-CHECK: Jika diset Lunas DAN Checkbox Catat Pengeluaran dicentang
            if ($status_bayar === 'Lunas' && $status_sebelumnya === 'Belum Bayar') {
                if ($catat_pengeluaran) {
                    $nama_pengeluaran = "Bayar tagihan " . strtolower($jenis) . " bulan " . $bulan . "/" . $tahun;
                    
                    // PERBAIKAN: Sesuaikan dengan kategori di table_pengeluaran database asli
                    $jenis_pengeluaran = 'Rutin';
                    $kategori = ($jenis === 'PDAM') ? 'Air' : 'Internet'; 
                    
                    $ins_out = $koneksi->prepare("INSERT INTO table_pengeluaran (jenispengeluaran, kategoripengeluaran, namapengeluaran, tanggalpengeluaran, jumlahpengeluaran, id_kost, id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $ins_out->execute([$jenis_pengeluaran, $kategori, $nama_pengeluaran, $tanggal_bayar, $nominal, $id_kost_tagihan, $id_user_aktif]);
                }
            }

            $koneksi->commit();
            
            $pesan_redir = ($status_bayar === 'Lunas') ? 'tagihan_lunas' : 'tagihan_disimpan';
            echo "<script>window.location.href='index.php?bln_tagihan=$bulan&thn_tagihan=$tahun&pesan=$pesan_redir';</script>";
            exit;

        } catch (Exception $e) {
            $koneksi->rollBack();
            // Handle error silently
        }
    }
}

// ==============================================================================
// 2. SKRIP SILUMAN (AUTO-UPDATE STATUS JATUH TEMPO)
// ==============================================================================
$stmt_cek_expired = $koneksi->query("
    SELECT t.id_kamar, t.id_customer 
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    WHERE t.habissewa < CURDATE() AND c.statuscustomer = 'Aktif'
    AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = t.id_customer)
");
$expired_data = $stmt_cek_expired->fetchAll(PDO::FETCH_ASSOC);

if (!empty($expired_data)) {
    try {
        $koneksi->beginTransaction();
        $stmt_update_cust = $koneksi->prepare("UPDATE table_customer SET statuscustomer = 'Tidak Aktif' WHERE id_customer = ?");
        $stmt_update_kamar = $koneksi->prepare("UPDATE table_kamar SET status_kamar = 'Kosong' WHERE id_kamar = ?");
        
        foreach ($expired_data as $exp) {
            $stmt_update_cust->execute([$exp['id_customer']]);
            $stmt_update_kamar->execute([$exp['id_kamar']]);
        }
        $koneksi->commit();
    } catch (Exception $e) {
        $koneksi->rollBack();
    }
}

// AMBIL DATA USER AKTIF
$stmt_user = $koneksi->prepare("SELECT username FROM table_user WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id'] ?? 1]);
$user_aktif = $stmt_user->fetchColumn() ?: 'Admin';

// ==============================================================================
// 3. LOGIKA FILTER PER CABANG / LOKASI KOST
// ==============================================================================
$data_lokasi_kost = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC")->fetchAll(PDO::FETCH_ASSOC);

$filter_kost = $_GET['filter_kost'] ?? '';
$param_filter = [];
$where_kamar = "";
$where_transaksi = "";
$where_pengeluaran = "";

if (!empty($filter_kost)) {
    $where_kamar = " AND id_kost = ?";
    $where_transaksi = " AND k.id_kost = ?";
    $where_pengeluaran = " AND id_kost = ?"; 
    $param_filter = [$filter_kost];
}

// 4. KALKULASI STATISTIK PROPERTI
$total_kost_global = $koneksi->query("SELECT COUNT(*) FROM table_kost")->fetchColumn();
$tampil_total_kost = empty($filter_kost) ? $total_kost_global : 1; 

$stmt_kamar = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar WHERE 1=1 $where_kamar");
$stmt_kamar->execute($param_filter);
$total_kamar = $stmt_kamar->fetchColumn();

$stmt_isi = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) IN ('isi', 'terisi') $where_kamar");
$stmt_isi->execute($param_filter);
$kamar_isi = $stmt_isi->fetchColumn();

$stmt_kosong = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) = 'kosong' $where_kamar");
$stmt_kosong->execute($param_filter);
$kamar_kosong = $stmt_kosong->fetchColumn();

// 5. QUERY PERINGATAN JATUH TEMPO
$query_warning = "
    SELECT 
        c.id_customer, c.namacustomer, c.nohpcustomer,
        k.nomor_kamar, k.jenis_kamar,
        ko.nama_kost,
        t.habissewa,
        DATEDIFF(t.habissewa, CURDATE()) as sisa_hari,
        DATEDIFF(t.habissewa, t.mulaisewa) as total_durasi_hari
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE c.statuscustomer = 'Aktif' 
    AND t.habissewa >= CURDATE()
    $where_transaksi
    AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = t.id_customer)
    HAVING 
        (total_durasi_hari > 20 AND sisa_hari <= 7) OR 
        (total_durasi_hari > 6 AND total_durasi_hari <= 20 AND sisa_hari <= 3) OR 
        (total_durasi_hari <= 6 AND sisa_hari <= 1)
    ORDER BY sisa_hari ASC
";
$stmt_warning = $koneksi->prepare($query_warning);
$stmt_warning->execute($param_filter);
$data_warning = $stmt_warning->fetchAll(PDO::FETCH_ASSOC);

// 6. QUERY CUSTOMER BELUM LUNAS
$query_belum_lunas = "
    SELECT 
        c.id_customer, c.namacustomer, c.nohpcustomer,
        k.nomor_kamar, ko.nama_kost,
        t.id_transaksi, t.jumlahtransaksi, t.diskontransaksi, t.jumlah_charge, t.jumlah_bayar
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE t.status_bayar = 'Belum Lunas'
    $where_transaksi
    ORDER BY t.tanggaltransaksi DESC
";
$stmt_belum_lunas = $koneksi->prepare($query_belum_lunas);
$stmt_belum_lunas->execute($param_filter);
$data_belum_lunas = $stmt_belum_lunas->fetchAll(PDO::FETCH_ASSOC);

// 7. KALKULASI KEUANGAN (HANYA BULAN BERJALAN SAJA)
$bulan_ini_start = date('Y-m-01');
$bulan_ini_end = date('Y-m-t');

$param_keuangan = array_merge([$bulan_ini_start, $bulan_ini_end], $param_filter);

$stmt_in = $koneksi->prepare("
    SELECT 
        SUM(t.jumlahtransaksi - t.diskontransaksi + t.jumlah_charge) as total_tagihan,
        SUM(t.jumlah_bayar) as uang_diterima
    FROM table_transaksi t 
    JOIN table_kamar k ON t.id_kamar = k.id_kamar 
    WHERE t.tanggaltransaksi BETWEEN ? AND ? $where_transaksi
");
$stmt_in->execute($param_keuangan);
$hasil_sum = $stmt_in->fetch(PDO::FETCH_ASSOC);

$total_pendapatan = $hasil_sum['total_tagihan'] ?: 0;
$total_pemasukan_riil = $hasil_sum['uang_diterima'] ?: 0;
$total_piutang = $total_pendapatan - $total_pemasukan_riil;

$stmt_out = $koneksi->prepare("SELECT SUM(jumlahpengeluaran) FROM table_pengeluaran WHERE tanggalpengeluaran BETWEEN ? AND ? $where_pengeluaran");
$stmt_out->execute($param_keuangan);
$total_pengeluaran = $stmt_out->fetchColumn() ?: 0;

$saldo_bersih = $total_pemasukan_riil - $total_pengeluaran;

$nama_bulan_arr = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$bulan_ini_teks = $nama_bulan_arr[date('m')] . ' ' . date('Y');

// 8. QUERY DATA KOST UNTUK TAGIHAN RUTIN (DENGAN FILTER BULAN)
$bln_tagihan = $_GET['bln_tagihan'] ?? date('m');
$thn_tagihan = $_GET['thn_tagihan'] ?? date('Y');

$query_tagihan = "SELECT id_kost, nama_kost, no_pdam, no_indihome FROM table_kost WHERE (no_pdam IS NOT NULL AND no_pdam != '') OR (no_indihome IS NOT NULL AND no_indihome != '')";
if (!empty($filter_kost)) {
    $query_tagihan .= " AND id_kost = ?";
    $stmt_tagihan = $koneksi->prepare($query_tagihan);
    $stmt_tagihan->execute([$filter_kost]);
} else {
    $stmt_tagihan = $koneksi->query($query_tagihan);
}
$data_kost_tagihan = $stmt_tagihan->fetchAll(PDO::FETCH_ASSOC);

$stmt_rutin = $koneksi->prepare("SELECT * FROM table_tagihan_rutin WHERE bulan_tagihan=? AND tahun_tagihan=?");
$stmt_rutin->execute([$bln_tagihan, $thn_tagihan]);
$rutin_db = $stmt_rutin->fetchAll(PDO::FETCH_ASSOC);
$map_tagihan = [];
foreach ($rutin_db as $r) {
    $map_tagihan[$r['id_kost']][$r['jenis_tagihan']] = $r;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="pb-32 max-w-[1400px] mx-auto">

    <?php if(isset($_GET['pesan'])): ?>
        <?php if($_GET['pesan'] == 'tagihan_disimpan'): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded shadow-sm font-semibold">Nominal tagihan properti berhasil dicatat/diperbarui.</div>
        <?php elseif($_GET['pesan'] == 'tagihan_lunas'): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-semibold">Pembayaran berhasil diproses sesuai kriteria kroscek Anda.</div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="mb-8 flex flex-col lg:flex-row justify-between items-start lg:items-end gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight">Selamat datang, <span class="text-yellow-600 capitalize"><?= htmlspecialchars($user_aktif) ?></span>!</h1>
            <p class="text-gray-500 mt-2 text-sm">Berikut adalah ringkasan performa dan statistik properti Kost Sun Anda saat ini.</p>
        </div>
        
        <div class="w-full lg:w-auto flex flex-col sm:flex-row gap-3">
            <form action="index.php" method="GET" class="w-full sm:w-auto">
                <input type="hidden" name="bln_tagihan" value="<?= htmlspecialchars($bln_tagihan) ?>">
                <input type="hidden" name="thn_tagihan" value="<?= htmlspecialchars($thn_tagihan) ?>">
                <select name="filter_kost" onchange="this.form.submit()" class="block w-full sm:w-56 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 text-sm font-semibold text-gray-700 bg-white shadow-sm cursor-pointer transition-colors hover:bg-gray-50">
                    <option value="">Semua Lokasi (Global)</option>
                    <?php foreach($data_lokasi_kost as $lk): ?>
                        <option value="<?= $lk['id_kost'] ?>" <?= ($filter_kost == $lk['id_kost']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lk['nama_kost']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <a href="laporan.php?kost=<?= $filter_kost ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-lg transition-colors shadow-sm whitespace-nowrap flex items-center justify-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Laporan Bulanan
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
        
        <div class="xl:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
            <div class="bg-emerald-600 px-5 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Arus Kas (<?= $bulan_ini_teks ?>)</h3>
                </div>
                <a href="keuangan.php" class="text-xs text-white hover:text-emerald-100 font-semibold flex items-center gap-1">Buku Besar <span aria-hidden="true">&rarr;</span></a>
            </div>
            
            <div class="p-0 overflow-x-auto flex-1 bg-white">
                <table class="w-full text-left border-collapse text-sm">
                    <tbody class="divide-y divide-gray-200">
                        <tr class="hover:bg-green-50 transition-colors">
                            <td class="py-4 px-6 font-bold text-gray-700 w-1/2">Kas Masuk (Riil)</td>
                            <td class="py-4 px-6 text-right font-black text-green-600 text-lg">Rp <?= number_format($total_pemasukan_riil, 0, ',', '.') ?></td>
                        </tr>
                        <tr class="hover:bg-orange-50 transition-colors">
                            <td class="py-4 px-6 font-bold text-gray-700">Piutang Sewa (Belum Dibayar)</td>
                            <td class="py-4 px-6 text-right font-black text-orange-500 text-lg">Rp <?= number_format($total_piutang, 0, ',', '.') ?></td>
                        </tr>
                        <tr class="hover:bg-red-50 transition-colors">
                            <td class="py-4 px-6 font-bold text-gray-700">Total Pengeluaran Operasional</td>
                            <td class="py-4 px-6 text-right font-black text-red-600 text-lg">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></td>
                        </tr>
                        <tr class="bg-gray-900 text-white">
                            <td class="py-4 px-6 font-bold tracking-wider uppercase text-gray-300">Saldo Bersih Kas</td>
                            <td class="py-4 px-6 text-right font-black text-xl <?= $saldo_bersih >= 0 ? 'text-yellow-400' : 'text-red-500' ?>">Rp <?= number_format($saldo_bersih, 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="xl:col-span-1 bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
            <div class="bg-blue-600 px-4 py-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <h3 class="text-xs font-bold text-white tracking-wide uppercase">Tagihan Rutin</h3>
                </div>
                <form action="index.php" method="GET" class="flex gap-1 items-center">
                    <input type="hidden" name="filter_kost" value="<?= htmlspecialchars($filter_kost) ?>">
                    <select name="bln_tagihan" onchange="this.form.submit()" class="text-xs rounded px-1.5 py-0.5 text-gray-800 font-bold bg-white/90 border-0 focus:ring-0 cursor-pointer">
                        <?php foreach($nama_bulan_arr as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $bln_tagihan == $num ? 'selected' : '' ?>><?= substr($name,0,3) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="thn_tagihan" onchange="this.form.submit()" class="text-xs rounded px-1.5 py-0.5 text-gray-800 font-bold bg-white/90 border-0 focus:ring-0 cursor-pointer">
                        <?php for($y = 2023; $y <= date('Y')+1; $y++): ?>
                            <option value="<?= $y ?>" <?= $thn_tagihan == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
            
            <div class="p-0 overflow-y-auto flex-1 bg-gray-50/50 max-h-[300px]">
                <?php if (!empty($data_kost_tagihan)): ?>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach($data_kost_tagihan as $tagihan): ?>
                            <li class="p-4 hover:bg-blue-50 transition-colors">
                                <h4 class="font-bold text-gray-800 text-sm mb-3"><?= htmlspecialchars($tagihan['nama_kost']) ?></h4>
                                
                                <?php if(!empty($tagihan['no_pdam'])): 
                                    $pdam = $map_tagihan[$tagihan['id_kost']]['PDAM'] ?? null;
                                ?>
                                <div class="flex justify-between items-center mb-2 bg-white p-2.5 rounded border border-gray-200 shadow-sm">
                                    <div>
                                        <p class="text-[10px] font-bold text-blue-500 uppercase tracking-wider">Air / PDAM</p>
                                        <?php if(!$pdam): ?>
                                            <p class="text-xs font-semibold text-gray-400 italic">Belum Diinput</p>
                                        <?php else: ?>
                                            <p class="text-sm font-black text-gray-800">Rp <?= number_format($pdam['nominal'], 0, ',', '.') ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-1 flex-wrap justify-end">
                                        <?php if(!$pdam): ?>
                                            <button onclick="bukaModalTagihan(<?= $tagihan['id_kost'] ?>, 'PDAM', '<?= htmlspecialchars(addslashes($tagihan['nama_kost'])) ?>', '', false)" class="px-3 py-1 bg-gray-800 text-white hover:bg-black rounded text-[10px] font-bold shadow-sm transition-colors">Input</button>
                                        <?php elseif($pdam['status_bayar'] === 'Lunas'): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-700 border border-green-200 rounded text-[10px] font-bold uppercase tracking-wider text-center block w-full">Lunas Tgl <br><?= date('d/m', strtotime($pdam['tanggal_bayar'])) ?></span>
                                        <?php else: ?>
                                            <button onclick="bukaModalTagihan(<?= $tagihan['id_kost'] ?>, 'PDAM', '<?= htmlspecialchars(addslashes($tagihan['nama_kost'])) ?>', <?= $pdam['nominal'] ?>, false)" class="px-2 py-1 border border-yellow-500 text-yellow-600 hover:bg-yellow-50 rounded text-[10px] font-bold transition-colors">Edit</button>
                                            <button onclick="bukaModalTagihan(<?= $tagihan['id_kost'] ?>, 'PDAM', '<?= htmlspecialchars(addslashes($tagihan['nama_kost'])) ?>', <?= $pdam['nominal'] ?>, true)" class="px-3 py-1 bg-green-600 text-white hover:bg-green-700 rounded text-[10px] font-bold shadow-sm transition-colors">Bayar</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($tagihan['no_indihome'])): 
                                    $indihome = $map_tagihan[$tagihan['id_kost']]['IndiHome'] ?? null;
                                ?>
                                <div class="flex justify-between items-center bg-white p-2.5 rounded border border-gray-200 shadow-sm">
                                    <div>
                                        <p class="text-[10px] font-bold text-red-500 uppercase tracking-wider">Internet / IndiHome</p>
                                        <?php if(!$indihome): ?>
                                            <p class="text-xs font-semibold text-gray-400 italic">Belum Diinput</p>
                                        <?php else: ?>
                                            <p class="text-sm font-black text-gray-800">Rp <?= number_format($indihome['nominal'], 0, ',', '.') ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-1 flex-wrap justify-end">
                                        <?php if(!$indihome): ?>
                                            <button onclick="bukaModalTagihan(<?= $tagihan['id_kost'] ?>, 'IndiHome', '<?= htmlspecialchars(addslashes($tagihan['nama_kost'])) ?>', '', false)" class="px-3 py-1 bg-gray-800 text-white hover:bg-black rounded text-[10px] font-bold shadow-sm transition-colors">Input</button>
                                        <?php elseif($indihome['status_bayar'] === 'Lunas'): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-700 border border-green-200 rounded text-[10px] font-bold uppercase tracking-wider text-center block w-full">Lunas Tgl <br><?= date('d/m', strtotime($indihome['tanggal_bayar'])) ?></span>
                                        <?php else: ?>
                                            <button onclick="bukaModalTagihan(<?= $tagihan['id_kost'] ?>, 'IndiHome', '<?= htmlspecialchars(addslashes($tagihan['nama_kost'])) ?>', <?= $indihome['nominal'] ?>, false)" class="px-2 py-1 border border-yellow-500 text-yellow-600 hover:bg-yellow-50 rounded text-[10px] font-bold transition-colors">Edit</button>
                                            <button onclick="bukaModalTagihan(<?= $tagihan['id_kost'] ?>, 'IndiHome', '<?= htmlspecialchars(addslashes($tagihan['nama_kost'])) ?>', <?= $indihome['nominal'] ?>, true)" class="px-3 py-1 bg-green-600 text-white hover:bg-green-700 rounded text-[10px] font-bold shadow-sm transition-colors">Bayar</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center p-8 h-full">
                        <p class="font-bold text-gray-500 text-sm text-center">Nomor Pelanggan Belum Diatur</p>
                        <p class="text-[10px] text-gray-400 mt-1 text-center">Isi nomor PDAM/IndiHome di database untuk memunculkan panel ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
        
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
            <div class="bg-red-500 px-5 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Sewa Segera Berakhir</h3>
                </div>
                <span class="bg-red-700 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= count($data_warning) ?></span>
            </div>
            
            <div class="p-0 overflow-x-auto flex-1 bg-gray-50/50">
                <?php if (!empty($data_warning)): ?>
                <table class="w-full text-left border-collapse text-sm min-w-[450px]">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="py-2 px-4 font-bold text-gray-700 text-xs">Customer (Kamar)</th>
                            <th class="py-2 px-4 font-bold text-gray-700 text-xs">Sisa Waktu</th>
                            <th class="py-2 px-4 font-bold text-gray-700 text-xs text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($data_warning as $warn): ?>
                        <tr class="hover:bg-red-50 transition-colors">
                            <td class="py-3 px-4">
                                <p class="font-bold text-gray-800 leading-tight"><?= htmlspecialchars($warn['namacustomer']) ?></p>
                                <p class="text-[10px] text-gray-500 mt-0.5"><?= htmlspecialchars($warn['nama_kost']) ?> - Kmr <?= htmlspecialchars($warn['nomor_kamar']) ?></p>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($warn['sisa_hari'] == 0): ?>
                                    <span class="bg-red-600 text-white px-2 py-0.5 rounded text-[10px] font-bold animate-pulse">HARI INI</span>
                                <?php else: ?>
                                    <span class="bg-orange-100 text-orange-800 border border-orange-300 px-2 py-0.5 rounded text-[10px] font-bold">
                                        <?= $warn['sisa_hari'] ?> Hari (<?= date('d M', strtotime($warn['habissewa'])) ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="perpanjang.php?id=<?= $warn['id_customer'] ?>" class="inline-block bg-black hover:bg-gray-800 text-yellow-500 px-3 py-1.5 rounded text-[10px] font-bold shadow-sm">
                                    Perpanjang
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="flex flex-col items-center justify-center p-8 h-full min-h-[150px]">
                    <div class="bg-green-100 text-green-500 p-3 rounded-full mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <p class="font-bold text-gray-700">Situasi Aman!</p>
                    <p class="text-xs text-gray-500 mt-1">Tidak ada jadwal sewa yang mendekati masa jatuh tempo.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
            <div class="bg-orange-500 px-5 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Tagihan / Piutang Sewa</h3>
                </div>
                <span class="bg-orange-700 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= count($data_belum_lunas) ?></span>
            </div>
            
            <div class="p-0 overflow-x-auto flex-1 bg-gray-50/50">
                <?php if (!empty($data_belum_lunas)): ?>
                <table class="w-full text-left border-collapse text-sm min-w-[450px]">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="py-2 px-4 font-bold text-gray-700 text-xs">Customer (Kamar)</th>
                            <th class="py-2 px-4 font-bold text-gray-700 text-xs text-right">Kekurangan (Rp)</th>
                            <th class="py-2 px-4 font-bold text-gray-700 text-xs text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($data_belum_lunas as $bl): 
                            $tot_tagihan = $bl['jumlahtransaksi'] - $bl['diskontransaksi'] + $bl['jumlah_charge'];
                            $krg_bayar = $tot_tagihan - $bl['jumlah_bayar'];
                        ?>
                        <tr class="hover:bg-orange-50 transition-colors">
                            <td class="py-3 px-4">
                                <p class="font-bold text-gray-800 leading-tight"><?= htmlspecialchars($bl['namacustomer']) ?></p>
                                <p class="text-[10px] text-gray-500 mt-0.5"><?= htmlspecialchars($bl['nama_kost']) ?> - Kmr <?= htmlspecialchars($bl['nomor_kamar']) ?></p>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <p class="font-black text-red-600">- <?= number_format($krg_bayar, 0, ',', '.') ?></p>
                                <p class="text-[9px] text-gray-400 font-semibold mt-0.5">Trx: #<?= $bl['id_transaksi'] ?></p>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="keuangan.php?in_status=Belum+Lunas#section_pemasukan" class="inline-block bg-orange-600 hover:bg-orange-700 text-white px-3 py-1.5 rounded text-[10px] font-bold shadow-sm">
                                    Bayar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="flex flex-col items-center justify-center p-8 h-full min-h-[150px]">
                    <div class="bg-green-100 text-green-500 p-3 rounded-full mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <p class="font-bold text-gray-700">Lunas Semua!</p>
                    <p class="text-xs text-gray-500 mt-1">Seluruh tagihan customer Anda telah dibayar lunas.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="mb-4">
        <h2 class="text-xl font-bold text-gray-800 border-b border-gray-200 pb-2">Status Properti Saat Ini</h2>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-center">
            <p class="text-xs font-semibold text-gray-500 mb-1">Total Properti</p>
            <p class="text-2xl font-black text-gray-800"><?= $tampil_total_kost ?> <span class="text-xs font-medium text-gray-400">Lokasi</span></p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-center">
            <p class="text-xs font-semibold text-gray-500 mb-1">Kapasitas</p>
            <p class="text-2xl font-black text-gray-800"><?= $total_kamar ?> <span class="text-xs font-medium text-gray-400">Pintu</span></p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-center">
            <p class="text-xs font-semibold text-gray-500 mb-1 text-green-600">Terisi</p>
            <p class="text-2xl font-black text-green-600"><?= $kamar_isi ?> <span class="text-xs font-medium text-gray-400">Pintu</span></p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-center">
            <p class="text-xs font-semibold text-gray-500 mb-1 text-red-500">Kosong</p>
            <p class="text-2xl font-black text-red-600"><?= $kamar_kosong ?> <span class="text-xs font-medium text-gray-400">Pintu</span></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex flex-col items-center justify-center">
            <h3 class="w-full text-left font-bold text-gray-700 mb-4 border-b pb-2">Rasio Okupansi Kamar</h3>
            <div class="relative w-full max-w-[200px] aspect-square">
                <canvas id="occupancyChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 lg:col-span-2 flex flex-col justify-center">
            <h3 class="w-full text-left font-bold text-gray-700 mb-4 border-b pb-2">Analisis Arus Kas (<?= $bulan_ini_teks ?>)</h3>
            <div class="relative w-full h-[200px]">
                <canvas id="cashflowChart"></canvas>
            </div>
        </div>
    </div>

</div>

<div id="modal_tagihan" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="bg-blue-600 px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-white text-lg" id="modal_tagihan_title">Input Tagihan</h3>
            <button type="button" onclick="tutupModalTagihan()" class="text-white hover:text-blue-200 font-bold text-xl">&times;</button>
        </div>
        
        <form action="index.php" method="POST" class="p-6" onsubmit="return konfirmasiTagihan()">
            <input type="hidden" name="aksi_tagihan" value="simpan_tagihan">
            <input type="hidden" name="id_kost" id="modal_id_kost">
            <input type="hidden" name="jenis_tagihan" id="modal_jenis_tagihan">
            
            <div class="mb-4 bg-gray-50 p-3 rounded border border-gray-200 shadow-inner">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Periode Pemakaian Layanan</p>
                <div class="flex gap-2">
                    <select name="bulan_tagihan" id="modal_bulan_tagihan" class="w-2/3 border border-gray-300 px-2 py-1 rounded font-bold text-gray-800 bg-white">
                        <?php foreach($nama_bulan_arr as $num => $name): ?>
                            <option value="<?= $num ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tahun_tagihan" id="modal_tahun_tagihan" class="w-1/3 border border-gray-300 px-2 py-1 rounded font-bold text-gray-800 bg-white">
                        <?php for($y = 2023; $y <= date('Y')+1; $y++): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <p class="text-xs font-semibold text-gray-700 mt-2 border-t pt-2" id="modal_detail_layanan"></p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Nominal Tagihan (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="nominal_tagihan" id="modal_nominal" required min="1" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold text-lg text-gray-800 bg-white">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Status Pembayaran</label>
                <select name="status_bayar" id="modal_status_bayar" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white font-semibold" onchange="toggleTanggalBayar()">
                    <option value="Belum Bayar">Belum Dibayar</option>
                    <option value="Lunas">Sudah Dibayar (Lunas)</option>
                </select>
            </div>

            <div id="wrap_tanggal_bayar" class="mb-6 hidden bg-green-50 p-4 border border-green-200 rounded">
                <label class="block text-xs font-bold text-green-800 mb-1">Tanggal Bayar / Pelunasan <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal_bayar" id="modal_tanggal_bayar" value="<?= date('Y-m-d') ?>" class="w-full border border-green-300 px-3 py-2 rounded focus:ring-2 focus:ring-green-500 font-medium text-sm">
                
                <div class="mt-3 flex items-start gap-2">
                    <input type="checkbox" name="catat_pengeluaran" id="catat_pengeluaran" value="1" checked class="mt-0.5">
                    <label for="catat_pengeluaran" class="text-[10px] text-green-800 leading-tight">
                        <strong>Otomatis catat pengeluaran ke Buku Besar.</strong><br>
                        <span class="text-red-600 font-semibold">(Hapus centang ini jika Anda sudah pernah menginputnya secara manual di menu Keuangan, agar saldo tidak berkurang 2 kali).</span>
                    </label>
                </div>
            </div>
            
            <div class="flex gap-3 justify-end border-t border-gray-200 pt-4 mt-2">
                <button type="button" onclick="tutupModalTagihan()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded font-bold hover:bg-gray-300 transition-colors shadow-sm">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded font-bold hover:bg-blue-700 transition-colors shadow-md">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<script>
    const globalBulanTagihan = '<?= $bln_tagihan ?>';
    const globalTahunTagihan = '<?= $thn_tagihan ?>';

    function bukaModalTagihan(idKost, jenis, namaKost, currentNominal, setLunas) {
        document.getElementById('modal_id_kost').value = idKost;
        document.getElementById('modal_jenis_tagihan').value = jenis;
        
        document.getElementById('modal_bulan_tagihan').value = globalBulanTagihan;
        document.getElementById('modal_tahun_tagihan').value = globalTahunTagihan;
        
        document.getElementById('modal_detail_layanan').textContent = `Layanan: ${jenis} - ${namaKost}`;
        document.getElementById('modal_nominal').value = currentNominal || '';
        
        const statusSelect = document.getElementById('modal_status_bayar');
        if (setLunas) {
            statusSelect.value = 'Lunas';
            document.getElementById('modal_tagihan_title').textContent = 'Pelunasan Tagihan';
        } else {
            statusSelect.value = 'Belum Bayar';
            document.getElementById('modal_tagihan_title').textContent = currentNominal ? 'Edit Tagihan Bulan Ini' : 'Input Tagihan Baru';
        }
        
        toggleTanggalBayar(); 
        document.getElementById('modal_tagihan').classList.remove('hidden');
    }

    function tutupModalTagihan() {
        document.getElementById('modal_tagihan').classList.add('hidden');
    }

    function toggleTanggalBayar() {
        const status = document.getElementById('modal_status_bayar').value;
        const wrapTgl = document.getElementById('wrap_tanggal_bayar');
        if(status === 'Lunas') {
            wrapTgl.classList.remove('hidden');
            document.getElementById('modal_tanggal_bayar').setAttribute('required', 'true');
        } else {
            wrapTgl.classList.add('hidden');
            document.getElementById('modal_tanggal_bayar').removeAttribute('required');
        }
    }

    function konfirmasiTagihan() {
        const status = document.getElementById('modal_status_bayar').value;
        const nominal = parseInt(document.getElementById('modal_nominal').value).toLocaleString('id-ID');
        const jenis = document.getElementById('modal_jenis_tagihan').value;
        
        const bln = document.getElementById('modal_bulan_tagihan').options[document.getElementById('modal_bulan_tagihan').selectedIndex].text;
        const thn = document.getElementById('modal_tahun_tagihan').value;

        if (status === 'Lunas') {
            const tglBayar = document.getElementById('modal_tanggal_bayar').value;
            const dicatat = document.getElementById('catat_pengeluaran').checked;
            let pesanEkstra = dicatat 
                ? "Sistem akan OTOMATIS mencatat ini sebagai Pengeluaran Operasional di Buku Besar." 
                : "Sistem TIDAK AKAN mencatat ini ke Buku Besar (hanya mengubah status menjadi Lunas).";

            return confirm(`KONFIRMASI PELUNASAN:\n\nAnda mengatur tagihan ${jenis} (Periode: ${bln} ${thn}) sebesar Rp ${nominal} menjadi LUNAS pada tanggal ${tglBayar}.\n\n${pesanEkstra}\n\nLanjutkan?`);
        } else {
            return confirm(`Simpan nominal tagihan ${jenis} (Periode: ${bln} ${thn}) sebesar Rp ${nominal} dengan status Belum Dibayar?`);
        }
    }
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const kmrIsi = <?= $kamar_isi ?>;
    const kmrKosong = <?= $kamar_kosong ?>;
    const kasMasuk = <?= $total_pemasukan_riil ?>;
    const kasKeluar = <?= $total_pengeluaran ?>;

    const ctxOcc = document.getElementById('occupancyChart').getContext('2d');
    new Chart(ctxOcc, {
        type: 'doughnut',
        data: {
            labels: ['Terisi', 'Kosong'],
            datasets: [{ data: [kmrIsi, kmrKosong], backgroundColor: ['#16a34a', '#dc2626'], hoverOffset: 4, borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: {family: 'sans-serif', weight: 'bold'} } } }, cutout: '70%' }
    });

    const ctxCash = document.getElementById('cashflowChart').getContext('2d');
    new Chart(ctxCash, {
        type: 'bar',
        data: {
            labels: ['Arus Kas'],
            datasets: [
                { label: 'Pemasukan (Riil)', data: [kasMasuk], backgroundColor: '#16a34a', borderRadius: 4 },
                { label: 'Pengeluaran', data: [kasKeluar], backgroundColor: '#dc2626', borderRadius: 4 }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: {family: 'sans-serif', weight: 'bold'} } },
                tooltip: { callbacks: { label: function(context) { return ' Rp ' + context.raw.toLocaleString('id-ID'); } } }
            },
            scales: { x: { grid: { display: false }, ticks: { callback: function(value) { return 'Rp ' + (value/1000000) + 'M'; } } }, y: { grid: { display: false }, display: false } }
        }
    });
});
</script>

<?php require 'footer.php'; ?>