<?php
require 'koneksi.php';
require 'header.php'; // Menggunakan header standar Anda

// Ambil data Kost untuk Dropdown Filter
$stmt_kost = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$list_kost = $stmt_kost->fetchAll(PDO::FETCH_ASSOC);

// Inisialisasi Filter
$filter_tipe = $_GET['tipe_waktu'] ?? 'bulan';
$filter_bulan = $_GET['bulan'] ?? date('Y-m');
$filter_start = $_GET['start_date'] ?? date('Y-m-01');
$filter_end = $_GET['end_date'] ?? date('Y-m-t');
$filter_kost = $_GET['id_kost'] ?? '';

// Kalkulasi Tanggal Awal dan Akhir berdasarkan Tipe Filter
if ($filter_tipe === 'bulan') {
    $date_start = $filter_bulan . '-01';
    $date_end = date('Y-m-t', strtotime($date_start));
    $periode_teks = "Bulan " . date('F Y', strtotime($date_start));
} else {
    $date_start = $filter_start;
    $date_end = $filter_end;
    $periode_teks = date('d/m/Y', strtotime($date_start)) . " - " . date('d/m/Y', strtotime($date_end));
}

// Logika Query: Mencari transaksi yang masa sewanya beririsan dengan periode filter
// (Mulai sewa <= Akhir Periode AND Habis sewa >= Awal Periode)
$query = "
    SELECT c.namacustomer, c.nikcustomer, c.nohpcustomer, 
           k.nomor_kamar, ko.nama_kost, 
           t.mulaisewa, t.habissewa, t.status_bayar
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE t.mulaisewa <= ? AND t.habissewa >= ?
";
$params = [$date_end, $date_start];

// Tambahkan filter lokasi kost jika dipilih
$nama_kost_teks = "Semua Lokasi Kost";
if (!empty($filter_kost)) {
    $query .= " AND ko.id_kost = ?";
    $params[] = $filter_kost;
    
    // Cari nama kost untuk judul laporan
    foreach($list_kost as $k) {
        if($k['id_kost'] == $filter_kost) $nama_kost_teks = $k['nama_kost'];
    }
}

$query .= " ORDER BY ko.nama_kost ASC, k.nomor_kamar ASC";

$stmt = $koneksi->prepare($query);
$stmt->execute($params);
$data_tamu = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
    @media print {
        body { background-color: #fff; }
        .print-hidden { display: none !important; }
        .print-area { width: 100% !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important;}
        @page { size: A4 portrait; margin: 15mm; }
    }
</style>

<div class="pb-24 max-w-6xl mx-auto">
    <div class="print-hidden mb-6 flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Laporan Daftar Tamu</h2>
            <p class="text-sm text-gray-500 mt-1">Filter dan cetak daftar penghuni aktif berdasarkan lokasi dan waktu.</p>
        </div>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow-md transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak Laporan
        </button>
    </div>

    <div class="print-hidden bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6">
        <form action="" method="GET" class="flex flex-col md:flex-row gap-4 items-end">
            <div class="w-full md:w-auto min-w-[200px]">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Lokasi Kost</label>
                <select name="id_kost" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white text-sm">
                    <option value="">Semua Lokasi</option>
                    <?php foreach($list_kost as $k): ?>
                        <option value="<?= $k['id_kost'] ?>" <?= $filter_kost == $k['id_kost'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kost']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="w-full md:w-auto">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Metode Filter</label>
                <select name="tipe_waktu" id="tipe_waktu" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white text-sm" onchange="toggleWaktu()">
                    <option value="bulan" <?= $filter_tipe == 'bulan' ? 'selected' : '' ?>>Per Bulan</option>
                    <option value="rentang" <?= $filter_tipe == 'rentang' ? 'selected' : '' ?>>Rentang Tanggal</option>
                </select>
            </div>

            <div id="wrap_bulan" class="w-full md:w-auto <?= $filter_tipe == 'bulan' ? 'block' : 'hidden' ?>">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pilih Bulan</label>
                <input type="month" name="bulan" value="<?= $filter_bulan ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white text-sm">
            </div>

            <div id="wrap_rentang" class="w-full md:w-auto <?= $filter_tipe == 'rentang' ? 'flex' : 'hidden' ?> gap-2">
                <div class="w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dari</label>
                    <input type="date" name="start_date" value="<?= $filter_start ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white text-sm">
                </div>
                <div class="w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sampai</label>
                    <input type="date" name="end_date" value="<?= $filter_end ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white text-sm">
                </div>
            </div>

            <button type="submit" class="w-full md:w-auto bg-gray-800 text-white px-6 py-2 rounded font-bold text-sm hover:bg-gray-900 transition-colors">Terapkan</button>
        </form>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 print-area">
        
        <div class="text-center mb-8 border-b-2 border-gray-800 pb-4">
            <h1 class="text-2xl font-black text-gray-900 uppercase tracking-wider">DAFTAR PENGHUNI KOST AKTIF</h1>
            <p class="text-lg font-bold text-gray-700 mt-1"><?= htmlspecialchars($nama_kost_teks) ?></p>
            <p class="text-sm text-gray-500 mt-1">Periode: <?= $periode_teks ?></p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 border-y-2 border-gray-400">
                        <th class="py-3 px-2 text-xs font-bold text-gray-800 uppercase text-center w-10">No</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase">Nama Penghuni / Kontak</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase">Properti & Kamar</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase text-center">Periode Sewa</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase text-center">Status Bayar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    <?php 
                    $no = 1;
                    foreach ($data_tamu as $tamu): 
                        $status_class = $tamu['status_bayar'] == 'Lunas' ? 'text-green-700' : 'text-red-600 font-bold';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-2 text-sm text-gray-800 text-center"><?= $no++ ?></td>
                        <td class="py-3 px-4">
                            <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($tamu['namacustomer']) ?></p>
                            <p class="text-[11px] text-gray-500 mt-0.5">NIK: <?= htmlspecialchars($tamu['nikcustomer']) ?> | HP: <?= htmlspecialchars($tamu['nohpcustomer']) ?></p>
                        </td>
                        <td class="py-3 px-4">
                            <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($tamu['nama_kost']) ?></p>
                            <p class="text-[12px] font-semibold text-gray-600 mt-0.5">Kamar <?= htmlspecialchars($tamu['nomor_kamar']) ?></p>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <p class="text-[12px] text-gray-800"><?= date('d/m/Y', strtotime($tamu['mulaisewa'])) ?> - <span class="font-bold text-blue-700"><?= date('d/m/Y', strtotime($tamu['habissewa'])) ?></span></p>
                        </td>
                        <td class="py-3 px-4 text-center text-sm <?= $status_class ?>">
                            <?= htmlspecialchars($tamu['status_bayar']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($data_tamu)): ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-gray-500 font-medium">Tidak ada data penghuni aktif pada periode dan lokasi ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-8 flex justify-end text-sm text-gray-700">
            <div class="text-center">
                <p>Dicetak pada: <?= date('d M Y, H:i') ?></p>
                <p class="mt-16 font-bold underline">Admin KostSun</p>
            </div>
        </div>

    </div>
</div>

<script>
function toggleWaktu() {
    const tipe = document.getElementById('tipe_waktu').value;
    if (tipe === 'bulan') {
        document.getElementById('wrap_bulan').classList.remove('hidden');
        document.getElementById('wrap_bulan').classList.add('block');
        document.getElementById('wrap_rentang').classList.remove('flex');
        document.getElementById('wrap_rentang').classList.add('hidden');
    } else {
        document.getElementById('wrap_bulan').classList.remove('block');
        document.getElementById('wrap_bulan').classList.add('hidden');
        document.getElementById('wrap_rentang').classList.remove('hidden');
        document.getElementById('wrap_rentang').classList.add('flex');
    }
}
</script>

<?php require 'footer.php'; ?>