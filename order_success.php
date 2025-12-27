<?php
require_once __DIR__ . '/functions.php';

// Ensure we have PDO (fallback to config.php)
if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
}

// Accept either ?id= or ?order_id= so both flows work
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0);
$whatsapp_url = '';

// --- START: Auto WhatsApp Block (build url if possible) ---
if ($order_id > 0 && isset($pdo)) {
    $stmt = $pdo->prepare("
        SELECT total_amount, shipping_name, customer_name 
        FROM orders 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $o = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($o) {
        $name = !empty($o['shipping_name']) ? $o['shipping_name'] : ($o['customer_name'] ?? 'Customer');
        $total = number_format((float)$o['total_amount'], 2);

        $msg = "New Order Received!\nOrder ID: $order_id\nCustomer: $name\nTotal: ₹$total";
        $admin = "916260096745"; // admin number without +

        $whatsapp_url = "https://wa.me/{$admin}?text=" . urlencode($msg);
    }
}
// --- END: Auto WhatsApp Block ---

// Optional: If your site requires login to view orders, uncomment:
// requireUser();

// Load order (for display)
$stmt = $pdo->prepare("
    SELECT o.*, a.name AS addr_name, a.phone AS addr_phone,
           a.address_line1, a.address_line2, a.city, a.state,
           a.postal_code, a.country
    FROM orders o
    LEFT JOIN addresses a ON o.address_id = a.id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

$page_title = $order ? "Order #{$order_id} placed" : "Order placed";
include __DIR__ . '/partials/header.php';
?>

<div class="my-5 container">

  <?php if (!$order): ?>
      <h1 class="text-center">Order Not Found</h1>
      <p class="text-center">Sorry, this order does not exist.</p>
      <?php include __DIR__ . '/partials/footer.php'; exit; ?>
  <?php endif; ?>

  <div class="text-center mb-5">
      <h1 class="mb-3">Thank you for your purchase!</h1>
      <p>Your order <strong>#<?= $order_id ?></strong> has been placed successfully.</p>
  </div>

  <div class="row">

    <!-- ORDER DETAILS -->
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header bg-primary text-white">
            Order Details
        </div>
        <div class="card-body">

          <p><strong>Order ID:</strong> <?= $order_id ?></p>
          <p><strong>Total Amount:</strong> ₹<?= number_format($order['total_amount'], 2) ?></p>
          <p><strong>Delivery Charge:</strong> ₹<?= number_format($order['delivery_charge'], 2) ?></p>
          <p><strong>Discount:</strong> ₹<?= number_format($order['discount'], 2) ?></p>

          <hr>

          <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
          <p><strong>Payment Status:</strong> 
              <span class="badge bg-<?= $order['payment_status'] === 'PAID' ? 'success' : 'warning' ?>">
                <?= htmlspecialchars($order['payment_status']) ?>
              </span>
          </p>

          <p><strong>Order Status:</strong> <?= htmlspecialchars($order['order_status']) ?></p>

        </div>
      </div>
    </div>

    <!-- SHIPPING ADDRESS -->
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header bg-success text-white">
            Shipping Address
        </div>
        <div class="card-body">
          <p><strong>Name:</strong> <?= htmlspecialchars($order['addr_name']) ?></p>
          <p><strong>Phone:</strong> <?= htmlspecialchars($order['addr_phone']) ?></p>
          <p><strong>Address:</strong><br>
            <?= htmlspecialchars($order['address_line1']) ?><br>
            <?= htmlspecialchars($order['address_line2']) ?><br>
            <?= htmlspecialchars($order['city']) ?>,
            <?= htmlspecialchars($order['state']) ?> - 
            <?= htmlspecialchars($order['postal_code']) ?><br>
            <?= htmlspecialchars($order['country']) ?>
          </p>
        </div>
      </div>
    </div>

  </div>

  <!-- ORDER ITEMS -->
  <div class="card mb-4">
      <div class="card-header bg-dark text-white">Items in your order</div>
      <div class="card-body">
        <?php
          $istmt = $pdo->prepare("
            SELECT oi.*, p.name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
          ");
          $istmt->execute([$order_id]);
          $items = $istmt->fetchAll();
        ?>

        <?php if ($items): ?>
            <?php foreach ($items as $item): ?>
              <div class="d-flex justify-content-between border-bottom py-2">
                <div>
                  <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                  <small>Qty: <?= $item['quantity'] ?> × ₹<?= number_format($item['price'], 2) ?></small>
                </div>
                <div>
                  ₹<?= number_format($item['quantity'] * $item['price'], 2) ?>
                </div>
              </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No items found.</p>
        <?php endif; ?>
      </div>
  </div>

  <div class="text-center">
    <a href="index.php" class="btn btn-primary">Continue Shopping</a>
  </div>

</div>

<?php if (!empty($whatsapp_url)): ?>
<script>
(function() {
  const waUrl = <?= json_encode($whatsapp_url, JSON_UNESCAPED_SLASHES) ?>;

  // Try to open WhatsApp in a new tab immediately.
  // Many browsers allow window.open('_blank') from a navigation that follows user action (redirect),
  // but it may be blocked — we detect that and fall back to showing an unobtrusive banner.
  let opened = null;
  try {
    opened = window.open(waUrl, '_blank', 'noopener,noreferrer');
  } catch (err) {
    opened = null;
  }

  // A short timeout to allow popup to open; some browsers immediately block and return null.
  setTimeout(() => {
    const blocked = !opened || opened.closed || typeof opened.closed === 'undefined';

    if (!blocked) {
      // Success: show a small ephemeral toast confirming WhatsApp opened.
      const toast = document.createElement('div');
      toast.textContent = 'WhatsApp opened in a new tab — we will keep you here.';
      toast.setAttribute('role','status');
      Object.assign(toast.style, {
        position: 'fixed',
        right: '18px',
        bottom: '18px',
        background: '#0f5132',
        color: '#fff',
        padding: '10px 14px',
        borderRadius: '8px',
        boxShadow: '0 6px 18px rgba(15,21,34,0.12)',
        fontSize: '14px',
        zIndex: 99999,
        opacity: '0.98'
      });
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 3500);
      return;
    }

    // Blocked: create an unobtrusive banner with a direct button to open WhatsApp (user gesture)
    const banner = document.createElement('div');
    banner.innerHTML = `
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="font-size:14px;color:#222;">We've prepared a WhatsApp message for your order. Click to open:</div>
        <a id="openWhatsAppBtn" target="_blank" rel="noopener noreferrer" href="${waUrl}" style="text-decoration:none;padding:8px 12px;border-radius:8px;background:#25D366;color:white;font-weight:600;box-shadow:0 6px 18px rgba(37,211,102,0.12);">Open WhatsApp</a>
        <button id="dismissWhatsAppNotify" aria-label="Dismiss" style="background:transparent;border:none;color:#666;font-size:14px;padding:6px 8px;cursor:pointer;">Dismiss</button>
      </div>
    `;
    Object.assign(banner.style, {
      position: 'fixed',
      right: '18px',
      bottom: '18px',
      background: '#fff',
      border: '1px solid rgba(0,0,0,0.08)',
      padding: '10px 14px',
      borderRadius: '10px',
      boxShadow: '0 10px 30px rgba(11,27,40,0.06)',
      zIndex: 99999,
      maxWidth: '380px',
      fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial'
    });

    document.body.appendChild(banner);

    const openBtn = document.getElementById('openWhatsAppBtn');
    const dismissBtn = document.getElementById('dismissWhatsAppNotify');

    // If user clicks the Open button, we remove the banner and no further action needed.
    openBtn.addEventListener('click', function() {
      try { banner.remove(); } catch(e) {}
    });

    // Dismiss behavior
    dismissBtn.addEventListener('click', function() {
      try { banner.remove(); } catch(e) {}
    });

  }, 250); // 250ms wait
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
