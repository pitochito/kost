<?php
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$mode_edit = false;

// Default value
$edit_id = '';
$edit_data = [
    'nikcustomer' => '', 'namacustomer' => '', 'kotaasalcustomer' => '', 
    'alamatcustomer' => '', 'nohpcustomer' => '', 'namakontakdarurat' => '', 
    'kontakdarurat' => '', 'statuscustomer' => 'Aktif',
    'fotoktpcustomer' => '', 'fotoselfiecustomer' => ''
];

// TANGKAP DATA UNTUK EDIT
if (isset($_GET['edit'])) {
    $mode_edit = true;
    $edit_id = $_GET['edit'];
    $stmt = $koneksi->prepare("SELECT * FROM table_customer WHERE id_customer = ?");
    $stmt->execute([$edit_id]);
    $data_db = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data_db) {
        $edit_data = $data_db;
    } else {
        header("Location: customer.php");
        exit;
    }
}

// PROSES SIMPAN DATA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cust_post = $_POST['id_customer'] ?? '';
    
    // Ambil data teks
    $nik = trim($_POST['nikcustomer']);
    $nama = trim($_POST['namacustomer']);
    $kota = trim($_POST['kotaasalcustomer']);
    $alamat = trim($_POST['alamatcustomer']);
    $nohp = trim($_POST['nohpcustomer']);
    $nama_darurat = trim($_POST['namakontakdarurat']);
    $kontak_darurat = trim($_POST['kontakdarurat']);
    $status = $_POST['statuscustomer'];
    
    // Siapkan nama file lama sebagai default jika tidak ada file baru yang diunggah
    $nama_ktp = $_POST['fotoktpcustomer_lama'] ?? '';
    $nama_selfie = $_POST['fotoselfiecustomer_lama'] ?? '';

    // Validasi input
    if (empty($nik) || empty($nama)) {
        $pesan_error = "NIK dan Nama Customer wajib diisi!";
    } else {
        // PROSES UPLOAD FOTO KTP
        if (isset($_FILES['fotoktpcustomer']) && $_FILES['fotoktpcustomer']['error'] === UPLOAD_ERR_OK) {
            $tmp_ktp = $_FILES['fotoktpcustomer']['tmp_name'];
            $ext_ktp = pathinfo($_FILES['fotoktpcustomer']['name'], PATHINFO_EXTENSION);
            $nama_ktp = 'KTP_' . time() . '_' . rand(100,999) . '.' . $ext_ktp;
            move_uploaded_file($tmp_ktp, 'ktpcust/' . $nama_ktp);
            
            // Hapus KTP lama jika edit
            if ($mode_edit && !empty($_POST['fotoktpcustomer_lama']) && file_exists('ktpcust/' . $_POST['fotoktpcustomer_lama'])) {
                unlink('ktpcust/' . $_POST['fotoktpcustomer_lama']);
            }
        }

        // PROSES UPLOAD FOTO SELFIE
        if (isset($_FILES['fotoselfiecustomer']) && $_FILES['fotoselfiecustomer']['error'] === UPLOAD_ERR_OK) {
            $tmp_selfie = $_FILES['fotoselfiecustomer']['tmp_name'];
            $ext_selfie = pathinfo($_FILES['fotoselfiecustomer']['name'], PATHINFO_EXTENSION);
            $nama_selfie = 'SELFIE_' . time() . '_' . rand(100,999) . '.' . $ext_selfie;
            move_uploaded_file($tmp_selfie, 'selfiecust/' . $nama_selfie);
            
            // Hapus Selfie lama jika edit
            if ($mode_edit && !empty($_POST['fotoselfiecustomer_lama']) && file_exists('selfiecust/' . $_POST['fotoselfiecustomer_lama'])) {
                unlink('selfiecust/' . $_POST['fotoselfiecustomer_lama']);
            }
        }

        // SIMPAN KE DATABASE
        if (!empty($id_cust_post)) {
            $stmt = $koneksi->prepare("UPDATE table_customer SET 
                nikcustomer=?, namacustomer=?, kotaasalcustomer=?, alamatcustomer=?, 
                nohpcustomer=?, namakontakdarurat=?, kontakdarurat=?, statuscustomer=?,
                fotoktpcustomer=?, fotoselfiecustomer=? WHERE id_customer=?");
            $stmt->execute([$nik, $nama, $kota, $alamat, $nohp, $nama_darurat, $kontak_darurat, $status, $nama_ktp, $nama_selfie, $id_cust_post]);
        } else {
            $stmt = $koneksi->prepare("INSERT INTO table_customer (
                nikcustomer, namacustomer, kotaasalcustomer, alamatcustomer, 
                nohpcustomer, namakontakdarurat, kontakdarurat, statuscustomer,
                fotoktpcustomer, fotoselfiecustomer
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nik, $nama, $kota, $alamat, $nohp, $nama_darurat, $kontak_darurat, $status, $nama_ktp, $nama_selfie]);
        }
        header("Location: customer.php");
        exit;
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="customer.php" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Kembali ke Data Customer</a>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">
            <?= $mode_edit ? 'Edit Data Customer' : 'Tambah Customer Baru' ?>
        </h2>

        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm text-sm font-medium"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form action="form_customer.php<?= $mode_edit ? '?edit='.$edit_id : '' ?>" method="POST" enctype="multipart/form-data" class="flex flex-col gap-6">
            <input type="hidden" name="id_customer" value="<?= htmlspecialchars($edit_id) ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <h3 class="font-bold text-gray-700 border-b pb-2">Informasi Pribadi</h3>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">NIK (Sesuai KTP)</label>
                        <input type="text" name="nikcustomer" value="<?= htmlspecialchars($edit_data['nikcustomer']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap</label>
                        <input type="text" name="namacustomer" value="<?= htmlspecialchars($edit_data['namacustomer']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Kota Asal</label>
                        <input type="text" name="kotaasalcustomer" value="<?= htmlspecialchars($edit_data['kotaasalcustomer']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Alamat Lengkap</label>
                        <textarea name="alamatcustomer" rows="3" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500"><?= htmlspecialchars($edit_data['alamatcustomer']) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status Customer</label>
                        <select name="statuscustomer" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                            <option value="Aktif" <?= strtolower($edit_data['statuscustomer']) == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="Tidak Aktif" <?= strtolower($edit_data['statuscustomer']) == 'tidak aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="font-bold text-gray-700 border-b pb-2">Kontak & Berkas</h3>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">No. HP / WhatsApp</label>
                        <input type="text" name="nohpcustomer" value="<?= htmlspecialchars($edit_data['nohpcustomer']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Kontak Darurat</label>
                            <input type="text" name="namakontakdarurat" value="<?= htmlspecialchars($edit_data['namakontakdarurat']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">No. HP Darurat</label>
                            <input type="text" name="kontakdarurat" value="<?= htmlspecialchars($edit_data['kontakdarurat']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded border border-gray-200 mt-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Foto KTP</label>
                        <input type="file" name="fotoktpcustomer" accept="image/*" class="text-sm text-gray-500 w-full mb-2">
                        <input type="hidden" name="fotoktpcustomer_lama" value="<?= htmlspecialchars($edit_data['fotoktpcustomer']) ?>">
                        <?php if($mode_edit && !empty($edit_data['fotoktpcustomer'])): ?>
                            <a href="ktpcust/<?= $edit_data['fotoktpcustomer'] ?>" target="_blank" class="text-xs text-blue-600 underline">Lihat KTP Saat Ini</a>
                        <?php endif; ?>
                    </div>

                    <div class="bg-gray-50 p-4 rounded border border-gray-200">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Foto Selfie (Opsional)</label>
                        <input type="file" name="fotoselfiecustomer" accept="image/*" class="text-sm text-gray-500 w-full mb-2">
                        <input type="hidden" name="fotoselfiecustomer_lama" value="<?= htmlspecialchars($edit_data['fotoselfiecustomer']) ?>">
                        <?php if($mode_edit && !empty($edit_data['fotoselfiecustomer'])): ?>
                            <a href="selfiecust/<?= $edit_data['fotoselfiecustomer'] ?>" target="_blank" class="text-xs text-blue-600 underline">Lihat Selfie Saat Ini</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-4 border-t pt-6">
                <button type="submit" class="flex-1 md:flex-none bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 px-8 rounded-md transition-colors shadow-sm">
                    <?= $mode_edit ? 'Simpan Perubahan' : 'Simpan Customer Baru' ?>
                </button>
                <a href="customer.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-md text-center transition-colors shadow-sm">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>