<?php
// 1. Memanggil koneksi dan header (yang di dalamnya sudah ada proteksi sesi login)
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$pesan_sukses = '';

// 2. Proses saat tombol Simpan ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil inputan dari form
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';
    $user_id = $_SESSION['user_id'];

    // Ambil hash password lama dari database untuk dicocokkan
    $stmt = $koneksi->prepare("SELECT password FROM table_user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Validasi Bertingkat
    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $pesan_error = "Semua kolom wajib diisi!";
    } elseif (!password_verify($password_lama, $user['password'])) {
        $pesan_error = "Password lama yang Anda masukkan salah.";
    } elseif ($password_baru !== $konfirmasi_password) {
        $pesan_error = "Password baru dan konfirmasi password tidak cocok.";
    } elseif (strlen($password_baru) < 4) {
        $pesan_error = "Password baru terlalu pendek (minimal 4 karakter).";
    } else {
        // Jika semua lolos, enkripsi password baru
        $password_hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);

        // Update ke database
        $stmt_update = $koneksi->prepare("UPDATE table_user SET password = ? WHERE id = ?");
        if ($stmt_update->execute([$password_hash_baru, $user_id])) {
            $pesan_sukses = "Password berhasil diperbarui! Silakan gunakan password baru untuk login selanjutnya.";
        } else {
            $pesan_error = "Sistem gagal memperbarui password. Silakan coba lagi.";
        }
    }
}
?>

<main class="max-w-2xl mx-auto px-4 py-8 w-full flex-1">
    
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Keamanan Akun</h2>
        <p class="text-sm text-gray-500 mt-1">Perbarui password Anda secara berkala untuk menjaga keamanan sistem.</p>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-200">
        
        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm font-medium text-sm"><?= $pesan_error ?></div>
        <?php endif; ?>
        
        <?php if ($pesan_sukses): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-medium text-sm"><?= $pesan_sukses ?></div>
        <?php endif; ?>

        <form action="ubah_password.php" method="POST" class="flex flex-col gap-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Password Lama</label>
                <input type="password" name="password_lama" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-50" required>
            </div>

            <hr class="border-gray-200 my-2">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Password Baru</label>
                <input type="password" name="password_baru" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Ketik Ulang Password Baru</label>
                <input type="password" name="konfirmasi_password" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" required>
            </div>

            <div class="mt-4">
                <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 px-4 rounded-md transition-colors shadow-sm">
                    Simpan Password Baru
                </button>
            </div>
        </form>
    </div>

</main>

<?php 
// 5. Memanggil Footer
require 'footer.php'; 
?>