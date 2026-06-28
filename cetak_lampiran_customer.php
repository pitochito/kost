<?php
session_start();
require 'koneksi.php';

// Pastikan yang akses sudah login (sesuaikan dengan logic auth Anda)
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

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
$query = "
    SELECT DISTINCT c.*, ko.nama_kost, k.nomor_kamar 
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
        /* Pengaturan Khusus Print (Cetak) */
        @media print {
            @page { 
                margin: 1cm;
                size: landscape; /* Kertas format Horizontal / Landscape agar muat banyak kolom */
            }
            body { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
            .no-print { 
                display: none !important; 
            }
        }
        
        /* Table border styling untuk print agar solid & profesional */
        table, th, td {
            border: 1px solid #4a5568 !important; /* gray-700 */
        }
    </style>
</head>
<body class="bg-white text-gray-900 p-8 font-sans text-sm">

    <div class="no-print mb-6 flex gap-4 border-b pb-4 border-gray-200">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">Cetak / Print Ulang</button>
        <button onclick="window.close()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded">Tutup Halaman</button>
    </div>

    <div class="text-center mb-6">
        <h1 class="text-xl font-bold uppercase tracking-wider">Lampiran Data Customer</h1>
        <p class="text-md font-semibold text-gray-700 mt-1">Dicetak pada: <?= date('d-m-Y H:i') ?></p>
        
        <?php if(!empty($status) || !empty($filter_kost)): ?>
        <p class="text-sm text-gray-600 mt-1 italic">
            Filter diterapkan: 
            <?= !empty($status) ? "Status - " . htmlspecialchars($status) : "" ?>
            <?= (!empty($status) && !empty($filter_kost)) ? " | " : "" ?>
            <?= !empty($filter_kost) ? "Lokasi Kost Tertentu" : "" ?>
        </p>
        <?php endif; ?>
    </div>

    <table class="w-full text-left border-collapse">
        <thead class="bg-gray-100">
            <tr>
                <th class="py-2 px-3 text-center w-10">No</th>
                <th class="py-2 px-3 w-48">Identitas Penyewa</th>
                <th class="py-2 px-3 w-40">Kontak</th>
                <th class="py-2 px-3">Asal & Alamat Domisili</th>
                <th class="py-2 px-3 w-32">Kamar & Status</th>
                <th class="py-2 px-3 w-40 text-center">Lampiran KTP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-300">
            <?php if(count($data_customer) > 0): ?>
                <?php $no = 1; foreach ($data_customer as $cust): ?>
                <tr>
                    <td class="py-3 px-3 text-center align-middle font-bold"><?= $no++ ?></td>
                    <td class="py-3 px-3 align-middle">
                        <div class="font-bold"><?= htmlspecialchars($cust['namacustomer']) ?></div>
                        <div class="text-xs mt-1">NIK: <?= htmlspecialchars($cust['nikcustomer']) ?></div>
                    </td>
                    <td class="py-3 px-3 align-middle">
                        <div><?= htmlspecialchars($cust['nohpcustomer']) ?: '-' ?></div>
                        <div class="text-xs mt-1 text-gray-600">Darurat: <?= htmlspecialchars($cust['namakontakdarurat']) ?></div>
                    </td>
                    <td class="py-3 px-3 align-middle">
                        <div class="font-bold text-xs"><?= htmlspecialchars($cust['kotaasalcustomer']) ?></div>
                        <div class="text-xs mt-1"><?= htmlspecialchars($cust['alamatcustomer']) ?></div>
                    </td>
                    <td class="py-3 px-3 align-middle">
                        <?php if (strtolower($cust['statuscustomer']) == 'aktif'): ?>
                            <div class="font-bold">Kmr <?= htmlspecialchars($cust['nomor_kamar'] ?? '-') ?></div>
                            <div class="text-xs mt-1 text-gray-600"><?= htmlspecialchars($cust['nama_kost'] ?? '-') ?></div>
                        <?php else: ?>
                            <div class="italic text-gray-500 text-xs">Tidak Aktif</div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-3 align-middle text-center">
                        <?php 
                        $foto = $cust['fotoktpcustomer'];
                        $path = 'uploads/' . $foto;
                        
                        // Cek apakah file fisik KTP benar-benar ada
                        if (!empty($foto) && file_exists($path)): ?>
                            <img src="<?= $path ?>" alt="KTP" style="max-width: 120px; height: auto;" class="rounded mx-auto block shadow-sm border border-gray-300">
                        <?php else: ?>
                            <span class="text-xs italic text-gray-400">Tidak ada KTP</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center py-6 font-bold text-gray-500">
                        Tidak ada data customer yang sesuai untuk dicetak.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        // Membuka dialog print secara otomatis ketika halaman selesai di-load
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500); // Jeda setengah detik agar gambar ter-load dengan baik
        };
    </script>
</body>
</html>