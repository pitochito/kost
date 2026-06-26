<?php
require 'koneksi.php';
require 'header.php';

// ==============================================================================
// 1. SKRIP SILUMAN (AUTO-UPDATE STATUS JATUH TEMPO)
// ==============================================================================
// Cari transaksi yang masa sewanya SUDAH HABIS (habissewa < hari ini) 
// DAN status customernya masih 'Aktif'
$stmt_cek_expired = $koneksi->query("
    SELECT t.id_kamar, t.id_customer 
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    WHERE t.habissewa < CURDATE() AND c.statuscustomer = 'Aktif'
    -- Ambil transaksi terakhir untuk tiap customer agar tidak salah update
    AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = t.id_customer)
");
$expired_data = $stmt_cek_expired->fetchAll(PDO::FETCH_ASSOC);

if (!empty($expired_data)) {
    try {
        $koneksi->beginTransaction();
        $stmt_update_cust = $koneksi->prepare("UPDATE table_customer SET statuscustomer = 'Tidak Aktif' WHERE id_customer = ?");
        $stmt_update_kamar = $koneksi->prepare("UPDATE table_kamar SET status_kamar = 'Kosong' WHERE id_kamar = ?");
        
        foreach ($expired_data as $exp) {
            $stmt_update_cust->execute([$exp['id_customer']]);
            $stmt_update_kamar->execute([$exp['id_kamar']]);
        }
        $koneksi->commit();
    } catch (Exception $e) {
        $koneksi->rollBack();
        // Gagal update siluman, abaikan (akan dicoba lagi saat refresh)
    }
}
// ==============================================================================


// 2. AMBIL DATA USER AKTIF
$stmt_user = $koneksi->prepare("SELECT username FROM table_user WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$user_aktif = $stmt_user->fetchColumn();

// 3. KALKULASI STATISTIK PROPERTI
$total_kost = $koneksi->query("SELECT COUNT(*) FROM table_kost")->fetchColumn();
$total_kamar = $koneksi->query("SELECT COUNT(*) FROM table_kamar")->fetchColumn();
$kamar_isi = $koneksi->query("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) = 'isi' OR LOWER(status_kamar) = 'terisi'")->fetchColumn();
$kamar_kosong = $koneksi->query("SELECT COUNT(*) FROM table_kamar WHERE LOWER(status_kamar) = 'kosong'")->fetchColumn();


// 4. QUERY PERINGATAN JATUH TEMPO MENDATANG (WARNING)
$query_warning = "
    SELECT 
        c.id_customer, c.namacustomer, c.nohpcustomer,
        k.nomor_kamar, k.jenis_kamar,
        ko.nama_kost,
        t.habissewa,
        DATEDIFF(t.habissewa, CURDATE()) as sisa_hari,
        DATEDIFF(t.habissewa, t.mulaisewa) as total_durasi_hari
    FROM table_transaksi t
    JOIN table_customer c ON t.id_customer = c.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE c.statuscustomer = 'Aktif' 
    AND t.habissewa >= CURDATE()
    AND t.id_transaksi = (SELECT MAX(id_transaksi) FROM table_transaksi WHERE id_customer = t.id_customer)
    HAVING 
        (total_durasi_hari > 20 AND sisa_hari <= 7) OR   -- Bulanan
        (total_durasi_hari > 6 AND total_durasi_hari <= 20 AND sisa_hari <= 3) OR -- Mingguan
        (total_durasi_hari <= 6 AND sisa_hari <= 1)      -- Harian
    ORDER BY sisa_hari ASC
";
$stmt_warning = $koneksi->query($query_warning);
$data_warning = $stmt_warning->fetchAll(PDO::FETCH_ASSOC);

// 5. KALKULASI KEUANGAN (PEMASUKAN & PENGELUARAN)
$stmt_in = $koneksi->query("SELECT SUM(jumlahtransaksi) FROM table_transaksi");
$total_pemasukan = $stmt_in->fetchColumn() ?: 0;

$stmt_out = $koneksi->query("SELECT SUM(jumlahpengeluaran) FROM table_pengeluaran");
$total_pengeluaran = $stmt_out->fetchColumn() ?: 0;

$saldo_bersih = $total_pemasukan - $total_pengeluaran;

?>

<div class="mb-8">
    <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight">Selamat datang, <span class="text-yellow-600 capitalize"><?= htmlspecialchars($user_aktif) ?></span>!</h1>
    <p class="text-gray-500 mt-2 text-sm">Berikut adalah ringkasan performa dan statistik properti Kost Sun Anda saat ini.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Lokasi Kost</p>
        <p class="text-3xl font-black text-gray-800"><?= $total_kost ?> <span class="text-sm font-medium text-gray-400">Properti</span></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Total Keseluruhan Kamar</p>
        <p class="text-3xl font-black text-gray-800"><?= $total_kamar ?> <span class="text-sm font-medium text-gray-400">Pintu</span></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Kamar Terisi</p>
        <p class="text-3xl font-black text-green-600"><?= $kamar_isi ?> <span class="text-sm font-medium text-green-400">Pintu</span></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500 flex flex-col justify-center">
        <p class="text-sm font-semibold text-gray-500 mb-1">Kamar Kosong</p>
        <p class="text-3xl font-black text-red-600"><?= $kamar_kosong ?> <span class="text-sm font-medium text-red-400">Pintu</span></p>
    </div>
</div>

<?php if (!empty($data_warning)): ?>
<div class="mb-10 bg-red-50 border border-red-200 rounded-xl overflow-hidden shadow-sm">
    <div class="bg-red-500 px-6 py-4 flex items-center gap-3">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <h3 class="text-lg font-bold text-white tracking-wide">PERHATIAN: Sewa Segera Berakhir</h3>
    </div>
    
    <div class="p-6 overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[700px]">
            <thead class="border-b border-red-200">
                <tr>
                    <th class="py-2 px-2 text-sm font-bold text-red-800">Customer & Kontak</th>
                    <th class="py-2 px-2 text-sm font-bold text-red-800">Properti</th>
                    <th class="py-2 px-2 text-sm font-bold text-red-800">Sisa Waktu</th>
                    <th class="py-2 px-2 text-sm font-bold text-red-800 text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-red-100">
                <?php foreach ($data_warning as $warn): ?>
                <tr class="hover:bg-red-100 transition-colors">
                    <td class="py-3 px-2">
                        <p class="font-bold text-gray-800"><?= htmlspecialchars($warn['namacustomer']) ?></p>
                        <p class="text-xs font-semibold text-red-600"><?= htmlspecialchars($warn['nohpcustomer']) ?></p>
                    </td>
                    <td class="py-3 px-2">
                        <p class="font-semibold text-gray-700"><?= htmlspecialchars($warn['nama_kost']) ?></p>
                        <p class="text-xs text-gray-600">Kamar <?= htmlspecialchars($warn['nomor_kamar']) ?> (<?= htmlspecialchars($warn['jenis_kamar']) ?>)</p>
                    </td>
                    <td class="py-3 px-2">
                        <?php if ($warn['sisa_hari'] == 0): ?>
                            <span class="bg-red-600 text-white px-2 py-1 rounded text-xs font-bold animate-pulse">HARI INI</span>
                        <?php else: ?>
                            <span class="bg-orange-100 text-orange-800 border border-orange-300 px-2 py-1 rounded text-xs font-bold">
                                <?= $warn['sisa_hari'] ?> Hari Lagi (<?= date('d M Y', strtotime($warn['habissewa'])) ?>)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-2 text-center">
                        <a href="perpanjang.php?id=<?= $warn['id_customer'] ?>" class="inline-block bg-black hover:bg-gray-800 text-yellow-500 px-4 py-2 rounded text-xs font-bold shadow-sm transition-transform hover:scale-105">
                            + Perpanjang Sewa
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="mt-8 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-gray-800">Ringkasan Arus Kas</h2>
        <a href="keuangan.php" class="text-sm text-blue-600 hover:underline font-semibold">Buka Modul Keuangan &rarr;</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-green-100 border-l-4 border-l-green-500 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-500 mb-1">Total Pemasukan</p>
            <p class="text-2xl font-black text-green-600">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-red-100 border-l-4 border-l-red-500 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-500 mb-1">Total Pengeluaran</p>
            <p class="text-2xl font-black text-red-600">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></p>
        </div>
        <div class="bg-gray-900 p-6 rounded-xl shadow-md border border-gray-800 flex flex-col justify-center">
            <p class="text-sm font-semibold text-gray-400 mb-1">Saldo Bersih Saat Ini</p>
            <p class="text-2xl font-black text-yellow-500">Rp <?= number_format($saldo_bersih, 0, ',', '.') ?></p>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>