<?php
session_start();
require 'koneksi.php';

// Proteksi keamanan
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'header.php';

// Data Array Referensi
$nama_bulan_arr = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$tahun_sekarang = date('Y');

// Ambil Data Referensi Kost untuk Filter
$stmt_kost_list = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$list_kost_db = $stmt_kost_list->fetchAll(PDO::FETCH_ASSOC);

// Tangkap Parameter Filter
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_kost = $_GET['kost'] ?? '';

// Label Kost
$nama_kost_label = "SEMUA LOKASI (GLOBAL)";
if (!empty($filter_kost)) {
    $stmt_nama = $koneksi->prepare("SELECT nama_kost FROM table_kost WHERE id_kost = ?");
    $stmt_nama->execute([$filter_kost]);
    $nama_kost_label = strtoupper($stmt_nama->fetchColumn());
}

// Persiapan Query & Parameter
$where_in = "YEAR(t.tanggaltransaksi) = ?";
$where_out = "YEAR(p.tanggalpengeluaran) = ?";
$params = [$filter_tahun];

if (!empty($filter_kost)) {
    $where_in .= " AND k.id_kost = ?";
    $where_out .= " AND p.id_kost = ?";
    $params[] = $filter_kost;
}

// 1. QUERY PEMASUKAN & PIUTANG PER BULAN
$stmt_in = $koneksi->prepare("
    SELECT 
        MONTH(t.tanggaltransaksi) as bulan,
        SUM(t.jumlahtransaksi - t.diskontransaksi + t.jumlah_charge) as total_omzet,
        SUM(t.jumlah_bayar) as total_riil
    FROM table_transaksi t
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    WHERE $where_in
    GROUP BY MONTH(t.tanggaltransaksi)
");
$stmt_in->execute($params);
$data_in = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

// 2. QUERY PENGELUARAN PER BULAN
$stmt_out = $koneksi->prepare("
    SELECT 
        MONTH(p.tanggalpengeluaran) as bulan,
        SUM(p.jumlahpengeluaran) as total_pengeluaran
    FROM table_pengeluaran p
    WHERE $where_out
    GROUP BY MONTH(p.tanggalpengeluaran)
");
$stmt_out->execute($params);
$data_out = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

// 3. SUSUN ARRAY DATA 12 BULAN
$laporan_tahunan = [];
for ($i = 1; $i <= 12; $i++) {
    $laporan_tahunan[$i] = [
        'omzet' => 0,
        'riil' => 0,
        'piutang' => 0,
        'pengeluaran' => 0,
        'saldo_bersih' => 0
    ];
}

// Masukkan data ke dalam array sesuai bulannya
foreach ($data_in as $in) {
    $b = $in['bulan'];
    $laporan_tahunan[$b]['omzet'] = $in['total_omzet'];
    $laporan_tahunan[$b]['riil'] = $in['total_riil'];
    $laporan_tahunan[$b]['piutang'] = $in['total_omzet'] - $in['total_riil'];
}

foreach ($data_out as $out) {
    $b = $out['bulan'];
    $laporan_tahunan[$b]['pengeluaran'] = $out['total_pengeluaran'];
}

// Kalkulasi Saldo Bersih & Grand Total
$grand_total = ['omzet' => 0, 'riil' => 0, 'piutang' => 0, 'pengeluaran' => 0, 'saldo_bersih' => 0];

for ($i = 1; $i <= 12; $i++) {
    $laporan_tahunan[$i]['saldo_bersih'] = $laporan_tahunan[$i]['riil'] - $laporan_tahunan[$i]['pengeluaran'];
    
    $grand_total['omzet'] += $laporan_tahunan[$i]['omzet'];
    $grand_total['riil'] += $laporan_tahunan[$i]['riil'];
    $grand_total['piutang'] += $laporan_tahunan[$i]['piutang'];
    $grand_total['pengeluaran'] += $laporan_tahunan[$i]['pengeluaran'];
    $grand_total['saldo_bersih'] += $laporan_tahunan[$i]['saldo_bersih'];
}
?>

<style>
    @media print {
        /* 1. Sembunyikan Navigasi & Sidebar bawaan header.php */
        aside, header, #sidebar, #sidebar-overlay { 
            display: none !important; 
        }
        
        /* 2. Lepaskan batasan layout agar tabel diprint penuh (tidak terpotong scroll) */
        body, html, main, .flex-1 { 
            display: block !important;
            height: auto !important; 
            overflow: visible !important; 
            background: white !important;
            position: static !important;
        }
        
        /* 3. Hilangkan padding/margin bawaan dari workspace aplikasi */
        main {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        /* 4. Aturan styling khusus cetakan Laporan */
        .no-print { display: none !important; }
        .print-border { border: 1px solid #e5e7eb !important; }
        .print-shadow-none { box-shadow: none !important; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        
        /* 5. Setelan Kertas */
        @page { margin: 1cm; size: A4 landscape; }
    }
</style>

<div class="pb-32 max-w-6xl mx-auto">
    
    <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4 no-print">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Laporan Tahunan</h2>
            <p class="text-sm text-gray-500 mt-1">Rekapitulasi performa keuangan (Omzet, Arus Kas, & Piutang) selama satu tahun penuh.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-gray-800 hover:bg-black text-white px-5 py-2 rounded font-bold shadow-sm transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg> Cetak Laporan
            </button>
        </div>
    </div>

    <!-- FILTER PENCARIAN -->
    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6 no-print">
        <form action="laporan_tahunan.php" method="GET" class="flex flex-col md:flex-row gap-4 items-end flex-wrap">
            <div class="w-full md:w-48">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Tahun Laporan</label>
                <select name="tahun" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                    <?php for($y = 2023; $y <= $tahun_sekarang + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Lokasi Kost</label>
                <select name="kost" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                    <option value="">Semua Lokasi (Global)</option>
                    <?php foreach($list_kost_db as $k): ?>
                        <option value="<?= $k['id_kost'] ?>" <?= $filter_kost == $k['id_kost'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama_kost']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex gap-2 w-full md:w-auto">
                <button type="submit" class="flex-1 md:flex-none bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition-colors shadow-md">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <!-- KERTAS CETAKAN -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden print-shadow-none print-border">
        
        <!-- HEADER LAPORAN -->
        <div class="p-6 md:p-8 border-b-4 border-gray-900 flex justify-between items-center bg-gray-50">
            <div>
                <h1 class="text-2xl md:text-3xl font-black text-gray-900 uppercase tracking-tight">LAPORAN TAHUNAN</h1>
                <p class="text-sm font-bold text-gray-600 uppercase tracking-widest mt-1"><?= htmlspecialchars($nama_kost_label) ?></p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Tahun Pembukuan</p>
                <p class="text-3xl font-black text-blue-700"><?= htmlspecialchars($filter_tahun) ?></p>
            </div>
        </div>

        <div class="overflow-x-auto p-0 md:p-4">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="bg-gray-100 border-b-2 border-gray-300">
                        <th class="py-4 px-4 text-xs font-bold text-gray-700 uppercase tracking-wider">Bulan</th>
                        <th class="py-4 px-4 text-xs font-bold text-gray-700 uppercase tracking-wider text-right">Omzet (Tagihan)</th>
                        <th class="py-4 px-4 text-xs font-bold text-gray-700 uppercase tracking-wider text-right">Kas Masuk (Riil)</th>
                        <th class="py-4 px-4 text-xs font-bold text-gray-700 uppercase tracking-wider text-right">Piutang</th>
                        <th class="py-4 px-4 text-xs font-bold text-gray-700 uppercase tracking-wider text-right">Pengeluaran</th>
                        <th class="py-4 px-4 text-xs font-black text-gray-900 uppercase tracking-wider text-right bg-gray-200">Saldo Bersih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php for ($i = 1; $i <= 12; $i++): 
                        $bg_row = ($i % 2 == 0) ? 'bg-gray-50' : 'bg-white';
                    ?>
                    <tr class="<?= $bg_row ?> hover:bg-blue-50 transition-colors">
                        <td class="py-3 px-4 font-bold text-gray-800 uppercase text-sm"><?= $nama_bulan_arr[$i] ?></td>
                        <td class="py-3 px-4 text-right font-semibold text-gray-700">Rp <?= number_format($laporan_tahunan[$i]['omzet'], 0, ',', '.') ?></td>
                        <td class="py-3 px-4 text-right font-bold text-green-600">Rp <?= number_format($laporan_tahunan[$i]['riil'], 0, ',', '.') ?></td>
                        <td class="py-3 px-4 text-right font-bold <?= $laporan_tahunan[$i]['piutang'] > 0 ? 'text-orange-500' : 'text-gray-400' ?>">
                            <?= $laporan_tahunan[$i]['piutang'] > 0 ? 'Rp ' . number_format($laporan_tahunan[$i]['piutang'], 0, ',', '.') : '-' ?>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-red-600">Rp <?= number_format($laporan_tahunan[$i]['pengeluaran'], 0, ',', '.') ?></td>
                        <td class="py-3 px-4 text-right font-black text-base <?= $laporan_tahunan[$i]['saldo_bersih'] >= 0 ? 'text-blue-700' : 'text-red-600' ?> bg-gray-100/50 border-l border-gray-200">
                            Rp <?= number_format($laporan_tahunan[$i]['saldo_bersih'], 0, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot class="bg-gray-800 text-white border-t-4 border-gray-900">
                    <tr>
                        <td class="py-5 px-4 font-black uppercase text-sm">TOTAL TAHUNAN</td>
                        <td class="py-5 px-4 text-right font-bold text-sm">Rp <?= number_format($grand_total['omzet'], 0, ',', '.') ?></td>
                        <td class="py-5 px-4 text-right font-black text-green-400 text-base">Rp <?= number_format($grand_total['riil'], 0, ',', '.') ?></td>
                        <td class="py-5 px-4 text-right font-bold <?= $grand_total['piutang'] > 0 ? 'text-orange-400' : 'text-white' ?>">
                            Rp <?= number_format($grand_total['piutang'], 0, ',', '.') ?>
                        </td>
                        <td class="py-5 px-4 text-right font-black text-red-400 text-base">Rp <?= number_format($grand_total['pengeluaran'], 0, ',', '.') ?></td>
                        <td class="py-5 px-4 text-right font-black text-lg <?= $grand_total['saldo_bersih'] >= 0 ? 'text-blue-300' : 'text-red-400' ?> border-l border-gray-700">
                            Rp <?= number_format($grand_total['saldo_bersih'], 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>