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
    'diskontransaksi' => '0',
    'jumlahtransaksi' => ''
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
        header("Location: keuangan.php");
        exit;
    }
}

// PROSES SIMPAN DATA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_trans_post = $_POST['id_transaksi'] ?? '';
    $id_customer = $_POST['id_customer'] ?? '';
    $id_kamar = $_POST['id_kamar'] ?? '';
    $tanggaltransaksi = $_POST['tanggaltransaksi'];
    $namatransaksi = trim($_POST['namatransaksi']);
    $mulaisewa = $_POST['mulaisewa'];
    $habissewa = $_POST['habissewa'];
    
    $diskontransaksi = (int)str_replace('.', '', $_POST['diskontransaksi']);
    $jumlahtransaksi = (int)str_replace('.', '', $_POST['jumlahtransaksi']);

    // PROTEKSI NULL: Ambil ID User dari Session Aktif untuk Audit Trail
    $id_user_aktif = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if (empty($id_customer) || empty($id_kamar) || empty($namatransaksi) || empty($_POST['jumlahtransaksi'])) {
        $pesan_error = "Semua field yang bertanda * wajib diisi secara lengkap!";
    } else {
        if (!empty($id_trans_post)) {
            // UPDATE: Menyimpan 'id' pengubah terakhir (sesuai db_kost)
            $stmt = $koneksi->prepare("UPDATE table_transaksi SET id_customer=?, id_kamar=?, tanggaltransaksi=?, namatransaksi=?, mulaisewa=?, habissewa=?, diskontransaksi=?, jumlahtransaksi=?, id=? WHERE id_transaksi=?");
            $stmt->execute([$id_customer, $id_kamar, $tanggaltransaksi, $namatransaksi, $mulaisewa, $habissewa, $diskontransaksi, $jumlahtransaksi, $id_user_aktif, $id_trans_post]);
        } else {
            // INSERT: Menyimpan 'id' pembuat (sesuai db_kost)
            $stmt = $koneksi->prepare("INSERT INTO table_transaksi (id_customer, id_kamar, tanggaltransaksi, namatransaksi, mulaisewa, habissewa, diskontransaksi, jumlahtransaksi, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_customer, $id_kamar, $tanggaltransaksi, $namatransaksi, $mulaisewa, $habissewa, $diskontransaksi, $jumlahtransaksi, $id_user_aktif]);
        }
        header("Location: keuangan.php");
        exit;
    }
}
?>

<div class="max-w-4xl mx-auto">
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
            Alat ini mengabaikan otomatisasi harga. Gunakan secara teliti untuk mengoreksi pembukuan (backdate) tanpa perlu mendaftarkan customer baru.
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
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tgl Transaksi Bayar *</label>
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

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Keterangan Transaksi *</label>
                <input type="text" id="namatransaksi" name="namatransaksi" value="<?= htmlspecialchars($edit_data['namatransaksi']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Diskon Diberikan (Rp)</label>
                    <input type="number" id="diskontransaksi" name="diskontransaksi" value="<?= htmlspecialchars($edit_data['diskontransaksi']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Total Tagihan Final (Rp) *</label>
                    <input type="number" id="jumlahtransaksi" name="jumlahtransaksi" value="<?= htmlspecialchars($edit_data['jumlahtransaksi']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 text-lg font-bold text-green-700" required placeholder="Cth: 1500000">
                </div>
            </div>

            <div class="flex gap-4 mt-8 border-t pt-6">
                <button type="submit" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-10 rounded-md transition-colors shadow-sm">
                    <?= $mode_edit ? 'Simpan Pembaruan Transaksi' : 'Sisipkan Transaksi Manual' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const modeEditTransJS = <?= json_encode($mode_edit) ?>;

function konfirmasiTransaksi() {
    const custSelect = document.getElementById('id_customer');
    const kamarSelect = document.getElementById('id_kamar');
    const tglTrans = document.getElementById('tanggaltransaksi').value;
    const tglMulai = document.getElementById('mulaisewa').value;
    const tglHabis = document.getElementById('habissewa').value;
    const rincian = document.getElementById('namatransaksi').value;
    const diskon = document.getElementById('diskontransaksi').value || 0;
    const nominal = document.getElementById('jumlahtransaksi').value;

    // Abaikan kustom dialog jika validasi internal HTML5 mendeteksi data kosong
    if (custSelect.value === "" || kamarSelect.value === "" || !tglTrans || !tglMulai || !tglHabis || !rincian || !nominal) {
        return true;
    }

    const customer = custSelect.options[custSelect.selectedIndex].text;
    const kamar = kamarSelect.options[kamarSelect.selectedIndex].text;

    const nominalRp = parseInt(nominal).toLocaleString('id-ID');
    const diskonRp = parseInt(diskon).toLocaleString('id-ID');
    const aksi = modeEditTransJS ? 'PERUBAHAN' : 'PENYIMPANAN';

    const pesan = `KONFIRMASI ${aksi} TRANSAKSI MANUAL:\n\n` +
                  `• Penyewa   : ${customer}\n` +
                  `• Alokasi   : ${kamar}\n` +
                  `• Tgl Bayar : ${tglTrans}\n` +
                  `• Periode   : ${tglMulai} s/d ${tglHabis}\n` +
                  `• Rincian   : ${rincian}\n` +
                  `• Potongan  : Rp ${diskonRp}\n` +
                  `• Total Kas : Rp ${nominalRp}\n\n` +
                  `Apakah kueri penyesuaian transaksi di atas sudah benar?`;

    return confirm(pesan);
}
</script>

<?php require 'footer.php'; ?>