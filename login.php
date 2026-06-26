<?php
session_start();
require 'koneksi.php';

// Jika sudah login, langsung tendang ke index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$pesan_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $koneksi->prepare("SELECT * FROM table_user WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cek apakah user ada DAN password cocok dengan hash di database
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: index.php");
        exit;
    } else {
        $pesan_error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-sm border border-gray-200">
        <div class="flex justify-center mb-6">
            <img src="logo.jpg" alt="Logo Kost Sun" class="h-16 object-contain rounded">
        </div>
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Login Sistem</h2>
        
        <?php if ($pesan_error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center font-medium"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="flex flex-col gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                <input type="text" name="username" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                <input type="password" name="password" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
            </div>
            <button type="submit" class="bg-black hover:bg-gray-800 text-yellow-500 font-bold py-2 px-4 rounded transition-colors mt-2">
                Masuk
            </button>
        </form>
    </div>
</body>
</html>