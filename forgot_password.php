<?php
/**
 * forgot_password.php
 * Phase 2 – Forgot Password (Request Reset Link)
 * Safe, silent, non-breaking implementation
 */

require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

/**
 * Only allow POST requests
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/**
 * Generic response (VERY IMPORTANT)
 * This message is shown in ALL cases
 */
$genericMessage = 'If an account exists with this email, a password reset link has been sent.';

/**
 * Read & validate email (silently)
 */
$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo $genericMessage;
    exit;
}

/**
 * Look up user (DO NOT reveal existence)
 */
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/**
 * If user exists, generate and store reset token
 */
if ($user) {
    try {
        // 1. Generate secure random token (64 chars)
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // 2. Invalidate any existing unused tokens
        $stmt = $pdo->prepare(
            'UPDATE password_resets
             SET used_at = NOW()
             WHERE user_id = ?
               AND used_at IS NULL'
        );
        $stmt->execute([$user['id']]);

        // 3. Insert new reset token
        $stmt = $pdo->prepare(
            'INSERT INTO password_resets
             (user_id, token_hash, expires_at, request_ip, user_agent)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?, ?)'
        );

        $stmt->execute([
            $user['id'],
            $tokenHash,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        // 4. Build reset link
        $resetLink = 'http://localhost/womenshop/reset_password.php?token=' . $rawToken;
        echo "<br><b>TEST RESET LINK:</b> <a href='$resetLink'>$resetLink</a>";


        // 5. Send email (basic mail() for now)
        $subject = 'Reset your SheMart password';

        $message  = "Hello,\n\n";
        $message .= "We received a request to reset your password.\n\n";
        $message .= "Click the link below to set a new password:\n";
        $message .= $resetLink . "\n\n";
        $message .= "This link will expire in 30 minutes.\n\n";
        $message .= "If you did not request this, you can safely ignore this email.\n\n";
        $message .= "– SheMart Team";

        @mail($email, $subject, $message);

    } catch (Throwable $e) {
        /**
         * IMPORTANT:
         * Do nothing here.
         * We NEVER reveal errors to the user.
         */
    }
}

/**
 * Always return generic success
 */
echo $genericMessage;
exit;
