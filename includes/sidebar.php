<nav class="sidebar">
    <div class="brand-section">
        <i class="fa-solid fa-boxes-stacked brand-icon"></i>
        <span class="brand-name">Stockbarang</span>
    </div>
    
    <ul class="nav-links">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link-custom <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="barang.php" class="nav-link-custom <?php echo ($current_page == 'barang.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i>
                <span>Data Barang</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="transaksi.php" class="nav-link-custom <?php echo ($current_page == 'transaksi.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-right-left"></i>
                <span>Transaksi Log</span>
            </a>
        </li>
        <li class="nav-item mt-4">
            <a href="logout.php" class="nav-link-custom text-danger">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Keluar</span>
            </a>
        </li>
    </ul>
    
    <div class="user-profile-bar">
        <div class="user-avatar">
            <?php 
                $name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin';
                echo strtoupper(substr($name, 0, 1)); 
            ?>
        </div>
        <div class="overflow-hidden">
            <div class="text-white text-truncate fw-semibold" style="font-size: 0.85rem;">
                <?php echo htmlspecialchars($name); ?>
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">Administrator</div>
        </div>
    </div>
</nav>
<div class="main-content">
