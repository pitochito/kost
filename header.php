<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pengelola Kost Sun</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <header class="bg-black shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <img src="logo.jpg" alt="Logo Kost Sun" class="h-12 object-contain rounded">
                <h1 class="text-2xl font-bold text-yellow-500 tracking-wide">SISTEM PENGELOLA KOST</h1>
            </div>
            <a href="logout.php" onclick="return confirm('Yakin ingin keluar?');" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm font-bold transition-colors">
                Logout
            </a>
        </div>
    </header>