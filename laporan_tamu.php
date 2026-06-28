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
    $periode_teks = date('d/m/Y', strtotime($date_start)) . " s.d " . date('d/m/Y', strtotime($date_end));
}

// Query Fokus Identitas (Tanpa Harga/Status Bayar, Memanggil KTP dan Alamat Lengkap)
$query = "
    SELECT c.namacustomer, c.nikcustomer, c.nohpcustomer, c.kotaasalcustomer, 
           c.alamatcustomer, c.fotoktpcustomer, c.namakontakdarurat, c.kontakdarurat,
           k.nomor_kamar, ko.nama_kost, 
           t.mulaisewa, t.habissewa
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
        
        /* Set kertas mendatar (Landscape) agar KTP dan detail terbaca lega */
        @page { size: A4 landscape; margin: 15mm; }
        
        /* Mencegah satu baris terpotong di tengah halaman Kertas */
        .baris-tamu { page-break-inside: avoid; }
        
        /* Memastikan gambar KTP tercetak jelas */
        .ktp-print { max-width: 220px !important; height: auto !important; object-fit: contain; border: 1px solid #ccc; border-radius: 4px; }
    }
</style>

<div class="pb-24 max-w-[1400px] mx-auto">
    <div class="print-hidden mb-6 flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Laporan Tembusan RT/RW</h2>
            <p class="text-sm text-gray-500 mt-1">Cetak daftar penghuni kost beserta lampiran identitas untuk pelaporan lingkungan.</p>
        </div>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow-md transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak Dokumen
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
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Metode Waktu</label>
                <select name="tipe_waktu" id="tipe_waktu" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white text-sm" onchange="toggleWaktu()">
                    <option value="bulan" <?= $filter_tipe == 'bulan' ? 'selected' : '' ?>>Bulan Aktif</option>
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
            <h1 class="text-2xl font-black text-gray-900 uppercase tracking-wider">DAFTAR PENGHUNI & LAMPIRAN IDENTITAS</h1>
            <p class="text-lg font-bold text-gray-700 mt-1"><?= htmlspecialchars($nama_kost_teks) ?></p>
            <p class="text-sm text-gray-500 mt-1">Periode Laporan: <?= $periode_teks ?></p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 border-y-2 border-gray-400">
                        <th class="py-3 px-2 text-xs font-bold text-gray-800 uppercase text-center w-8">No</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase text-center w-64">Lampiran Foto KTP</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase">Data Diri & Asal</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase">Kontak Darurat</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase">Properti & Masa Tinggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    <?php 
                    $no = 1;
                    foreach ($data_tamu as $tamu): 
                    ?>
                    <tr class="baris-tamu hover:bg-gray-50">
                        <td class="py-4 px-2 text-sm font-bold text-gray-800 text-center align-top"><?= $no++ ?></td>
                        
                        <td class="py-4 px-4 align-top text-center">
                            <?php if (!empty($tamu['fotoktpcustomer']) && file_exists('uploads/' . $tamu['fotoktpcustomer'])): ?>
                                <img src="uploads/<?= htmlspecialchars($tamu['fotoktpcustomer']) ?>" alt="KTP <?= htmlspecialchars($tamu['namacustomer']) ?>" class="ktp-print max-w-[220px] shadow-sm rounded border border-gray-300 mx-auto">
                            <?php else: ?>
                                <div class="bg-gray-100 text-gray-400 border border-dashed border-gray-300 rounded p-4 text-xs font-semibold h-24 flex items-center justify-center">
                                    [Foto KTP Tidak Terlampir]
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="py-4 px-4 align-top">
                            <p class="text-sm font-black text-gray-900 uppercase"><?= htmlspecialchars($tamu['namacustomer']) ?></p>
                            <p class="text-[12px] font-mono font-bold text-gray-700 mt-1 mb-2 border-b pb-1">NIK: <?= htmlspecialchars($tamu['nikcustomer']) ?></p>
                            
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wide">Alamat Asal KTP:</p>
                            <p class="text-[12px] text-gray-800 leading-tight mt-0.5">
                                <?= htmlspecialchars($tamu['alamatcustomer']) ?><br>
                                <span class="font-semibold text-gray-600"><?= htmlspecialchars($tamu['kotaasalcustomer']) ?></span>
                            </p>
                        </td>

                        <td class="py-4 px-4 align-top">
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wide">Nomor Handphone:</p>
                            <p class="text-[12px] font-bold text-gray-900 mb-2 border-b pb-1"><?= htmlspecialchars($tamu['nohpcustomer']) ?: '-' ?></p>
                            
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wide">Kontak Darurat:</p>
                            <p class="text-[12px] text-gray-800 leading-tight mt-0.5">
                                <?= htmlspecialchars($tamu['namakontakdarurat']) ?: 'Tidak Ada' ?><br>
                                <span class="font-semibold text-gray-600"><?= htmlspecialchars($tamu['kontakdarurat']) ?: '-' ?></span>
                            </p>
                        </td>

                        <td class="py-4 px-4 align-top">
                            <p class="text-sm font-bold text-gray-800 bg-gray-100 p-1.5 rounded inline-block border mb-1">
                                <?= htmlspecialchars($tamu['nama_kost']) ?>
                            </p>
                            <p class="text-sm font-black text-gray-900 mb-3 ml-1">Kamar <?= htmlspecialchars($tamu['nomor_kamar']) ?></p>
                            
                            <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wide">Periode Aktif:</p>
                            <p class="text-[12px] text-gray-800 mt-0.5 font-medium">
                                Mulai: <span class="text-green-700"><?= date('d M Y', strtotime($tamu['mulaisewa'])) ?></span><br>
                                Habis: <span class="text-red-600"><?= date('d M Y', strtotime($tamu['habissewa'])) ?></span>
                            </p>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($data_tamu)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-gray-500 font-medium bg-gray-50">Tidak ada data penghuni aktif pada periode dan lokasi ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-12 flex justify-between text-sm text-gray-800 px-8">
            <div class="text-center">
                <p>Mengetahui,</p>
                <p class="font-bold">Ketua RT / Pengurus Setempat</p>
                <p class="mt-20 border-b border-gray-800 w-48 mx-auto"></p>
            </div>
            <div class="text-center">
                <p>Pontianak, <?= date('d M Y') ?></p>
                <p class="font-bold">Pengelola / Admin KostSun</p>
                <p class="mt-20 border-b border-gray-800 w-48 mx-auto"></p>
                <p class="mt-1"><?= !empty($_SESSION['username']) ? htmlspecialchars(ucfirst($_SESSION['username'])) : 'Admin' ?></p>
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