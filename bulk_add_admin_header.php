<?php
/**
 * bulk_add_admin_header.php
 *
 * Usage:
 *  php bulk_add_admin_header.php --dir="C:\path\to\womenshop\admin" [--dry-run]
 *
 * What it does:
 *  - Recursively finds PHP files under the given admin directory
 *  - Skips login.php and any files inside an "ajax" directory
 *  - Skips files that already include admin_auth or call adminRequireUser()
 *  - Inserts the centralized auth header right after the first "<?php" tag
 *  - Creates a backup for each modified file: filename.bak_YYYYMMDD_HHMMSS
 *
 * Be careful and run with --dry-run first.
 */

function usageAndExit($msg = '') {
    if ($msg) echo "ERROR: $msg\n\n";
    echo "Usage:\n  php bulk_add_admin_header.php --dir=\"C:\\path\\to\\womenshop\\admin\" [--dry-run]\n\n";
    exit($msg ? 1 : 0);
}

// parse args
$options = getopt('', ['dir:', 'dry-run']);
$dir = $options['dir'] ?? null;
$dryRun = isset($options['dry-run']);

if (!$dir) usageAndExit('Missing --dir parameter');

if (!is_dir($dir)) usageAndExit("Directory not found: $dir");

// normalize path
$dir = rtrim(str_replace('\\', '/', $dir), '/');

$headerToInsert = <<<'PH'
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

// Project helpers (safe - no session start here)
require_once __DIR__ . '/../includes/functions.php';

// If DB connection is still not set, get it
if (!isset($pdo)) {
    $pdo = getDB();
}
PH;

$filesScanned = 0;
$filesModified = 0;
$filesSkipped = 0;
$skippedFiles = [];
$modifiedFiles = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getRealPath();
    // only .php files
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') continue;

    $filesScanned++;

    // normalize path for checks
    $npath = str_replace('\\', '/', $path);

    // Skip login.php (top-level or nested)
    if (preg_match('#/login\.php$#i', $npath)) {
        $filesSkipped++;
        $skippedFiles[] = [$npath, 'login.php (skip)'];
        continue;
    }

    // Skip anything inside an ajax directory: e.g. admin/ajax/*
    if (preg_match('#/ajax/#i', $npath)) {
        $filesSkipped++;
        $skippedFiles[] = [$npath, 'ajax dir (skip)'];
        continue;
    }

    // read file
    $content = file_get_contents($path);
    if ($content === false) {
        $filesSkipped++;
        $skippedFiles[] = [$npath, 'read failed'];
        continue;
    }

    // if file already requires admin_auth.php or calls adminRequireUser(), skip it
    if (stripos($content, 'admin_auth.php') !== false || stripos($content, 'adminRequireUser(') !== false) {
        $filesSkipped++;
        $skippedFiles[] = [$npath, 'already has auth (skip)'];
        continue;
    }

    // find first <?php tag
    $pos = stripos($content, '<?php');
    if ($pos === false) {
        // No php open tag? skip
        $filesSkipped++;
        $skippedFiles[] = [$npath, 'no <?php tag (skip)'];
        continue;
    }

    // Determine insertion point: right after "<?php" and following newline if present
    $afterOpenPos = $pos + 5; // position after "<?php"
    // if the file has <?php\n or <?php\r\n, include the newline in insertion point
    if (preg_match('/\A.*?<\?php(\r\n|\n)/is', $content, $m)) {
        // find exact match
        $match = $m[0];
        $afterOpenPos = strlen($match);
    } else {
        // else just use after <?php
        $afterOpenPos = $pos + 5;
    }

    // Build insertion text (we will keep file's PHP open tag; we only insert the header lines)
    $insertText = "\n" . $headerToInsert . "\n\n";

    // Create new content
    $newContent = substr($content, 0, $afterOpenPos) . $insertText . substr($content, $afterOpenPos);

    // Safety: check not to duplicate if header appears somehow after insertion point
    if (stripos($newContent, 'adminRequireUser(') === false) {
        // extreme fallback: if still doesn't contain adminRequireUser after insertion (shouldn't happen), skip
        $filesSkipped++;
        $skippedFiles[] = [$npath, 'insertion verification failed'];
        continue;
    }

    // show dry-run info
    if ($dryRun) {
        echo "[DRY RUN] Would patch: $npath\n";
        $filesModified++;
        $modifiedFiles[] = $npath;
        continue;
    }

    // backup original with timestamp
    $bakSuffix = '.bak_' . date('Ymd_His');
    $bakPath = $path . $bakSuffix;
    if (!copy($path, $bakPath)) {
        $filesSkipped++;
        $skippedFiles[] = [$npath, "backup failed"];
        continue;
    }

    // write new content
    $written = file_put_contents($path, $newContent);
    if ($written === false) {
        // restore backup
        copy($bakPath, $path);
        $filesSkipped++;
        $skippedFiles[] = [$npath, "write failed; restored backup"];
        continue;
    }

    $filesModified++;
    $modifiedFiles[] = $npath;
    echo "[PATCHED] $npath  (backup: " . basename($bakPath) . ")\n";
}

// summary
echo "\nSummary:\n";
echo "Scanned files: $filesScanned\n";
echo "Modified files: $filesModified\n";
echo "Skipped files: $filesSkipped\n";

if ($dryRun) {
    if (!empty($modifiedFiles)) {
        echo "\nFiles that WOULD be modified:\n";
        foreach ($modifiedFiles as $f) echo "  - $f\n";
    } else {
        echo "\nNo files would be modified.\n";
    }
} else {
    if (!empty($modifiedFiles)) {
        echo "\nModified files:\n";
        foreach ($modifiedFiles as $f) echo "  - $f\n";
    }
    if (!empty($skippedFiles)) {
        echo "\nSkipped files details:\n";
        foreach ($skippedFiles as [$p,$why]) echo "  - $p  => $why\n";
    }
}

exit(0);
