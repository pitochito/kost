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
    $where_pengeluaran = " WHERE id_kost = ?";
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

// 7. KALKULASI KEUANGAN
$stmt_in = $koneksi->prepare("SELECT SUM(t.jumlah_bayar) FROM table_transaksi t JOIN table_kamar k ON t.id_kamar = k.id_kamar WHERE 1=1 $where_transaksi");
$stmt_in->execute($param_filter);
$total_pemasukan = $stmt_in->fetchColumn() ?: 0;

$stmt_out = $koneksi->prepare("SELECT SUM(jumlahpengeluaran) FROM table_pengeluaran $where_pengeluaran");
$stmt_out->execute($param_filter);
$total_pengeluaran = $stmt_out->fetchColumn() ?: 0;

$saldo_bersih = $total_pemasukan - $total_pengeluaran;
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="mb-8 flex flex-col lg:flex-row justify-between items-start lg:items-end gap-4">
    <div>
        <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight">Selamat datang, <span class="text-yellow-600 capitalize"><?= htmlspecialchars($user_aktif) ?></span>!</h1>
        <p class="text-gray-500 mt-2 text-sm">Berikut adalah ringkasan performa dan statistik properti Kost Sun Anda saat ini.</p>
    </div>
    
    <div class="w-full lg:w-auto flex flex-col sm:flex-row gap-3">
        <!-- Filter Lokasi -->
        <form action="index.php" method="GET" class="relative w-full sm:w-auto">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <select name="filter_kost" onchange="this.form.submit()" class="block w-full sm:w-56 pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 text-sm font-semibold text-gray-700 bg-white shadow-sm cursor-pointer transition-colors hover:bg-gray-50">
                <option value="">Semua Lokasi (Global)</option>
                <?php foreach($data_lokasi_kost as $lk): ?>
                    <option value="<?= $lk['id_kost'] ?>" <?= ($filter_kost == $lk['id_kost']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lk['nama_kost']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        
        <!-- Tombol Cetak Laporan -->
        <a href="laporan.php?kost=<?= $filter_kost ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-lg transition-colors shadow-sm whitespace-nowrap flex items-center justify-center gap-2 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Laporan Bulanan
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Lokasi Dipantau</p>
        <p class="text-3xl font-black text-gray-800"><?= $tampil_total_kost ?> <span class="text-sm font-medium text-gray-400">Properti</span></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Kapasitas Kamar</p>
        <p class="text-3xl font-black text-gray-800"><?= $total_kamar ?> <span class="text-sm font-medium text-gray-400">Pintu</span></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Kamar Terisi</p>
        <p class="text-3xl font-black text-green-600"><?= $kamar_isi ?> <span class="text-sm font-medium text-green-400">Pintu</span></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Kamar Kosong</p>
        <p class="text-3xl font-black text-red-600"><?= $kamar_kosong ?> <span class="text-sm font-medium text-red-400">Pintu</span></p>
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
        <h3 class="w-full text-left font-bold text-gray-700 mb-4 border-b pb-2">Analisis Arus Kas Terkini</h3>
        <div class="relative w-full h-[200px]">
            <canvas id="cashflowChart"></canvas>
        </div>
    </div>
</div>

<?php if (!empty($data_warning)): ?>
<div class="mb-10 bg-red-50 border border-red-200 rounded-xl overflow-hidden shadow-sm">
    <div class="bg-red-500 px-6 py-4 flex items-center gap-3">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <h3 class="text-lg font-bold text-white tracking-wide">PERHATIAN: Sewa Segera Berakhir</h3>
    </div>
    
    <div class="p-6 overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[700px]">
            <thead class="border-b border-red-200">
                <tr>
                    <th class="py-2 px-2 text-sm font-bold text-red-800">Customer & Kontak</th>
                    <th class="py-2 px-2 text-sm font-bold text-red-800">Properti</th>
                    <th class="py-2 px-2 text-sm font-bold text-red-800">Sisa Waktu</th>
                    <th class="py-2 px-2 text-sm font-bold text-red-800 text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-red-100">
                <?php foreach ($data_warning as $warn): ?>
                <tr class="hover:bg-red-100 transition-colors">
                    <td class="py-3 px-2">
                        <p class="font-bold text-gray-800"><?= htmlspecialchars($warn['namacustomer']) ?></p>
                        <p class="text-xs font-semibold text-red-600"><?= htmlspecialchars($warn['nohpcustomer']) ?></p>
                    </td>
                    <td class="py-3 px-2">
                        <p class="font-semibold text-gray-700"><?= htmlspecialchars($warn['nama_kost']) ?></p>
                        <p class="text-xs text-gray-600">Kamar <?= htmlspecialchars($warn['nomor_kamar']) ?> (<?= htmlspecialchars($warn['jenis_kamar']) ?>)</p>
                    </td>
                    <td class="py-3 px-2">
                        <?php if ($warn['sisa_hari'] == 0): ?>
                            <span class="bg-red-600 text-white px-2 py-1 rounded text-xs font-bold animate-pulse">HARI INI</span>
                        <?php else: ?>
                            <span class="bg-orange-100 text-orange-800 border border-orange-300 px-2 py-1 rounded text-xs font-bold">
                                <?= $warn['sisa_hari'] ?> Hari Lagi (<?= date('d M Y', strtotime($warn['habissewa'])) ?>)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-2 text-center">
                        <a href="perpanjang.php?id=<?= $warn['id_customer'] ?>" class="inline-block bg-black hover:bg-gray-800 text-yellow-500 px-4 py-2 rounded text-xs font-bold shadow-sm transition-transform hover:scale-105">
                            + Perpanjang Sewa
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($data_belum_lunas)): ?>
<div class="mb-10 bg-orange-50 border border-orange-200 rounded-xl overflow-hidden shadow-sm">
    <div class="bg-orange-500 px-6 py-4 flex items-center gap-3">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <h3 class="text-lg font-bold text-white tracking-wide">PEMBERITAHUAN: Tagihan Belum Lunas</h3>
    </div>
    
    <div class="p-6 overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[700px]">
            <thead class="border-b border-orange-200">
                <tr>
                    <th class="py-2 px-2 text-sm font-bold text-orange-800">Customer & Kontak</th>
                    <th class="py-2 px-2 text-sm font-bold text-orange-800">Properti</th>
                    <th class="py-2 px-2 text-sm font-bold text-orange-800 text-right">Kekurangan (Rp)</th>
                    <th class="py-2 px-2 text-sm font-bold text-orange-800 text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-orange-100">
                <?php foreach ($data_belum_lunas as $bl): 
                    $tot_tagihan = $bl['jumlahtransaksi'] - $bl['diskontransaksi'] + $bl['jumlah_charge'];
                    $krg_bayar = $tot_tagihan - $bl['jumlah_bayar'];
                ?>
                <tr class="hover:bg-orange-100 transition-colors">
                    <td class="py-3 px-2">
                        <p class="font-bold text-gray-800"><?= htmlspecialchars($bl['namacustomer']) ?></p>
                        <p class="text-xs font-semibold text-orange-600"><?= htmlspecialchars($bl['nohpcustomer']) ?></p>
                    </td>
                    <td class="py-3 px-2">
                        <p class="font-semibold text-gray-700"><?= htmlspecialchars($bl['nama_kost']) ?></p>
                        <p class="text-xs text-gray-600">Kamar <?= htmlspecialchars($bl['nomor_kamar']) ?></p>
                    </td>
                    <td class="py-3 px-2 text-right">
                        <p class="font-black text-red-600">- <?= number_format($krg_bayar, 0, ',', '.') ?></p>
                        <p class="text-[10px] text-gray-500 font-semibold mt-0.5">Total Tagihan: <?= number_format($tot_tagihan, 0, ',', '.') ?></p>
                    </td>
                    <td class="py-3 px-2 text-center">
                        <a href="keuangan.php?status_filter=Belum+Lunas" class="inline-block bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded text-xs font-bold shadow-sm transition-colors">
                            Bayar di Keuangan
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="mt-8 mb-6">
    <div class="flex justify-between items-center mb-4 border-b border-gray-200 pb-2">
        <h2 class="text-xl font-bold text-gray-800">Ringkasan Arus Kas</h2>
        <a href="keuangan.php" class="text-sm text-blue-600 hover:underline font-semibold bg-blue-50 px-3 py-1 rounded-full">Buka Buku Besar &rarr;</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-500 mb-1">Pemasukan (Telah Dibayar)</p>
            <p class="text-2xl font-black text-green-600">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-500 mb-1">Total Pengeluaran</p>
            <p class="text-2xl font-black text-red-600">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></p>
        </div>
        <div class="bg-gray-900 p-6 rounded-xl shadow-md border border-gray-800 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-400 mb-1">Saldo Bersih Saat Ini</p>
            <p class="text-2xl font-black text-yellow-500">Rp <?= number_format($saldo_bersih, 0, ',', '.') ?></p>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const kmrIsi = <?= $kamar_isi ?>;
    const kmrKosong = <?= $kamar_kosong ?>;
    const kasMasuk = <?= $total_pemasukan ?>;
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
                { label: 'Pemasukan', data: [kasMasuk], backgroundColor: '#16a34a', borderRadius: 4 },
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