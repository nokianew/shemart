<?php
/* ============================================================
   ADMIN CRUD GOLDEN TEMPLATE
   Use this as the base for all admin CRUD pages
   ============================================================ */

/* ============================================================
   1. AUTH (ALWAYS FIRST)
   ============================================================ */
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

/* ============================================================
   2. HELPERS / DB
   ============================================================ */
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

/* ============================================================
   3. PAGE META (NO OUTPUT)
   ============================================================ */
$page_title = "Entity Name"; // e.g. Products, Categories, Orders
$errors = [];
$messages = [];

/* ============================================================
   4. MODE / INPUT
   ============================================================ */
$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ============================================================
   5. HANDLE POST (WRITE OPERATIONS ONLY)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token. Please refresh and try again.';
    } else {

        /* ---------- CREATE ---------- */
        if ($action === 'create') {

            // TODO: validate input
            // TODO: INSERT query

            header('Location: file.php?m=created');
            exit;
        }

        /* ---------- UPDATE ---------- */
        if ($action === 'update' && $id > 0) {

            // TODO: validate input
            // TODO: UPDATE query

            header('Location: file.php?m=updated');
            exit;
        }

        /* ---------- DELETE ---------- */
        if ($action === 'delete' && $id > 0) {

            // TODO: DELETE query

            header('Location: file.php?m=deleted');
            exit;
        }
    }
}

/* ============================================================
   6. READ DATA (FOR VIEW ONLY)
   ============================================================ */

/* --- Single record for edit --- */
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM table_name WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        header('Location: file.php');
        exit;
    }
}

/* --- List for index --- */
if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM table_name ORDER BY id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ============================================================
   7. HEADER (ONLY NOW)
   ============================================================ */
require_once __DIR__ . '/_admin_header.php';
?>

<!-- ==========================================================
     8. HTML VIEW (NO DB LOGIC)
     ========================================================== -->

<?php if ($action === 'list'): ?>

  <!-- LIST VIEW -->
  <!-- table, buttons, pagination -->

<?php elseif ($action === 'edit'): ?>

  <!-- EDIT FORM -->

<?php elseif ($action === 'add'): ?>

  <!-- ADD FORM -->

<?php endif; ?>

<?php
/* ============================================================
   9. FOOTER
   ============================================================ */
require_once __DIR__ . '/_admin_footer.php';
