<?php
require 'koneksi.php';
require 'header.php';

if (!isset($_GET['id'])) {
    header("Location: customer.php");
    exit;
}

$id_customer = $_GET['id'];
$pesan_error = '';

// Ambil data customer dan transaksi TERAKHIR
$query_data = "
    SELECT 
        c.namacustomer, c.statuscustomer,
        t.id_kamar, t.habissewa as tgl_mulai_baru,
        k.nomor_kamar, k.jenis_kamar, k.harga_kamar, k.harga_minggu, k.harga_hari,
        ko.nama_kost
    FROM table_customer c
    JOIN table_transaksi t ON c.id_customer = t.id_customer
    JOIN table_kamar k ON t.id_kamar = k.id_kamar
    JOIN table_kost ko ON k.id_kost = ko.id_kost
    WHERE c.id_customer = ?
    ORDER BY t.id_transaksi DESC LIMIT 1
";
$stmt = $koneksi->prepare($query_data);
$stmt->execute([$id_customer]);
$data_sewa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data_sewa) {
    echo "Data transaksi sebelumnya tidak ditemukan.";
    exit;
}

// PROSES SIMPAN PERPANJANGAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenissewa = $_POST['jenissewa'];
    $durasi = (int)$_POST['durasi'];
    $diskon = (int)str_replace('.', '', $_POST['diskontransaksi']);
    $total_harga = (int)str_replace('.', '', $_POST['total_harga_hidden']);
    
    // TANGKAL MANIPULASI TANGGAL
    $mulaisewa = $data_sewa['tgl_mulai_baru']; 
    
    $date_obj = new DateTime($mulaisewa);
    if ($jenissewa == 'Bulanan') { $date_obj->modify("+$durasi month"); }
    elseif ($jenissewa == 'Mingguan') { $days = $durasi * 7; $date_obj->modify("+$days days"); }
    elseif ($jenissewa == 'Harian') { $date_obj->modify("+$durasi days"); }
    $habissewa = $date_obj->format('Y-m-d');

    // Ambil ID User dari Session Aktif untuk Audit Trail
    $id_user_aktif = $_SESSION['user_id'];

    try {
        $koneksi->beginTransaction();

        // 1. Catat Transaksi Baru (Mencatat id_user pemroses)
        $stmt_trans = $koneksi->prepare("INSERT INTO table_transaksi (tanggaltransaksi, mulaisewa, habissewa, namatransaksi, diskontransaksi, jumlahtransaksi, id_kamar, id_customer, id_user) VALUES (CURDATE(), ?, ?, 'Perpanjangan Sewa', ?, ?, ?, ?, ?)");
        $stmt_trans->execute([$mulaisewa, $habissewa, $diskon, $total_harga, $data_sewa['id_kamar'], $id_customer, $id_user_aktif]);

        // 2. Pastikan Customer Aktif & Kamar Terisi
        $koneksi->prepare("UPDATE table_customer SET statuscustomer = 'Aktif' WHERE id_customer = ?")->execute([$id_customer]);
        $koneksi->prepare("UPDATE table_kamar SET status_kamar = 'Terisi' WHERE id_kamar = ?")->execute([$data_sewa['id_kamar']]);

        $koneksi->commit();
        header("Location: index.php"); 
        exit;

    } catch (Exception $e) {
        $koneksi->rollBack();
        $pesan_error = "Gagal memperpanjang sewa: " . $e->getMessage();
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="index.php" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Batal & Kembali</a>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200">
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Form Perpanjangan Sewa</h2>
        <p class="text-gray-600 border-b pb-4 mb-6">Penyewa: <span class="font-bold text-black"><?= htmlspecialchars($data_sewa['namacustomer']) ?></span></p>

        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm text-sm font-medium"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form action="perpanjang.php?id=<?= $id_customer ?>" method="POST" onsubmit="return confirm('Proses perpanjangan sewa?');">
            
            <div class="bg-gray-50 border border-gray-200 p-4 rounded mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Lokasi Properti</p>
                    <p class="font-bold text-lg text-gray-800"><?= htmlspecialchars($data_sewa['nama_kost']) ?></p>
                    <p class="text-sm text-gray-600">Kamar <?= htmlspecialchars($data_sewa['nomor_kamar']) ?> (<?= htmlspecialchars($data_sewa['jenis_kamar']) ?>)</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider">Tanggal Lanjut Sewa</p>
                    <p class="font-black text-xl text-yellow-600 bg-yellow-50 px-3 py-1 rounded border border-yellow-200 inline-block mt-1">
                        <?= date('d M Y', strtotime($data_sewa['tgl_mulai_baru'])) ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Sewa Lanjutan</label>
                    <select name="jenissewa" id="jenissewa" class="w-full border border-gray-300 px-3 py-2 rounded bg-white" required>
                        <option value="Bulanan">Bulanan</option>
                        <option value="Mingguan">Mingguan</option>
                        <option value="Harian">Harian</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Durasi</label>
                    <select name="durasi" id="durasi" class="w-full border border-gray-300 px-3 py-2 rounded bg-white" required></select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Diskon (Rp)</label>
                    <input type="number" name="diskontransaksi" id="diskontransaksi" value="0" class="w-full border border-gray-300 px-3 py-2 rounded bg-white">
                </div>
            </div>

            <div class="bg-black text-white p-5 rounded-lg mt-8 shadow-md">
                <div class="flex justify-between items-center mb-3 border-b border-gray-700 pb-3">
                    <span class="text-sm text-gray-400">Tgl. Habis Sewa Baru:</span>
                    <span id="display_habissewa" class="font-bold text-yellow-500 text-lg">-</span>
                </div>
                <div class="flex justify-between items-end">
                    <span class="text-sm font-semibold">Total Tagihan:</span>
                    <span class="text-3xl font-black text-yellow-500" id="display_total">Rp 0</span>
                    <input type="hidden" name="total_harga_hidden" id="total_harga_hidden" value="0">
                </div>
            </div>

            <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-4 px-10 rounded-md transition-colors shadow-lg text-lg mt-6">
                Proses Transaksi Perpanjangan
            </button>
        </form>
    </div>
</div>

<script>
    const tarifBulanan = <?= $data_sewa['harga_kamar'] ?: 0 ?>;
    const tarifMingguan = <?= $data_sewa['harga_minggu'] ?: 0 ?>;
    const tarifHarian = <?= $data_sewa['harga_hari'] ?: 0 ?>;
    const tglMulaiFix = '<?= $data_sewa['tgl_mulai_baru'] ?>';

    const jenissewaSelect = document.getElementById('jenissewa');
    const durasiSelect = document.getElementById('durasi');
    const diskonInput = document.getElementById('diskontransaksi');
    const displayHabisSewa = document.getElementById('display_habissewa');
    const displayTotal = document.getElementById('display_total');
    const hiddenTotal = document.getElementById('total_harga_hidden');

    function updateOpsiDurasi() {
        const jenis = jenissewaSelect.value;
        durasiSelect.innerHTML = '';
        let batas = 12; let label = 'Bulan';
        if (jenis === 'Mingguan') { batas = 4; label = 'Minggu'; }
        else if (jenis === 'Harian') { batas = 6; label = 'Hari'; }

        for (let i = 1; i <= batas; i++) {
            const opt = document.createElement('option');
            opt.value = i; opt.textContent = `${i} ${label}`;
            durasiSelect.appendChild(opt);
        }
        kalkulasiSemua();
    }

    function kalkulasiSemua() {
        const jenis = jenissewaSelect.value;
        let tarifAktif = 0;
        if (jenis === 'Bulanan') tarifAktif = tarifBulanan;
        else if (jenis === 'Mingguan') tarifAktif = tarifMingguan;
        else if (jenis === 'Harian') tarifAktif = tarifHarian;

        const durasi = parseInt(durasiSelect.value) || 1;
        const diskon = parseInt(diskonInput.value) || 0;
        
        let total = (tarifAktif * durasi) - diskon;
        if (total < 0) total = 0;
        
        displayTotal.textContent = 'Rp ' + total.toLocaleString('id-ID');
        hiddenTotal.value = total;

        const dateObj = new Date(tglMulaiFix);
        if (jenis === 'Bulanan') { dateObj.setMonth(dateObj.getMonth() + durasi); } 
        else if (jenis === 'Mingguan') { dateObj.setDate(dateObj.getDate() + (durasi * 7)); } 
        else if (jenis === 'Harian') { dateObj.setDate(dateObj.getDate() + durasi); }
        
        const opsi = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        displayHabisSewa.textContent = dateObj.toLocaleDateString('id-ID', opsi);
    }

    jenissewaSelect.addEventListener('change', updateOpsiDurasi);
    durasiSelect.addEventListener('change', kalkulasiSemua);
    diskonInput.addEventListener('input', kalkulasiSemua);
    
    updateOpsiDurasi();
</script>

<?php require 'footer.php'; ?>