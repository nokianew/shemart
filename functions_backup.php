<?php
// functions.php
require_once __DIR__ . '/config.php';

function getCategories() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll();
}

function getProducts(int $limit = 8): array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}


function getProductsByCategorySlug(string $slug): array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE c.slug = ? AND p.status = 'active'
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$slug]);
    return $stmt->fetchAll();
}


function getProductById(int $id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}


function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
// ---------- PRODUCT SEARCH ----------
function searchProducts(string $query, int $limit = 20) {
    global $pdo;
    $like = '%' . $query . '%';
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE ? OR p.description LIKE ?
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $like, PDO::PARAM_STR);
    $stmt->bindValue(2, $like, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ---------- CUSTOMER AUTH ----------
function currentUser() {
    return $_SESSION['user'] ?? null;
}

function isUserLoggedIn(): bool {
    return isset($_SESSION['user']['id']);
}

function requireUser() {
    if (!isUserLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

?>
