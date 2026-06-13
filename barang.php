<?php
require_once 'config/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$success_msg = '';
$error_msg = '';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Helper: Auto-generate Kode Barang
    function generateKodeBarang($db) {
        $stmt = $db->query("SELECT kode_barang FROM barang ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetch();
        if ($last) {
            $last_code = $last['kode_barang'];
            $num = intval(substr($last_code, 4)) + 1;
            return "BRG-" . str_pad($num, 4, "0", STR_PAD_LEFT);
        }
        return "BRG-0001";
    }

    // CRUD Handlers
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1. ADD ITEM
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            $nama = trim($_POST['nama_barang']);
            $kategori = trim($_POST['kategori']);
            $stok = intval($_POST['stok']);
            $stok_min = intval($_POST['stok_minimum']);
            $harga = floatval($_POST['harga']);
            $ket = trim($_POST['keterangan']);
            
            if (!empty($nama) && !empty($kategori)) {
                $db->beginTransaction();
                try {
                    $kode = generateKodeBarang($db);
                    
                    // Insert Item
                    $stmt = $db->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori, stok, stok_minimum, harga, keterangan) 
                                          VALUES (:kode, :nama, :kategori, :stok, :stok_min, :harga, :ket)");
                    $stmt->execute([
                        ':kode' => $kode,
                        ':nama' => $nama,
                        ':kategori' => $kategori,
                        ':stok' => $stok,
                        ':stok_min' => $stok_min,
                        ':harga' => $harga,
                        ':ket' => $ket
                    ]);
                    
                    $barang_id = $db->lastInsertId();
                    
                    // Log Initial Stock Transaction
                    if ($stok > 0) {
                        $tx_stmt = $db->prepare("INSERT INTO transaksi (barang_id, tipe_transaksi, jumlah, keterangan) 
                                                 VALUES (:barang_id, 'masuk', :jumlah, 'Stok awal barang baru')");
                        $tx_stmt->execute([
                            ':barang_id' => $barang_id,
                            ':jumlah' => $stok
                        ]);
                    }
                    
                    $db->commit();
                    $success_msg = "Barang berhasil ditambahkan dengan kode: $kode";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error_msg = "Gagal menambahkan barang: " . $e->getMessage();
                }
            } else {
                $error_msg = "Harap isi semua bidang wajib (Nama & Kategori).";
            }
        }

        // 2. EDIT ITEM
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $nama = trim($_POST['nama_barang']);
            $kategori = trim($_POST['kategori']);
            $stok_min = intval($_POST['stok_minimum']);
            $harga = floatval($_POST['harga']);
            $ket = trim($_POST['keterangan']);

            if ($id > 0 && !empty($nama) && !empty($kategori)) {
                $stmt = $db->prepare("UPDATE barang SET nama_barang = :nama, kategori = :kategori, 
                                      stok_minimum = :stok_min, harga = :harga, keterangan = :ket WHERE id = :id");
                if ($stmt->execute([
                    ':nama' => $nama,
                    ':kategori' => $kategori,
                    ':stok_min' => $stok_min,
                    ':harga' => $harga,
                    ':ket' => $ket,
                    ':id' => $id
                ])) {
                    $success_msg = "Detail barang berhasil diperbarui.";
                } else {
                    $error_msg = "Gagal memperbarui barang.";
                }
            } else {
                $error_msg = "Harap isi semua data dengan benar.";
            }
        }

        // 3. DELETE ITEM
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            if ($id > 0) {
                $stmt = $db->prepare("DELETE FROM barang WHERE id = :id");
                if ($stmt->execute([':id' => $id])) {
                    $success_msg = "Barang berhasil dihapus.";
                } else {
                    $error_msg = "Gagal menghapus barang.";
                }
            }
        }
    }

    // Search Logic
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    if (!empty($search)) {
        $stmt = $db->prepare("SELECT * FROM barang WHERE nama_barang LIKE :search OR kode_barang LIKE :search OR kategori LIKE :search ORDER BY kode_barang ASC");
        $stmt->execute([':search' => "%$search%"]);
    } else {
        $stmt = $db->query("SELECT * FROM barang ORDER BY kode_barang ASC");
    }
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="text-white mb-1">Data Barang</h1>
            <p class="text-muted mb-0">Kelola informasi produk, kategori, harga, dan batasan minimum stok.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalAddBarang">
            <i class="fa-solid fa-plus me-2"></i> Tambah Barang
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
        <!-- Search bar -->
        <div class="d-flex justify-content-end mb-4">
            <form action="barang.php" method="GET" class="d-flex gap-2 w-100" style="max-width: 350px;">
                <input type="text" name="search" class="form-control-custom py-2" placeholder="Cari nama, kode, kategori..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-secondary-custom py-2">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>
        </div>

        <table class="table table-custom text-white">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th class="text-end">Harga</th>
                    <th class="text-center">Stok</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Data barang tidak ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="fw-semibold text-primary"><?php echo htmlspecialchars($item['kode_barang']); ?></td>
                            <td><?php echo htmlspecialchars($item['nama_barang']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['kategori']); ?></span></td>
                            <td class="text-end">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                            <td class="text-center fw-bold"><?php echo htmlspecialchars($item['stok']); ?></td>
                            <td class="text-center">
                                <?php if ($item['stok'] == 0): ?>
                                    <span class="badge badge-custom badge-out">Habis</span>
                                <?php elseif ($item['stok'] <= $item['stok_minimum']): ?>
                                    <span class="badge badge-custom badge-warning-custom">Menipis</span>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-in">Aman</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button class="btn btn-sm btn-secondary-custom edit-btn" 
                                            data-id="<?php echo $item['id']; ?>"
                                            data-nama="<?php echo htmlspecialchars($item['nama_barang']); ?>"
                                            data-kategori="<?php echo htmlspecialchars($item['kategori']); ?>"
                                            data-stok_min="<?php echo $item['stok_minimum']; ?>"
                                            data-harga="<?php echo $item['harga']; ?>"
                                            data-ket="<?php echo htmlspecialchars($item['keterangan']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#modalEditBarang">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" 
                                            data-id="<?php echo $item['id']; ?>"
                                            data-nama="<?php echo htmlspecialchars($item['nama_barang']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#modalDeleteBarang">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Tambah Barang -->
<div class="modal fade" id="modalAddBarang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title modal-title-custom"><i class="fa-solid fa-plus text-primary me-2"></i>Tambah Barang Baru</h5>
                <button type="button" class="close-btn-custom" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form action="barang.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="form-group-custom">
                        <label class="form-label-custom">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" name="nama_barang" class="form-control-custom" placeholder="Contoh: Processor Intel Core i5" required>
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="kategori" class="form-control-custom" placeholder="Contoh: Hardware, RAM, Storage" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group-custom">
                                <label class="form-label-custom">Stok Awal</label>
                                <input type="number" name="stok" class="form-control-custom" value="0" min="0">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group-custom">
                                <label class="form-label-custom">Stok Minimum</label>
                                <input type="number" name="stok_minimum" class="form-control-custom" value="5" min="1">
                            </div>
                        </div>
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Harga (Rp)</label>
                        <input type="number" name="harga" class="form-control-custom" value="0" min="0" step="100">
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Keterangan</label>
                        <textarea name="keterangan" class="form-control-custom" rows="3" placeholder="Tambahkan deskripsi singkat barang..."></textarea>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom">Simpan Barang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Barang -->
<div class="modal fade" id="modalEditBarang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title modal-title-custom"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Informasi Barang</h5>
                <button type="button" class="close-btn-custom" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form action="barang.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-body p-4">
                    <div class="form-group-custom">
                        <label class="form-label-custom">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" name="nama_barang" id="edit-nama" class="form-control-custom" required>
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="kategori" id="edit-kategori" class="form-control-custom" required>
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Stok Minimum</label>
                        <input type="number" name="stok_minimum" id="edit-stok_min" class="form-control-custom" min="1">
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Harga (Rp)</label>
                        <input type="number" name="harga" id="edit-harga" class="form-control-custom" min="0" step="100">
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Keterangan</label>
                        <textarea name="keterangan" id="edit-ket" class="form-control-custom" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom" style="background-color: var(--accent-warning); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Hapus Barang -->
<div class="modal fade" id="modalDeleteBarang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title modal-title-custom text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="close-btn-custom" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form action="barang.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete-id">
                <div class="modal-body p-4 text-center">
                    <p class="text-muted">Apakah Anda yakin ingin menghapus barang berikut?</p>
                    <h4 class="text-white mt-2 mb-4" id="delete-nama">Barang A</h4>
                    <p class="text-danger" style="font-size: 0.85rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Tindakan ini tidak dapat dibatalkan dan akan menghapus semua riwayat log transaksi untuk barang ini.</p>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger py-2 px-3 fw-bold">Hapus Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Populate Edit Modal
    const editButtons = document.querySelectorAll(".edit-btn");
    editButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            document.getElementById("edit-id").value = this.dataset.id;
            document.getElementById("edit-nama").value = this.dataset.nama;
            document.getElementById("edit-kategori").value = this.dataset.kategori;
            document.getElementById("edit-stok_min").value = this.dataset.stok_min;
            document.getElementById("edit-harga").value = this.dataset.harga;
            document.getElementById("edit-ket").value = this.dataset.ket;
        });
    });

    // Populate Delete Modal
    const deleteButtons = document.querySelectorAll(".delete-btn");
    deleteButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            document.getElementById("delete-id").value = this.dataset.id;
            document.getElementById("delete-nama").innerText = this.dataset.nama;
        });
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
