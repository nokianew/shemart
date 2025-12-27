<?php
// includes/roles.php
// requires $pdo to be available (or adjust to create a connection here)

function getAllRoles($pdo) {
    $stmt = $pdo->prepare("SELECT id, name, slug, description FROM roles ORDER BY id");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRolesByAdmin($pdo, $adminId) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.slug
        FROM roles r
        JOIN admin_role ar ON r.id = ar.role_id
        WHERE ar.admin_id = ?
    ");
    $stmt->execute([$adminId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// assign roles (many-to-many) with audit logging
function assignRolesToAdmin($pdo, $changerAdminId, $targetAdminId, $roleIds = []) {
    // normalize role ids
    $roleIds = array_map('intval', array_values($roleIds));

    // fetch old role ids
    $old = $pdo->prepare("SELECT role_id FROM admin_role WHERE admin_id = ?");
    $old->execute([$targetAdminId]);
    $oldRoles = $old->fetchAll(PDO::FETCH_COLUMN);

    // begin transaction
    $pdo->beginTransaction();
    try {
        // remove old
        $del = $pdo->prepare("DELETE FROM admin_role WHERE admin_id = ?");
        $del->execute([$targetAdminId]);

        // insert new
        $ins = $pdo->prepare("INSERT INTO admin_role (admin_id, role_id) VALUES (?, ?)");
        foreach ($roleIds as $rid) {
            $ins->execute([$targetAdminId, $rid]);
        }

        // log audit
        $log = $pdo->prepare("INSERT INTO admin_role_audit (changed_by_admin_id, target_admin_id, old_roles, new_roles) VALUES (?, ?, ?, ?)");
        $log->execute([
            $changerAdminId,
            $targetAdminId,
            implode(',', $oldRoles),
            implode(',', $roleIds)
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// simple check for role slug
function adminHasRole($pdo, $adminId, $roleSlug) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM roles r
        JOIN admin_role ar ON r.id = ar.role_id
        WHERE ar.admin_id = ? AND r.slug = ?
    ");
    $stmt->execute([$adminId, $roleSlug]);
    return $stmt->fetchColumn() > 0;
}
