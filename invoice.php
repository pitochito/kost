<?php
session_start();
require 'koneksi.php';

// Proteksi keamanan
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: keuangan.php");
    exit;
}

$id_transaksi = $_GET['id'];

// Ambil data transaksi secara komprehensif (Customer + Kamar + Kost)
$query = "
    SELECT 
        t.*, 
        c.namacustomer, c.nikcustomer, c.nohpcustomer, c.alamatcustomer, c.kotaasalcustomer,
        k.nomor_kamar, k.jenis_kamar, 
        ko.nama_kost, ko.alamat_kost, ko.kota_kost
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE t.id_transaksi = ?
";
$stmt = $koneksi->prepare($query);
$stmt->execute([$id_transaksi]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Data Transaksi Tidak Ditemukan.</h2><a href='keuangan.php'>Kembali</a></div>";
    exit;
}

// Generate Nomor Invoice Unik
$invoice_number = "INV/" . date('Y/m/d', strtotime($invoice['tanggaltransaksi'])) . "/" . str_pad($invoice['id_transaksi'], 4, '0', STR_PAD_LEFT);

// ==========================================
// KALKULASI KEUANGAN TERBARU
// ==========================================
$harga_dasar = (int)$invoice['jumlahtransaksi']; // Nilai tarif murni (Tarif x Durasi)
$diskon = (int)$invoice['diskontransaksi'];
$charge = (isset($invoice['jumlah_charge'])) ? (int)$invoice['jumlah_charge'] : 0;

$total_tagihan = $harga_dasar - $diskon + $charge;
$telah_dibayar = (isset($invoice['jumlah_bayar'])) ? (int)$invoice['jumlah_bayar'] : $total_tagihan; // Fallback jika data lama

$kurang_bayar = $total_tagihan - $telah_dibayar;
if ($kurang_bayar < 0) $kurang_bayar = 0;

$status_bayar = (isset($invoice['status_bayar'])) ? $invoice['status_bayar'] : (($telah_dibayar >= $total_tagihan) ? 'Lunas' : 'Belum Lunas');
$warna_status = ($status_bayar === 'Lunas') ? 'text-green-600' : 'text-red-600';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $invoice_number ?> - Kost Sun</title>
    <!-- Tambahkan baris ini -->
    <link rel="icon" type="image/jpeg" href="ikon-sun.jpg">
    <link rel="apple-touch-icon" href="ikon-sun.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS khusus untuk merapikan hasil cetakan PDF / Kertas */
        @media print {
            body { background-color: white !important; }
            .no-print { display: none !important; }
            .print-border { border: 1px solid #e5e7eb !important; }
            .print-shadow-none { box-shadow: none !important; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        /* Cap Air (Watermark) untuk Invoice Belum Lunas */
        .watermark-container {
            position: relative;
            z-index: 1;
        }
        .watermark {
            position: absolute;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 7rem;
            color: rgba(239, 68, 68, 0.08); /* Warna merah sangat tipis */
            font-weight: 900;
            z-index: -1;
            pointer-events: none;
            white-space: nowrap;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8 flex justify-center min-h-screen relative overflow-x-hidden">

    <div class="max-w-3xl w-full">
        
        <div class="mb-6 flex justify-between items-center no-print">
            <a href="keuangan.php" class="text-sm font-bold text-gray-500 hover:text-black">&larr; Kembali</a>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow-sm flex items-center gap-2 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak / Simpan PDF
            </button>
        </div>

        <div class="bg-white p-8 md:p-12 rounded-lg shadow-md print-shadow-none print-border watermark-container overflow-hidden">
            
            <?php if ($status_bayar === 'Belum Lunas'): ?>
                <div class="watermark">BELUM LUNAS</div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b-2 border-gray-800 pb-6 mb-8">
                <div class="flex items-center gap-4 mb-4 md:mb-0">
                    <img src="logo.jpg" alt="Logo" class="h-16 w-16 object-contain rounded bg-black p-1">
                    <div>
                        <h1 class="text-3xl font-black text-gray-900 tracking-tight uppercase">Kost Sun</h1>
                        <p class="text-sm text-gray-500 font-medium tracking-wide">Manajemen Properti & Sewa Kamar</p>
                    </div>
                </div>
                <div class="text-left md:text-right">
                    <h2 class="text-2xl font-bold text-gray-300 uppercase tracking-widest">
                        <?= ($status_bayar === 'Belum Lunas') ? 'INVOICE / DP' : 'INVOICE' ?>
                    </h2>
                    <p class="text-sm font-bold text-gray-800 mt-1"><?= $invoice_number ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Ditagihkan Kepada:</h3>
                    <p class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($invoice['namacustomer']) ?></p>
                    <p class="text-sm text-gray-600 mt-1">NIK: <?= htmlspecialchars($invoice['nikcustomer']) ?></p>
                    <p class="text-sm text-gray-600">No. HP: <?= htmlspecialchars($invoice['nohpcustomer'] ?: '-') ?></p>
                    <p class="text-sm text-gray-600 mt-2"><?= htmlspecialchars($invoice['alamatcustomer']) ?><br><?= htmlspecialchars($invoice['kotaasalcustomer']) ?></p>
                </div>
                <div class="md:text-right">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Detail Pembayaran:</h3>
                    <table class="w-full text-sm">
                        <tr>
                            <td class="text-gray-500 py-1 md:text-right pr-4">Tanggal Transaksi</td>
                            <td class="font-bold text-gray-800 text-right"><?= date('d F Y', strtotime($invoice['tanggaltransaksi'])) ?></td>
                        </tr>
                        <tr>
                            <td class="text-gray-500 py-1 md:text-right pr-4">Jenis Transaksi</td>
                            <td class="font-bold text-gray-800 text-right"><?= htmlspecialchars($invoice['namatransaksi']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-gray-500 py-1 md:text-right pr-4">Status Pembayaran</td>
                            <td class="font-bold <?= $warna_status ?> text-right uppercase border-b-2 <?= ($status_bayar === 'Lunas') ? 'border-green-200' : 'border-red-200' ?>">
                                <?= $status_bayar ?>
                            </td>
                        </tr>
                        <?php if (isset($invoice['tanggal_bayar']) && $invoice['tanggal_bayar'] != '0000-00-00'): ?>
                        <tr>
                            <td class="text-gray-400 text-xs py-1 md:text-right pr-4">Update Terakhir</td>
                            <td class="font-semibold text-gray-500 text-xs text-right"><?= date('d M Y', strtotime($invoice['tanggal_bayar'])) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <table class="w-full text-left mb-8 border-collapse">
                <thead>
                    <tr class="bg-gray-100 border-y border-gray-300">
                        <th class="py-3 px-4 text-sm font-bold text-gray-700">Deskripsi Properti & Masa Sewa</th>
                        <th class="py-3 px-4 text-sm font-bold text-gray-700 text-right">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="py-4 px-4">
                            <p class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($invoice['nama_kost']) ?> - Kamar <?= htmlspecialchars($invoice['nomor_kamar']) ?></p>
                            <p class="text-sm text-gray-500">Fasilitas: <?= htmlspecialchars($invoice['jenis_kamar']) ?></p>
                            <p class="text-sm text-gray-600 mt-2">
                                <span class="font-semibold text-gray-700">Periode:</span> 
                                <?= date('d M Y', strtotime($invoice['mulaisewa'])) ?> 
                                <span class="mx-1 text-gray-400">&rarr;</span> 
                                <?= date('d M Y', strtotime($invoice['habissewa'])) ?>
                            </p>
                        </td>
                        <td class="py-4 px-4 text-right font-semibold text-gray-800 align-top pt-5">
                            <?= number_format($harga_dasar, 0, ',', '.') ?>
                        </td>
                    </tr>
                    
                    <?php if ($diskon > 0): ?>
                    <tr>
                        <td class="py-3 px-4 text-right text-sm font-semibold text-gray-600 italic">Diskon</td>
                        <td class="py-3 px-4 text-right font-semibold text-red-500">- <?= number_format($diskon, 0, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php if ($charge > 0): ?>
                    <tr>
                        <td class="py-3 px-4 text-right text-sm font-semibold text-gray-600 italic">Biaya Tambahan / Charge</td>
                        <td class="py-3 px-4 text-right font-semibold text-gray-800">+ <?= number_format($charge, 0, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                
                <tfoot>
                    <tr class="border-t-2 border-gray-800 bg-gray-50">
                        <td class="py-3 px-4 text-right font-bold text-gray-800 text-sm uppercase tracking-wider">
                            Total Tagihan Keseluruhan
                        </td>
                        <td class="py-3 px-4 text-right font-black text-gray-900 text-xl">
                            Rp <?= number_format($total_tagihan, 0, ',', '.') ?>
                        </td>
                    </tr>
                    
                    <tr class="bg-white">
                        <td class="py-3 px-4 text-right font-bold text-green-700 text-sm uppercase tracking-wider border-b border-gray-200">
                            Telah Dibayar (Uang Masuk)
                        </td>
                        <td class="py-3 px-4 text-right font-black text-green-700 text-xl border-b border-gray-200">
                            Rp <?= number_format($telah_dibayar, 0, ',', '.') ?>
                        </td>
                    </tr>
                    
                    <?php if ($kurang_bayar > 0): ?>
                    <tr class="bg-red-50">
                        <td class="py-3 px-4 text-right font-bold text-red-700 text-sm uppercase tracking-wider border-b-2 border-red-200">
                            SISA KURANG BAYAR
                        </td>
                        <td class="py-3 px-4 text-right font-black text-red-700 text-xl border-b-2 border-red-200">
                            Rp <?= number_format($kurang_bayar, 0, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>

            <div class="flex justify-between items-end mt-12 pt-8 border-t border-gray-200">
                <div class="text-xs text-gray-400">
                    <p>Terima kasih telah mempercayakan akomodasi Anda pada Kost Sun.</p>
                    <p>Dokumen ini adalah bukti penagihan/pembayaran yang sah dan dicetak secara otomatis oleh sistem.</p>
                </div>
                <div class="text-center w-40">
                    <p class="text-sm font-bold text-gray-800 mb-8">Pihak Pengelola,</p>
                    <p class="text-xs font-bold text-gray-500 uppercase border-t border-gray-300 pt-1">Kost Sun Management</p>
                </div>
            </div>

        </div>
    </div>

</body>
</html>