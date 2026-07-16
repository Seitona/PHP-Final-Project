<?php
session_start();

// Leanne - same shared connection lang gamitin natin dito

require_once __DIR__ . '/../includes/leanne_db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['message'] = 'Please log in as an admin first.';
    header('Location: ../yana_login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
    } elseif (!$user_id || $user_id === (int) $_SESSION['user_id']) {
        $_SESSION['message'] = 'You cannot change your own account here.';
    } elseif ($action === 'verify') {
        $sql = 'UPDATE users SET is_verified = 1 WHERE user_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = 'User email verified.';
    } elseif ($action === 'make_admin') {
        $role = 'admin';
        $sql = 'UPDATE users SET role = ? WHERE user_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $role, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = 'User is now an admin.';
    } elseif ($action === 'make_customer') {
        $role = 'customer';
        $sql = 'UPDATE users SET role = ? WHERE user_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $role, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = 'User is now a customer.';
    } elseif ($action === 'delete') {
        $sql = 'DELETE FROM users WHERE user_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = 'User deleted.';
    }

    header('Location: yana_users.php');
    exit;
}

$users = mysqli_query($conn, 'SELECT user_id, full_name, email, role, is_verified FROM users ORDER BY user_id DESC');
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Party4U</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h1 class="h3 mb-0">Manage Users</h1><small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small></div>
            <a href="../yana_logout.php" class="btn btn-outline-danger">Logout</a>
        </div>
        <?php if ($message !== ''): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-dark"><tr><th>Name</th><th>Email</th><th>Role</th><th>Verified</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while ($user = mysqli_fetch_assoc($users)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge text-bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>"><?php echo htmlspecialchars($user['role']); ?></span></td>
                        <td><?php echo (int) $user['is_verified'] === 1 ? 'Yes' : 'No'; ?></td>
                        <td>
                        <?php if ((int) $user['user_id'] !== (int) $_SESSION['user_id']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                <?php if ((int) $user['is_verified'] !== 1): ?><button name="action" value="verify" class="btn btn-sm btn-success">Verify</button><?php endif; ?>
                                <button name="action" value="<?php echo $user['role'] === 'admin' ? 'make_customer' : 'make_admin'; ?>" class="btn btn-sm btn-outline-primary"><?php echo $user['role'] === 'admin' ? 'Make Customer' : 'Make Admin'; ?></button>
                                <button name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">Delete</button>
                            </form>
                        <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div></div></div>
    </main>
</body>
</html>
