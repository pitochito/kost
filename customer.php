<?php
require 'koneksi.php';
require 'header.php';

$pesan_error = '';
$pesan_sukses = '';

// PROSES HAPUS DATA CUSTOMER
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    
    // Ambil nama file foto sebelum dihapus untuk dihapus juga dari folder
    $stmt_foto = $koneksi->prepare("SELECT fotoktpcustomer, fotoselfiecustomer FROM table_customer WHERE id_customer = ?");
    $stmt_foto->execute([$id_hapus]);
    $foto = $stmt_foto->fetch(PDO::FETCH_ASSOC);

    $stmt_hapus = $koneksi->prepare("DELETE FROM table_customer WHERE id_customer = ?");
    if ($stmt_hapus->execute([$id_hapus])) {
        // Hapus file fisik jika ada
        if ($foto['fotoktpcustomer'] && file_exists('ktpcust/' . $foto['fotoktpcustomer'])) {
            unlink('ktpcust/' . $foto['fotoktpcustomer']);
        }
        if ($foto['fotoselfiecustomer'] && file_exists('selfiecust/' . $foto['fotoselfiecustomer'])) {
            unlink('selfiecust/' . $foto['fotoselfiecustomer']);
        }
        header("Location: customer.php?pesan=sukses_hapus");
        exit;
    }
}

if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses_hapus') {
    $pesan_sukses = "Data customer beserta fotonya berhasil dihapus.";
}

// LOGIKA PENCARIAN & FILTER
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT * FROM table_customer WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (namacustomer LIKE ? OR nikcustomer LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $query .= " AND statuscustomer = ?";
    $params[] = $status;
}

$query .= " ORDER BY id_customer DESC";
$stmt = $koneksi->prepare($query);
$stmt->execute($params);
$data_customer = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Data Customer</h2>
        <p class="text-sm text-gray-500 mt-1">Kelola data penyewa dan calon penyewa kost Anda.</p>
    </div>
    <a href="form_customer.php" class="bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-4 rounded transition-colors shadow-sm whitespace-nowrap">
        + Tambah Customer
    </a>
</div>

<?php if ($pesan_sukses): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm"><?= $pesan_sukses ?></div>
<?php endif; ?>

<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <form action="customer.php" method="GET" class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Nama atau NIK..." 
                   class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500">
        </div>
        <div class="md:w-48">
            <select name="status" class="w-full border border-gray-300 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500 bg-white">
                <option value="">Semua Status</option>
                <option value="Aktif" <?= $status == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="Tidak Aktif" <?= $status == 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
            </select>
        </div>
        <button type="submit" class="bg-black hover:bg-gray-800 text-white font-bold py-2 px-6 rounded transition-colors">
            Cari / Filter
        </button>
        <?php if(!empty($search) || !empty($status)): ?>
            <a href="customer.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-center transition-colors">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
    <table class="w-full text-left border-collapse min-w-[900px]">
        <thead class="bg-gray-100 border-b border-gray-200">
            <tr>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">Nama & NIK</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">Kontak</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">Kota Asal</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600">Status</th>
                <th class="py-3 px-4 text-sm font-bold text-gray-600 text-center">Tindakan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($data_customer as $cust) : ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="py-3 px-4">
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($cust['namacustomer']) ?></p>
                    <p class="text-xs text-gray-500">NIK: <?= htmlspecialchars($cust['nikcustomer']) ?></p>
                </td>
                <td class="py-3 px-4 text-sm">
                    <p class="font-semibold text-gray-700"><?= htmlspecialchars($cust['nohpcustomer']) ?></p>
                    <p class="text-xs text-gray-500">Darurat: <?= htmlspecialchars($cust['namakontakdarurat']) ?> (<?= htmlspecialchars($cust['kontakdarurat']) ?>)</p>
                </td>
                <td class="py-3 px-4 text-sm text-gray-700">
                    <?= htmlspecialchars($cust['kotaasalcustomer']) ?>
                </td>
                <td class="py-3 px-4 text-sm">
                    <?php if (strtolower($cust['statuscustomer']) == 'aktif'): ?>
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">Aktif</span>
                    <?php else: ?>
                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold">Tidak Aktif</span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4 flex flex-wrap justify-center gap-2">
                    <a href="profil_customer.php?id=<?= $cust['id_customer'] ?>" class="bg-blue-600 text-white hover:bg-blue-700 px-3 py-1.5 rounded text-xs font-bold transition-colors">Profil</a>

                    <?php if (strtolower($cust['statuscustomer']) == 'aktif'): ?>
                        <a href="perpanjang.php?id=<?= $cust['id_customer'] ?>" class="bg-black text-yellow-500 hover:bg-gray-800 px-3 py-1.5 rounded text-xs font-bold transition-colors">Perpanjang</a>
                    <?php endif; ?>
                    
                    <a href="form_customer.php?edit=<?= $cust['id_customer'] ?>" class="border border-yellow-500 text-yellow-600 hover:bg-yellow-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Edit</a>
                    
                    <a href="customer.php?hapus=<?= $cust['id_customer'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus customer ini? Semua foto terkait juga akan terhapus.');" class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded text-xs font-semibold transition-colors">Hapus</a>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(empty($data_customer)): ?>
            <tr>
                <td colspan="5" class="text-center py-8 text-gray-500">Tidak ada data customer yang ditemukan.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'footer.php'; ?>