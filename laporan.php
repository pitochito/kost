<?php
session_start();
require 'koneksi.php';

// Proteksi keamanan
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Data Array Referensi
$nama_bulan_arr = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$tahun_sekarang = date('Y');

// Ambil Data Referensi Kost untuk Filter
$stmt_kost_list = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$list_kost_db = $stmt_kost_list->fetchAll(PDO::FETCH_ASSOC);

// Tangkap Parameter Filter
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_kost = $_GET['kost'] ?? '';

// Hitung Rentang Tanggal
$tanggal_mulai = $filter_tahun . '-' . $filter_bulan . '-01';
$tanggal_selesai = date('Y-m-t', strtotime($tanggal_mulai));
$periode_cetak = $nama_bulan_arr[$filter_bulan] . ' ' . $filter_tahun;

// Label Kost
$nama_kost_label = "SEMUA LOKASI (GLOBAL)";
if (!empty($filter_kost)) {
    $stmt_nama = $koneksi->prepare("SELECT nama_kost FROM table_kost WHERE id_kost = ?");
    $stmt_nama->execute([$filter_kost]);
    $nama_kost_label = strtoupper($stmt_nama->fetchColumn());
}

// Setup Parameter Query
$where_in = "t.tanggal_bayar BETWEEN ? AND ?";
$where_out = "p.tanggalpengeluaran BETWEEN ? AND ?";
$where_kamar = "";
$params = [$tanggal_mulai, $tanggal_selesai];

if (!empty($filter_kost)) {
    $where_in .= " AND k.id_kost = ?";
    $where_out .= " AND p.id_kost = ?";
    $where_kamar .= " AND id_kost = ?";
    $params[] = $filter_kost;
}

// 1. AGREGASI PEMASUKAN
$stmt_sum_in = $koneksi->prepare("SELECT SUM(t.jumlah_bayar) FROM table_transaksi t JOIN table_kamar k ON t.id_kamar = k.id_kamar WHERE $where_in");
$stmt_sum_in->execute($params);
$total_pemasukan = $stmt_sum_in->fetchColumn() ?: 0;

// 2. AGREGASI PENGELUARAN
$stmt_sum_out = $koneksi->prepare("SELECT SUM(p.jumlahpengeluaran) FROM table_pengeluaran p WHERE $where_out");
$stmt_sum_out->execute($params);
$total_pengeluaran = $stmt_sum_out->fetchColumn() ?: 0;

$saldo_bersih = $total_pemasukan - $total_pengeluaran;

// 3. STATISTIK OKUPANSI (Kondisi Terkini)
$param_kamar = !empty($filter_kost) ? [$filter_kost] : [];
$where_kamar_clause = !empty($filter_kost) ? "WHERE id_kost = ?" : "";

$kamar_total = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar $where_kamar_clause"); $kamar_total->execute($param_kamar); $tot_kmr = $kamar_total->fetchColumn();
$kamar_isi = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) IN ('isi','terisi') $where_kamar"); $kamar_isi->execute($param_kamar); $isi_kmr = $kamar_isi->fetchColumn();
$kamar_ksg = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) = 'kosong' $where_kamar"); $kamar_ksg->execute($param_kamar); $ksg_kmr = $kamar_ksg->fetchColumn();
$persentase = ($tot_kmr > 0) ? round(($isi_kmr / $tot_kmr) * 100, 1) : 0;

// 4. RINCIAN TABEL PEMASUKAN (Hanya yang ada pembayaran masuk di bulan tsb)
$query_list_in = "
    SELECT t.tanggal_bayar, c.namacustomer, k.nomor_kamar, t.namatransaksi, t.jumlah_bayar
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    WHERE $where_in AND t.jumlah_bayar > 0
    ORDER BY t.tanggal_bayar ASC
";
$stmt_list_in = $koneksi->prepare($query_list_in);
$stmt_list_in->execute($params);
$rincian_pemasukan = $stmt_list_in->fetchAll(PDO::FETCH_ASSOC);

// 5. RINCIAN TABEL PENGELUARAN (Di-group per kategori agar rapi)
$query_list_out = "
    SELECT p.kategoripengeluaran, SUM(p.jumlahpengeluaran) as total_biaya
    FROM table_pengeluaran p
    WHERE $where_out
    GROUP BY p.kategoripengeluaran
    ORDER BY total_biaya DESC
";
$stmt_list_out = $koneksi->prepare($query_list_out);
$stmt_list_out->execute($params);
$rekap_pengeluaran = $stmt_list_out->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bulanan - Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { background-color: white !important; }
            .no-print { display: none !important; }
            .print-border { border: 1px solid #e5e7eb !important; }
            .print-shadow-none { box-shadow: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { margin: 1cm; size: A4 portrait; }
        }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8 min-h-screen text-gray-800 font-sans">

    <!-- PANEL PENGATURAN (Sembunyi saat diprint) -->
    <div class="max-w-4xl mx-auto mb-8 bg-white p-6 rounded-lg shadow-md border-t-4 border-blue-600 no-print">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h2 class="text-xl font-bold text-gray-800">Pengaturan Cetak Laporan</h2>
            <a href="index.php" class="text-sm font-semibold text-gray-500 hover:text-black">&larr; Kembali ke Dasbor</a>
        </div>
        
        <form action="laporan.php" method="GET" class="flex flex-col md:flex-row gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
                <select name="bulan" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                    <?php foreach($nama_bulan_arr as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $filter_bulan == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
                <select name="tahun" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                    <?php for($y = 2023; $y <= $tahun_sekarang + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cakupan Lokasi Kost</label>
                <select name="kost" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                    <option value="">Semua Lokasi / Global</option>
                    <?php foreach($list_kost_db as $k): ?>
                        <option value="<?= $k['id_kost'] ?>" <?= $filter_kost == $k['id_kost'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kost']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 w-full md:w-auto mt-4 md:mt-0">
                <button type="submit" class="bg-gray-800 hover:bg-black text-white px-6 py-2 rounded font-bold transition-colors">Terapkan</button>
                <button type="button" onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-bold shadow-md flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg> Cetak PDF
                </button>
            </div>
        </form>
    </div>

    <!-- KERTAS LAPORAN (Area yang diprint) -->
    <div class="max-w-4xl mx-auto bg-white p-8 md:p-12 shadow-lg print-shadow-none print-border rounded">
        
        <!-- HEADER KOP SURAT -->
        <div class="flex border-b-4 border-gray-900 pb-6 mb-8 items-center gap-6">
            <img src="logo.jpg" alt="Logo" class="h-24 w-24 object-contain rounded bg-black p-1">
            <div class="flex-1">
                <h1 class="text-3xl font-black text-gray-900 tracking-tight uppercase">KOST SUN MANAGEMENT</h1>
                <p class="text-sm font-semibold text-gray-600 mt-1 uppercase tracking-widest">LAPORAN KINERJA & KEUANGAN BULANAN</p>
                <p class="text-xs text-gray-500 mt-1">Dicetak pada: <?= date('d/m/Y H:i') ?></p>
            </div>
            <div class="text-right border-l-2 border-gray-200 pl-6 hidden sm:block">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Periode Laporan</p>
                <p class="text-xl font-black text-blue-800 uppercase"><?= $periode_cetak ?></p>
                <p class="text-xs font-bold text-gray-600 mt-1"><?= $nama_kost_label ?></p>
            </div>
        </div>
        
        <!-- Pengecualian view mobile untuk tampilan kanan header -->
        <div class="sm:hidden mb-6 bg-gray-50 p-4 border border-gray-200 text-center">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Periode Laporan</p>
            <p class="text-xl font-black text-blue-800 uppercase"><?= $periode_cetak ?></p>
            <p class="text-xs font-bold text-gray-600 mt-1"><?= $nama_kost_label ?></p>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="grid grid-cols-2 gap-4 mb-10">
            <!-- Box Keuangan -->
            <div class="border-2 border-gray-200 rounded-lg p-5">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 border-b pb-2">Ringkasan Arus Kas</h3>
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-semibold text-gray-600">Total Pemasukan Masuk</span>
                    <span class="font-bold text-green-600">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-sm mb-3">
                    <span class="font-semibold text-gray-600">Total Pengeluaran</span>
                    <span class="font-bold text-red-600">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 mt-2">
                    <span class="font-black text-gray-800 text-lg uppercase">Saldo Bersih</span>
                    <span class="font-black text-lg <?= $saldo_bersih >= 0 ? 'text-blue-700' : 'text-red-600' ?>">Rp <?= number_format($saldo_bersih, 0, ',', '.') ?></span>
                </div>
            </div>
            
            <!-- Box Okupansi -->
            <div class="border-2 border-gray-200 rounded-lg p-5">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 border-b pb-2">Statistik Properti</h3>
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-semibold text-gray-600">Kapasitas Pintu</span>
                    <span class="font-bold text-gray-800"><?= $tot_kmr ?> Kamar</span>
                </div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-semibold text-gray-600">Kamar Terisi</span>
                    <span class="font-bold text-green-600"><?= $isi_kmr ?> Kamar</span>
                </div>
                <div class="flex justify-between text-sm mb-3">
                    <span class="font-semibold text-gray-600">Kamar Kosong</span>
                    <span class="font-bold text-red-600"><?= $ksg_kmr ?> Kamar</span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 mt-2">
                    <span class="font-black text-gray-800 text-lg uppercase">Okupansi</span>
                    <span class="font-black text-lg text-blue-700"><?= $persentase ?>%</span>
                </div>
            </div>
        </div>

        <!-- TABEL REKAP PENGELUARAN (Grouped) -->
        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest mb-3 border-b-2 border-red-500 inline-block pb-1">Beban Operasional & Pengeluaran</h3>
        <table class="w-full text-left mb-10 border border-gray-300">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-2 px-4 text-xs font-bold text-gray-700 border-b border-gray-300 w-16 text-center">No</th>
                    <th class="py-2 px-4 text-xs font-bold text-gray-700 border-b border-gray-300">Kategori Biaya</th>
                    <th class="py-2 px-4 text-xs font-bold text-gray-700 border-b border-gray-300 text-right">Total Penyerapan (Rp)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
                <?php if (empty($rekap_pengeluaran)): ?>
                    <tr><td colspan="3" class="py-4 text-center text-gray-500 italic">Nihil / Tidak ada pengeluaran operasional di bulan ini.</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach ($rekap_pengeluaran as $out): ?>
                    <tr>
                        <td class="py-2 px-4 text-center text-gray-500"><?= $no++ ?></td>
                        <td class="py-2 px-4 font-semibold text-gray-700"><?= htmlspecialchars($out['kategoripengeluaran']) ?></td>
                        <td class="py-2 px-4 text-right font-bold text-red-600"><?= number_format($out['total_biaya'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-300">
                <tr>
                    <td colspan="2" class="py-2 px-4 text-right text-xs uppercase tracking-wider">Total Beban Operasional:</td>
                    <td class="py-2 px-4 text-right text-red-700">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- TABEL RINCIAN PEMASUKAN -->
        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest mb-3 border-b-2 border-green-500 inline-block pb-1">Rincian Arus Kas Masuk (Penerimaan Sewa)</h3>
        <table class="w-full text-left mb-10 border border-gray-300 text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-2 px-4 text-xs font-bold text-gray-700 border-b border-gray-300">Tanggal</th>
                    <th class="py-2 px-4 text-xs font-bold text-gray-700 border-b border-gray-300">Penyewa</th>
                    <th class="py-2 px-4 text-xs font-bold text-gray-700 border-b border-gray-300 text-center">Kamar</th>
                    <th class="py-2 px-4 text-xs font-bold text-gray-700 border-b border-gray-300 text-right">Nominal Masuk (Rp)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($rincian_pemasukan)): ?>
                    <tr><td colspan="4" class="py-4 text-center text-gray-500 italic">Nihil / Tidak ada pembayaran masuk di bulan ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($rincian_pemasukan as $in): ?>
                    <tr>
                        <td class="py-2 px-4 text-gray-600"><?= date('d/m/Y', strtotime($in['tanggal_bayar'])) ?></td>
                        <td class="py-2 px-4 font-semibold text-gray-800"><?= htmlspecialchars($in['namacustomer']) ?></td>
                        <td class="py-2 px-4 text-center text-gray-600"><?= htmlspecialchars($in['nomor_kamar']) ?></td>
                        <td class="py-2 px-4 text-right font-bold text-green-600"><?= number_format($in['jumlah_bayar'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-300">
                <tr>
                    <td colspan="3" class="py-2 px-4 text-right text-xs uppercase tracking-wider">Total Kas Masuk Diterima:</td>
                    <td class="py-2 px-4 text-right text-green-700">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- KOLOM TANDA TANGAN -->
        <div class="mt-16 flex justify-end">
            <div class="text-center w-64">
                <p class="text-sm font-semibold text-gray-600 mb-20">Pontianak, <?= date('d F Y') ?></p>
                <!-- Garis lurus untuk tanda tangan -->
                <div class="border-b border-gray-800 w-48 mx-auto mb-1"></div>
                <p class="text-xs text-gray-500 font-bold tracking-wider uppercase mt-1">Manager / Pengelola</p>
            </div>
        </div>

    </div>
</body>
</html>