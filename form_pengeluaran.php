<?php
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$mode_edit = false;

$stmt_kost = $koneksi->query("SELECT id_kost, nama_kost FROM table_kost ORDER BY nama_kost ASC");
$data_kost = $stmt_kost->fetchAll(PDO::FETCH_ASSOC);

$edit_id = '';
$edit_data = [
    'id_kost' => '',
    'jenispengeluaran' => 'Rutin',
    'kategoripengeluaran' => 'Listrik',
    'namapengeluaran' => '',
    'tanggalpengeluaran' => date('Y-m-d'),
    'jumlahpengeluaran' => ''
];

if (isset($_GET['edit'])) {
    $mode_edit = true;
    $edit_id = $_GET['edit'];
    $stmt = $koneksi->prepare("SELECT * FROM table_pengeluaran WHERE id_pengeluaran = ?");
    $stmt->execute([$edit_id]);
    $data_db = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data_db) {
        $edit_data = $data_db;
    } else {
        header("Location: keuangan.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pengeluaran_post = $_POST['id_pengeluaran'] ?? '';
    $id_kost = $_POST['id_kost'] ?? null; 
    $jenis = $_POST['jenispengeluaran'];
    $kategori = $_POST['kategoripengeluaran'];
    $nama = trim($_POST['namapengeluaran']);
    $tanggal = $_POST['tanggalpengeluaran'];
    $jumlah = (int)str_replace('.', '', $_POST['jumlahpengeluaran']);

    // Ambil ID User dari Session Aktif untuk Audit Trail
    $id_user_aktif = $_SESSION['user_id'];

    if (empty($nama) || empty($jumlah) || empty($id_kost)) {
        $pesan_error = "Lokasi properti, rincian pengeluaran, dan nominal wajib diisi!";
    } else {
        if (!empty($id_pengeluaran_post)) {
            // UPDATE: Menyimpan id_user pengubah terakhir
            $stmt = $koneksi->prepare("UPDATE table_pengeluaran SET jenispengeluaran=?, kategoripengeluaran=?, namapengeluaran=?, tanggalpengeluaran=?, jumlahpengeluaran=?, id_kost=?, id_user=? WHERE id_pengeluaran=?");
            $stmt->execute([$jenis, $kategori, $nama, $tanggal, $jumlah, $id_kost, $id_user_aktif, $id_pengeluaran_post]);
        } else {
            // INSERT: Menyimpan id_user pembuat pertama
            $stmt = $koneksi->prepare("INSERT INTO table_pengeluaran (jenispengeluaran, kategoripengeluaran, namapengeluaran, tanggalpengeluaran, jumlahpengeluaran, id_kost, id_user) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$jenis, $kategori, $nama, $tanggal, $jumlah, $id_kost, $id_user_aktif]);
        }
        header("Location: keuangan.php");
        exit;
    }
}
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="keuangan.php" class="text-sm font-semibold text-gray-500 hover:text-black mb-2 inline-block">&larr; Kembali ke Keuangan</a>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200 border-t-4 border-t-red-600">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">
            <?= $mode_edit ? 'Edit Pengeluaran' : 'Catat Pengeluaran Baru' ?>
        </h2>

        <?php if ($pesan_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm text-sm font-medium"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form id="formPengeluaran" action="form_pengeluaran.php<?= $mode_edit ? '?edit='.$edit_id : '' ?>" method="POST" class="space-y-5" onsubmit="return konfirmasiSimpan()">
            <input type="hidden" name="id_pengeluaran" value="<?= htmlspecialchars($edit_id) ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Pengeluaran</label>
                    <input type="date" id="tanggalpengeluaran" name="tanggalpengeluaran" value="<?= htmlspecialchars($edit_data['tanggalpengeluaran']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500 bg-white" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Lokasi Properti</label>
                    <select id="id_kost" name="id_kost" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500 bg-white" required>
                        <option value="" disabled <?= empty($edit_data['id_kost']) ? 'selected' : '' ?>>-- Pilih Lokasi Properti --</option>
                        <?php foreach($data_kost as $k): ?>
                            <option value="<?= $k['id_kost'] ?>" <?= $edit_data['id_kost'] == $k['id_kost'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kost']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 bg-gray-50 p-4 rounded border border-gray-200">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Biaya</label>
                    <select id="jenispengeluaran" name="jenispengeluaran" class="w-full border border-gray-300 px-3 py-2 rounded bg-white" required>
                        <option value="Rutin" <?= $edit_data['jenispengeluaran'] == 'Rutin' ? 'selected' : '' ?>>Rutin (Bulanan)</option>
                        <option value="Insidental" <?= $edit_data['jenispengeluaran'] == 'Insidental' ? 'selected' : '' ?>>Insidental (Mendadak)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Kategori Pengeluaran</label>
                    <select id="kategoripengeluaran" name="kategoripengeluaran" class="w-full border border-gray-300 px-3 py-2 rounded bg-white" required>
                        <option value="Listrik" <?= $edit_data['kategoripengeluaran'] == 'Listrik' ? 'selected' : '' ?>>Token Listrik / PLN</option>
                        <option value="Air" <?= $edit_data['kategoripengeluaran'] == 'Air' ? 'selected' : '' ?>>Air / PDAM</option>
                        <option value="Internet" <?= $edit_data['kategoripengeluaran'] == 'Internet' ? 'selected' : '' ?>>Internet / WiFi</option>
                        <option value="Belanja Bahan" <?= $edit_data['kategoripengeluaran'] == 'Belanja Bahan' ? 'selected' : '' ?>>Belanja Bahan (Galon, Gas, dll)</option>
                        <option value="Perbaikan" <?= $edit_data['kategoripengeluaran'] == 'Perbaikan' ? 'selected' : '' ?>>Perbaikan / Maintenance</option>
                        <option value="Gaji" <?= $edit_data['kategoripengeluaran'] == 'Gaji' ? 'selected' : '' ?>>Gaji Karyawan</option>
                        <option value="Kebersihan/Keamanan" <?= $edit_data['kategoripengeluaran'] == 'Kebersihan/Keamanan' ? 'selected' : '' ?>>Jasa Kebersihan / Keamanan</option>
                        <option value="Lainnya" <?= $edit_data['kategoripengeluaran'] == 'Lainnya' ? 'selected' : '' ?>>Lain-lain</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Rincian Pengeluaran</label>
                <input type="text" id="namapengeluaran" name="namapengeluaran" value="<?= htmlspecialchars($edit_data['namapengeluaran']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Contoh: Beli token listrik Kamar 101" required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nominal (Rp)</label>
                <input type="number" id="jumlahpengeluaran" name="jumlahpengeluaran" value="<?= htmlspecialchars($edit_data['jumlahpengeluaran']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500 text-lg font-bold text-red-600" required>
            </div>

            <div class="flex gap-4 mt-8 border-t pt-6">
                <button type="submit" class="w-full md:w-auto bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-10 rounded-md transition-colors shadow-sm">
                    <?= $mode_edit ? 'Simpan Pembaruan' : 'Simpan Pengeluaran' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function konfirmasiSimpan() {
    const tgl = document.getElementById('tanggalpengeluaran').value;
    const kostSelect = document.getElementById('id_kost');
    const kost = kostSelect.options[kostSelect.selectedIndex].text;
    const jenis = document.getElementById('jenispengeluaran').value;
    const kategori = document.getElementById('kategoripengeluaran').value;
    const rincian = document.getElementById('namapengeluaran').value;
    const nominal = document.getElementById('jumlahpengeluaran').value;

    if (!tgl || kostSelect.value === "" || !rincian || !nominal) {
        return true; 
    }

    const nominalRp = parseInt(nominal).toLocaleString('id-ID');

    const pesan = `KONFIRMASI <?= $mode_edit ? 'PERUBAHAN' : 'PENYIMPANAN' ?> PENGELUARAN:\n\n` +
                  `• Tanggal   : ${tgl}\n` +
                  `• Lokasi    : ${kost}\n` +
                  `• Kategori : ${kategori} (${jenis})\n` +
                  `• Rincian   : ${rincian}\n` +
                  `• Nominal   : Rp ${nominalRp}\n\n` +
                  `Apakah data di atas sudah benar dan ingin dilanjutkan?`;

    return confirm(pesan);
}
</script>

<?php require 'footer.php'; ?>