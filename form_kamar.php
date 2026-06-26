<?php
require 'koneksi.php';

// Wajib ada id_kost
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
        $edit_letak = $data_edit['letak_kamar'];
    }
}

// PROSES SIMPAN DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor = trim($_POST['nomor_kamar']);
    $status = $_POST['status_kamar'];
    $jenis = $_POST['jenis_kamar'];
    $harga = trim($_POST['harga_kamar']);
    $letak = trim($_POST['letak_kamar']);
    $id_kamar_post = $_POST['id_kamar'] ?? '';

    // Validasi sederhana: Nomor Kamar tidak boleh kosong
    if (empty($nomor)) {
        $pesan_error = "Nomor Kamar wajib diisi!";
    } else {
        // Hilangkan titik jika pengguna mengetik format uang, misal 850.000 menjadi 850000
        $harga = str_replace('.', '', $harga); 

        if (!empty($id_kamar_post)) {
            $stmt = $koneksi->prepare("UPDATE table_kamar SET nomor_kamar=?, status_kamar=?, jenis_kamar=?, harga_kamar=?, letak_kamar=? WHERE id_kamar=? AND id_kost=?");
            $stmt->execute([$nomor, $status, $jenis, $harga, $letak, $id_kamar_post, $id_kost]);
        } else {
            $stmt = $koneksi->prepare("INSERT INTO table_kamar (nomor_kamar, status_kamar, jenis_kamar, harga_kamar, letak_kamar, id_kost) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nomor, $status, $jenis, $harga, $letak, $id_kost]);
        }
        header("Location: kamar.php?id_kost=$id_kost");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode_edit ? 'Edit Kamar' : 'Tambah Kamar' ?> - Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <header class="bg-black shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center">
            <img src="logo.jpg" alt="Logo Kost Sun" class="h-12 object-contain rounded mr-4">
            <h1 class="text-2xl font-bold text-yellow-500 tracking-wide">KOST SUN</h1>
        </div>
    </header>

    <main class="max-w-xl mx-auto px-4 py-12 w-full flex-1">
        
        <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">
                <?= $mode_edit ? 'Edit Data Kamar' : 'Tambah Kamar Baru' ?>
            </h2>

            <?php if ($pesan_error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_error ?></div>
            <?php endif; ?>

            <form action="form_kamar.php?id_kost=<?= $id_kost ?><?= $mode_edit ? '&edit='.$edit_id : '' ?>" method="POST" class="flex flex-col gap-5">
                <input type="hidden" name="id_kamar" value="<?= htmlspecialchars($edit_id) ?>">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">No. Kamar</label>
                        <input type="text" name="nomor_kamar" value="<?= htmlspecialchars($edit_nomor) ?>" 
                               class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-50" placeholder="Misal: 101">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Letak Lantai</label>
                        <input type="text" name="letak_kamar" value="<?= htmlspecialchars($edit_letak) ?>" 
                               class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-50" placeholder="Misal: Lantai 1">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
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

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Harga Sewa (Rp)</label>
                    <input type="number" name="harga_kamar" value="<?= htmlspecialchars($edit_harga) ?>" 
                           class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-50" placeholder="Misal: 850000">
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 px-4 rounded-md transition-colors shadow-sm">
                        <?= $mode_edit ? 'Simpan Perubahan' : 'Simpan Kamar' ?>
                    </button>
                    <a href="kamar.php?id_kost=<?= $id_kost ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-md text-center transition-colors shadow-sm">Batal</a>
                </div>
            </form>
        </div>

    </main>

    <footer class="border-t border-gray-200 bg-white py-6 mt-8 text-center text-sm text-gray-500 w-full">
        <p>&copy; <?= date('Y') ?> Kost Sun Management. All rights reserved.</p>
    </footer>

</body>
</html>