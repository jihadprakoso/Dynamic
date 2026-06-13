<?php
require_once 'config/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$success_msg = '';
$error_msg = '';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Handle New Transaction Post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_transaction') {
        $barang_id = intval($_POST['barang_id']);
        $tipe = $_POST['tipe_transaksi']; // 'masuk' or 'keluar'
        $jumlah = intval($_POST['jumlah']);
        $ket = trim($_POST['keterangan']);

        if ($barang_id > 0 && ($tipe === 'masuk' || $tipe === 'keluar') && $jumlah > 0) {
            
            // Check Current Stock of the Item
            $item_stmt = $db->prepare("SELECT nama_barang, stok FROM barang WHERE id = :id LIMIT 1");
            $item_stmt->execute([':id' => $barang_id]);
            $item = $item_stmt->fetch();

            if ($item) {
                $current_stock = intval($item['stok']);
                $item_name = $item['nama_barang'];

                // Validate Stock-Out constraints
                if ($tipe === 'keluar' && $current_stock < $jumlah) {
                    $error_msg = "Transaksi Gagal: Stok untuk '$item_name' tidak mencukupi (Tersedia: $current_stock, Diminta: $jumlah).";
                } else {
                    // Start Database Transaction to guarantee atomic operations
                    $db->beginTransaction();
                    try {
                        // 1. Insert Transaction Log
                        $tx_stmt = $db->prepare("INSERT INTO transaksi (barang_id, tipe_transaksi, jumlah, keterangan) 
                                                 VALUES (:barang_id, :tipe, :jumlah, :ket)");
                        $tx_stmt->execute([
                            ':barang_id' => $barang_id,
                            ':tipe' => $tipe,
                            ':jumlah' => $jumlah,
                            ':ket' => $ket
                        ]);

                        // 2. Adjust Stock Value
                        if ($tipe === 'masuk') {
                            $new_stock = $current_stock + $jumlah;
                        } else {
                            $new_stock = $current_stock - $jumlah;
                        }

                        $update_stmt = $db->prepare("UPDATE barang SET stok = :new_stock WHERE id = :id");
                        $update_stmt->execute([
                            ':new_stock' => $new_stock,
                            ':id' => $barang_id
                        ]);

                        $db->commit();
                        $success_msg = "Transaksi " . ($tipe === 'masuk' ? 'Masuk' : 'Keluar') . " berhasil dicatat untuk '$item_name'.";
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error_msg = "Gagal memproses transaksi: " . $e->getMessage();
                    }
                }
            } else {
                $error_msg = "Barang tidak ditemukan.";
            }
        } else {
            $error_msg = "Harap isi semua bidang input transaksi dengan benar.";
        }
    }

    // Fetch list of items for dropdown selector
    $items_stmt = $db->query("SELECT id, kode_barang, nama_barang, stok FROM barang ORDER BY kode_barang ASC");
    $dropdown_items = $items_stmt->fetchAll();

    // Fetch transactions list
    $tx_list_stmt = $db->query("SELECT t.*, b.kode_barang, b.nama_barang, b.kategori 
                                FROM transaksi t 
                                JOIN barang b ON t.barang_id = b.id 
                                ORDER BY t.tanggal_transaksi DESC");
    $transactions = $tx_list_stmt->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="text-white mb-1">Transaksi Log</h1>
            <p class="text-muted mb-0">Catat dan pantau riwayat mutasi stok masuk dan keluar gudang.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalAddTransaksi">
            <i class="fa-solid fa-right-left me-2"></i> Tambah Transaksi
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-custom alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-circle-check text-success me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger-custom alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Table Container -->
    <div class="table-responsive-custom">
        <table class="table table-custom text-white">
            <thead>
                <tr>
                    <th>Waktu Transaksi</th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th class="text-center">Tipe</th>
                    <th class="text-end">Jumlah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Belum ada log transaksi.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td class="text-secondary" style="font-size: 0.9rem;">
                                <?php 
                                $date = new DateTime($tx['tanggal_transaksi']);
                                echo $date->format('d M Y, H:i'); 
                                ?>
                            </td>
                            <td class="fw-semibold text-primary"><?php echo htmlspecialchars($tx['kode_barang']); ?></td>
                            <td><?php echo htmlspecialchars($tx['nama_barang']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($tx['kategori']); ?></span></td>
                            <td class="text-center">
                                <?php if ($tx['tipe_transaksi'] === 'masuk'): ?>
                                    <span class="badge badge-custom badge-in"><i class="fa-solid fa-arrow-down-long me-1"></i> Masuk</span>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-out"><i class="fa-solid fa-arrow-up-long me-1"></i> Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold <?php echo ($tx['tipe_transaksi'] === 'masuk') ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($tx['tipe_transaksi'] === 'masuk') ? '+' : '-'; ?><?php echo number_format($tx['jumlah']); ?>
                            </td>
                            <td class="text-muted" style="font-size: 0.9rem; font-style: italic;">
                                <?php echo !empty($tx['keterangan']) ? '"' . htmlspecialchars($tx['keterangan']) . '"' : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Tambah Transaksi -->
<div class="modal fade" id="modalAddTransaksi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title modal-title-custom"><i class="fa-solid fa-right-left text-primary me-2"></i>Catat Mutasi Stok</h5>
                <button type="button" class="close-btn-custom" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form action="transaksi.php" method="POST">
                <input type="hidden" name="action" value="add_transaction">
                <div class="modal-body p-4">
                    <div class="form-group-custom">
                        <label class="form-label-custom">Pilih Barang <span class="text-danger">*</span></label>
                        <select name="barang_id" class="form-control-custom" style="background-image: none;" required>
                            <option value="" disabled selected>-- Pilih Barang dari Gudang --</option>
                            <?php foreach ($dropdown_items as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['kode_barang']); ?> - <?php echo htmlspecialchars($item['nama_barang']); ?> (Stok: <?php echo $item['stok']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-custom mt-3">
                        <label class="form-label-custom">Tipe Mutasi <span class="text-danger">*</span></label>
                        <div class="d-flex gap-4 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipe_transaksi" id="radioMasuk" value="masuk" checked>
                                <label class="form-check-label text-success fw-semibold" for="radioMasuk">
                                    <i class="fa-solid fa-arrow-down-long me-1"></i> Stok Masuk (Penambahan)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipe_transaksi" id="radioKeluar" value="keluar">
                                <label class="form-check-label text-danger fw-semibold" for="radioKeluar">
                                    <i class="fa-solid fa-arrow-up-long me-1"></i> Stok Keluar (Pengurangan)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-custom mt-3">
                        <label class="form-label-custom">Jumlah Unit <span class="text-danger">*</span></label>
                        <input type="number" name="jumlah" class="form-control-custom" placeholder="Contoh: 10" min="1" required>
                    </div>

                    <div class="form-group-custom mt-3">
                        <label class="form-label-custom">Keterangan / Catatan</label>
                        <textarea name="keterangan" class="form-control-custom" rows="3" placeholder="Contoh: Restock supplier A, Penjualan ritel..."></textarea>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom">Catat Transaksi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
