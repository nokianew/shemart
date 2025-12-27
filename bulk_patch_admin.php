<?php
// bulk_patch_admin.php
// Usage: php bulk_patch_admin.php /path/to/womenshop/admin /path/to/output/patched_admin.zip [--recursive]
if ($argc < 3) {
    echo "Usage: php bulk_patch_admin.php <admin_folder_path> <output_zip_path> [--recursive]\n";
    exit(1);
}

$adminPath = rtrim($argv[1], DIRECTORY_SEPARATOR);
$outputZip = $argv[2];
$recursive = in_array('--recursive', $argv, true);

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
foreach ($files as $file) {
    $basename = basename($file);
    // Skip the admin auth itself (do not modify includes/admin_auth.php or files in includes)
    if (stripos($file, DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    // Skip login.php, we don't want to add adminRequireUser() there
    if (strtolower($basename) === 'login.php') {
        echo "Skipping login.php: $file\n";
        continue;
    }
    $content = file_get_contents($file);
    $origContent = $content;

    // If file already includes admin_auth, skip adding header but still try to clean old session_start lines
    $hasAdminAuth = (strpos($content, "admin_auth.php") !== false || strpos($content, "adminRequireUser") !== false);

    // 1) Remove session_start(); and session_name(...) lines (simple removal)
    $content = preg_replace('/^\s*session_start\s*\(\s*\)\s*;\s*$/mi', '', $content);
    $content = preg_replace('/^\s*session_name\s*\(\s*[^;]+\)\s*;\s*$/mi', '', $content);

    // 2) Remove cookie/session param lines that set session_set_cookie_params or ini_set related to session name (best-effort)
    $content = preg_replace('/^\s*(session_set_cookie_params|ini_set|session_regenerate_id)\s*\([^;]*\)\s*;\s*$/mi', '', $content);

    // 3) Remove simple manual login-check if-blocks that check $_SESSION and redirect to login.php
    // We'll search for "if (!isset($_SESSION" or "if (empty($_SESSION" and remove the whole balanced-brace block following it.
    $content = remove_session_check_blocks($content);

    // 4) Remove repeated headers redirecting to login (standalone header lines referencing login.php)
    $content = preg_replace('/^\s*header\s*\(\s*[\'"]Location:\s*.*login\.php[\'"]\s*\)\s*;\s*$/mi', '', $content);

    // 5) Prepend headerSnippet if not present
    if (!$hasAdminAuth) {
        // Ensure we don't create multiple PHP opening tags
        // If file starts with <?php, we will replace the opening with our header plus the rest (but avoid double <?php)
        if (preg_match('/^\s*<\?php/i', $content)) {
            // remove the first <?php tag so we can put our header (which includes <?php)
            $content = preg_replace('/^\s*<\?php/i', '', $content, 1);
            $newContent = $headerSnippet . "\n" . ltrim($content, "\r\n");
        } else {
            // file doesn't start with <?php (unlikely), just prepend header
            $newContent = $headerSnippet . "\n" . $content;
        }
        $content = $newContent;
    }

    // 6) If content changed, write backup and save
    if ($content !== $origContent) {
        $bak = $file . '.bak_' . date('Ymd_His');
        copy($file, $bak);
        file_put_contents($file, $content);
        $modified[] = ['file' => $file, 'bak' => $bak];
        echo "Patched: $file (backup: $bak)\n";
    } else {
        echo "No changes: $file\n";
    }
}

// Create zip archive of the admin folder (patched)
// We'll create a zip containing the contents of $adminPath directory
$zip = new ZipArchive();
if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "Error: cannot create zip at $outputZip\n";
    exit(1);
}

$filesToZip = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($adminPath));
foreach ($filesToZip as $name => $fileInfo) {
    if (!$fileInfo->isFile()) continue;
    $filePath = $fileInfo->getRealPath();
    // Add file to zip using path relative to $adminPath
    $localPath = substr($filePath, strlen($adminPath) + 1);
    $zip->addFile($filePath, $localPath);
}
$zip->close();

echo "\nDone. Patched files: " . count($modified) . "\n";
echo "Patched admin folder zipped to: $outputZip\n";
echo "Backups created with '.bak_YYYYMMDD_HHMMSS' suffix next to each original file.\n";

// --- helper function to remove session-check if blocks ---
function remove_session_check_blocks($content) {
    $patternIf = '/if\s*\(\s*(?:!isset\s*\(|empty\s*\()(?:(?:\$_SESSION)|(?:\$_COOKIE|isset\(\s*\$_SESSION))/i';
    // fallback: remove blocks starting with if (!isset($_SESSION or if (empty($_SESSION
    $offset = 0;
    while (preg_match('/if\s*\(\s*!isset\s*\(\s*\$_SESSION|\bif\s*\(\s*empty\s*\(\s*\$_SESSION/i', $content, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $startPos = $m[0][1];
        // find the next '{' after the if
        $bracePos = strpos($content, '{', $startPos);
        if ($bracePos === false) {
            // if no brace, try to remove up to next semicolon (single-line)
            $semiPos = strpos($content, ';', $startPos);
            if ($semiPos === false) { $offset = $startPos + 1; continue; }
            $content = substr($content, 0, $startPos) . substr($content, $semiPos + 1);
            $offset = $startPos;
            continue;
        }
        // find matching closing brace
        $level = 0;
        $len = strlen($content);
        $i = $bracePos;
        while ($i < $len) {
            if ($content[$i] === '{') { $level++; }
            else if ($content[$i] === '}') { $level--; if ($level === 0) break; }
            $i++;
        }
        if ($i >= $len) {
            // unmatched brace; abort this removal
            $offset = $startPos + 1;
            continue;
        }
        $endPos = $i; // position of closing brace
        // remove from startPos to endPos (inclusive)
        $content = substr($content, 0, $startPos) . substr($content, $endPos + 1);
        $offset = $startPos;
    }
    return $content;
}
