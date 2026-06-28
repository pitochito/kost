<?php
require 'koneksi.php';

// Ambil data Kost untuk Dropdown Filter
$stmt_kost_db = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$list_kost = $stmt_kost_db->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// MENANGKAP FILTER DARI URL
// ==========================================
$status = $_GET['status'] ?? '';
$filter_kost = $_GET['filter_kost'] ?? '';

$where_arr = ["1=1"];
$params = [];

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
// AMBIL KESELURUHAN DATA TANPA LIMIT (UNTUK PRINT)
// ==========================================
$query = "
    SELECT DISTINCT c.*, ko.nama_kost, k.nomor_kamar, t.mulaisewa, t.habissewa 
    FROM table_customer c
    LEFT JOIN table_transaksi t ON c.id_customer = t.id_customer AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = c.id_customer)
    LEFT JOIN table_kamar k ON t.id_kamar = k.id_kamar
    LEFT JOIN table_kost ko ON k.id_kost = ko.id_kost
    $where_clause
    ORDER BY c.namacustomer ASC
";

$stmt = $koneksi->prepare($query);
$stmt->execute($params);
$data_customer = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Lampiran Customer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Desain Khusus Print (Portrait dengan Margin Kecil) */
        @media print {
            body { background-color: #fff; }
            .print-hidden { display: none !important; }
            .print-area { width: 100% !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important;}
            
            /* Kertas tegak (Portrait) dengan margin menyempit agar kolom tidak tertekan */
            @page { size: A4 portrait; margin: 10mm; }
            
            /* Mencegah satu baris terpotong di tengah halaman Kertas */
            .baris-tamu { page-break-inside: avoid; }
            
            /* Memastikan gambar KTP tercetak jelas, namun dikecilkan untuk Portrait */
            .ktp-print { max-width: 140px !important; height: auto !important; object-fit: contain; border: 1px solid #ccc; border-radius: 4px; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans min-h-screen">

    <div class="print-hidden p-6 max-w-[1200px] mx-auto">
        <div class="mb-6 flex flex-col md:flex-row justify-between items-end gap-4">
            <div>
                <a href="customer.php" class="text-blue-600 hover:underline font-semibold text-sm inline-flex items-center gap-1 mb-2">
                    &larr; Kembali ke Data Customer
                </a>
                <h2 class="text-2xl font-bold text-gray-800">Cetak Lampiran Customer</h2>
                <p class="text-sm text-gray-500 mt-1">Pilih filter data yang ingin dicetak, lalu tekan tombol Cetak Dokumen.</p>
            </div>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow-md transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak Dokumen
            </button>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6">
            <form action="" method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="w-full md:w-auto min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status Sewa</label>
                    <select name="status" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white text-sm">
                        <option value="">Semua Status</option>
                        <option value="Aktif" <?= $status == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="Tidak Aktif" <?= $status == 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                    </select>
                </div>
                <div class="w-full md:w-auto min-w-[250px]">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Lokasi Kost</label>
                    <select name="filter_kost" class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 bg-white text-sm">
                        <option value="">Semua Lokasi</option>
                        <?php foreach($list_kost as $k): ?>
                            <option value="<?= $k['id_kost'] ?>" <?= $filter_kost == $k['id_kost'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kost']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="w-full md:w-auto bg-gray-800 text-white px-6 py-2 rounded font-bold text-sm hover:bg-gray-900 transition-colors">Terapkan Filter</button>
            </form>
        </div>
    </div>
    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 print-area max-w-[1200px] mx-auto mb-10">
        
        <div class="text-center mb-8 border-b-2 border-gray-800 pb-4">
            <h1 class="text-2xl font-black text-gray-900 uppercase tracking-wider">LAMPIRAN DATA CUSTOMER</h1>
            <?php if(!empty($filter_kost) || !empty($status)): ?>
                <p class="text-lg font-bold text-gray-700 mt-1">
                    Filter: <?= !empty($status) ? "Status " . htmlspecialchars($status) : "" ?> 
                    <?= (!empty($status) && !empty($filter_kost)) ? " | " : "" ?> 
                    <?= !empty($filter_kost) ? "Lokasi Spesifik" : "" ?>
                </p>
            <?php endif; ?>
            <p class="text-sm text-gray-500 mt-1">Dicetak pada: <?= date('d M Y, H:i') ?></p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 border-y-2 border-gray-400">
                        <th class="py-2 px-2 text-[10px] sm:text-xs font-bold text-gray-800 uppercase text-center w-8">No</th>
                        <th class="py-2 px-2 text-[10px] sm:text-xs font-bold text-gray-800 uppercase text-center w-40">Lampiran Foto KTP</th>
                        <th class="py-2 px-2 text-[10px] sm:text-xs font-bold text-gray-800 uppercase">Data Diri & Asal</th>
                        <th class="py-2 px-2 text-[10px] sm:text-xs font-bold text-gray-800 uppercase w-32">Kontak Darurat</th>
                        <th class="py-2 px-2 text-[10px] sm:text-xs font-bold text-gray-800 uppercase w-32">Properti & Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    <?php if(count($data_customer) > 0): ?>
                        <?php $no = 1; foreach ($data_customer as $cust): ?>
                        <tr class="baris-tamu hover:bg-gray-50">
                            <td class="py-3 px-2 text-xs font-bold text-gray-800 text-center align-top"><?= $no++ ?></td>
                            
                            <td class="py-3 px-2 align-top text-center">
                                <?php if (!empty($cust['fotoktpcustomer']) && file_exists('uploads/' . $cust['fotoktpcustomer'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($cust['fotoktpcustomer']) ?>" alt="KTP" class="ktp-print max-w-[140px] shadow-sm rounded border border-gray-300 mx-auto">
                                <?php else: ?>
                                    <div class="bg-gray-100 text-gray-400 border border-dashed border-gray-300 rounded p-2 text-[10px] font-semibold h-16 flex items-center justify-center">
                                        [Foto KTP Tidak Terlampir]
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="py-3 px-2 align-top">
                                <p class="text-xs font-black text-gray-900 uppercase"><?= htmlspecialchars($cust['namacustomer']) ?></p>
                                <p class="text-[10px] font-mono font-bold text-gray-700 mt-0.5 mb-1 border-b pb-1">NIK: <?= htmlspecialchars($cust['nikcustomer']) ?></p>
                                
                                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wide">Alamat Asal KTP:</p>
                                <p class="text-[10px] text-gray-800 leading-tight mt-0.5">
                                    <?= htmlspecialchars($cust['alamatcustomer']) ?><br>
                                    <span class="font-semibold text-gray-600"><?= htmlspecialchars($cust['kotaasalcustomer']) ?></span>
                                </p>
                            </td>

                            <td class="py-3 px-2 align-top">
                                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wide">Nomor Handphone:</p>
                                <p class="text-[10px] font-bold text-gray-900 mb-1 border-b pb-1"><?= htmlspecialchars($cust['nohpcustomer']) ?: '-' ?></p>
                                
                                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wide">Kontak Darurat:</p>
                                <p class="text-[10px] text-gray-800 leading-tight mt-0.5">
                                    <?= htmlspecialchars($cust['namakontakdarurat']) ?: 'Tidak Ada' ?><br>
                                    <span class="font-semibold text-gray-600"><?= htmlspecialchars($cust['kontakdarurat']) ?: '-' ?></span>
                                </p>
                            </td>

                            <td class="py-3 px-2 align-top">
                                <?php if (!empty($cust['nama_kost'])): ?>
                                    <p class="text-[10px] font-bold text-gray-800 bg-gray-100 p-1 rounded inline-block border mb-1 leading-tight">
                                        <?= htmlspecialchars($cust['nama_kost']) ?>
                                    </p>
                                    <p class="text-[11px] font-black text-gray-900 mb-2 ml-1">Kamar <?= htmlspecialchars($cust['nomor_kamar'] ?? '-') ?></p>
                                <?php else: ?>
                                    <p class="text-[10px] font-bold text-gray-800 bg-gray-100 p-1 rounded inline-block border mb-2">Belum Ada Properti</p>
                                <?php endif; ?>
                                
                                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wide">Status / Periode:</p>
                                <?php if (strtolower($cust['statuscustomer']) == 'aktif' && !empty($cust['mulaisewa'])): ?>
                                    <p class="text-[10px] text-gray-800 mt-0.5 font-medium leading-tight">
                                        Mulai: <span class="text-green-700"><?= date('d M Y', strtotime($cust['mulaisewa'])) ?></span><br>
                                        Habis: <span class="text-red-600"><?= date('d M Y', strtotime($cust['habissewa'])) ?></span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-[10px] text-red-600 mt-0.5 font-bold uppercase">Tidak Aktif</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-500 text-xs font-medium bg-gray-50">Tidak ada data customer yang sesuai untuk dicetak.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>