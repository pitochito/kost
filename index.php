<?php
require 'koneksi.php';
require 'header.php';

// ==============================================================================
// 1. SKRIP SILUMAN (AUTO-UPDATE STATUS JATUH TEMPO)
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
// ==============================================================================

// 2. AMBIL DATA USER AKTIF
$stmt_user = $koneksi->prepare("SELECT username FROM table_user WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$user_aktif = $stmt_user->fetchColumn();

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
    $where_pengeluaran = " AND id_kost = ?"; // diubah jadi AND karena WHERE sudah dipakai di query
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

// Gabungkan parameter tanggal dengan parameter filter kost
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

// Array nama bulan untuk UI
$nama_bulan_arr = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$bulan_ini_teks = $nama_bulan_arr[date('m')] . ' ' . date('Y');
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="pb-32 max-w-[1400px] mx-auto">

    <div class="mb-8 flex flex-col lg:flex-row justify-between items-start lg:items-end gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight">Selamat datang, <span class="text-yellow-600 capitalize"><?= htmlspecialchars($user_aktif) ?></span>!</h1>
            <p class="text-gray-500 mt-2 text-sm">Berikut adalah ringkasan performa dan statistik properti Kost Sun Anda saat ini.</p>
        </div>
        
        <div class="w-full lg:w-auto flex flex-col sm:flex-row gap-3">
            <form action="index.php" method="GET" class="w-full sm:w-auto">
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

    <div class="mb-8">
        <div class="flex justify-between items-center mb-4 border-b border-gray-200 pb-2">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Arus Kas & Piutang Bulan Ini</h2>
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Periode: <?= $bulan_ini_teks ?></p>
            </div>
            <a href="keuangan.php" class="text-sm text-blue-600 hover:underline font-semibold bg-blue-50 px-3 py-1 rounded-full hidden sm:block">Buka Buku Besar &rarr;</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500 flex flex-col justify-center">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Kas Masuk (Riil)</p>
                <p class="text-xl font-black text-green-600">Rp <?= number_format($total_pemasukan_riil, 0, ',', '.') ?></p>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-orange-100 border-l-4 border-l-orange-500 flex flex-col justify-center">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Piutang (Belum Dibayar)</p>
                <p class="text-xl font-black text-orange-500">Rp <?= number_format($total_piutang, 0, ',', '.') ?></p>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500 flex flex-col justify-center">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Total Pengeluaran</p>
                <p class="text-xl font-black text-red-600">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></p>
            </div>
            <div class="bg-gray-900 p-5 rounded-xl shadow-md border border-gray-800 flex flex-col justify-center">
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Saldo Bersih Kas</p>
                <p class="text-xl font-black <?= $saldo_bersih >= 0 ? 'text-yellow-500' : 'text-red-500' ?>">Rp <?= number_format($saldo_bersih, 0, ',', '.') ?></p>
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
                    <h3 class="text-sm font-bold text-white tracking-wide uppercase">Tagihan / Piutang</h3>
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