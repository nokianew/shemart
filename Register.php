<?php
require_once __DIR__ . '/functions.php';

$page_title = "Create Account";
$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if ($name === '') {
        $errors[] = "Name is required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (strlen($pass) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($pass !== $pass2) {
        $errors[] = "Passwords do not match.";
    }

    if (!$errors) {
        // check if email exists
        global $pdo;
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "An account with this email already exists.";
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?,?,?)");
            $stmt->execute([$name, $email, $hash]);

            // auto-login
            $_SESSION['user'] = [
                'id'    => $pdo->lastInsertId(),
                'name'  => $name,
                'email' => $email,
            ];

            header('Location: index.php');
            exit;
        }
    }
}

include __DIR__ . '/partials/header.php';
?>

<h1 class="mb-4">Create your account</h1>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" class="col-md-6">
  <div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control"
           value="<?= htmlspecialchars($name); ?>" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control"
           value="<?= htmlspecialchars($email); ?>" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Password</label>
    <input type="password" name="password" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Confirm Password</label>
    <input type="password" name="password_confirm" class="form-control" required>
  </div>

  <button class="btn btn-primary">Create Account</button>
  <a href="login.php" class="btn btn-link">Already have an account? Login</a>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
