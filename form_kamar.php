<?php
require 'koneksi.php';
require 'header.php';

if (!isset($_GET['id_kost'])) {
    header("Location: index.php");
    exit;
}

$id_kost = $_GET['id_kost'];
$pesan_error = '';
$mode_edit = false;

// Nilai default form
$edit_id = '';
$edit_nomor = '';
$edit_status = 'Kosong';
$edit_jenis = 'Non-AC';
$edit_harga = '';
$edit_harga_minggu = '';
$edit_harga_hari = '';
$edit_letak = '';

// TANGKAP DATA JIKA MODE EDIT
if (isset($_GET['edit'])) {
    $mode_edit = true;
    $edit_id = $_GET['edit'];
    $stmt = $koneksi->prepare("SELECT * FROM table_kamar WHERE id_kamar = ? AND id_kost = ?");
    $stmt->execute([$edit_id, $id_kost]);
    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data_edit) {
        $edit_nomor = $data_edit['nomor_kamar'];
        $edit_status = $data_edit['status_kamar'];
        $edit_jenis = $data_edit['jenis_kamar'];
        $edit_harga = $data_edit['harga_kamar'];
        $edit_harga_minggu = $data_edit['harga_minggu'];
        $edit_harga_hari = $data_edit['harga_hari'];
        $edit_letak = $data_edit['letak_kamar'];
    }
}

// PROSES SIMPAN DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor = trim($_POST['nomor_kamar']);
    $status = $_POST['status_kamar'];
    $jenis = $_POST['jenis_kamar'];
    $letak = trim($_POST['letak_kamar']);
    $id_kamar_post = $_POST['id_kamar'] ?? '';

    // Hilangkan titik untuk format uang
    $harga = str_replace('.', '', $_POST['harga_kamar']); 
    $harga_minggu = str_replace('.', '', $_POST['harga_minggu']); 
    $harga_hari = str_replace('.', '', $_POST['harga_hari']); 

    if (empty($nomor)) {
        $pesan_error = "Nomor Kamar wajib diisi!";
    } else {
        if (!empty($id_kamar_post)) {
            $stmt = $koneksi->prepare("UPDATE table_kamar SET nomor_kamar=?, status_kamar=?, jenis_kamar=?, harga_kamar=?, harga_minggu=?, harga_hari=?, letak_kamar=? WHERE id_kamar=? AND id_kost=?");
            $stmt->execute([$nomor, $status, $jenis, $harga, $harga_minggu, $harga_hari, $letak, $id_kamar_post, $id_kost]);
        } else {
            $stmt = $koneksi->prepare("INSERT INTO table_kamar (nomor_kamar, status_kamar, jenis_kamar, harga_kamar, harga_minggu, harga_hari, letak_kamar, id_kost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nomor, $status, $jenis, $harga, $harga_minggu, $harga_hari, $letak, $id_kost]);
        }
        header("Location: kamar.php?id_kost=$id_kost");
        exit;
    }
}
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="kamar.php?id_kost=<?= $id_kost ?>" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Kembali ke Daftar Kamar</a>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">
            <?= $mode_edit ? 'Edit Data Kamar' : 'Tambah Kamar Baru' ?>
        </h2>

        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form action="form_kamar.php?id_kost=<?= $id_kost ?><?= $mode_edit ? '&edit='.$edit_id : '' ?>" method="POST" class="flex flex-col gap-5">
            <input type="hidden" name="id_kamar" value="<?= htmlspecialchars($edit_id) ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">No. Kamar</label>
                    <input type="text" name="nomor_kamar" value="<?= htmlspecialchars($edit_nomor) ?>" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" placeholder="Misal: 101">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Letak Lantai</label>
                    <input type="text" name="letak_kamar" value="<?= htmlspecialchars($edit_letak) ?>" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" placeholder="Misal: Lantai 1">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Fasilitas Kamar</label>
                    <select name="jenis_kamar" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                        <option value="Non-AC" <?= $edit_jenis == 'Non-AC' ? 'selected' : '' ?>>Non-AC</option>
                        <option value="AC" <?= $edit_jenis == 'AC' ? 'selected' : '' ?>>AC</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status Kamar</label>
                    <select name="status_kamar" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                        <option value="Kosong" <?= strtolower($edit_status) == 'kosong' ? 'selected' : '' ?>>Kosong</option>
                        <option value="Terisi" <?= strtolower($edit_status) == 'isi' || strtolower($edit_status) == 'terisi' ? 'selected' : '' ?>>Terisi</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
                <h3 class="font-bold text-gray-700 mb-4">Pengaturan Tarif Sewa (Rp)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tarif Bulanan</label>
                        <input type="number" name="harga_kamar" value="<?= htmlspecialchars($edit_harga) ?>" class="w-full border border-gray-300 px-3 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-50" placeholder="Misal: 850000">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tarif Mingguan</label>
                        <input type="number" name="harga_minggu" value="<?= htmlspecialchars($edit_harga_minggu) ?>" class="w-full border border-gray-300 px-3 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-50" placeholder="Misal: 300000">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tarif Harian</label>
                        <input type="number" name="harga_hari" value="<?= htmlspecialchars($edit_harga_hari) ?>" class="w-full border border-gray-300 px-3 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-50" placeholder="Misal: 100000">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-4">
                <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 px-4 rounded-md transition-colors shadow-sm">
                    <?= $mode_edit ? 'Simpan Perubahan' : 'Simpan Kamar' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>