<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Only logged-in customers can see this
requireUser();

$page_title = "My Account";
$user = currentUser();


// Determine the logged-in user id safely (support multiple variable names)
$loggedUserId = null;
if (isset($user['id']) && $user['id']) {
    $loggedUserId = (int)$user['id'];
} elseif (isset($_SESSION['user']['id']) && $_SESSION['user']['id']) {
    $loggedUserId = (int)$_SESSION['user']['id'];
}

// If we still don't have an id, force re-login
if (!$loggedUserId) {
    header('Location: auth.php');
    exit;
}

// Get fresh user data from DB using the safe id
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$loggedUserId]);
$userRow = $stmt->fetch();

if (!$userRow) {
    // something is wrong, log out
    unset($_SESSION['user']);
    header('Location: auth.php');
    exit;
}

$tab = $_GET['tab'] ?? 'overview';

$success_msg = '';
$error_msg   = '';

// Handle forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -------- Update profile (name, phone) --------
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            $error_msg = "Name cannot be empty.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $userRow['id']]);

            // Update session (if session holds user data)
            if (isset($_SESSION['user'])) {
                $_SESSION['user']['name'] = $name;
            }

            $success_msg = "Profile updated successfully.";
            $tab = 'overview';
        }
    }

    // -------- Update address --------
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
                $address_line1, $address_line2, $city, $state, $postal_code, $country, $userRow['id']
            ]);

            $success_msg = "Address updated successfully.";
            $tab = 'address';
        }
    }

    // -------- Change password --------
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new === '' || strlen($new) < 6) {
            $error_msg = "New password must be at least 6 characters.";
        } elseif ($new !== $confirm) {
            $error_msg = "New passwords do not match.";
        } else {
            // Check current password
            if (!password_verify($current, $userRow['password_hash'])) {
                $error_msg = "Current password is incorrect.";
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hash, $userRow['id']]);
                $success_msg = "Password changed successfully.";
                $tab = 'security';
            }
        }
    }

    // Refresh data after any update
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userRow['id']]);
    $userRow = $stmt->fetch();
}

$page_title = "My Account";
include __DIR__ . '/partials/header.php';

// -------------------------
// Fetch orders for this user
// -------------------------
$orders = [];
try {
    $sql = "
        SELECT * FROM orders
        WHERE (customer_email IS NOT NULL AND customer_email = :email)
           OR (user_id IS NOT NULL AND user_id = :uid)
        ORDER BY created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $userRow['email'],
        ':uid'   => $userRow['id']
    ]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
}

// Path used on the site to serve images (web path)
$UPLOADS_PATH = '/womenshop/assets/images/'; // <- matches C:\xampp\htdocs\womenshop\assets\images

// helper to resolve stored image value to a usable <img> src
function resolveImageSrc($val, $uploadsPath) {
    if (!$val) return '';
    $val = trim($val);
    // if it's already an absolute URL or starts with a slash, return as-is
    if (stripos($val, 'http://') === 0 || stripos($val, 'https://') === 0 || strpos($val, '/') === 0) {
        return $val;
    }
    // otherwise treat as filename and prefix uploads path
    return rtrim($uploadsPath, '/') . '/' . ltrim($val, '/');
}

// --- Build product summaries for the listed orders (so we can show "Pendrive x1, Cable x2") ---
$orderProducts = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $orderIds = array_map('intval', $orderIds);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    $sqlItems = "
        SELECT oi.order_id, oi.product_id,
               p.name AS product_name,
               COALESCE(oi.quantity, oi.qty, 1) AS qty
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_id, oi.id
    ";
    try {
        $stmt = $pdo->prepare($sqlItems);
        $stmt->execute($orderIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $oid = (int)$r['order_id'];
            $name = $r['product_name'] ?? ('Product #' . ($r['product_id'] ?? ''));
            $qty  = (int)$r['qty'];
            $orderProducts[$oid][] = $name . ' x' . $qty;
        }

        foreach ($orderProducts as $oid => $itemsArr) {
            $orderProducts[$oid] = implode(', ', $itemsArr);
        }
    } catch (Exception $e) {
        $orderProducts = [];
    }
}

// Ensure detail variables exist to avoid warnings
$orderDetail = null;
$orderItems  = [];

// -------------------------
// Handle optional single-order view
// -------------------------
$viewOrder = isset($_GET['view']) ? (int)$_GET['view'] : 0;
if ($viewOrder > 0) {
    try {
        // fetch order and ensure it belongs to current user (by email OR user_id)
        $stmt = $pdo->prepare("
            SELECT * FROM orders 
            WHERE id = :oid 
              AND (customer_email = :email OR user_id = :uid)
            LIMIT 1
        ");
        $stmt->execute([
            ':oid'   => $viewOrder,
            ':email' => $userRow['email'],
            ':uid'   => $userRow['id']
        ]);
        $orderDetail = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($orderDetail) {
            // fetch items with product name + image
            $stmt = $pdo->prepare("
                SELECT oi.*,
                       p.name AS product_name,
                       COALESCE(p.image,'') AS product_image
                FROM order_items oi
                LEFT JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$viewOrder]);
            $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- load saved address row when address_id present ---
            $orderDetail['address'] = [];
            if (!empty($orderDetail['address_id'])) {
                try {
                    $st = $pdo->prepare("SELECT * FROM addresses WHERE id = ? LIMIT 1");
                    $st->execute([$orderDetail['address_id']]);
                    $addrRow = $st->fetch(PDO::FETCH_ASSOC);
                    if ($addrRow) {
                        $orderDetail['address'] = $addrRow;
                    }
                } catch (Exception $e) {
                    // ignore if addresses table/row missing
                    $orderDetail['address'] = [];
                }
            }
        }
    } catch (Exception $e) {
        $orderDetail = null;
        $orderItems = [];
    }
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
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'overview' ? 'active' : ''; ?>" href="?tab=overview">Profile</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'address' ? 'active' : ''; ?>" href="?tab=address">Address</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'orders' ? 'active' : ''; ?>" href="?tab=orders">Orders</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'security' ? 'active' : ''; ?>" href="?tab=security">Security</a>
  </li>
</ul>

<div class="tab-content">

  <!-- PROFILE TAB -->
  <div class="tab-pane fade <?= $tab === 'overview' ? 'show active' : ''; ?>" id="tab-overview">
    <form method="post" class="col-md-6">
      <input type="hidden" name="action" value="update_profile">
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control"
               value="<?= htmlspecialchars($userRow['name']); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email (cannot change)</label>
        <input type="email" class="form-control" value="<?= htmlspecialchars($userRow['email']); ?>" disabled>
      </div>
      <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control"
               value="<?= htmlspecialchars($userRow['phone'] ?? ''); ?>">
      </div>
      <button class="btn btn-primary">Save Profile</button>
    </form>
  </div>

  <!-- ADDRESS TAB -->
  <div class="tab-pane fade <?= $tab === 'address' ? 'show active' : ''; ?>" id="tab-address">
    <form method="post" class="col-md-8">
      <input type="hidden" name="action" value="update_address">
      <div class="mb-3">
        <label class="form-label">Address Line 1</label>
        <input type="text" name="address_line1" class="form-control"
               value="<?= htmlspecialchars($userRow['address_line1'] ?? ''); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Address Line 2</label>
        <input type="text" name="address_line2" class="form-control"
               value="<?= htmlspecialchars($userRow['address_line2'] ?? ''); ?>">
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">City</label>
          <input type="text" name="city" class="form-control"
                 value="<?= htmlspecialchars($userRow['city'] ?? ''); ?>" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">State</label>
          <input type="text" name="state" class="form-control"
                 value="<?= htmlspecialchars($userRow['state'] ?? ''); ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Postal Code</label>
          <input type="text" name="postal_code" class="form-control"
                 value="<?= htmlspecialchars($userRow['postal_code'] ?? ''); ?>" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Country</label>
        <input type="text" name="country" class="form-control"
               value="<?= htmlspecialchars($userRow['country'] ?? ''); ?>">
      </div>
      <button class="btn btn-primary">Save Address</button>
    </form>
  </div>

  <!-- ORDERS TAB -->
  <div class="tab-pane fade <?= $tab === 'orders' ? 'show active' : ''; ?>" id="tab-orders">

    <?php
    // small helper functions
    if (!function_exists('orderBadgeClass')) {
        function orderBadgeClass($status) {
            $s = strtolower((string)$status);
            if (str_contains($s, 'placed')) return 'bg-secondary';
            if (str_contains($s, 'processing')) return 'bg-warning text-dark';
            if (str_contains($s, 'shipped')) return 'bg-info text-dark';
            if (str_contains($s, 'delivered')) return 'bg-success';
            if (str_contains($s, 'cancel')) return 'bg-danger';
            return 'bg-light text-dark';
        }
    }
    if (!function_exists('fmt_date')) {
        function fmt_date($d) {
            if (!$d) return '-';
            return date('d M Y, H:i', strtotime($d));
        }
    }
    if (!function_exists('fmt_amount')) {
        function fmt_amount($v) {
            return '₹' . number_format((float)($v ?? 0), 2);
        }
    }
    ?>

    <?php if (empty($orders) && !$orderDetail): ?>
      <p class="text-muted">You have no orders yet.</p>

    <?php elseif ($orderDetail): ?>
      <!-- ORDER DETAIL -->
      <a href="?tab=orders" class="btn btn-sm btn-outline-secondary mb-3">← Back to Orders</a>

      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <strong>Order #<?= (int)$orderDetail['id']; ?></strong>
            <div class="small text-muted">Placed: <?= fmt_date($orderDetail['created_at']); ?></div>
          </div>
          <div class="text-end">
            <div>
              <span class="badge <?= orderBadgeClass($orderDetail['order_status'] ?? $orderDetail['tracking_status'] ?? $orderDetail['status'] ?? '') ?>">
                <?= htmlspecialchars(strtoupper($orderDetail['order_status'] ?? $orderDetail['tracking_status'] ?? $orderDetail['status'] ?? 'UNKNOWN')) ?>
              </span>
            </div>
            <div class="small mt-1">
              Tracking:
              <?php if (!empty($orderDetail['tracking_code'])): ?>
                <span class="badge bg-secondary"><?= htmlspecialchars($orderDetail['tracking_code']) ?></span>
              <?php else: ?>
                <span class="text-muted small">Not assigned</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <h6>Shipping Address</h6>

              <?php
              // Build shipping HTML from address row, then shipping_* columns, then legacy shipping_address
              $showShipping = false;
              $shippingHtml = '';

              // 1) address row loaded above
              if (!empty($orderDetail['address']) && is_array($orderDetail['address']) && count($orderDetail['address'])>0) {
                  $a = $orderDetail['address'];
                  $showShipping = true;
                  $parts = [];
                  if (!empty($a['name'])) $parts[] = htmlspecialchars($a['name']);
                  $street = trim((($a['address_line1'] ?? '') . ' ' . ($a['address_line2'] ?? '')));
                  if ($street) $parts[] = nl2br(htmlspecialchars($street));
                  $cityLine = trim((($a['city'] ?? '') . ' ' . ($a['state'] ?? '') . ' ' . ($a['postal_code'] ?? '')));
                  if ($cityLine) $parts[] = htmlspecialchars($cityLine);
                  if (!empty($a['country'])) $parts[] = htmlspecialchars($a['country']);
                  if (!empty($a['phone'])) $parts[] = 'Phone: ' . htmlspecialchars($a['phone']);
                  $shippingHtml = implode("<br>\n", $parts);
              }

              // 2) fallback to shipping_* columns on orders
              elseif (!empty($orderDetail['shipping_name']) || !empty($orderDetail['shipping_address_line1']) || !empty($orderDetail['shipping_address_line2'])) {
                  $showShipping = true;
                  $parts = [];
                  if (!empty($orderDetail['shipping_name'])) $parts[] = htmlspecialchars($orderDetail['shipping_name']);
                  $street = trim((($orderDetail['shipping_address_line1'] ?? '') . ' ' . ($orderDetail['shipping_address_line2'] ?? '')));
                  if ($street) $parts[] = nl2br(htmlspecialchars($street));
                  $cityLine = trim((($orderDetail['shipping_city'] ?? '') . ' ' . ($orderDetail['shipping_state'] ?? '') . ' ' . ($orderDetail['shipping_postal_code'] ?? '')));
                  if ($cityLine) $parts[] = htmlspecialchars($cityLine);
                  if (!empty($orderDetail['shipping_country'])) $parts[] = htmlspecialchars($orderDetail['shipping_country']);
                  if (!empty($orderDetail['shipping_phone'])) $parts[] = 'Phone: ' . htmlspecialchars($orderDetail['shipping_phone']);
                  $shippingHtml = implode("<br>\n", $parts);
              }

              // 3) legacy text column
              elseif (!empty($orderDetail['shipping_address'])) {
                  $showShipping = true;
                  $shippingHtml = nl2br(htmlspecialchars($orderDetail['shipping_address']));
              }
              ?>

              <?php if ($showShipping): ?>
                <div class="small"><?= $shippingHtml; ?></div>
              <?php else: ?>
                <div class="small text-muted">No shipping address saved.</div>
              <?php endif; ?>

            </div>
            <div class="col-md-6 text-end">
              <h6>Order Summary</h6>
              <div><strong>Total:</strong> <?= fmt_amount($orderDetail['total_amount'] ?? $orderDetail['total'] ?? 0); ?></div>
              <?php if (!empty($orderDetail['delivery_charge'])): ?>
                <div class="small">Delivery: <?= fmt_amount($orderDetail['delivery_charge']); ?></div>
              <?php endif; ?>
              <?php if (!empty($orderDetail['discount'])): ?>
                <div class="small">Discount: <?= fmt_amount($orderDetail['discount']); ?></div>
              <?php endif; ?>
            </div>
          </div>

          <h6 class="mb-2">Items</h6>
          <?php if (empty($orderItems)): ?>
            <p class="text-muted">No items found for this order.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $calc_total = 0; foreach ($orderItems as $it):
                    $qty = isset($it['quantity']) ? (int)$it['quantity'] : (isset($it['qty']) ? (int)$it['qty'] : 1);
                    $price = (float)($it['price'] ?? $it['unit_price'] ?? 0);
                    $subtotal = $qty * $price;
                    $calc_total += $subtotal;

                    // use product_image from DB; resolve to correct web path
                    $product_image = $it['product_image'] ?? '';
                    $imgSrc = resolveImageSrc($product_image, $UPLOADS_PATH);
                    $product_name = $it['product_name'] ?? ($it['name'] ?? ('Product #' . (int)($it['product_id'] ?? 0)));
                  ?>
                    <tr>
                      <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                          <?php if ($imgSrc): ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product_name) ?>" style="width:64px;height:64px;object-fit:cover;border-radius:6px;">
                          <?php else: ?>
                            <div style="width:64px;height:64px;background:#f3f3f3;border-radius:6px;display:inline-block"></div>
                          <?php endif; ?>
                          <div>
                            <div style="font-weight:600"><?= htmlspecialchars($product_name) ?></div>
                            <?php if (!empty($it['variant'])): ?>
                              <div class="text-muted" style="font-size:13px"><?= htmlspecialchars($it['variant']) ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="text-center"><?= $qty; ?></td>
                      <td class="text-end"><?= fmt_amount($price); ?></td>
                      <td class="text-end"><?= fmt_amount($subtotal); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="3" class="text-end">Items total</th>
                    <th class="text-end"><?= fmt_amount($calc_total); ?></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php endif; ?>

        </div>
      </div>

    <?php else: ?>
      <!-- ORDERS LIST -->
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Date</th>
              <th>Total</th>
              <th>Status</th>
              <th>Tracking</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <tr>
                <td>
                  <?php
                    $oid = (int)$order['id'];
                    // fetch first item for preview (with image)
                    try {
                        $stmtItem = $pdo->prepare("
                            SELECT oi.*, COALESCE(p.name,'') AS product_name, COALESCE(p.image,'') AS product_image
                            FROM order_items oi
                            LEFT JOIN products p ON p.id = oi.product_id
                            WHERE oi.order_id = ?
                            ORDER BY oi.id ASC
                            LIMIT 1
                        ");
                        $stmtItem->execute([$oid]);
                        $firstItem = $stmtItem->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $firstItem = false;
                    }

                    echo '#' . $oid . '<br>';

                    if ($firstItem) {
                        $productImage = $firstItem['product_image'] ?? '';
                        $img = resolveImageSrc($productImage, $UPLOADS_PATH);
                        $qty = (int)($firstItem['quantity'] ?? $firstItem['qty'] ?? 1);
                        ?>
                        <div style="display:flex;align-items:center;gap:8px;">
                          <?php if ($img): ?>
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($firstItem['product_name'] ?? '') ?>" style="width:44px;height:44px;object-fit:cover;border-radius:6px;">
                          <?php else: ?>
                            <div style="width:44px;height:44px;background:#f3f3f3;border-radius:6px;display:inline-block"></div>
                          <?php endif; ?>
                          <div>
                            <div style="font-weight:600;font-size:0.95em;"><?= htmlspecialchars($firstItem['product_name'] ?: 'Product') ?></div>
                            <div class="text-muted" style="font-size:0.85em">Qty: <?= $qty ?></div>
                          </div>
                        </div>
                        <?php
                    } else {
                        if (!empty($orderProducts[$order['id']])) {
                            echo '<small class="text-muted">' . htmlspecialchars($orderProducts[$order['id']]) . '</small>';
                        } else {
                            echo '<small class="text-muted">No items</small>';
                        }
                    }
                  ?>
                </td>
                <td><?= fmt_date($order['created_at']); ?></td>
                <td><?= fmt_amount($order['total_amount'] ?? $order['total'] ?? 0); ?></td>
                <td>
                  <span class="badge <?= orderBadgeClass($order['order_status'] ?? $order['tracking_status'] ?? $order['status'] ?? '') ?>">
                    <?= htmlspecialchars(strtoupper($order['order_status'] ?? $order['tracking_status'] ?? $order['status'] ?? 'UNKNOWN')) ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($order['tracking_code'])): ?>
                    <span class="badge bg-secondary"><?= htmlspecialchars($order['tracking_code']); ?></span>
                  <?php else: ?>
                    <span class="text-muted small">Not assigned</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="?tab=orders&view=<?= (int)$order['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>

  <!-- SECURITY TAB -->
  <div class="tab-pane fade <?= $tab === 'security' ? 'show active' : ''; ?>" id="tab-security">
    <form method="post" class="col-md-6">
      <input type="hidden" name="action" value="change_password">
      <div class="mb-3">
        <label class="form-label">Current Password</label>
        <input type="password" name="current_password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>
      <button class="btn btn-primary">Change Password</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
