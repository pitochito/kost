<?php
// Konfigurasi db
$host     = "127.0.0.1"; // Alamat server lokal
$port     = "3306";      // Port default MySQL DBngin
$database = "db_kost";   // Nama database
$username = "root";      // Username default
$password = "";          // Dikosongkan, DBngin tidak menggunakan password secara default

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