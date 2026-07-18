<?php
session_start();
require_once __DIR__ . '/includes/yana_mailer.php';

$message = '';
$sent = false;
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid Gmail address.';
    } else {
        $verify_link = yana_site_url('yana_verify_email.php?code=' . str_repeat('a', 64));
        $result = yana_send_verification_email($email, 'Party4U Tester', $verify_link);
        $sent = (bool) $result['sent'];
        $message = $sent
            ? 'Test email sent. Please check your Gmail inbox and spam folder.'
            : 'Test email failed: ' . $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email | Party4U</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h1 class="h3 text-center mb-4">Test Gmail Sending</h1>
                        <?php if ($message !== ''): ?>
                            <div class="alert <?php echo $sent ? 'alert-success' : 'alert-warning'; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Send test email to</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Test Email</button>
                        </form>
                        <p class="text-center mt-3 mb-0"><a href="yana_register.php">Back to registration</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
