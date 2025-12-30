<?php
require_once __DIR__ . '/functions.php';

$tab = $_GET['tab'] ?? 'login';
if (!in_array($tab, ['login', 'signup'])) {
    $tab = 'login';
}

$page_title = "Login / Sign up";

$errors_login = [];
$errors_signup = [];

$name = '';
$email_login = '';
$email_signup = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ================= LOGIN ================= */
    if ($_POST['mode'] === 'login') {
        $email_login = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';

        if (!filter_var($email_login, FILTER_VALIDATE_EMAIL)) {
            $errors_login[] = "Enter a valid email.";
        }
        if ($pass === '') {
            $errors_login[] = "Password is required.";
        }

        if (!$errors_login) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email_login]);
            $user = $stmt->fetch();

            if ($user && password_verify($pass, $user['password_hash'])) {
                $_SESSION['user'] = [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                ];
                header('Location: index.php');
                exit;
            } else {
                $errors_login[] = "Invalid email or password.";
            }
        }

        $tab = 'login';
    }

    /* ================= SIGNUP ================= */
    if ($_POST['mode'] === 'signup') {
        $name = trim($_POST['name'] ?? '');
        $email_signup = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $pass2 = $_POST['password_confirm'] ?? '';

        if ($name === '') {
            $errors_signup[] = "Name is required.";
        }
        if (!filter_var($email_signup, FILTER_VALIDATE_EMAIL)) {
            $errors_signup[] = "Valid email is required.";
        }
        if (strlen($pass) < 6) {
            $errors_signup[] = "Password must be at least 6 characters.";
        }
        if ($pass !== $pass2) {
            $errors_signup[] = "Passwords do not match.";
        }

        if (!$errors_signup) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email_signup]);

            if ($stmt->fetch()) {
                $errors_signup[] = "Email already registered.";
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, password_hash) VALUES (?,?,?)"
                );
                $stmt->execute([$name, $email_signup, $hash]);

                $_SESSION['user'] = [
                    'id'    => $pdo->lastInsertId(),
                    'name'  => $name,
                    'email' => $email_signup,
                ];

                header('Location: index.php');
                exit;
            }
        }

        $tab = 'signup';
    }
}

include __DIR__ . '/partials/header.php';
?>

<h1 class="mb-4">Welcome to SheMart</h1>

<div class="mb-3">
  <a href="auth.php?tab=login" class="btn <?= $tab === 'login' ? 'btn-primary' : 'btn-outline-primary'; ?>">
    Login
  </a>
  <a href="auth.php?tab=signup" class="btn <?= $tab === 'signup' ? 'btn-primary' : 'btn-outline-primary'; ?>">
    Sign up
  </a>
</div>

<?php if ($tab === 'login'): ?>

  <?php if ($errors_login): ?>
    <div class="alert alert-danger">
      <?= implode('<br>', array_map('htmlspecialchars', $errors_login)); ?>
    </div>
  <?php endif; ?>

  <form method="post" class="col-md-6">
    <input type="hidden" name="mode" value="login">
    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control"
             value="<?= htmlspecialchars($email_login); ?>" required>
    </div>
    <div class="mb-3">
      <label>Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-primary">Login</button>
  </form>

<?php else: ?>

  <?php if ($errors_signup): ?>
    <div class="alert alert-danger">
      <?= implode('<br>', array_map('htmlspecialchars', $errors_signup)); ?>
    </div>
  <?php endif; ?>

  <form method="post" class="col-md-6">
    <input type="hidden" name="mode" value="signup">
    <div class="mb-3">
      <label>Name</label>
      <input type="text" name="name" class="form-control"
             value="<?= htmlspecialchars($name); ?>" required>
    </div>
    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control"
             value="<?= htmlspecialchars($email_signup); ?>" required>
    </div>
    <div class="mb-3">
      <label>Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Confirm Password</label>
      <input type="password" name="password_confirm" class="form-control" required>
    </div>
    <button class="btn btn-primary">Create account</button>
  </form>

<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
