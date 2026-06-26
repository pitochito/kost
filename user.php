<?php
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$pesan_sukses = '';

// PROSES HAPUS DATA USER
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    
    // Keamanan: Cegah user menghapus dirinya sendiri yang sedang login
    if ($id_hapus == $_SESSION['user_id']) {
        $pesan_error = "Akses ditolak: Anda tidak dapat menghapus akun Anda sendiri saat sedang menggunakannya!";
    } else {
        $stmt = $koneksi->prepare("DELETE FROM table_user WHERE id = ?");
        $stmt->execute([$id_hapus]);
        header("Location: user.php?pesan=sukses_hapus");
        exit;
    }
}

if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses_hapus') {
    $pesan_sukses = "Akun pengguna berhasil dihapus.";
}

// AMBIL SEMUA DATA USER (Kecuali Password)
$query = "SELECT id, username, role FROM table_user ORDER BY id DESC";
$stmt = $koneksi->prepare($query);
$stmt->execute();
$data_user = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Manajemen Akses Sistem</h2>
        <p class="text-sm text-gray-500 mt-1">Kelola daftar pengguna dan hak akses (role) mereka.</p>
    </div>
    <a href="form_user.php" class="bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-4 rounded transition-colors shadow-sm">
        + Tambah User Baru
    </a>
</div>

<?php if ($pesan_error): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_error ?></div>
<?php endif; ?>
<?php if ($pesan_sukses): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_sukses ?></div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
    <table class="w-full text-left border-collapse min-w-[500px]">
        <thead class="bg-gray-100 border-b border-gray-200">
            <tr>
                <th class="py-3 px-4 text-sm font-bold text-gray-600 w-16">ID</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">Username</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">Hak Akses (Role)</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600 text-center w-48">Tindakan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($data_user as $user) : ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="py-3 px-4 text-gray-500 font-medium">#<?= htmlspecialchars($user['id']) ?></td>
                <td class="py-3 px-4 font-bold text-gray-800"><?= htmlspecialchars($user['username']) ?></td>
                <td class="py-3 px-4">
                    <?php if (strtolower($user['role']) == 'super admin'): ?>
                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">Super Admin</span>
                    <?php else: ?>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider"><?= htmlspecialchars($user['role']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4 flex justify-center gap-2">
                    <a href="form_user.php?edit=<?= $user['id'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Edit</a>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="user.php?hapus=<?= $user['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus akun <?= htmlspecialchars($user['username']) ?>?');" class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Hapus</a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 text-xs font-semibold text-gray-400 italic">Akun Anda</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require 'footer.php'; ?>