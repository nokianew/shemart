<?php
// --------------------------------------
// Orders Dashboard — RBAC Protected
// --------------------------------------

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/permissions.php';

// 🔐 View orders permission
requirePermission('orders.view');

require_once __DIR__ . '/../includes/functions.php';

if (!isset($pdo) && function_exists('getDB')) {
    $pdo = getDB();
}

$page_title = "Orders";
require_once __DIR__ . '/_admin_header.php';
?>

<div class="row mb-3">
  <div class="col">
    <h1 class="h3 mb-1">Orders</h1>
    <p class="text-muted">Manage all orders placed in your SheMart store.</p>
  </div>
  <div class="col text-end">
    <a href="dashboard.php" class="btn btn-primary">View Dashboard</a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">

    <!-- Filters -->
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
      <input id="q" type="search" class="form-control" style="max-width:260px"
             placeholder="Search order id, product, customer…">

      <select id="statusFilter" class="form-select" style="max-width:160px">
        <option value="">All statuses</option>
        <option value="PLACED">Placed</option>
        <option value="PROCESSING">Processing</option>
        <option value="SHIPPED">Shipped</option>
        <option value="DELIVERED">Delivered</option>
        <option value="CANCELLED">Cancelled</option>
      </select>

      <select id="perPage" class="form-select" style="max-width:120px">
        <option value="10">10 / page</option>
        <option value="25">25 / page</option>
        <option value="50">50 / page</option>
      </select>

      <button id="refresh" class="btn btn-outline-primary">Refresh</button>
      <span id="stats" class="text-muted ms-auto">Loading…</span>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Shipping</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="ordersBody">
          <tr><td colspan="9" class="text-muted">Loading…</td></tr>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <div>
        <button id="prevPage" class="btn btn-sm btn-outline-secondary">Prev</button>
        <span id="pageInfo" class="text-muted ms-2">Page 1</span>
        <button id="nextPage" class="btn btn-sm btn-outline-secondary ms-2">Next</button>
      </div>
    </div>

  </div>
</div>

<style>
.status-pill{padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;color:#fff}
.status-placed{background:#f59e0b}
.status-processing{background:#3b82f6}
.status-shipped{background:#7c3aed}
.status-delivered{background:#10b981}
.status-cancelled{background:#ef4444}
</style>

<!-- ORDER MODAL -->
<div id="orderModal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Order <span id="mOrderId"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div id="modalContent" class="mb-3 text-muted">Loading…</div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
          <select id="changeStatus" class="form-select" style="max-width:200px"></select>
          <button id="saveStatus" class="btn btn-primary">Save</button>

          <button id="copyAddress" class="btn btn-outline-secondary btn-sm">
            Copy address
          </button>

          <a id="printInvoice" class="btn btn-outline-dark btn-sm" target="_blank">
            Print / Invoice
          </a>

          <span id="statusMsg" class="text-muted ms-auto"></span>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
/* ================= DOM REFERENCES (CRITICAL FIX) ================= */
const ordersBody   = document.getElementById('ordersBody');
const stats        = document.getElementById('stats');
const pageInfo     = document.getElementById('pageInfo');
const q            = document.getElementById('q');
const statusFilter = document.getElementById('statusFilter');
const perPage      = document.getElementById('perPage');
const refresh      = document.getElementById('refresh');
const prevPage     = document.getElementById('prevPage');
const nextPage     = document.getElementById('nextPage');

const modalContent = document.getElementById('modalContent');
const changeStatus = document.getElementById('changeStatus');
const saveStatus   = document.getElementById('saveStatus');
const copyAddress  = document.getElementById('copyAddress');
const printInvoice = document.getElementById('printInvoice');
const statusMsg    = document.getElementById('statusMsg');
const mOrderId     = document.getElementById('mOrderId');
const orderModal   = document.getElementById('orderModal');

const apiBase = './ajax/orders_api.php';

const STATUS_MAP = {
  PLACED:{label:'Placed',cls:'status-placed'},
  PROCESSING:{label:'Processing',cls:'status-processing'},
  SHIPPED:{label:'Shipped',cls:'status-shipped'},
  DELIVERED:{label:'Delivered',cls:'status-delivered'},
  CANCELLED:{label:'Cancelled',cls:'status-cancelled'}
};

let state = { page:1, perPage:10, q:'', status:'' };
let currentOrderId = null;
let modal = null;
let rawAddress = '';

/* ================= LIST ================= */
async function fetchOrders(){
  const qs = new URLSearchParams({...state, action:'list'});
  const res = await fetch(apiBase + '?' + qs, {credentials:'same-origin'});
  const json = await res.json();

  ordersBody.innerHTML = '';

  if (!json.success || !json.data.length) {
    ordersBody.innerHTML = '<tr><td colspan="9">No orders found</td></tr>';
    return;
  }

  json.data.forEach(o=>{
    const s = STATUS_MAP[o.status];
    ordersBody.innerHTML += `
      <tr>
        <td>#${o.id}</td>
        <td>${o.created_at}</td>
        <td>${o.customer_name}<br><small>${o.customer_email}</small></td>
        <td>${o.items_preview}</td>
        <td>₹${Number(o.total_amount).toFixed(2)}</td>
        <td>${o.payment_method}</td>
        <td>${o.shipping_name}<br><small>${o.shipping_city}</small><br>📞 ${o.shipping_phone}</td>
        <td><span class="status-pill ${s.cls}">${s.label}</span></td>
        <td><button class="btn btn-sm btn-outline-primary" onclick="openOrder(${o.id})">View</button></td>
      </tr>`;
  });

  stats.textContent = `${json.total_count} orders`;
  pageInfo.textContent = `Page ${json.page}`;
}

/* ================= VIEW ================= */
async function openOrder(id){
  currentOrderId = id;
  mOrderId.textContent = '#'+id;
  modalContent.innerHTML = 'Loading…';
  statusMsg.textContent = '';

  if (!modal) modal = new bootstrap.Modal(orderModal);
  modal.show();

  const res = await fetch(`${apiBase}?action=view&id=${id}`, {credentials:'same-origin'});
  const json = await res.json();

  if (!json.success) {
    modalContent.innerHTML = '<span class="text-danger">Failed to load order</span>';
    return;
  }

  const o = json.order;
  rawAddress = o.shipping_address_raw || '';

  modalContent.innerHTML = `
    <div class="row">
      <div class="col-md-6">
        <strong>Customer</strong><br>
        ${o.customer_name}<br>
        ${o.customer_email}<br>
        📞 ${o.customer_phone}
        <hr>
        <strong>Shipping address</strong><br>
        ${o.shipping_address}
      </div>
      <div class="col-md-6">
        <strong>Payment</strong><br>
        ${o.payment_method}
        <hr>
        <strong>Items</strong><br>
        ${o.items_html}
      </div>
    </div>
  `;

  changeStatus.innerHTML = '';
  Object.keys(STATUS_MAP).forEach(s=>{
    changeStatus.innerHTML += `<option value="${s}" ${s===o.status?'selected':''}>${STATUS_MAP[s].label}</option>`;
  });

  printInvoice.href = json.print_url;
}

/* ================= ACTIONS ================= */
saveStatus.onclick = async ()=>{
  statusMsg.textContent = 'Saving…';
  const fd = new URLSearchParams({id:currentOrderId,status:changeStatus.value});
  const res = await fetch(`${apiBase}?action=update_status`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:fd
  });
  const json = await res.json();
  statusMsg.textContent = json.success ? 'Saved' : 'Failed';
  fetchOrders();
};

copyAddress.onclick = ()=>{
  if(rawAddress){
    navigator.clipboard.writeText(rawAddress);
    statusMsg.textContent = 'Address copied';
  }
};

/* ================= FILTERS ================= */
q.oninput = e=>{state.q=e.target.value;state.page=1;fetchOrders();}
statusFilter.onchange = e=>{state.status=e.target.value;state.page=1;fetchOrders();}
perPage.onchange = e=>{state.perPage=e.target.value;state.page=1;fetchOrders();}
refresh.onclick = fetchOrders;
prevPage.onclick = ()=>{if(state.page>1){state.page--;fetchOrders();}}
nextPage.onclick = ()=>{state.page++;fetchOrders();}

fetchOrders();
</script>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
