<?php
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$mode_edit = false;
$edit_id = '';

// Ambil data KOST dan KAMAR KOSONG untuk Dropdown (Hanya jika Tambah Baru)
$data_kost = [];
$data_kamar_kosong = [];
if (!isset($_GET['edit'])) {
    $stmt_kost = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
    $data_kost = $stmt_kost->fetchAll(PDO::FETCH_ASSOC);

    $stmt_kamar = $koneksi->query("SELECT id_kamar, id_kost, nomor_kamar, jenis_kamar, harga_kamar, harga_minggu, harga_hari FROM table_kamar WHERE LOWER(status_kamar) = 'kosong'");
    $data_kamar_kosong = $stmt_kamar->fetchAll(PDO::FETCH_ASSOC);
}

$edit_data = [
    'nikcustomer' => '', 'namacustomer' => '', 'kotaasalcustomer' => '', 
    'alamatcustomer' => '', 'nohpcustomer' => '', 'namakontakdarurat' => '', 
    'kontakdarurat' => '', 'statuscustomer' => 'Aktif',
    'fotoktpcustomer' => '', 'fotoselfiecustomer' => ''
];

// TANGKAP DATA UNTUK EDIT PROFIL SAJA
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
    
    $nik = trim($_POST['nikcustomer']);
    $nama = trim($_POST['namacustomer']);
    $kota = trim($_POST['kotaasalcustomer']);
    $alamat = trim($_POST['alamatcustomer']);
    $nohp = trim($_POST['nohpcustomer']);
    $nama_darurat = trim($_POST['namakontakdarurat']);
    $kontak_darurat = trim($_POST['kontakdarurat']);
    $status = $mode_edit ? $_POST['statuscustomer'] : 'Aktif';

    // PROTEKSI NULL: Ambil ID User dari Session Aktif untuk Audit Trail
    $id_user_aktif = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if (empty($nik) || empty($nama)) {
        $pesan_error = "NIK dan Nama Customer wajib diisi!";
    } else {
        
        // PENGAMANAN FOLDER: Buat folder uploads jika belum ada
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        // PROSES UPLOAD
        $nama_ktp = $_POST['fotoktpcustomer_lama'] ?? '';
        if (isset($_FILES['fotoktpcustomer']) && $_FILES['fotoktpcustomer']['error'] === UPLOAD_ERR_OK) {
            $ext_ktp = pathinfo($_FILES['fotoktpcustomer']['name'], PATHINFO_EXTENSION);
            $nama_ktp = 'KTP_' . time() . '_' . rand(100,999) . '.' . $ext_ktp;
            move_uploaded_file($_FILES['fotoktpcustomer']['tmp_name'], 'uploads/' . $nama_ktp);
            if ($mode_edit && !empty($_POST['fotoktpcustomer_lama']) && file_exists('uploads/' . $_POST['fotoktpcustomer_lama'])) { 
                unlink('uploads/' . $_POST['fotoktpcustomer_lama']); 
            }
        }

        $nama_selfie = $_POST['fotoselfiecustomer_lama'] ?? '';
        if (isset($_FILES['fotoselfiecustomer']) && $_FILES['fotoselfiecustomer']['error'] === UPLOAD_ERR_OK) {
            $ext_selfie = pathinfo($_FILES['fotoselfiecustomer']['name'], PATHINFO_EXTENSION);
            $nama_selfie = 'SELFIE_' . time() . '_' . rand(100,999) . '.' . $ext_selfie;
            move_uploaded_file($_FILES['fotoselfiecustomer']['tmp_name'], 'uploads/' . $nama_selfie);
            if ($mode_edit && !empty($_POST['fotoselfiecustomer_lama']) && file_exists('uploads/' . $_POST['fotoselfiecustomer_lama'])) { 
                unlink('uploads/' . $_POST['fotoselfiecustomer_lama']); 
            }
        }

        try {
            $koneksi->beginTransaction();

            if (!empty($id_cust_post)) {
                // UPDATE CUSTOMER
                $stmt = $koneksi->prepare("UPDATE table_customer SET nikcustomer=?, namacustomer=?, kotaasalcustomer=?, alamatcustomer=?, nohpcustomer=?, namakontakdarurat=?, kontakdarurat=?, statuscustomer=?, fotoktpcustomer=?, fotoselfiecustomer=?, id=? WHERE id_customer=?");
                $stmt->execute([$nik, $nama, $kota, $alamat, $nohp, $nama_darurat, $kontak_darurat, $status, $nama_ktp, $nama_selfie, $id_user_aktif, $id_cust_post]);
            } else {
                // INSERT CUSTOMER BARU
                $stmt = $koneksi->prepare("INSERT INTO table_customer (nikcustomer, namacustomer, kotaasalcustomer, alamatcustomer, nohpcustomer, namakontakdarurat, kontakdarurat, statuscustomer, fotoktpcustomer, fotoselfiecustomer, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nik, $nama, $kota, $alamat, $nohp, $nama_darurat, $kontak_darurat, $status, $nama_ktp, $nama_selfie, $id_user_aktif]);
                $new_customer_id = $koneksi->lastInsertId();

                $id_kamar = $_POST['id_kamar'];
                $mulaisewa = $_POST['mulaisewa'];
                $jenissewa = $_POST['jenissewa'];
                $durasi = (int)$_POST['durasi'];
                
                // Variabel Keuangan (PERBAIKAN LOGIKA)
                $jumlahtransaksi = (int)$_POST['harga_dasar_hidden']; // Mengambil Harga Dasar (Tarif x Durasi)
                $diskon = (int)$_POST['diskontransaksi'];
                $charge = (int)$_POST['jumlah_charge'];
                
                // Kalkulasi ulang di backend untuk pengamanan
                $total_tagihan_final = $jumlahtransaksi - $diskon + $charge; 
                $bayar = (int)$_POST['jumlah_bayar'];
                
                $status_bayar = ($bayar >= $total_tagihan_final) ? 'Lunas' : 'Belum Lunas';

                // Kalkulasi Tanggal Habis Sewa
                $date_obj = new DateTime($mulaisewa);
                if ($jenissewa == 'Bulanan') { $date_obj->modify("+$durasi month"); }
                elseif ($jenissewa == 'Mingguan') { $days = $durasi * 7; $date_obj->modify("+$days days"); }
                elseif ($jenissewa == 'Harian') { $date_obj->modify("+$durasi days"); }
                $habissewa = $date_obj->format('Y-m-d');

                // INSERT TRANSAKSI SEWA BARU 
                $stmt_trans = $koneksi->prepare("INSERT INTO table_transaksi (tanggaltransaksi, mulaisewa, habissewa, namatransaksi, diskontransaksi, jumlah_charge, jumlahtransaksi, jumlah_bayar, status_bayar, tanggal_bayar, id_kamar, id_customer, id) VALUES (CURDATE(), ?, ?, 'Sewa Baru', ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)");
                $stmt_trans->execute([$mulaisewa, $habissewa, $diskon, $charge, $jumlahtransaksi, $bayar, $status_bayar, $id_kamar, $new_customer_id, $id_user_aktif]);

                // UPDATE STATUS KAMAR
                $stmt_kamar_update = $koneksi->prepare("UPDATE table_kamar SET status_kamar = 'Terisi' WHERE id_kamar = ?");
                $stmt_kamar_update->execute([$id_kamar]);
            }

            $koneksi->commit();
            header("Location: customer.php");
            exit;

        } catch (Exception $e) {
            $koneksi->rollBack();
            $pesan_error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <a href="customer.php" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Kembali ke Data Customer</a>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">
            <?= $mode_edit ? 'Edit Profil Customer' : 'Pendaftaran Customer & Sewa Baru' ?>
        </h2>

        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm text-sm font-medium"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form action="form_customer.php<?= $mode_edit ? '?edit='.$edit_id : '' ?>" method="POST" enctype="multipart/form-data" onsubmit="return konfirmasiCustomer();">
            <input type="hidden" name="id_customer" value="<?= htmlspecialchars($edit_id) ?>">
            
            <div class="flex flex-col lg:flex-row gap-8">
                
                <div class="flex-1 space-y-6">
                    <h3 class="font-bold text-gray-700 bg-gray-100 p-2 rounded">Informasi Pribadi & Kontak</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">NIK (Sesuai KTP)</label>
                            <input type="text" id="nikcustomer" name="nikcustomer" value="<?= htmlspecialchars($edit_data['nikcustomer']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap</label>
                            <input type="text" id="namacustomer" name="namacustomer" value="<?= htmlspecialchars($edit_data['namacustomer']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">No. HP / WhatsApp</label>
                            <input type="text" name="nohpcustomer" value="<?= htmlspecialchars($edit_data['nohpcustomer']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Kota Asal</label>
                            <input type="text" name="kotaasalcustomer" value="<?= htmlspecialchars($edit_data['kotaasalcustomer']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Alamat Lengkap</label>
                        <textarea name="alamatcustomer" rows="2" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500"><?= htmlspecialchars($edit_data['alamatcustomer']) ?></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 border border-gray-200 rounded">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Kontak Darurat</label>
                            <input type="text" name="namakontakdarurat" value="<?= htmlspecialchars($edit_data['namakontakdarurat']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">No. HP Darurat</label>
                            <input type="text" name="kontakdarurat" value="<?= htmlspecialchars($edit_data['kontakdarurat']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 border border-gray-200 rounded">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Upload KTP</label>
                            <input type="file" name="fotoktpcustomer" accept="image/*" class="text-xs text-gray-500 w-full">
                            <input type="hidden" name="fotoktpcustomer_lama" value="<?= htmlspecialchars($edit_data['fotoktpcustomer']) ?>">
                            <?php if($mode_edit && !empty($edit_data['fotoktpcustomer'])): ?>
                                <a href="uploads/<?= $edit_data['fotoktpcustomer'] ?>" target="_blank" class="text-xs text-blue-600 underline mt-2 block">Lihat KTP Saat Ini</a>
                            <?php endif; ?>
                        </div>
                        <div class="bg-gray-50 p-3 border border-gray-200 rounded">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Upload Selfie</label>
                            <input type="file" name="fotoselfiecustomer" accept="image/*" class="text-xs text-gray-500 w-full">
                            <input type="hidden" name="fotoselfiecustomer_lama" value="<?= htmlspecialchars($edit_data['fotoselfiecustomer']) ?>">
                            <?php if($mode_edit && !empty($edit_data['fotoselfiecustomer'])): ?>
                                <a href="uploads/<?= $edit_data['fotoselfiecustomer'] ?>" target="_blank" class="text-xs text-blue-600 underline mt-2 block">Lihat Selfie Saat Ini</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($mode_edit): ?>
                    <div class="mt-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status Customer</label>
                        <select id="statuscustomer" name="statuscustomer" class="w-full border border-gray-300 px-3 py-2 rounded bg-white">
                            <option value="Aktif" <?= strtolower($edit_data['statuscustomer']) == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="Tidak Aktif" <?= strtolower($edit_data['statuscustomer']) == 'tidak aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$mode_edit): ?>
                <div class="flex-1 space-y-5 bg-yellow-50 p-6 rounded-lg border border-yellow-200 shadow-inner relative">
                    <h3 class="font-bold text-yellow-800 border-b border-yellow-300 pb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Pilih Properti & Transaksi
                    </h3>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih Lokasi Kost</label>
                        <select id="id_kost" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" required>
                            <option value="" disabled selected>-- Pilih Lokasi Kost --</option>
                            <?php foreach($data_kost as $k): ?>
                                <option value="<?= $k['id_kost'] ?>"><?= htmlspecialchars($k['nama_kost']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih Kamar Kosong</label>
                        <select name="id_kamar" id="id_kamar" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-100 cursor-not-allowed" disabled required>
                            <option value="" disabled selected>-- Pilih Kost Terlevelih Dahulu --</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Mulai</label>
                            <input type="date" name="mulaisewa" id="mulaisewa" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Sewa</label>
                            <select name="jenissewa" id="jenissewa" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" required>
                                <option value="Bulanan">Bulanan</option>
                                <option value="Mingguan">Mingguan</option>
                                <option value="Harian">Harian</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Durasi Sewa</label>
                            <select name="durasi" id="durasi" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" required></select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Diskon (Rp)</label>
                            <input type="number" name="diskontransaksi" id="diskontransaksi" value="0" min="0" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Biaya Tambahan / Charge (Rp)</label>
                        <input type="number" name="jumlah_charge" id="jumlah_charge" value="0" min="0" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                    </div>

                    <div class="bg-black text-white p-4 rounded-lg mt-4 shadow-md">
                        <div class="flex justify-between items-center mb-2 border-b border-gray-700 pb-2">
                            <span class="text-sm text-gray-400">Tgl. Habis Sewa:</span>
                            <span id="display_habissewa" class="font-bold text-yellow-500">-</span>
                        </div>
                        <div class="flex justify-between items-end">
                            <span class="text-sm font-semibold">Total Tagihan:</span>
                            <span class="text-2xl font-black text-yellow-500" id="display_total">Rp 0</span>
                            <input type="hidden" name="harga_dasar_hidden" id="harga_dasar_hidden" value="0">
                            <input type="hidden" name="total_harga_hidden" id="total_harga_hidden" value="0">
                        </div>
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 p-4 rounded-lg mt-2">
                        <label class="block text-sm font-bold text-green-800 mb-1">Nominal Telah Dibayar (Rp)</label>
                        <input type="number" name="jumlah_bayar" id="jumlah_bayar" value="0" min="0" required class="w-full border border-green-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500 font-bold text-lg text-gray-800">
                        <p class="text-xs text-green-700 mt-1">*Sistem otomatis menetapkan status <strong>Lunas / Belum Lunas</strong> sesuai nominal bayar vs total tagihan.</p>
                    </div>

                </div>
                <?php endif; ?>
            </div>

            <div class="flex gap-4 mt-8 border-t pt-6">
                <button type="submit" class="w-full md:w-auto bg-black hover:bg-gray-800 text-yellow-500 font-bold py-3 px-10 rounded-md transition-colors shadow-lg text-lg">
                    <?= $mode_edit ? 'Simpan Pembaruan Data' : 'Daftarkan & Buat Transaksi' ?>
                </button>
                <a href="customer.php" class="w-full md:w-auto bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-8 rounded-md text-center transition-colors shadow-sm text-lg">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
    const modeEditJS = <?= json_encode($mode_edit) ?>;

    function konfirmasiCustomer() {
        const nama = document.getElementById('namacustomer').value;
        const nik = document.getElementById('nikcustomer').value;

        if (!nama || !nik) return true;

        let pesan = "";

        if (modeEditJS) {
            const status = document.getElementById('statuscustomer').value;
            pesan = `KONFIRMASI PERUBAHAN PROFIL CUSTOMER:\n\n• Nama Lengkap : ${nama}\n• NIK Customer : ${nik}\n• Status : ${status}\n\nApakah data perubahan profil di atas sudah benar?`;
        } else {
            const kostSelect = document.getElementById('id_kost');
            const kamarSelect = document.getElementById('id_kamar');
            if (kostSelect.value === "" || kamarSelect.selectedIndex <= 0) return true;

            const namaKost = kostSelect.options[kostSelect.selectedIndex].text;
            const nomorKamar = kamarSelect.options[kamarSelect.selectedIndex].text;
            const tglMulai = document.getElementById('mulaisewa').value;
            const jenisSewa = document.getElementById('jenissewa').value;
            const durasi = document.getElementById('durasi').value;
            const diskon = document.getElementById('diskontransaksi').value || 0;
            const charge = document.getElementById('jumlah_charge').value || 0;
            const total = document.getElementById('total_harga_hidden').value;
            const bayar = document.getElementById('jumlah_bayar').value || 0;

            const totalRp = parseInt(total).toLocaleString('id-ID');
            const diskonRp = parseInt(diskon).toLocaleString('id-ID');
            const chargeRp = parseInt(charge).toLocaleString('id-ID');
            const bayarRp = parseInt(bayar).toLocaleString('id-ID');
            
            const statusVisual = (parseInt(bayar) >= parseInt(total)) ? "LUNAS" : "BELUM LUNAS";

            pesan = `KONFIRMASI PENDAFTARAN & SEWA BARU:\n\n[Data Pribadi]\n• Nama : ${nama}\n• NIK : ${nik}\n\n[Rincian Sewa]\n• Properti : ${namaKost}\n• Alokasi : ${nomorKamar}\n• Tgl Mulai : ${tglMulai}\n• Kontrak : ${durasi} ${jenisSewa}\n• Potongan : Rp ${diskonRp}\n• Biaya Charge : Rp ${chargeRp}\n\n[Ringkasan Pembayaran]\n• TOTAL TAGIHAN : Rp ${totalRp}\n• NOMINAL DIBAYAR : Rp ${bayarRp}\n• STATUS SISTEM : ${statusVisual}\n\nApakah rincian pendaftaran ini sudah benar?`;
        }
        return confirm(pesan);
    }
</script>

<?php if (!$mode_edit): ?>
<script>
    const dataKamarSemua = <?= json_encode($data_kamar_kosong) ?>;
    const kostSelect = document.getElementById('id_kost');
    const kamarSelect = document.getElementById('id_kamar');
    const jenissewaSelect = document.getElementById('jenissewa');
    const durasiSelect = document.getElementById('durasi');
    const diskonInput = document.getElementById('diskontransaksi');
    const chargeInput = document.getElementById('jumlah_charge');
    const bayarInput = document.getElementById('jumlah_bayar');
    const mulaiSewaInput = document.getElementById('mulaisewa');
    const displayHabisSewa = document.getElementById('display_habissewa');
    const displayTotal = document.getElementById('display_total');
    const hiddenTotal = document.getElementById('total_harga_hidden');
    const hiddenDasar = document.getElementById('harga_dasar_hidden');

    let tarifAktif = 0;

    kostSelect.addEventListener('change', function() {
        const selectedKost = this.value;
        kamarSelect.innerHTML = '<option value="" disabled selected>-- Pilih Nomor Kamar --</option>';
        const kamarFilter = dataKamarSemua.filter(k => k.id_kost == selectedKost);
        
        if (kamarFilter.length > 0) {
            kamarSelect.disabled = false;
            kamarSelect.classList.remove('bg-gray-100', 'cursor-not-allowed');
            kamarSelect.classList.add('bg-white');
            kamarFilter.forEach(k => {
                const opt = document.createElement('option');
                opt.value = k.id_kamar;
                opt.dataset.bulanan = k.harga_kamar || 0;
                opt.dataset.mingguan = k.harga_minggu || 0;
                opt.dataset.harian = k.harga_hari || 0;
                opt.textContent = `Kamar ${k.nomor_kamar} (${k.jenis_kamar})`;
                kamarSelect.appendChild(opt);
            });
        } else {
            kamarSelect.innerHTML = '<option value="" disabled selected>Kamar Kosong Tidak Tersedia</option>';
            kamarSelect.disabled = true;
            kamarSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
            kamarSelect.classList.remove('bg-white');
        }
        kalkulasiSemua();
    });

    function updateOpsiDurasi() {
        const jenis = jenissewaSelect.value;
        const durasiSaatIni = durasiSelect.value;
        durasiSelect.innerHTML = '';
        let batas = 12; label = 'Bulan';
        if (jenis === 'Mingguan') { batas = 4; label = 'Minggu'; }
        else if (jenis === 'Harian') { batas = 6; label = 'Hari'; }

        for (let i = 1; i <= batas; i++) {
            const opt = document.createElement('option');
            opt.value = i; opt.textContent = `${i} ${label}`;
            durasiSelect.appendChild(opt);
        }
        if (durasiSaatIni && durasiSaatIni <= batas) { durasiSelect.value = durasiSaatIni; }
        kalkulasiSemua();
    }
    jenissewaSelect.addEventListener('change', updateOpsiDurasi);
    updateOpsiDurasi(); 
    
    // Flag untuk deteksi apakah input bayar sudah diisi manual
    bayarInput.addEventListener('input', () => {
        bayarInput.dataset.manual = 'true';
    });

    function kalkulasiSemua() {
        if (kamarSelect.selectedIndex > 0) {
            const optKamar = kamarSelect.options[kamarSelect.selectedIndex];
            const jenis = jenissewaSelect.value;
            if (jenis === 'Bulanan') tarifAktif = parseInt(optKamar.dataset.bulanan);
            else if (jenis === 'Mingguan') tarifAktif = parseInt(optKamar.dataset.mingguan);
            else if (jenis === 'Harian') tarifAktif = parseInt(optKamar.dataset.harian);
        } else { tarifAktif = 0; }

        const durasi = parseInt(durasiSelect.value) || 1;
        const diskon = parseInt(diskonInput.value) || 0;
        const charge = parseInt(chargeInput.value) || 0;
        
        let hargaDasar = tarifAktif * durasi; // Nilai murni sebelum diutak-atik
        let total = hargaDasar - diskon + charge; // Nilai penagihan riil
        if (total < 0) total = 0;
        
        displayTotal.textContent = 'Rp ' + total.toLocaleString('id-ID');
        hiddenTotal.value = total;
        hiddenDasar.value = hargaDasar;
        
        // Auto-fill jumlah bayar dengan total HANYA jika admin belum mengeditnya secara manual
        if (bayarInput.dataset.manual !== 'true') {
            bayarInput.value = total;
        }

        const tglMulai = mulaiSewaInput.value;
        if (tglMulai) {
            const dateObj = new Date(tglMulai);
            if (jenissewaSelect.value === 'Bulanan') { dateObj.setMonth(dateObj.getMonth() + durasi); } 
            else if (jenissewaSelect.value === 'Mingguan') { dateObj.setDate(dateObj.getDate() + (durasi * 7)); } 
            else if (jenissewaSelect.value === 'Harian') { dateObj.setDate(dateObj.getDate() + durasi); }
            const opsi = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            displayHabisSewa.textContent = dateObj.toLocaleDateString('id-ID', opsi);
        } else { displayHabisSewa.textContent = '-'; }
    }

    kamarSelect.addEventListener('change', kalkulasiSemua);
    durasiSelect.addEventListener('change', kalkulasiSemua);
    diskonInput.addEventListener('input', kalkulasiSemua);
    chargeInput.addEventListener('input', kalkulasiSemua);
    mulaiSewaInput.addEventListener('change', kalkulasiSemua);
    
    const hariIni = new Date().toISOString().split('T')[0];
    mulaiSewaInput.value = hariIni;
    kalkulasiSemua();
</script>
<?php endif; ?>

<?php require 'footer.php'; ?>