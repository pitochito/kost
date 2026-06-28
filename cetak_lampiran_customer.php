<?php
session_start();
require 'koneksi.php';

// Data Array Referensi Bulan
$nama_bulan_arr = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$tahun_sekarang = date('Y');

// Ambil data Kost untuk Dropdown Filter
$stmt_kost_db = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$list_kost = $stmt_kost_db->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// TANGKAP PARAMETER FILTER (2 Langkah Waktu)
// ==========================================
$filter_kost = $_GET['id_kost'] ?? '';
$filter_tipe = $_GET['tipe_waktu'] ?? 'bulan';
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_start = $_GET['start_date'] ?? date('Y-m-01');
$filter_end = $_GET['end_date'] ?? date('Y-m-t');

// Kalkulasi Tanggal Awal dan Akhir berdasarkan Tipe Filter Waktu
if ($filter_tipe === 'bulan') {
    $date_start = $filter_tahun . '-' . $filter_bulan . '-01';
    $date_end = date('Y-m-t', strtotime($date_start));
    $periode_teks = "Bulan " . $nama_bulan_arr[$filter_bulan] . " " . $filter_tahun;
} else {
    $date_start = $filter_start;
    $date_end = $filter_end;
    $periode_teks = date('d/m/Y', strtotime($date_start)) . " s/d " . date('d/m/Y', strtotime($date_end));
}

// Set Nama Lokasi Kost untuk Header
$nama_kost_teks = "SEMUA LOKASI (GLOBAL)";
if (!empty($filter_kost)) {
    foreach($list_kost as $k) {
        if($k['id_kost'] == $filter_kost) {
            $nama_kost_teks = strtoupper($k['nama_kost']);
            break;
        }
    }
}

// ==========================================
// QUERY DATA CUSTOMER BERDASARKAN PERIODE & LOKASI
// (Syarat: Mulai sewa <= batas akhir AND Habis sewa >= batas awal)
// ==========================================
$query = "
    SELECT c.*, ko.nama_kost, k.nomor_kamar, t.mulaisewa, t.habissewa 
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE t.mulaisewa <= ? AND t.habissewa >= ?
";
$params = [$date_end, $date_start];

if (!empty($filter_kost)) {
    $query .= " AND ko.id_kost = ?";
    $params[] = $filter_kost;
}

$query .= " ORDER BY ko.nama_kost ASC, k.nomor_kamar ASC";

$stmt = $koneksi->prepare($query);
$stmt->execute($params);
$data_customer = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Lampiran Customer - Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Desain Khusus Print */
        @media print {
            body { background-color: #fff !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .print-border { border: none !important; padding: 0 !important; }
            .print-shadow-none { box-shadow: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            
            /* Portrait dengan Margin kecil untuk memaksimalkan ruang */
            @page { size: A4 portrait; margin: 10mm; }
            
            /* Kunci lebar tabel agar tidak tembus/terpotong di luar batas margin A4 */
            table { table-layout: fixed; width: 100%; }
            th, td { word-wrap: break-word; overflow-wrap: break-word; }
            
            .baris-tamu { page-break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-gray-100 p-2 sm:p-4 md:p-8 min-h-screen text-gray-800 font-sans">

    <div class="max-w-5xl mx-auto mb-6 md:mb-8 bg-white p-4 md:p-6 rounded-lg shadow-md border-t-4 border-blue-600 no-print">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h2 class="text-lg md:text-xl font-bold text-gray-800">Filter Cetak Lampiran</h2>
            <a href="customer.php" class="text-xs md:text-sm font-semibold text-gray-500 hover:text-black">&larr; Kembali</a>
        </div>
        
        <form action="" method="GET" class="flex flex-col gap-4">
            <div class="flex flex-col md:flex-row gap-4 items-end">
                <div class="w-full md:w-1/3">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Lokasi Kost</label>
                    <select name="id_kost" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">Semua Lokasi / Global</option>
                        <?php foreach($list_kost as $k): ?>
                            <option value="<?= $k['id_kost'] ?>" <?= $filter_kost == $k['id_kost'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kost']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="w-full md:w-1/4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Metode Filter</label>
                    <select name="tipe_waktu" id="tipe_waktu" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white" onchange="toggleWaktu()">
                        <option value="bulan" <?= $filter_tipe == 'bulan' ? 'selected' : '' ?>>Bulan Aktif</option>
                        <option value="rentang" <?= $filter_tipe == 'rentang' ? 'selected' : '' ?>>Rentang Tanggal</option>
                    </select>
                </div>
                
                <div id="wrap_bulan" class="w-full md:w-auto flex gap-2 <?= $filter_tipe == 'bulan' ? 'flex' : 'hidden' ?>">
                    <div class="w-1/2 md:w-auto">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
                        <select name="bulan" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                            <?php foreach($nama_bulan_arr as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $filter_bulan == $num ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-1/2 md:w-auto">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
                        <select name="tahun" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                            <?php for($y = 2023; $y <= $tahun_sekarang + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div id="wrap_rentang" class="w-full md:w-auto <?= $filter_tipe == 'rentang' ? 'flex' : 'hidden' ?> gap-2">
                    <div class="w-1/2 md:w-auto">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Mulai</label>
                        <input type="date" name="start_date" value="<?= $filter_start ?>" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                    </div>
                    <div class="w-1/2 md:w-auto">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sampai</label>
                        <input type="date" name="end_date" value="<?= $filter_end ?>" class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white">
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 mt-2 w-full justify-end">
                <button type="submit" class="w-full sm:w-auto bg-gray-800 hover:bg-black text-white px-6 py-2 rounded font-bold transition-colors">Terapkan Data</button>
                <button type="button" onclick="window.print()" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-bold shadow-md flex justify-center items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg> Cetak Dokumen
                </button>
            </div>
        </form>
    </div>
    <div class="max-w-5xl mx-auto bg-white p-4 sm:p-8 md:p-12 shadow-lg print-shadow-none print-border rounded">
        
        <div class="flex border-b-4 border-gray-900 pb-4 mb-6 md:mb-8 items-center gap-4">
            <div class="flex-1">
                <h1 class="text-xl md:text-3xl font-black text-gray-900 tracking-tight uppercase">LAMPIRAN DATA CUSTOMER</h1>
                <p class="text-[10px] md:text-sm font-semibold text-gray-600 mt-1 uppercase tracking-widest"><?= $nama_kost_teks ?></p>
                <p class="text-[10px] md:text-xs text-gray-500 mt-1">Dicetak pada: <?= date('d/m/Y H:i') ?></p>
            </div>
            <div class="text-right border-l-2 border-gray-200 pl-4">
                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1">Periode Laporan</p>
                <p class="text-sm md:text-lg font-black text-blue-800 uppercase"><?= $periode_teks ?></p>
            </div>
        </div>

        <div class="w-full">
            <table class="w-full text-left border-collapse border border-gray-400" style="table-layout: fixed;">
                <thead class="bg-gray-100 border-b-2 border-gray-400">
                    <tr>
                        <th class="py-2 px-1 sm:px-2 text-[9px] sm:text-[10px] font-bold text-gray-800 uppercase text-center w-[5%] border-r border-gray-300">No</th>
                        <th class="py-2 px-1 sm:px-2 text-[9px] sm:text-[10px] font-bold text-gray-800 uppercase text-center w-[25%] border-r border-gray-300">Lampiran KTP</th>
                        <th class="py-2 px-1 sm:px-2 text-[9px] sm:text-[10px] font-bold text-gray-800 uppercase w-[30%] border-r border-gray-300">Data Diri & Asal</th>
                        <th class="py-2 px-1 sm:px-2 text-[9px] sm:text-[10px] font-bold text-gray-800 uppercase w-[20%] border-r border-gray-300">Kontak Darurat</th>
                        <th class="py-2 px-1 sm:px-2 text-[9px] sm:text-[10px] font-bold text-gray-800 uppercase w-[20%]">Properti & Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    <?php if(count($data_customer) > 0): ?>
                        <?php $no = 1; foreach ($data_customer as $cust): ?>
                        <tr class="baris-tamu">
                            <td class="py-3 px-1 sm:px-2 text-[10px] font-bold text-gray-800 text-center align-top border-r border-gray-300"><?= $no++ ?></td>
                            
                            <td class="py-3 px-1 sm:px-2 align-top text-center border-r border-gray-300">
                                <?php if (!empty($cust['fotoktpcustomer']) && file_exists('uploads/' . $cust['fotoktpcustomer'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($cust['fotoktpcustomer']) ?>" alt="KTP" class="w-full max-w-[120px] shadow-sm rounded border border-gray-300 mx-auto object-contain">
                                <?php else: ?>
                                    <div class="bg-gray-100 text-gray-400 border border-dashed border-gray-300 rounded p-2 text-[9px] font-semibold h-12 flex items-center justify-center">
                                        [KTP Tdk Ada]
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="py-3 px-1 sm:px-2 align-top border-r border-gray-300">
                                <p class="text-[10px] sm:text-xs font-black text-gray-900 uppercase leading-tight"><?= htmlspecialchars($cust['namacustomer']) ?></p>
                                <p class="text-[9px] font-mono font-bold text-gray-700 mt-0.5 mb-1.5 border-b pb-1">NIK: <?= htmlspecialchars($cust['nikcustomer']) ?></p>
                                
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-wide">Alamat Asal KTP:</p>
                                <p class="text-[9px] sm:text-[10px] text-gray-800 leading-tight mt-0.5 break-words">
                                    <?= htmlspecialchars($cust['alamatcustomer']) ?><br>
                                    <span class="font-semibold text-gray-600"><?= htmlspecialchars($cust['kotaasalcustomer']) ?></span>
                                </p>
                            </td>

                            <td class="py-3 px-1 sm:px-2 align-top border-r border-gray-300">
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-wide">Nomor Handphone:</p>
                                <p class="text-[9px] sm:text-[10px] font-bold text-gray-900 mb-1.5 border-b pb-1"><?= htmlspecialchars($cust['nohpcustomer']) ?: '-' ?></p>
                                
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-wide">Kontak Darurat:</p>
                                <p class="text-[9px] sm:text-[10px] text-gray-800 leading-tight mt-0.5 break-words">
                                    <?= htmlspecialchars($cust['namakontakdarurat']) ?: 'Tidak Ada' ?><br>
                                    <span class="font-semibold text-gray-600"><?= htmlspecialchars($cust['kontakdarurat']) ?: '-' ?></span>
                                </p>
                            </td>

                            <td class="py-3 px-1 sm:px-2 align-top">
                                <?php if (!empty($cust['nama_kost'])): ?>
                                    <p class="text-[9px] font-bold text-gray-800 bg-gray-100 p-0.5 rounded inline-block border border-gray-200 mb-1 leading-tight break-words">
                                        <?= htmlspecialchars($cust['nama_kost']) ?>
                                    </p>
                                    <p class="text-[10px] font-black text-gray-900 mb-1.5 ml-0.5">Kamar <?= htmlspecialchars($cust['nomor_kamar'] ?? '-') ?></p>
                                <?php else: ?>
                                    <p class="text-[9px] font-bold text-gray-800 bg-gray-100 p-1 rounded inline-block border mb-1.5">Kosong</p>
                                <?php endif; ?>
                                
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-wide">Periode Sewa:</p>
                                <?php if (!empty($cust['mulaisewa'])): ?>
                                    <p class="text-[9px] text-gray-800 mt-0.5 font-medium leading-tight">
                                        Mulai: <span class="text-green-700"><?= date('d M Y', strtotime($cust['mulaisewa'])) ?></span><br>
                                        Habis: <span class="text-red-600"><?= date('d M Y', strtotime($cust['habissewa'])) ?></span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-[9px] text-gray-400 mt-0.5 italic">Tidak diketahui</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-500 text-xs font-medium bg-gray-50">Tidak ada penyewa yang aktif pada rentang waktu dan lokasi ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        function toggleWaktu() {
            const tipe = document.getElementById('tipe_waktu').value;
            if (tipe === 'bulan') {
                document.getElementById('wrap_bulan').classList.remove('hidden');
                document.getElementById('wrap_bulan').classList.add('flex');
                document.getElementById('wrap_rentang').classList.remove('flex');
                document.getElementById('wrap_rentang').classList.add('hidden');
            } else {
                document.getElementById('wrap_bulan').classList.remove('flex');
                document.getElementById('wrap_bulan').classList.add('hidden');
                document.getElementById('wrap_rentang').classList.remove('hidden');
                document.getElementById('wrap_rentang').classList.add('flex');
            }
        }
    </script>
</body>
</html>