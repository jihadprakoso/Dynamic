<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        try {
            $database = new Database();
            $db = $database->getConnection();

            $query = "SELECT * FROM users WHERE username = :username LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch();
                if (password_verify($password, $user['password'])) {
                    // Start session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_name'] = $user['name'];
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Kata sandi yang Anda masukkan salah.";
                }
            } else {
                $error = "Nama pengguna tidak ditemukan.";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    } else {
        $error = "Silakan isi semua bidang.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Stockbarang</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom Style Sheet -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-container">

    <div class="login-card text-center">
        <div class="mb-4">
            <i class="fa-solid fa-boxes-stacked text-primary" style="font-size: 3rem; text-shadow: 0 0 20px rgba(59, 130, 246, 0.4);"></i>
            <h2 class="mt-3 text-white">Stockbarang</h2>
            <p class="text-muted">Aplikasi Manajemen Inventaris Barang</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger-custom text-start mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group-custom text-start">
                <label for="username" class="form-label-custom">Username</label>
                <div class="position-relative">
                    <input type="text" class="form-control-custom" id="username" name="username" placeholder="Masukkan username admin" required autocomplete="off">
                </div>
            </div>
            
            <div class="form-group-custom text-start">
                <label for="password" class="form-label-custom">Kata Sandi</label>
                <div class="position-relative">
                    <input type="password" class="form-control-custom" id="password" name="password" placeholder="Masukkan password" required>
                </div>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary-custom py-2">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk
                </button>
            </div>
        </form>

        <div class="mt-4 text-center">
            <span class="text-muted" style="font-size: 0.85rem;">Default Login: admin / admin</span>
        </div>
    </div>

</body>
</html>
