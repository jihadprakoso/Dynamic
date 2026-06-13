<?php
require_once 'config/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Total Jenis Barang
    $stmt = $db->query("SELECT COUNT(*) as total FROM barang");
    $total_items = $stmt->fetch()['total'];

    // 2. Total Stok Kumulatif
    $stmt = $db->query("SELECT SUM(stok) as total FROM barang");
    $total_stock = $stmt->fetch()['total'] ?? 0;

    // 3. Barang Stok Menipis
    $stmt = $db->query("SELECT COUNT(*) as total FROM barang WHERE stok <= stok_minimum AND stok > 0");
    $low_stock = $stmt->fetch()['total'];

    // 4. Barang Habis
    $stmt = $db->query("SELECT COUNT(*) as total FROM barang WHERE stok = 0");
    $out_of_stock = $stmt->fetch()['total'];

    // 5. Transaksi Terbaru (5 Log Terakhir)
    $stmt = $db->query("SELECT t.*, b.nama_barang, b.kode_barang 
                        FROM transaksi t 
                        JOIN barang b ON t.barang_id = b.id 
                        ORDER BY t.tanggal_transaksi DESC LIMIT 5");
    $recent_tx = $stmt->fetchAll();

    // 6. Data Grafik (10 Transaksi Terakhir)
    $stmt = $db->query("SELECT t.*, b.nama_barang, b.kode_barang 
                        FROM transaksi t 
                        JOIN barang b ON t.barang_id = b.id 
                        ORDER BY t.tanggal_transaksi DESC LIMIT 8");
    $raw_chart_data = array_reverse($stmt->fetchAll()); // Balik urutan agar kronologis dari kiri ke kanan

    $chart_labels = [];
    $chart_values = [];
    $chart_colors = [];

    foreach ($raw_chart_data as $row) {
        $chart_labels[] = $row['kode_barang'] . ' (' . (strlen($row['nama_barang']) > 15 ? substr($row['nama_barang'], 0, 12) . '...' : $row['nama_barang']) . ')';
        $val = intval($row['jumlah']);
        if ($row['tipe_transaksi'] === 'keluar') {
            $chart_values[] = -$val;
            $chart_colors[] = 'rgba(239, 68, 68, 0.7)'; // Red background
        } else {
            $chart_values[] = $val;
            $chart_colors[] = 'rgba(16, 185, 129, 0.7)'; // Green background
        }
    }

} catch (PDOException $e) {
    die("Error retrieving dashboard data: " . $e->getMessage());
}
?>

<div class="container-fluid p-0">
    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="text-white mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Ringkasan kondisi inventaris barang terkini.</p>
        </div>
        <div class="text-muted" style="font-size: 0.9rem;">
            <i class="fa-regular fa-calendar-days me-2"></i><?php echo date('d M Y'); ?>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card-stat blue">
                <div class="text-muted fw-medium mb-1">Total Jenis Barang</div>
                <div class="fs-2 fw-bold text-white"><?php echo number_format($total_items); ?></div>
                <div class="text-muted mt-2" style="font-size: 0.8rem;">Macam produk terdaftar</div>
                <i class="fa-solid fa-box stat-icon text-primary"></i>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card-stat green">
                <div class="text-muted fw-medium mb-1">Total Stok Barang</div>
                <div class="fs-2 fw-bold text-white"><?php echo number_format($total_stock); ?></div>
                <div class="text-muted mt-2" style="font-size: 0.8rem;">Unit di gudang keseluruhan</div>
                <i class="fa-solid fa-cubes stat-icon text-success"></i>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card-stat warning">
                <div class="text-muted fw-medium mb-1">Stok Menipis</div>
                <div class="fs-2 fw-bold text-warning"><?php echo number_format($low_stock); ?></div>
                <div class="text-muted mt-2" style="font-size: 0.8rem;">Mencapai limit minimum</div>
                <i class="fa-solid fa-triangle-exclamation stat-icon text-warning"></i>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card-stat danger">
                <div class="text-muted fw-medium mb-1">Stok Habis</div>
                <div class="fs-2 fw-bold text-danger"><?php echo number_format($out_of_stock); ?></div>
                <div class="text-muted mt-2" style="font-size: 0.8rem;">Barang bernilai kosong</div>
                <i class="fa-solid fa-circle-xmark stat-icon text-danger"></i>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Chart Section -->
        <div class="col-12 col-lg-8">
            <div class="table-responsive-custom" style="height: 100%;">
                <h4 class="text-white mb-4">Grafik Transaksi Stok Terakhir</h4>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="stockChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Logs Section -->
        <div class="col-12 col-lg-4">
            <div class="table-responsive-custom" style="height: 100%;">
                <h4 class="text-white mb-4">Log Transaksi Terbaru</h4>
                <?php if (empty($recent_tx)): ?>
                    <p class="text-muted">Belum ada transaksi tercatat.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($recent_tx as $tx): ?>
                            <div class="list-group-item bg-transparent border-secondary px-0 py-3 text-white">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold" style="font-size: 0.9rem; max-width: 170px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($tx['nama_barang']); ?>
                                    </span>
                                    <span class="badge badge-custom <?php echo ($tx['tipe_transaksi'] == 'masuk') ? 'badge-in' : 'badge-out'; ?>">
                                        <?php echo ($tx['tipe_transaksi'] == 'masuk') ? '+' : '-'; ?><?php echo $tx['jumlah']; ?>
                                    </span>
                                </div>
                                <div class="d-flex w-100 justify-content-between">
                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($tx['kode_barang']); ?></small>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <?php 
                                        $date = new DateTime($tx['tanggal_transaksi']);
                                        echo $date->format('d M H:i'); 
                                        ?>
                                    </small>
                                </div>
                                <?php if (!empty($tx['keterangan'])): ?>
                                    <div class="mt-2 text-secondary" style="font-size: 0.8rem; font-style: italic;">
                                        "<?php echo htmlspecialchars($tx['keterangan']); ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ChartJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('stockChart').getContext('2d');
    
    const labels = <?php echo json_encode($chart_labels); ?>;
    const values = <?php echo json_encode($chart_values); ?>;
    const colors = <?php echo json_encode($chart_colors); ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Perubahan Stok (Unit)',
                data: values,
                backgroundColor: colors,
                borderColor: colors.map(c => c.replace('0.7', '1.0')),
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#94a3b8',
                        font: {
                            family: 'Inter'
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(51, 65, 85, 0.3)'
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(51, 65, 85, 0.3)'
                    },
                    ticks: {
                        color: '#94a3b8'
                    }
                }
            }
        }
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
