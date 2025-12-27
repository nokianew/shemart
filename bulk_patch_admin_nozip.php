<?php
// bulk_patch_admin_nozip.php
// Usage:
//   php bulk_patch_admin_nozip.php <admin_folder_path> [--recursive] [--dry-run]
// Example:
//   php C:\xampp\php\php.exe C:\xampp\htdocs\womenshop\bulk_patch_admin_nozip.php C:\xampp\htdocs\womenshop\admin --recursive --dry-run

if ($argc < 2) {
    echo "Usage: php bulk_patch_admin_nozip.php <admin_folder_path> [--recursive] [--dry-run]\n";
    exit(1);
}

$adminPath = rtrim($argv[1], DIRECTORY_SEPARATOR);
$recursive = in_array('--recursive', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);

if (!is_dir($adminPath)) {
    echo "Error: admin folder not found: $adminPath\n";
    exit(1);
}

$headerSnippet = <<<PHP
<?php
// Use centralized admin auth (do NOT use session_start()/session_name() here)
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

// Optional: require a role (future use)
// requireRole('admin'); // uncomment when role-based permissions implemented

PHP;

function getPhpFiles($dir, $recursive = false) {
    $files = [];
    $it = new DirectoryIterator($dir);
    foreach ($it as $fileinfo) {
        if ($fileinfo->isDot()) continue;
        if ($fileinfo->isDir()) {
            if ($recursive) {
                $files = array_merge($files, getPhpFiles($fileinfo->getPathname(), true));
            }
            continue;
        }
        if (strtolower($fileinfo->getExtension()) === 'php') {
            $files[] = $fileinfo->getPathname();
        }
    }
    return $files;
}

$files = getPhpFiles($adminPath, $recursive);
$modified = [];
$skipped = [];

foreach ($files as $file) {
    $basename = basename($file);

    // Skip files inside an includes folder to avoid modifying includes/admin_auth.php etc
    if (preg_match('#' . preg_quote(DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR, '#') . '#i', $file)) {
        $skipped[] = $file . " (inside includes/)";
        continue;
    }

    // Skip login.php — must remain public
    if (strtolower($basename) === 'login.php') {
        $skipped[] = $file . " (login.php skipped)";
        continue;
    }

    $content = file_get_contents($file);
    if ($content === false) { $skipped[] = $file . " (read error)"; continue; }
    $origContent = $content;

    // Detect if admin_auth already present
    $hasAdminAuth = (strpos($content, "admin_auth.php") !== false || strpos($content, "adminRequireUser") !== false);

    // Remove common session and manual login lines
    $content = preg_replace('/^\s*session_start\s*\(\s*\)\s*;\s*$/mi', '', $content);
    $content = preg_replace('/^\s*session_name\s*\(\s*[^;]+\)\s*;\s*$/mi', '', $content);
    $content = preg_replace('/^\s*(session_set_cookie_params|session_regenerate_id|ini_set)\s*\([^;]*\)\s*;\s*$/mi', '', $content);
    $content = remove_session_check_blocks($content);
    $content = preg_replace('/^\s*header\s*\(\s*[\'"]Location:\s*.*login\.php[\'"]\s*\)\s*;\s*$/mi', '', $content);

    if (!$hasAdminAuth) {
        if (preg_match('/^\s*<\?php/i', $content)) {
            $content = preg_replace('/^\s*<\?php/i', '', $content, 1);
            $newContent = $headerSnippet . "\n" . ltrim($content, "\r\n");
        } else {
            $newContent = $headerSnippet . "\n" . $content;
        }
        $content = $newContent;
    }

    if ($content !== $origContent) {
        if (!$dryRun) {
            $bak = $file . '.bak_' . date('Ymd_His');
            if (!copy($file, $bak)) {
                echo "Warning: failed to create backup for $file\n";
            } else {
                echo "Backup created: $bak\n";
            }
            if (file_put_contents($file, $content) === false) {
                echo "Error: failed to write patched file: $file\n";
                $skipped[] = $file . " (write error)";
                continue;
            } else {
                echo "Patched: $file\n";
                $modified[] = ['file' => $file, 'bak' => $bak];
            }
        } else {
            echo "Would patch: $file\n";
            $modified[] = ['file' => $file, 'bak' => null];
        }
    } else {
        echo "No change needed: $file\n";
    }
}

echo "\nCompleted.\n";
echo "Files modified: " . count($modified) . "\n";
echo "Files skipped: " . count($skipped) . "\n";
foreach ($skipped as $s) { echo " - $s\n"; }

if ($dryRun) {
    echo "\nDry-run done. No files were overwritten. Run without --dry-run to apply changes.\n";
} else {
    echo "\nBackups are saved next to each original file with suffix '.bak_YYYYMMDD_HHMMSS'.\n";
}

exit(0);

// helper to remove session-check if blocks
function remove_session_check_blocks($content) {
    $offset = 0;
    $pattern = '/\bif\s*\(\s*(?:!isset\s*\(\s*\$_SESSION\b|empty\s*\(\s*\$_SESSION\b|!isset\s*\(\s*\$_COOKIE\b|empty\s*\(\s*\$_COOKIE\b)/i';
    while (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $startPos = $m[0][1];
        $bracePos = strpos($content, '{', $startPos);
        if ($bracePos === false) {
            $semiPos = strpos($content, ';', $startPos);
            if ($semiPos === false) { $offset = $startPos + 1; continue; }
            $content = substr($content, 0, $startPos) . substr($content, $semiPos + 1);
            $offset = $startPos;
            continue;
        }
        $level = 0;
        $len = strlen($content);
        $i = $bracePos;
        while ($i < $len) {
            if ($content[$i] === '{') { $level++; }
            else if ($content[$i] === '}') { $level--; if ($level === 0) break; }
            $i++;
        }
        if ($i >= $len) { $offset = $startPos + 1; continue; }
        $endPos = $i;
        $content = substr($content, 0, $startPos) . substr($content, $endPos + 1);
        $offset = $startPos;
    }
    return $content;
}
