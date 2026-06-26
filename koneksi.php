<?php
// Konfigurasi db dinamis (Lokal vs Railway)
$host     = getenv('MYSQLHOST') ?: "127.0.0.1";
$port     = getenv('MYSQLPORT') ?: "3306";
$database = getenv('MYSQLDATABASE') ?: "db_kost";
$username = getenv('MYSQLUSER') ?: "root";
$password = getenv('MYSQLPASSWORD') ?: "";

// Lanjutkan dengan kode koneksi PDO atau mysqli di bawahnya...
try {
    // Inisialisasi koneksi via PDO (PHP Data Objects)
    $koneksi = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password);
    
    // Set error mode, Mengatur agar sistem menampilkan pesan error jika ada kesalahan database
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Pesan sukses sementara untuk pengujian
    //echo "Koneksi berhasil.";

} catch(PDOException $e) {
    // Tampilkan error jika koneksi gagal
    echo "Error koneksi: " . $e->getMessage();
}
?>