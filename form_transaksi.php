<?php
require 'koneksi.php';
require 'header.php';

// PROTEKSI HALAMAN: TENDANG JIKA BUKAN SUPER ADMIN
if ($role_aktif !== 'super admin') {
    echo "<script>alert('Akses Ditolak: Hanya Super Admin yang dapat mengakses alat penyesuaian transaksi manual.'); window.location.href='keuangan.php';</script>";
    exit;
}

$pesan_error = '';
$mode_edit = false;
$edit_id = '';

// Ambil Data Customer
$stmt_cust = $koneksi->query("SELECT id_customer, namacustomer, nikcustomer FROM table_customer ORDER BY namacustomer ASC");
$data_customer = $stmt_cust->fetchAll(PDO::FETCH_ASSOC);

// Ambil Data Kamar
$stmt_kamar = $koneksi->query("
    SELECT k.id_kamar, k.nomor_kamar, ko.nama_kost 
    FROM table_kamar k 
    JOIN table_kost ko ON k.id_kost = ko.id_kost 
    ORDER BY ko.nama_kost ASC, k.nomor_kamar ASC
");
$data_kamar = $stmt_kamar->fetchAll(PDO::FETCH_ASSOC);

// Nilai Default
$edit_data = [
    'id_customer' => '',
    'id_kamar' => '',
    'tanggaltransaksi' => date('Y-m-d'),
    'namatransaksi' => 'Koreksi Transaksi Sewa',
    'mulaisewa' => date('Y-m-d'),
    'habissewa' => date('Y-m-d', strtotime('+1 month')),
    'jumlahtransaksi' => '',
    'diskontransaksi' => '0',
    'jumlah_charge' => '0',
    'jumlah_bayar' => '',
    'tanggal_bayar' => date('Y-m-d')
];

// TANGKAP DATA JIKA MODE EDIT
if (isset($_GET['edit'])) {
    $mode_edit = true;
    $edit_id = $_GET['edit'];
    $stmt = $koneksi->prepare("SELECT * FROM table_transaksi WHERE id_transaksi = ?");
    $stmt->execute([$edit_id]);
    $data_db = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data_db) {
        $edit_data = $data_db;
    } else {
        echo "<script>window.location.href='keuangan.php';</script>";
        exit;
    }
}

// PROSES SIMPAN DATA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_trans_post = $_POST['id_transaksi'] ?? '';
    $id_customer = $_POST['id_customer'] ?? '';
    $id_kamar = $_POST['id_kamar'] ?? '';
    $tanggaltransaksi = $_POST['tanggaltransaksi'];
    
    // Logika Tangkap Keterangan Transaksi
    $namatransaksi_select = $_POST['namatransaksi_select'] ?? '';
    if ($namatransaksi_select === 'Lainnya') {
        $namatransaksi = trim($_POST['namatransaksi_lainnya']);
    } else {
        $namatransaksi = trim($namatransaksi_select);
    }

    $mulaisewa = $_POST['mulaisewa'];
    $habissewa = $_POST['habissewa'];
    
    // Variabel Keuangan Manual
    $jumlahtransaksi = (int)$_POST['jumlahtransaksi'];
    $diskontransaksi = (int)$_POST['diskontransaksi'];
    $jumlah_charge = (int)$_POST['jumlah_charge'];
    $jumlah_bayar = (int)$_POST['jumlah_bayar'];
    $tanggal_bayar = $_POST['tanggal_bayar'];

    // PROTEKSI NULL: Ambil ID User dari Session Aktif untuk Audit Trail
    $id_user_aktif = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Kalkulasi Status Bayar Otomatis
    $total_tagihan = $jumlahtransaksi - $diskontransaksi + $jumlah_charge;
    $status_bayar = ($jumlah_bayar >= $total_tagihan) ? 'Lunas' : 'Belum Lunas';

    if (empty($id_customer) || empty($id_kamar) || empty($namatransaksi) || empty($_POST['jumlahtransaksi'])) {
        $pesan_error = "Semua field yang bertanda * wajib diisi secara lengkap!";
    } else {
        if (!empty($id_trans_post)) {
            // UPDATE: Menyimpan 'id' pengubah terakhir (sesuai db_kost)
            $stmt = $koneksi->prepare("UPDATE table_transaksi SET id_customer=?, id_kamar=?, tanggaltransaksi=?, namatransaksi=?, mulaisewa=?, habissewa=?, jumlahtransaksi=?, diskontransaksi=?, jumlah_charge=?, jumlah_bayar=?, status_bayar=?, tanggal_bayar=?, id=? WHERE id_transaksi=?");
            $stmt->execute([$id_customer, $id_kamar, $tanggaltransaksi, $namatransaksi, $mulaisewa, $habissewa, $jumlahtransaksi, $diskontransaksi, $jumlah_charge, $jumlah_bayar, $status_bayar, $tanggal_bayar, $id_user_aktif, $id_trans_post]);
        } else {
            // INSERT: Menyimpan 'id' pembuat (sesuai db_kost)
            $stmt = $koneksi->prepare("INSERT INTO table_transaksi (id_customer, id_kamar, tanggaltransaksi, namatransaksi, mulaisewa, habissewa, jumlahtransaksi, diskontransaksi, jumlah_charge, jumlah_bayar, status_bayar, tanggal_bayar, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_customer, $id_kamar, $tanggaltransaksi, $namatransaksi, $mulaisewa, $habissewa, $jumlahtransaksi, $diskontransaksi, $jumlah_charge, $jumlah_bayar, $status_bayar, $tanggal_bayar, $id_user_aktif]);
        }
        
        // SOLUSI BLANK PAGE: Redirect Javascript
        echo "<script>window.location.href='keuangan.php';</script>";
        exit;
    }
}
?>

<div class="max-w-4xl mx-auto pb-32">
    <div class="mb-6">
        <a href="keuangan.php" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Kembali ke Keuangan</a>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200 border-t-4 border-t-green-600 relative overflow-hidden">
        
        <div class="absolute top-0 right-0 bg-yellow-500 text-black text-xs font-black px-4 py-1 rounded-bl-lg uppercase tracking-widest shadow-sm">
            Super Admin Override Tool
        </div>

        <h2 class="text-2xl font-bold text-gray-800 mb-2">
            <?= $mode_edit ? 'Edit Riwayat Transaksi' : 'Input Transaksi Manual' ?>
        </h2>
        <p class="text-gray-500 text-sm mb-6 border-b pb-4">
            Alat ini mengabaikan otomatisasi harga. Gunakan secara teliti untuk mengoreksi pembukuan (backdate) atau cicilan tanpa melalui prosedur normal.
        </p>

        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm text-sm font-medium"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form id="formTransaksiManual" action="form_transaksi.php<?= $mode_edit ? '?edit='.$edit_id : '' ?>" method="POST" class="space-y-6" onsubmit="return konfirmasiTransaksi()">
            <input type="hidden" name="id_transaksi" value="<?= htmlspecialchars($edit_id) ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Customer / Penyewa *</label>
                    <select id="id_customer" name="id_customer" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 bg-white" required>
                        <option value="" disabled <?= empty($edit_data['id_customer']) ? 'selected' : '' ?>>-- Pilih Customer Terdaftar --</option>
                        <?php foreach($data_customer as $cust): ?>
                            <option value="<?= $cust['id_customer'] ?>" <?= $edit_data['id_customer'] == $cust['id_customer'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cust['namacustomer']) ?> (NIK: <?= htmlspecialchars($cust['nikcustomer']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Lokasi & Nomor Kamar *</label>
                    <select id="id_kamar" name="id_kamar" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 bg-white" required>
                        <option value="" disabled <?= empty($edit_data['id_kamar']) ? 'selected' : '' ?>>-- Pilih Alokasi Kamar --</option>
                        <?php foreach($data_kamar as $k): ?>
                            <option value="<?= $k['id_kamar'] ?>" <?= $edit_data['id_kamar'] == $k['id_kamar'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kost']) ?> - Kamar <?= htmlspecialchars($k['nomor_kamar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 bg-gray-50 p-4 rounded border border-gray-200">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tgl Terjadinya Transaksi *</label>
                    <input type="date" id="tanggaltransaksi" name="tanggaltransaksi" value="<?= htmlspecialchars($edit_data['tanggaltransaksi']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 bg-white" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tgl Mulai Sewa *</label>
                    <input type="date" id="mulaisewa" name="mulaisewa" value="<?= htmlspecialchars($edit_data['mulaisewa']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 bg-white" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tgl Habis Sewa *</label>
                    <input type="date" id="habissewa" name="habissewa" value="<?= htmlspecialchars($edit_data['habissewa']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 bg-white" required>
                </div>
            </div>

            <?php 
                // Cek opsi default untuk dropdown
                $opsi_standar = ['Koreksi Transaksi Sewa', 'Perpanjangan Sewa', 'Sewa Baru'];
                $is_lainnya = !in_array($edit_data['namatransaksi'], $opsi_standar) && !empty($edit_data['namatransaksi']);
                $selected_dropdown = $is_lainnya ? 'Lainnya' : $edit_data['namatransaksi'];
            ?>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Keterangan Transaksi *</label>
                <select id="namatransaksi_select" name="namatransaksi_select" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 bg-white" required onchange="toggleKeteranganLainnya()">
                    <option value="Koreksi Transaksi Sewa" <?= $selected_dropdown == 'Koreksi Transaksi Sewa' ? 'selected' : '' ?>>Koreksi Transaksi Sewa</option>
                    <option value="Perpanjangan Sewa" <?= $selected_dropdown == 'Perpanjangan Sewa' ? 'selected' : '' ?>>Perpanjangan Sewa</option>
                    <option value="Sewa Baru" <?= $selected_dropdown == 'Sewa Baru' ? 'selected' : '' ?>>Sewa Baru</option>
                    <option value="Lainnya" <?= $selected_dropdown == 'Lainnya' ? 'selected' : '' ?>>Lainnya (Ketik Manual)</option>
                </select>
                <input type="text" id="namatransaksi_lainnya" name="namatransaksi_lainnya" value="<?= $is_lainnya ? htmlspecialchars($edit_data['namatransaksi']) : '' ?>" placeholder="Ketik keterangan transaksi di sini..." class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 mt-3 <?= $is_lainnya ? '' : 'hidden' ?>">
            </div>

            <div class="bg-blue-50 border border-blue-200 p-5 rounded-lg space-y-4">
                <h3 class="font-bold text-blue-800 border-b border-blue-200 pb-2">Rincian Tagihan</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Harga Dasar (Tarif x Durasi) *</label>
                        <input type="number" id="jumlahtransaksi" name="jumlahtransaksi" value="<?= htmlspecialchars($edit_data['jumlahtransaksi']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white" required placeholder="Cth: 1500000">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Diskon (Rp)</label>
                        <input type="number" id="diskontransaksi" name="diskontransaksi" value="<?= htmlspecialchars($edit_data['diskontransaksi']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Biaya Tambahan / Charge (Rp)</label>
                        <input type="number" id="jumlah_charge" name="jumlah_charge" value="<?= htmlspecialchars($edit_data['jumlah_charge']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                    </div>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 p-5 rounded-lg space-y-4 mt-2">
                <h3 class="font-bold text-green-800 border-b border-green-200 pb-2">Pembayaran & Arus Kas</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nominal Telah Dibayar (Rp) *</label>
                        <input type="number" id="jumlah_bayar" name="jumlah_bayar" value="<?= htmlspecialchars($edit_data['jumlah_bayar']) ?>" class="w-full border border-green-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 font-bold text-lg text-gray-800" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Tgl Pembayaran Terakhir *</label>
                        <input type="date" id="tanggal_bayar" name="tanggal_bayar" value="<?= htmlspecialchars($edit_data['tanggal_bayar']) ?>" class="w-full border border-green-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 bg-white" required>
                    </div>
                </div>
            </div>

            <div class="flex gap-4 mt-8 border-t pt-6">
                <button type="submit" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-10 rounded-md transition-colors shadow-sm text-lg">
                    <?= $mode_edit ? 'Simpan Pembaruan Transaksi' : 'Sisipkan Transaksi Manual' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const modeEditTransJS = <?= json_encode($mode_edit) ?>;

function toggleKeteranganLainnya() {
    const select = document.getElementById('namatransaksi_select');
    const inputLainnya = document.getElementById('namatransaksi_lainnya');
    
    if (select.value === 'Lainnya') {
        inputLainnya.classList.remove('hidden');
        inputLainnya.setAttribute('required', 'true');
    } else {
        inputLainnya.classList.add('hidden');
        inputLainnya.removeAttribute('required');
    }
}

// Inisialisasi saat load (khusus untuk Mode Edit)
document.addEventListener("DOMContentLoaded", function() {
    toggleKeteranganLainnya();
});

function konfirmasiTransaksi() {
    const custSelect = document.getElementById('id_customer');
    const kamarSelect = document.getElementById('id_kamar');
    
    // Abaikan jika data mandatory kosong (biarkan HTML5 validation bekerja)
    if (custSelect.value === "" || kamarSelect.value === "" || !document.getElementById('jumlahtransaksi').value) {
        return true; 
    }

    const customer = custSelect.options[custSelect.selectedIndex].text;
    const kamar = kamarSelect.options[kamarSelect.selectedIndex].text;
    
    // Logika Keterangan Transaksi untuk Alert
    let keterangan = document.getElementById('namatransaksi_select').value;
    if (keterangan === 'Lainnya') {
        keterangan = document.getElementById('namatransaksi_lainnya').value;
    }
    
    const dasar = parseInt(document.getElementById('jumlahtransaksi').value) || 0;
    const diskon = parseInt(document.getElementById('diskontransaksi').value) || 0;
    const charge = parseInt(document.getElementById('jumlah_charge').value) || 0;
    const bayar = parseInt(document.getElementById('jumlah_bayar').value) || 0;
    
    const totalTagihan = dasar - diskon + charge;
    const statusSistem = (bayar >= totalTagihan) ? 'LUNAS' : 'BELUM LUNAS (Kekurangan: Rp ' + (totalTagihan - bayar).toLocaleString('id-ID') + ')';

    const tglMulai = document.getElementById('mulaisewa').value;
    const tglHabis = document.getElementById('habissewa').value;
    const aksi = modeEditTransJS ? 'PERUBAHAN' : 'PENYIMPANAN';

    const pesan = `KONFIRMASI ${aksi} TRANSAKSI MANUAL:\n\n` +
                  `• Penyewa : ${customer}\n` +
                  `• Alokasi : ${kamar}\n` +
                  `• Periode : ${tglMulai} s/d ${tglHabis}\n` +
                  `• Keterangan : ${keterangan}\n\n` +
                  `[FINANSIAL]\n` +
                  `• Total Tagihan : Rp ${totalTagihan.toLocaleString('id-ID')}\n` +
                  `• Total Dibayar : Rp ${bayar.toLocaleString('id-ID')}\n` +
                  `• Status Sistem : ${statusSistem}\n\n` +
                  `Apakah kueri penyesuaian transaksi di atas sudah benar?`;

    return confirm(pesan);
}
</script>

<?php require 'footer.php'; ?>