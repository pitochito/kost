<?php
require 'koneksi.php';

// ==========================================
// MENANGKAP FILTER DARI URL
// ==========================================
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$filter_kost = $_GET['filter_kost'] ?? '';

$where_arr = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_arr[] = "(
        c.namacustomer LIKE ? OR 
        c.nikcustomer LIKE ? OR 
        c.nohpcustomer LIKE ? OR 
        c.alamatcustomer LIKE ? OR 
        c.kotaasalcustomer LIKE ?
    )";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param);
}

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
// Menambahkan t.mulaisewa dan t.habissewa untuk menyesuaikan format tabel baru
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
    <title>Lampiran Daftar Customer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Desain Khusus Print sesuai format yang diminta */
        @media print {
            body { background-color: #fff; }
            .no-print { display: none !important; }
            .print-area { width: 100% !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important;}
            
            /* Set kertas mendatar (Landscape) agar KTP dan detail terbaca lega */
            @page { size: A4 landscape; margin: 15mm; }
            
            /* Mencegah satu baris terpotong di tengah halaman Kertas */
            .baris-tamu { page-break-inside: avoid; }
            
            /* Memastikan gambar KTP tercetak jelas */
            .ktp-print { max-width: 220px !important; height: auto !important; object-fit: contain; border: 1px solid #ccc; border-radius: 4px; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 p-8 font-sans">

    <div class="no-print mb-6 flex gap-4 border-b pb-4 border-gray-200 max-w-[1400px] mx-auto">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow-md transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak Dokumen
        </button>
        <button onclick="window.close()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded transition-colors">
            Tutup Halaman
        </button>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 print-area max-w-[1400px] mx-auto">
        
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
                        <th class="py-3 px-2 text-xs font-bold text-gray-800 uppercase text-center w-8">No</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase text-center w-64">Lampiran Foto KTP</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase">Data Diri & Asal</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase">Kontak Darurat</th>
                        <th class="py-3 px-4 text-xs font-bold text-gray-800 uppercase">Properti & Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                    <?php if(count($data_customer) > 0): ?>
                        <?php $no = 1; foreach ($data_customer as $cust): ?>
                        <tr class="baris-tamu hover:bg-gray-50">
                            <td class="py-4 px-2 text-sm font-bold text-gray-800 text-center align-top"><?= $no++ ?></td>
                            
                            <td class="py-4 px-4 align-top text-center">
                                <?php if (!empty($cust['fotoktpcustomer']) && file_exists('uploads/' . $cust['fotoktpcustomer'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($cust['fotoktpcustomer']) ?>" alt="KTP" class="ktp-print max-w-[220px] shadow-sm rounded border border-gray-300 mx-auto">
                                <?php else: ?>
                                    <div class="bg-gray-100 text-gray-400 border border-dashed border-gray-300 rounded p-4 text-xs font-semibold h-24 flex items-center justify-center">
                                        [Foto KTP Tidak Terlampir]
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="py-4 px-4 align-top">
                                <p class="text-sm font-black text-gray-900 uppercase"><?= htmlspecialchars($cust['namacustomer']) ?></p>
                                <p class="text-[12px] font-mono font-bold text-gray-700 mt-1 mb-2 border-b pb-1">NIK: <?= htmlspecialchars($cust['nikcustomer']) ?></p>
                                
                                <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wide">Alamat Asal KTP:</p>
                                <p class="text-[12px] text-gray-800 leading-tight mt-0.5">
                                    <?= htmlspecialchars($cust['alamatcustomer']) ?><br>
                                    <span class="font-semibold text-gray-600"><?= htmlspecialchars($cust['kotaasalcustomer']) ?></span>
                                </p>
                            </td>

                            <td class="py-4 px-4 align-top">
                                <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wide">Nomor Handphone:</p>
                                <p class="text-[12px] font-bold text-gray-900 mb-2 border-b pb-1"><?= htmlspecialchars($cust['nohpcustomer']) ?: '-' ?></p>
                                
                                <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wide">Kontak Darurat:</p>
                                <p class="text-[12px] text-gray-800 leading-tight mt-0.5">
                                    <?= htmlspecialchars($cust['namakontakdarurat']) ?: 'Tidak Ada' ?><br>
                                    <span class="font-semibold text-gray-600"><?= htmlspecialchars($cust['kontakdarurat']) ?: '-' ?></span>
                                </p>
                            </td>

                            <td class="py-4 px-4 align-top">
                                <?php if (!empty($cust['nama_kost'])): ?>
                                    <p class="text-sm font-bold text-gray-800 bg-gray-100 p-1.5 rounded inline-block border mb-1">
                                        <?= htmlspecialchars($cust['nama_kost']) ?>
                                    </p>
                                    <p class="text-sm font-black text-gray-900 mb-3 ml-1">Kamar <?= htmlspecialchars($cust['nomor_kamar'] ?? '-') ?></p>
                                <?php else: ?>
                                    <p class="text-sm font-bold text-gray-800 bg-gray-100 p-1.5 rounded inline-block border mb-3">Belum Ada Properti</p>
                                <?php endif; ?>
                                
                                <p class="text-[11px] text-gray-500 font-bold uppercase tracking-wide">Status / Periode:</p>
                                <?php if (strtolower($cust['statuscustomer']) == 'aktif' && !empty($cust['mulaisewa'])): ?>
                                    <p class="text-[12px] text-gray-800 mt-0.5 font-medium">
                                        Mulai: <span class="text-green-700"><?= date('d M Y', strtotime($cust['mulaisewa'])) ?></span><br>
                                        Habis: <span class="text-red-600"><?= date('d M Y', strtotime($cust['habissewa'])) ?></span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-[12px] text-red-600 mt-0.5 font-bold uppercase">Tidak Aktif</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-500 font-medium bg-gray-50">Tidak ada data customer yang sesuai untuk dicetak.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500); 
        };
    </script>
</body>
</html>