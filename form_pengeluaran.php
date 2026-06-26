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
    $id_kost = $_POST['id_kost'] ?: null; // Jika kosong, set null (pengeluaran pusat)
    $jenis = $_POST['jenispengeluaran'];
    $kategori = $_POST['kategoripengeluaran'];
    $nama = trim($_POST['namapengeluaran']);
    $tanggal = $_POST['tanggalpengeluaran'];
    $jumlah = (int)str_replace('.', '', $_POST['jumlahpengeluaran']);

    if (empty($nama) || empty($jumlah)) {
        $pesan_error = "Rincian pengeluaran dan nominal wajib diisi!";
    } else {
        if (!empty($id_pengeluaran_post)) {
            $stmt = $koneksi->prepare("UPDATE table_pengeluaran SET jenispengeluaran=?, kategoripengeluaran=?, namapengeluaran=?, tanggalpengeluaran=?, jumlahpengeluaran=?, id_kost=? WHERE id_pengeluaran=?");
            $stmt->execute([$jenis, $kategori, $nama, $tanggal, $jumlah, $id_kost, $id_pengeluaran_post]);
        } else {
            $stmt = $koneksi->prepare("INSERT INTO table_pengeluaran (jenispengeluaran, kategoripengeluaran, namapengeluaran, tanggalpengeluaran, jumlahpengeluaran, id_kost) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$jenis, $kategori, $nama, $tanggal, $jumlah, $id_kost]);
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

        <form action="form_pengeluaran.php<?= $mode_edit ? '?edit='.$edit_id : '' ?>" method="POST" class="space-y-5">
            <input type="hidden" name="id_pengeluaran" value="<?= htmlspecialchars($edit_id) ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Pengeluaran</label>
                    <input type="date" name="tanggalpengeluaran" value="<?= htmlspecialchars($edit_data['tanggalpengeluaran']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500 bg-white" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Lokasi Properti (Opsional)</label>
                    <select name="id_kost" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500 bg-white">
                        <option value="">-- Pengeluaran Umum / Pusat --</option>
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
                    <select name="jenispengeluaran" class="w-full border border-gray-300 px-3 py-2 rounded bg-white">
                        <option value="Rutin" <?= $edit_data['jenispengeluaran'] == 'Rutin' ? 'selected' : '' ?>>Rutin (Bulanan)</option>
                        <option value="Insidental" <?= $edit_data['jenispengeluaran'] == 'Insidental' ? 'selected' : '' ?>>Insidental (Mendadak)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Kategori Pengeluaran</label>
                    <select name="kategoripengeluaran" class="w-full border border-gray-300 px-3 py-2 rounded bg-white">
                        <option value="Listrik" <?= $edit_data['kategoripengeluaran'] == 'Listrik' ? 'selected' : '' ?>>Token Listrik / PLN</option>
                        <option value="Air" <?= $edit_data['kategoripengeluaran'] == 'Air' ? 'selected' : '' ?>>Air / PDAM</option>
                        <option value="Internet" <?= $edit_data['kategoripengeluaran'] == 'Internet' ? 'selected' : '' ?>>Internet / WiFi</option>
                        <option value="Perbaikan" <?= $edit_data['kategoripengeluaran'] == 'Perbaikan' ? 'selected' : '' ?>>Perbaikan / Maintenance</option>
                        <option value="Kebersihan" <?= $edit_data['kategoripengeluaran'] == 'Kebersihan' ? 'selected' : '' ?>>Gaji / Kebersihan</option>
                        <option value="Lainnya" <?= $edit_data['kategoripengeluaran'] == 'Lainnya' ? 'selected' : '' ?>>Lain-lain</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Rincian Pengeluaran</label>
                <input type="text" name="namapengeluaran" value="<?= htmlspecialchars($edit_data['namapengeluaran']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Contoh: Beli token listrik Kost Sun Rawamangun" required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nominal (Rp)</label>
                <input type="number" name="jumlahpengeluaran" value="<?= htmlspecialchars($edit_data['jumlahpengeluaran']) ?>" class="w-full border border-gray-300 px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-red-500 text-lg font-bold text-red-600" required>
            </div>

            <div class="flex gap-4 mt-8 border-t pt-6">
                <button type="submit" class="w-full md:w-auto bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-10 rounded-md transition-colors shadow-sm">
                    <?= $mode_edit ? 'Simpan Pembaruan' : 'Simpan Pengeluaran' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>