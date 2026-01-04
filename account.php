<?php
require_once __DIR__ . '/includes/functions.php';
requireUser();

// --------------------------------------------------
// Page setup
// --------------------------------------------------
$page_title = "My Account";
$user = currentUser();
$loggedUserId = (int)$user['id'];

// --------------------------------------------------
// Fetch fresh user data
// --------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$loggedUserId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    unset($_SESSION['user']);
    header('Location: auth.php?tab=login');
    exit;
}

$tab = $_GET['tab'] ?? 'overview';
$success_msg = '';
$error_msg   = '';

// --------------------------------------------------
// Handle form submissions
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Update profile ----
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            $error_msg = "Name cannot be empty.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $loggedUserId]);

            $_SESSION['user']['name'] = $name;
            $success_msg = "Profile updated successfully.";
            $tab = 'overview';
        }
    }

    // ---- Update address ----
    if ($action === 'update_address') {
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $address_line2 = trim($_POST['address_line2'] ?? '');
        $city          = trim($_POST['city'] ?? '');
        $state         = trim($_POST['state'] ?? '');
        $postal_code   = trim($_POST['postal_code'] ?? '');
        $country       = trim($_POST['country'] ?? '');

        if ($address_line1 === '' || $city === '' || $postal_code === '') {
            $error_msg = "Address line 1, city and postal code are required.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $address_line1, $address_line2, $city, $state,
                $postal_code, $country, $loggedUserId
            ]);

            $success_msg = "Address updated successfully.";
            $tab = 'address';
        }
    }

    // ---- Change password ----
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 6) {
            $error_msg = "New password must be at least 6 characters.";
        } elseif ($new !== $confirm) {
            $error_msg = "New passwords do not match.";
        } elseif (!password_verify($current, $userRow['password_hash'])) {
            $error_msg = "Current password is incorrect.";
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $loggedUserId]);

            $success_msg = "Password changed successfully.";
            $tab = 'security';
        }
    }

    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$loggedUserId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/partials/header.php';

// --------------------------------------------------
// Fetch user orders
// --------------------------------------------------
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM orders
        WHERE (customer_email = :email OR user_id = :uid)
        ORDER BY created_at DESC
    ");
    $stmt->execute([
        ':email' => $userRow['email'],
        ':uid'   => $loggedUserId
    ]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
}
?>

<h1 class="mb-4">My Account</h1>

<?php if ($success_msg): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success_msg); ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error_msg); ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='overview'?'active':'' ?>" href="?tab=overview">Profile</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='address'?'active':'' ?>" href="?tab=address">Address</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='orders'?'active':'' ?>" href="?tab=orders">Orders</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='security'?'active':'' ?>" href="?tab=security">Security</a></li>
</ul>

<div class="tab-content">

<!-- PROFILE -->
<div class="tab-pane fade <?= $tab==='overview'?'show active':'' ?>">
<form method="post" class="col-md-6">
<input type="hidden" name="action" value="update_profile">
<div class="mb-3">
<label>Name</label>
<input type="text" name="name" class="form-control" value="<?= htmlspecialchars($userRow['name']); ?>" required>
</div>
<div class="mb-3">
<label>Email</label>
<input type="email" class="form-control" value="<?= htmlspecialchars($userRow['email']); ?>" disabled>
</div>
<div class="mb-3">
<label>Phone</label>
<input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($userRow['phone'] ?? ''); ?>">
</div>
<button class="btn btn-primary">Save</button>
</form>
</div>

<!-- ADDRESS -->
<div class="tab-pane fade <?= $tab==='address'?'show active':'' ?>">
<form method="post" class="col-md-8">
<input type="hidden" name="action" value="update_address">
<input class="form-control mb-2" name="address_line1" placeholder="Address Line 1" value="<?= htmlspecialchars($userRow['address_line1'] ?? ''); ?>">
<input class="form-control mb-2" name="address_line2" placeholder="Address Line 2" value="<?= htmlspecialchars($userRow['address_line2'] ?? ''); ?>">
<input class="form-control mb-2" name="city" placeholder="City" value="<?= htmlspecialchars($userRow['city'] ?? ''); ?>">
<input class="form-control mb-2" name="state" placeholder="State" value="<?= htmlspecialchars($userRow['state'] ?? ''); ?>">
<input class="form-control mb-2" name="postal_code" placeholder="Postal Code" value="<?= htmlspecialchars($userRow['postal_code'] ?? ''); ?>">
<input class="form-control mb-2" name="country" placeholder="Country" value="<?= htmlspecialchars($userRow['country'] ?? ''); ?>">
<button class="btn btn-primary">Save Address</button>
</form>
</div>

<!-- ORDERS -->
<div class="tab-pane fade <?= $tab==='orders'?'show active':'' ?>">
<?php if (empty($orders)): ?>
<p class="text-muted">You have no orders.</p>
<?php else: ?>
<table class="table">
<thead><tr><th>#</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($orders as $o): ?>
<tr>
<td>#<?= (int)$o['id'] ?></td>
<td><?= htmlspecialchars($o['created_at']) ?></td>
<td>₹<?= number_format($o['total_amount'] ?? 0, 2) ?></td>
<td><?= htmlspecialchars($o['order_status'] ?? ''); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<!-- SECURITY -->
<div class="tab-pane fade <?= $tab==='security'?'show active':'' ?>">
<form method="post" class="col-md-6">
<input type="hidden" name="action" value="change_password">
<input type="password" name="current_password" class="form-control mb-2" placeholder="Current password" required>
<input type="password" name="new_password" class="form-control mb-2" placeholder="New password" required>
<input type="password" name="confirm_password" class="form-control mb-2" placeholder="Confirm password" required>
<button class="btn btn-primary">Change Password</button>
</form>
</div>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
