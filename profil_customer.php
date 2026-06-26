<?php
require 'koneksi.php';
require 'header.php';

// Pastikan ada parameter id_customer yang dikirim
if (!isset($_GET['id'])) {
    header("Location: customer.php");
    exit;
}

$id_customer = $_GET['id'];

// Ambil data customer lengkap dengan riwayat kamar yang ditempati saat ini
$query = "
    SELECT c.*, k.nomor_kamar, ko.nama_kost, t.mulaisewa, t.habissewa, t.jumlahtransaksi
    FROM table_customer c
    LEFT JOIN table_transaksi t ON c.id_customer = t.id_customer
    LEFT JOIN table_kamar k ON t.id_kamar = k.id_kamar
    LEFT JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE c.id_customer = ?
    ORDER BY t.id_transaksi DESC LIMIT 1
";
$stmt = $koneksi->prepare($query);
$stmt->execute([$id_customer]);
$cust = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cust) {
    echo "<div class='p-6 text-center text-red-600 font-bold'>Data customer tidak ditemukan.</div>";
    require 'footer.php';
    exit;
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <button onclick="history.back()" class="text-sm font-semibold text-gray-500 hover:text-black">&larr; Kembali</button>
        <a href="form_customer.php?edit=<?= $cust['id_customer'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-4 py-2 rounded text-sm font-bold transition-colors">
            Edit Profil
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-black p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-2xl font-black text-white tracking-wide"><?= htmlspecialchars($cust['namacustomer']) ?></h2>
                <p class="text-gray-400 text-sm mt-1">NIK: <?= htmlspecialchars($cust['nikcustomer']) ?></p>
            </div>
            <div>
                <?php if (strtolower($cust['statuscustomer']) == 'aktif'): ?>
                    <span class="bg-green-500 text-black px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider">Penyewa Aktif</span>
                <?php else: ?>
                    <span class="bg-gray-700 text-gray-300 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider">Tidak Aktif</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-6 lg:p-8 grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider border-b pb-1 mb-3">Informasi Kontak & Asal</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 text-xs">No. HP / WhatsApp</p>
                            <p class="font-bold text-gray-800 mt-0.5"><?= htmlspecialchars($cust['nohpcustomer'] ?: '-') ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Kota Asal</p>
                            <p class="font-bold text-gray-800 mt-0.5"><?= htmlspecialchars($cust['kotaasalcustomer'] ?: '-') ?></p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-gray-500 text-xs">Alamat Sesuai KTP</p>
                            <p class="font-semibold text-gray-700 mt-0.5"><?= htmlspecialchars($cust['alamatcustomer'] ?: '-') ?></p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider border-b pb-1 mb-3">Kontak Kondisi Darurat</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm bg-gray-50 p-4 rounded-lg border border-gray-100">
                        <div>
                            <p class="text-gray-500 text-xs">Nama Kontak</p>
                            <p class="font-bold text-gray-800 mt-0.5"><?= htmlspecialchars($cust['namakontakdarurat'] ?: '-') ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">No. Telepon Darurat</p>
                            <p class="font-bold text-red-600 mt-0.5"><?= htmlspecialchars($cust['kontakdarurat'] ?: '-') ?></p>
                        </div>
                    </div>
                </div>

                <?php if (strtolower($cust['statuscustomer']) == 'aktif' && $cust['nomor_kamar']): ?>
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider border-b pb-1 mb-3">Status Sewa Saat Ini</h3>
                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600 text-xs">Lokasi Properti</p>
                            <p class="font-black text-gray-800"><?= htmlspecialchars($cust['nama_kost']) ?></p>
                            <p class="text-xs font-bold text-yellow-700">Kamar <?= htmlspecialchars($cust['nomor_kamar']) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-600 text-xs">Masa Sewa</p>
                            <p class="font-bold text-gray-800 text-xs"><?= date('d M Y', strtotime($cust['mulaisewa'])) ?> s/d</p>
                            <p class="font-black text-red-600 text-sm"><?= date('d M Y', strtotime($cust['habissewa'])) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="md:col-span-1 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider border-b pb-1 mb-3">Foto KTP</h3>
                    <div class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center min-h-[120px]">
                        <?php if (!empty($cust['fotoktpcustomer']) && file_exists('uploads/' . $cust['fotoktpcustomer'])): ?>
                            <a href="uploads/<?= $cust['fotoktpcustomer'] ?>" target="_blank" title="Klik untuk memperbesar">
                                <img src="uploads/<?= $cust['fotoktpcustomer'] ?>" alt="KTP" class="w-full h-auto object-cover hover:opacity-90 transition-opacity">
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-400 italic p-4 text-center">Foto KTP belum diunggah atau file tidak ditemukan</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider border-b pb-1 mb-3">Foto Selfie</h3>
                    <div class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center min-h-[120px]">
                        <?php if (!empty($cust['fotoselfiecustomer']) && file_exists('uploads/' . $cust['fotoselfiecustomer'])): ?>
                            <a href="uploads/<?= $cust['fotoselfiecustomer'] ?>" target="_blank" title="Klik untuk memperbesar">
                                <img src="uploads/<?= $cust['fotoselfiecustomer'] ?>" alt="Selfie" class="w-full h-auto object-cover hover:opacity-90 transition-opacity">
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-400 italic p-4 text-center">Foto Selfie belum diunggah</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>