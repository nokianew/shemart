<?php
/**
 * reset_password.php
 * Phase 2 – Forgot Password (Reset Password)
 * Secure, single-use, expiry-based reset
 */

require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

$error = '';
$success = '';
$showForm = false;

/**
 * Helper: validate token and fetch reset row
 */
function getValidResetToken(PDO $pdo, string $rawToken)
{
    if ($rawToken === '') {
        return false;
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare(
        'SELECT pr.id, pr.user_id
         FROM password_resets pr
         WHERE pr.token_hash = ?
           AND pr.used_at IS NULL
           AND pr.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * =========================
 * HANDLE GET (SHOW FORM)
 * =========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['token'] ?? '');

    $reset = getValidResetToken($pdo, $token);

    if (!$reset) {
        $error = 'This password reset link is invalid or has expired.';
    } else {
        $showForm = true;
    }
}

/**
 * =========================
 * HANDLE POST (RESET)
 * =========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token           = trim($_POST['token'] ?? '');
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Re-validate token (NEVER trust GET)
    $reset = getValidResetToken($pdo, $token);

    if (!$reset) {
        $error = 'This password reset link is invalid or has expired.';
    } elseif ($newPassword === '' || strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update user password
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET password_hash = ?
                 WHERE id = ?'
            );
            $stmt->execute([$passwordHash, $reset['user_id']]);

            // Mark this token as used
            $stmt = $pdo->prepare(
                'UPDATE password_resets
                 SET used_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([$reset['id']]);

            // Invalidate any other unused tokens for this user
            $stmt = $pdo->prepare(
                'UPDATE password_resets
                 SET used_at = NOW()
                 WHERE user_id = ?
                   AND used_at IS NULL'
            );
            $stmt->execute([$reset['user_id']]);

            // Redirect to login with success flag
            header('Location: login.php?reset=success');
            exit;

        } catch (Throwable $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 420px; margin: 60px auto; }
        input, button { width: 100%; padding: 10px; margin: 8px 0; }
        .error { color: #b00020; margin-bottom: 10px; }
        .success { color: #0a7a28; margin-bottom: 10px; }
    </style>
</head>
<body>

<h2>Reset Password</h2>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($showForm): ?>
    <form method="post" action="reset_password.php">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">

        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Set New Password</button>
    </form>
<?php elseif (!$error): ?>
    <p>Please use the password reset link sent to your email.</p>
<?php endif; ?>

</body>
</html>
