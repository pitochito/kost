<?php
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$mode_edit = false;

// Nilai bawaan (default)
$edit_id = '';
$edit_username = '';
$edit_role = 'admin';

// TANGKAP DATA JIKA MODE EDIT
if (isset($_GET['edit'])) {
    $mode_edit = true;
    $edit_id = $_GET['edit'];
    $stmt = $koneksi->prepare("SELECT id, username, role FROM table_user WHERE id = ?");
    $stmt->execute([$edit_id]);
    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data_edit) {
        $edit_username = $data_edit['username'];
        $edit_role = $data_edit['role'];
    } else {
        header("Location: user.php");
        exit;
    }
}

// PROSES SIMPAN DATA (TAMBAH / UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = $_POST['password'] ?? '';
    $id_user_post = $_POST['id_user'] ?? '';

    // Validasi Cek Ketersediaan Username (Agar tidak ada username ganda)
    $stmt_cek = $koneksi->prepare("SELECT id FROM table_user WHERE username = ? AND id != ?");
    $stmt_cek->execute([$username, $id_user_post]);
    
    if (empty($username)) {
        $pesan_error = "Username wajib diisi!";
    } elseif ($stmt_cek->rowCount() > 0) {
        $pesan_error = "Username '$username' sudah digunakan. Silakan pilih username lain.";
    } else {
        if (!empty($id_user_post)) {
            // PROSES UPDATE
            if (!empty($password)) {
                // Jika password diisi, update beserta password barunya
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("UPDATE table_user SET username=?, password=?, role=? WHERE id=?");
                $stmt->execute([$username, $password_hash, $role, $id_user_post]);
            } else {
                // Jika password kosong, biarkan password lama, hanya update username & role
                $stmt = $koneksi->prepare("UPDATE table_user SET username=?, role=? WHERE id=?");
                $stmt->execute([$username, $role, $id_user_post]);
            }
        } else {
            // PROSES TAMBAH BARU
            if (empty($password)) {
                $pesan_error = "Password wajib diisi untuk user baru!";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("INSERT INTO table_user (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$username, $password_hash, $role]);
            }
        }
        
        if (empty($pesan_error)) {
            header("Location: user.php");
            exit;
        }
    }
}
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="user.php" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Kembali ke Daftar Pengguna</a>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">
            <?= $mode_edit ? 'Edit Akses Pengguna' : 'Tambah Pengguna Baru' ?>
        </h2>

        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm text-sm font-medium"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form action="form_user.php<?= $mode_edit ? '?edit='.$edit_id : '' ?>" method="POST" class="flex flex-col gap-5">
            <input type="hidden" name="id_user" value="<?= htmlspecialchars($edit_id) ?>">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Username Login</label>
                <input type="text" name="username" value="<?= htmlspecialchars($edit_username) ?>" 
                       class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-gray-50" required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Pilih Hak Akses (Role)</label>
                <select name="role" class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                    <option value="admin" <?= strtolower($edit_role) == 'admin' ? 'selected' : '' ?>>Admin (Standar)</option>
                    <option value="super admin" <?= strtolower($edit_role) == 'super admin' ? 'selected' : '' ?>>Super Admin (Akses Penuh)</option>
                </select>
            </div>

            <hr class="border-gray-200 my-2">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <?= $mode_edit ? 'Reset Password Baru (Opsional)' : 'Password' ?>
                </label>
                <input type="password" name="password" 
                       class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white" 
                       <?= $mode_edit ? 'placeholder="Kosongkan jika tidak ingin mereset password"' : 'required' ?>>
                <?php if ($mode_edit): ?>
                    <p class="text-xs text-gray-400 mt-1">*Hanya isi kolom ini jika Anda ingin mengganti password pengguna tersebut.</p>
                <?php endif; ?>
            </div>

            <div class="flex gap-3 mt-4">
                <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 px-4 rounded-md transition-colors shadow-sm">
                    <?= $mode_edit ? 'Simpan Perubahan' : 'Buat Akun' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>