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

// Setup Parameter Query (DIUPDATE: Pemasukan menggunakan tanggaltransaksi)
$where_in = "t.tanggaltransaksi BETWEEN ? AND ?";
$where_out = "p.tanggalpengeluaran BETWEEN ? AND ?";
$where_kamar = "";
$params = [$tanggal_mulai, $tanggal_selesai];

if (!empty($filter_kost)) {
    $where_in .= " AND k.id_kost = ?";
    $where_out .= " AND p.id_kost = ?";
    $where_kamar .= " AND id_kost = ?";
    $params[] = $filter_kost;
}

// 1. AGREGASI PEMASUKAN & PIUTANG (ACCRUAL BASIS)
$stmt_sum_in = $koneksi->prepare("
    SELECT 
        SUM(t.jumlahtransaksi - t.diskontransaksi + t.jumlah_charge) as total_tagihan,
        SUM(t.jumlah_bayar) as uang_diterima
    FROM table_transaksi t 
    JOIN table_kamar k ON t.id_kamar = k.id_kamar 
    WHERE $where_in
");
$stmt_sum_in->execute($params);
$hasil_sum = $stmt_sum_in->fetch(PDO::FETCH_ASSOC);

$total_pendapatan = $hasil_sum['total_tagihan'] ?: 0;
$total_pemasukan_riil = $hasil_sum['uang_diterima'] ?: 0;
$total_piutang = $total_pendapatan - $total_pemasukan_riil;

// 2. AGREGASI PENGELUARAN
$stmt_sum_out = $koneksi->prepare("SELECT SUM(p.jumlahpengeluaran) FROM table_pengeluaran p WHERE $where_out");
$stmt_sum_out->execute($params);
$total_pengeluaran = $stmt_sum_out->fetchColumn() ?: 0;

$saldo_bersih = $total_pemasukan_riil - $total_pengeluaran; // Saldo berdasarkan uang di tangan

// 3. STATISTIK OKUPANSI (Kondisi Terkini)
$param_kamar = !empty($filter_kost) ? [$filter_kost] : [];
$where_kamar_clause = !empty($filter_kost) ? "WHERE id_kost = ?" : "";

$kamar_total = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar $where_kamar_clause"); $kamar_total->execute($param_kamar); $tot_kmr = $kamar_total->fetchColumn();
$kamar_isi = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) IN ('isi','terisi') $where_kamar"); $kamar_isi->execute($param_kamar); $isi_kmr = $kamar_isi->fetchColumn();
$kamar_ksg = $koneksi->prepare("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) = 'kosong' $where_kamar"); $kamar_ksg->execute($param_kamar); $ksg_kmr = $kamar_ksg->fetchColumn();
$persentase = ($tot_kmr > 0) ? round(($isi_kmr / $tot_kmr) * 100, 1) : 0;

// 4. RINCIAN TABEL PEMASUKAN (Semua transaksi di bulan tersebut termasuk Piutang)
$query_list_in = "
    SELECT 
        t.tanggaltransaksi, t.tanggal_bayar, c.namacustomer, k.nomor_kamar, 
        t.namatransaksi, t.jumlahtransaksi, t.diskontransaksi, t.jumlah_charge, 
        t.jumlah_bayar, t.status_bayar
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    WHERE $where_in
    ORDER BY t.tanggaltransaksi ASC
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
    <link rel="icon" type="image/jpeg" href="ikon-sun.jpg">
    <link rel="apple-touch-icon" href="ikon-sun.jpg">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kost Sun">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { background-color: white !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .print-border { border: 1px solid #e5e7eb !important; padding: 1.5rem !important; }
            .print-shadow-none { box-shadow: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { margin: 1cm; size: A4 portrait; }
            /* Memaksa elemen grid menjadi 2 kolom di kertas cetak */
            .print-grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
    </style>
</head>
<body class="bg-gray-100 p-2 sm:p-4 md:p-8 min-h-screen text-gray-800 font-sans">

    <div class="max-w-4xl mx-auto mb-6 md:mb-8 bg-white p-4 md:p-6 rounded-lg shadow-md border-t-4 border-blue-600 no-print">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h2 class="text-lg md:text-xl font-bold text-gray-800">Pengaturan Laporan</h2>
            <a href="index.php" class="text-xs md:text-sm font-semibold text-gray-500 hover:text-black">&larr; Kembali</a>
        </div>
        
        <form action="laporan.php" method="GET" class="flex flex-col md:flex-row gap-4 items-end">
            <div class="w-full md:w-auto">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
                <select name="bulan" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                    <?php foreach($nama_bulan_arr as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $filter_bulan == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-full md:w-auto">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
                <select name="tahun" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                    <?php for($y = 2023; $y <= $tahun_sekarang + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Lokasi Kost</label>
                <select name="kost" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                    <option value="">Semua Lokasi / Global</option>
                    <?php foreach($list_kost_db as $k): ?>
                        <option value="<?= $k['id_kost'] ?>" <?= $filter_kost == $k['id_kost'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kost']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 w-full md:w-auto mt-2 md:mt-0">
                <button type="submit" class="w-full sm:w-auto bg-gray-800 hover:bg-black text-white px-6 py-2 rounded font-bold transition-colors">Terapkan</button>
                <button type="button" onclick="window.print()" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-bold shadow-md flex justify-center items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg> Cetak
                </button>
            </div>
        </form>
    </div>

    <div class="max-w-4xl mx-auto bg-white p-4 sm:p-8 md:p-12 shadow-lg print-shadow-none print-border rounded">
        
        <div class="flex border-b-4 border-gray-900 pb-4 md:pb-6 mb-6 md:mb-8 items-center gap-3 md:gap-6">
            <img src="logo.jpg" alt="Logo" class="h-16 w-16 md:h-24 md:w-24 object-contain rounded bg-black p-1 shrink-0">
            <div class="flex-1">
                <h1 class="text-xl md:text-3xl font-black text-gray-900 tracking-tight uppercase">KOST SUN</h1>
                <p class="text-[10px] md:text-sm font-semibold text-gray-600 mt-0.5 md:mt-1 uppercase tracking-widest">LAPORAN BULANAN</p>
                <p class="text-[9px] md:text-xs text-gray-500 mt-1">Dicetak: <?= date('d/m/Y H:i') ?></p>
            </div>
            <div class="text-right border-l-2 border-gray-200 pl-4 md:pl-6 hidden sm:block">
                <p class="text-[9px] md:text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Periode</p>
                <p class="text-lg md:text-xl font-black text-blue-800 uppercase"><?= $periode_cetak ?></p>
                <p class="text-[10px] md:text-xs font-bold text-gray-600 mt-1"><?= $nama_kost_label ?></p>
            </div>
        </div>
        
        <div class="sm:hidden mb-6 bg-gray-50 p-3 border border-gray-200 text-center rounded">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Periode Laporan</p>
            <p class="text-lg font-black text-blue-800 uppercase"><?= $periode_cetak ?></p>
            <p class="text-xs font-bold text-gray-600 mt-0.5"><?= $nama_kost_label ?></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 print-grid-cols-2 gap-4 mb-8 md:mb-10">
            <div class="border-2 border-gray-200 rounded-lg p-4 md:p-5 flex flex-col justify-between">
                <div>
                    <h3 class="text-[11px] md:text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Ringkasan Keuangan</h3>
                    
                    <div class="mb-4">
                        <div class="flex justify-between text-xs md:text-sm mb-1">
                            <span class="font-semibold text-gray-600">Total Omset (Tagihan)</span>
                            <span class="font-bold text-gray-800">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></span>
                        </div>
                        
                        <?php if ($total_piutang > 0): ?>
                            <div class="bg-orange-50 border border-orange-200 p-2 md:p-2.5 rounded mt-1.5 print-border">
                                <div class="flex justify-between items-center text-[11px] md:text-xs">
                                    <span class="font-bold text-orange-800 flex items-center gap-1 uppercase tracking-wider">
                                        ⚠️ Piutang (Wajib Ditagih)
                                    </span>
                                    <span class="font-black text-orange-600 text-sm md:text-base">Rp <?= number_format($total_piutang, 0, ',', '.') ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex justify-between text-[11px] md:text-xs mt-1">
                                <span class="font-semibold text-gray-500">Piutang / Tunggakan</span>
                                <span class="font-bold text-green-600">Rp 0 (Lunas Semua)</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-dashed border-gray-300 pt-3 mb-2">
                        <div class="flex justify-between text-xs md:text-sm mb-1.5">
                            <span class="font-semibold text-gray-600">Uang Tunai Diterima</span>
                            <span class="font-bold text-green-600">+ Rp <?= number_format($total_pemasukan_riil, 0, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between text-xs md:text-sm mb-1">
                            <span class="font-semibold text-gray-600">Total Pengeluaran</span>
                            <span class="font-bold text-red-600">- Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center border-t-2 border-gray-800 pt-3 mt-2 bg-gray-50 p-2 rounded print-border">
                    <span class="font-black text-gray-800 text-xs md:text-sm uppercase tracking-wider">Saldo Bersih (Kas)</span>
                    <span class="font-black text-base md:text-lg <?= $saldo_bersih >= 0 ? 'text-blue-700' : 'text-red-600' ?>">Rp <?= number_format($saldo_bersih, 0, ',', '.') ?></span>
                </div>
            </div>
            
            <div class="border-2 border-gray-200 rounded-lg p-4 md:p-5">
                <h3 class="text-[11px] md:text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 md:mb-4 border-b pb-2">Statistik Properti</h3>
                <div class="flex justify-between text-xs md:text-sm mb-2">
                    <span class="font-semibold text-gray-600">Kapasitas Pintu</span>
                    <span class="font-bold text-gray-800"><?= $tot_kmr ?> Kamar</span>
                </div>
                <div class="flex justify-between text-xs md:text-sm mb-2">
                    <span class="font-semibold text-gray-600">Kamar Terisi</span>
                    <span class="font-bold text-green-600"><?= $isi_kmr ?> Kamar</span>
                </div>
                <div class="flex justify-between text-xs md:text-sm mb-3">
                    <span class="font-semibold text-gray-600">Kamar Kosong</span>
                    <span class="font-bold text-red-600"><?= $ksg_kmr ?> Kamar</span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 mt-2">
                    <span class="font-black text-gray-800 text-base md:text-lg uppercase">Okupansi</span>
                    <span class="font-black text-base md:text-lg text-blue-700"><?= $persentase ?>%</span>
                </div>
            </div>
        </div>

        <h3 class="text-[11px] md:text-sm font-bold text-gray-800 uppercase tracking-widest mb-3 border-b-2 border-red-500 inline-block pb-1">Beban Operasional & Pengeluaran</h3>
        <div class="overflow-x-auto print:overflow-visible w-full mb-8 md:mb-10">
            <table class="w-full text-left border border-gray-300 min-w-[500px] md:min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-2 px-3 md:px-4 text-[11px] md:text-xs font-bold text-gray-700 border-b border-gray-300 w-12 md:w-16 text-center">No</th>
                        <th class="py-2 px-3 md:px-4 text-[11px] md:text-xs font-bold text-gray-700 border-b border-gray-300">Kategori Biaya</th>
                        <th class="py-2 px-3 md:px-4 text-[11px] md:text-xs font-bold text-gray-700 border-b border-gray-300 text-right">Total Penyerapan (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xs md:text-sm">
                    <?php if (empty($rekap_pengeluaran)): ?>
                        <tr><td colspan="3" class="py-4 text-center text-gray-500 italic">Nihil / Tidak ada pengeluaran operasional di bulan ini.</td></tr>
                    <?php else: ?>
                        <?php $no=1; foreach ($rekap_pengeluaran as $out): ?>
                        <tr>
                            <td class="py-2 px-3 md:px-4 text-center text-gray-500"><?= $no++ ?></td>
                            <td class="py-2 px-3 md:px-4 font-semibold text-gray-700"><?= htmlspecialchars($out['kategoripengeluaran']) ?></td>
                            <td class="py-2 px-3 md:px-4 text-right font-bold text-red-600"><?= number_format($out['total_biaya'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-300">
                    <tr>
                        <td colspan="2" class="py-2 px-3 md:px-4 text-right text-[10px] md:text-xs uppercase tracking-wider">Total Beban Operasional:</td>
                        <td class="py-2 px-3 md:px-4 text-right text-red-700 text-xs md:text-sm">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <h3 class="text-[11px] md:text-sm font-bold text-gray-800 uppercase tracking-widest mb-3 border-b-2 border-green-500 inline-block pb-1">Rincian Transaksi Sewa (Omset & Kas)</h3>
        <div class="overflow-x-auto print:overflow-visible w-full mb-8 md:mb-10">
            <table class="w-full text-left border border-gray-300 text-xs md:text-sm min-w-[600px] md:min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-2 px-3 md:px-4 text-[11px] md:text-xs font-bold text-gray-700 border-b border-gray-300">Tanggal Trx</th>
                        <th class="py-2 px-3 md:px-4 text-[11px] md:text-xs font-bold text-gray-700 border-b border-gray-300">Penyewa</th>
                        <th class="py-2 px-3 md:px-4 text-[11px] md:text-xs font-bold text-gray-700 border-b border-gray-300 text-right">Tagihan & Status</th>
                        <th class="py-2 px-3 md:px-4 text-[11px] md:text-xs font-bold text-gray-700 border-b border-gray-300 text-right">Kas Diterima (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($rincian_pemasukan)): ?>
                        <tr><td colspan="4" class="py-4 text-center text-gray-500 italic">Nihil / Tidak ada transaksi masuk di bulan ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rincian_pemasukan as $in): 
                            $tagihan = $in['jumlahtransaksi'] - $in['diskontransaksi'] + $in['jumlah_charge'];
                            $kurang = $tagihan - $in['jumlah_bayar'];
                            $badge = ($in['status_bayar'] === 'Lunas') ? 'bg-green-100 text-green-700 border-green-200' : 'bg-orange-100 text-orange-700 border-orange-200';
                        ?>
                        <tr>
                            <td class="py-2 px-3 md:px-4 text-gray-600"><?= date('d/m/Y', strtotime($in['tanggaltransaksi'])) ?></td>
                            <td class="py-2 px-3 md:px-4">
                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($in['namacustomer']) ?></span><br>
                                <span class="text-[10px] md:text-xs text-gray-500">Kamar <?= htmlspecialchars($in['nomor_kamar']) ?></span>
                            </td>
                            <td class="py-2 px-3 md:px-4 text-right">
                                <span class="border px-1 py-0.5 rounded text-[9px] md:text-[10px] font-bold uppercase tracking-wider <?= $badge ?> float-left mt-0.5"><?= $in['status_bayar'] ?></span>
                                <span class="font-bold text-gray-700"><?= number_format($tagihan, 0, ',', '.') ?></span>
                            </td>
                            <td class="py-2 px-3 md:px-4 text-right">
                                <span class="font-bold text-green-600"><?= number_format($in['jumlah_bayar'], 0, ',', '.') ?></span>
                                <?php if($kurang > 0): ?><br><span class="text-[10px] text-orange-500 font-bold">Krg: <?= number_format($kurang, 0, ',', '.') ?></span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-300">
                    <tr>
                        <td colspan="2" class="py-2 px-3 md:px-4 text-right text-[10px] md:text-xs uppercase tracking-wider">Total Pembukuan:</td>
                        <td class="py-2 px-3 md:px-4 text-right text-gray-700 text-xs md:text-sm">Tagihan: Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
                        <td class="py-2 px-3 md:px-4 text-right text-green-700 text-xs md:text-sm">Riil: Rp <?= number_format($total_pemasukan_riil, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-12 md:mt-16 flex justify-end">
            <div class="text-center w-48 md:w-64">
                <p class="text-xs md:text-sm font-semibold text-gray-600 mb-16 md:mb-20">Pontianak, <?= date('d F Y') ?></p>
                <div class="border-b border-gray-800 w-36 md:w-48 mx-auto mb-1"></div>
                <p class="text-[10px] md:text-xs text-gray-500 font-bold tracking-wider uppercase mt-1">Manager / Pengelola</p>
            </div>
        </div>

    </div>
</body>
</html>