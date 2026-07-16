<?php
session_start();

// Leanne - 
// gamitin mo nalang yung shared $conn connection dito sa leanne_db.php

define('ALLOW_DB_FAILURE', true);
require_once __DIR__ . '/includes/leanne_db.php';

$db_ready = isset($conn) && $conn instanceof mysqli;
$db_message = $db_connection_error ?? '';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/yana_users.php');
    } else {
        // jeiven - palitan mo nalang ito ng customer homepage kapag tapos na.
        header('Location: leanne_cart.php');
    }
    exit;
}

$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$db_ready) {
        $_SESSION['message'] = 'Login is unavailable because MySQL is not connected. Start XAMPP MySQL and check that the car_rental_management database exists.';
    } elseif ($email === '' || $password === '') {
        $_SESSION['message'] = 'Please enter your email and password.';
    } else {
        $sql = 'SELECT user_id, full_name, password, role, is_verified FROM users WHERE email = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['message'] = 'Invalid email or password.';
        } elseif ((int) $user['is_verified'] !== 1) {
            $_SESSION['message'] = 'Please verify your email before logging in.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: admin/yana_users.php');
            } else {
                // Leanne: dito muna ang customer after login habang ginagawa mo cart page.
                header('Location: leanne_cart.php');
            }
            exit;
        }
    }
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Party4U</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h1 class="h3 text-center mb-4">Login</h1>
                        <?php if (!$db_ready): ?>
                            <div class="alert alert-warning">
                                <?php echo htmlspecialchars(
                                    $db_message !== ''
                                        ? $db_message
                                        : 'Login is unavailable because MySQL is not connected.'
                                ); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($message !== ''): ?>
                            <div class="alert alert-warning"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        <p class="text-center mt-3 mb-0">No account? <a href="yana_register.php">Register here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
