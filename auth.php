<?php
require_once __DIR__ . '/functions.php';

$page_title = "Login / Sign up";

$mode = $_POST['mode'] ?? 'login';  // which form was submitted
$activeTab = $_GET['tab'] ?? 'login';

$errors_login = [];
$errors_register = [];

$name = '';
$email_login = '';
$email_register = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---------- LOGIN ----------
    if ($mode === 'login') {
        $email_login = trim($_POST['email'] ?? '');
        $pass        = $_POST['password'] ?? '';

        if (!filter_var($email_login, FILTER_VALIDATE_EMAIL)) {
            $errors_login[] = "Enter a valid email.";
        }
        if ($pass === '') {
            $errors_login[] = "Password is required.";
        }

        if (!$errors_login) {
            global $pdo;
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

        $activeTab = 'login';
    }

    // ---------- REGISTER ----------
    if ($mode === 'register') {
        $name            = trim($_POST['name'] ?? '');
        $email_register  = trim($_POST['email'] ?? '');
        $pass            = $_POST['password'] ?? '';
        $pass2           = $_POST['password_confirm'] ?? '';

        if ($name === '') {
            $errors_register[] = "Name is required.";
        }
        if (!filter_var($email_register, FILTER_VALIDATE_EMAIL)) {
            $errors_register[] = "Valid email is required.";
        }
        if (strlen($pass) < 6) {
            $errors_register[] = "Password must be at least 6 characters.";
        }
        if ($pass !== $pass2) {
            $errors_register[] = "Passwords do not match.";
        }

        if (!$errors_register) {
            global $pdo;
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email_register]);
            if ($stmt->fetch()) {
                $errors_register[] = "An account with this email already exists.";
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?,?,?)");
                $stmt->execute([$name, $email_register, $hash]);

                $_SESSION['user'] = [
                    'id'    => $pdo->lastInsertId(),
                    'name'  => $name,
                    'email' => $email_register,
                ];

                header('Location: index.php');
                exit;
            }
        }

        $activeTab = 'register';
    }
}

include __DIR__ . '/partials/header.php';
?>

<h1 class="mb-4">Welcome to SheMart</h1>

<ul class="nav nav-pills mb-3" id="authTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab === 'login' ? 'active' : ''; ?>" 
            id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane"
            type="button" role="tab">
      Login
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab === 'register' ? 'active' : ''; ?>" 
            id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane"
            type="button" role="tab">
      Sign up
    </button>
  </li>
</ul>

<div class="tab-content" id="authTabsContent">
  <!-- LOGIN TAB -->
  <div class="tab-pane fade <?= $activeTab === 'login' ? 'show active' : ''; ?>" 
       id="login-pane" role="tabpanel">

    <?php if ($errors_login): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors_login as $e): ?>
            <li><?= htmlspecialchars($e); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="col-md-6">
      <input type="hidden" name="mode" value="login">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               value="<?= htmlspecialchars($email_login); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-primary">Login</button>
    </form>
  </div>

  <!-- REGISTER TAB -->
  <div class="tab-pane fade <?= $activeTab === 'register' ? 'show active' : ''; ?>" 
       id="register-pane" role="tabpanel">

    <?php if ($errors_register): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors_register as $e): ?>
            <li><?= htmlspecialchars($e); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="col-md-6">
      <input type="hidden" name="mode" value="register">
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control"
               value="<?= htmlspecialchars($name); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               value="<?= htmlspecialchars($email_register); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password_confirm" class="form-control" required>
      </div>
      <button class="btn btn-primary">Create account</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
