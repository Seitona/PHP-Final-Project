<?php
session_start();

// Leanne: gamitin mo nalang yung shared $conn connection dito sa leanne_db.php.
require_once __DIR__ . '/includes/leanne_db.php';

$full_name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        $_SESSION['message'] = 'Please complete all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $_SESSION['message'] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $_SESSION['message'] = 'Passwords do not match.';
    } else {
        $check_sql = 'SELECT user_id FROM users WHERE email = ? LIMIT 1';
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, 's', $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['message'] = 'This email is already registered.';
            mysqli_stmt_close($check_stmt);
        } else {
            mysqli_stmt_close($check_stmt);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_code = bin2hex(random_bytes(32));
            $role = 'customer';

            $sql = 'INSERT INTO users (full_name, email, password, role, is_verified, verification_code) VALUES (?, ?, ?, ?, 0, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssss', $full_name, $email, $hashed_password, $role, $verification_code);

            if (mysqli_stmt_execute($stmt)) {
                // Yana: email service setup nalang dito kapag may hosting na kayo.
                $verify_link = 'yana_verify_email.php?code=' . urlencode($verification_code);
                $_SESSION['message'] = 'Registration successful. Verify your account using the link below.';
                $_SESSION['verify_link'] = $verify_link;
                header('Location: yana_register.php');
                exit;
            }

            $_SESSION['message'] = 'Registration failed. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}

$message = $_SESSION['message'] ?? '';
$verify_link = $_SESSION['verify_link'] ?? '';
unset($_SESSION['message'], $_SESSION['verify_link']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Party4U</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h1 class="h3 text-center mb-4">Create Account</h1>
                        <?php if ($message !== ''): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if ($verify_link !== ''): ?>
                            <div class="alert alert-warning">For testing only: <a href="<?php echo htmlspecialchars($verify_link); ?>">Verify email now</a></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Register</button>
                        </form>
                        <p class="text-center mt-3 mb-0">Already registered? <a href="yana_login.php">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
