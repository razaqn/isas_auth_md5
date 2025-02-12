<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email'])) {
        $email = $_POST['email'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $reset_code = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            
            $stmt = $pdo->prepare("INSERT INTO verification_codes (user_id, code) VALUES (?, ?)");
            if ($stmt->execute([$user['id'], $reset_code])) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_code'] = $reset_code;
                $_SESSION['reset_time'] = time();
                $success = 'We have sent a verification code, it will expire in 5 minutes.';
            } else {
                $error = 'Failed to process password reset. Please try again.';
            }
        } else {
            $error = 'Email not found.';
        }
    } elseif (isset($_POST['verification_code']) && isset($_POST['password'])) {
        $verification_code = $_POST['verification_code'];
        $email = $_SESSION['reset_email'];
        
        $stmt = $pdo->prepare("SELECT v.* FROM verification_codes v JOIN users u ON v.user_id = u.id 
                              WHERE v.code = ? AND v.is_used = 0 
                              AND u.email = ? AND v.expires_at > CURRENT_TIMESTAMP");
        $stmt->execute([$verification_code, $email]);
        $verification = $stmt->fetch();
        
        if ($verification) {
            $password = md5($_POST['password']);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$password, $verification['user_id']])) {
                $stmt = $pdo->prepare("UPDATE verification_codes SET is_used = 1 WHERE id = ?");
                $stmt->execute([$verification['id']]);
                unset($_SESSION['reset_email']);
                header('Location: login.php?reset=success');
                exit();
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        } else {
            $error = 'Invalid or expired verification code.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Forgot Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <?php if (!isset($_SESSION['reset_email'])): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Emaills</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Send Verification Code</button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div id="countdown" class="alert alert-info mb-3"></div>
                        <div class="mb-3">
                            <button type="button" id="resendCode" class="btn btn-secondary">Resend Code</button>
                        </div>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="verification_code" class="form-label">Verification Code</label>
                                <input type="text" class="form-control" id="verification_code" name="verification_code" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                        </form>
                        <?php endif; ?>
                        <div class="mt-3 text-center">
                            <a href="login.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function updateCountdown() {
            const startTime = <?php echo isset($_SESSION['reset_time']) ? $_SESSION['reset_time'] : 0; ?>;
            const currentTime = Math.floor(Date.now() / 1000);
            const timeLeft = Math.max(0, startTime + 300 - currentTime);
            
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            document.getElementById('countdown').innerHTML = 
                timeLeft > 0 ? 
                `Time remaining: ${minutes}:${seconds.toString().padStart(2, '0')}` :
                'Code has expired. Please request a new one.';
            
            if (timeLeft > 0) {
                setTimeout(updateCountdown, 1000);
            }
        }

        document.getElementById('resendCode').addEventListener('click', function() {
            window.location.href = 'forgot_password.php?resend=1';
        });

        updateCountdown();
    </script>
</body>
</html>