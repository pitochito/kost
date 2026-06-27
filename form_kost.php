<?php
session_start();
require 'koneksi.php';

// Proteksi Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// PROTEKSI HALAMAN: HANYA SUPER ADMIN YANG BISA MENGAKSES FORM INI
$stmt_role = $koneksi->prepare("SELECT role FROM table_user WHERE id = ?");
$stmt_role->execute([$_SESSION['user_id']]);
$role_aktif = strtolower($stmt_role->fetchColumn());

if ($role_aktif !== 'super admin') {
    echo "<script>alert('Akses Ditolak: Hanya Super Admin yang diizinkan untuk menambah atau mengubah data properti Kost.'); window.location.href='data_kost.php';</script>";
    exit;
}

$pesan_error = '';
$mode_edit = false;
$edit_id = '';
$edit_nama = '';
$edit_alamat = '';
$edit_kota = '';

// TANGKAP DATA JIKA MODE EDIT
if (isset($_GET['edit'])) {
    $mode_edit = true;
    $edit_id = $_GET['edit'];
    $stmt = $koneksi->prepare("SELECT * FROM table_kost WHERE id_kost = ?");
    $stmt->execute([$edit_id]);
    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data_edit) {
        $edit_nama = $data_edit['nama_kost'];
        $edit_alamat = $data_edit['alamat_kost'];
        $edit_kota = $data_edit['kota_kost'];
    }
}

// PROSES SIMPAN DATA (TAMBAH / UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_kost']);
    $alamat = trim($_POST['alamat_kost']);
    $kota = trim($_POST['kota_kost']);
    $id_kost_post = $_POST['id_kost'] ?? '';

    if (empty($nama) || empty($alamat)) {
        $pesan_error = "Nama Kost dan Alamat wajib diisi!";
    } else {
        if (!empty($id_kost_post)) {
            $stmt = $koneksi->prepare("UPDATE table_kost SET nama_kost = ?, alamat_kost = ?, kota_kost = ? WHERE id_kost = ?");
            $stmt->execute([$nama, $alamat, $kota, $id_kost_post]);
        } else {
            $stmt = $koneksi->prepare("INSERT INTO table_kost (nama_kost, alamat_kost, kota_kost, kecamatan_kost, kelurahan_kost) VALUES (?, ?, ?, '', '')");
            $stmt->execute([$nama, $alamat, $kota]);
        }
        // Jika sukses, kembalikan ke halaman utama
        header("Location: index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode_edit ? 'Edit Kost' : 'Tambah Kost Baru' ?> - Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">

    <header class="bg-black shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center">
            <img src="logo.jpg" alt="Logo Kost Sun" class="h-12 object-contain rounded mr-4">
            <h1 class="text-2xl font-bold text-yellow-500 tracking-wide">KOST SUN</h1>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-12 w-full">
        
        <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">
                <?= $mode_edit ? 'Edit Data Kost' : 'Tambah Kost Baru' ?>
            </h2>

            <?php if ($pesan_error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_error ?></div>
            <?php endif; ?>

            <form action="form_kost.php<?= $mode_edit ? '?edit='.$edit_id : '' ?>" method="POST" class="flex flex-col gap-5">
                <input type="hidden" name="id_kost" value="<?= htmlspecialchars($edit_id) ?>">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Kost</label>
                    <input type="text" name="nama_kost" value="<?= htmlspecialchars($edit_nama) ?>" 
                           class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 transition-shadow">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Kota</label>
                    <input type="text" name="kota_kost" value="<?= htmlspecialchars($edit_kota) ?>" 
                           class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 transition-shadow">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Alamat Lengkap</label>
                    <textarea name="alamat_kost" rows="4" 
                              class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 transition-shadow"><?= htmlspecialchars($edit_alamat) ?></textarea>
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 px-4 rounded-md transition-colors shadow-sm">
                        <?= $mode_edit ? 'Simpan Perubahan' : 'Simpan Kost Baru' ?>
                    </button>
                    <a href="data_kost.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-md text-center transition-colors shadow-sm">Batal</a>
                </div>
            </form>
        </div>

    </main>

    <footer class="border-t border-gray-200 bg-white py-6 mt-8 text-gray-500">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm">
            <p>&copy; <?= date('Y') ?> Kost Sun Management. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>