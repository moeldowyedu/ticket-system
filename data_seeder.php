<?php
/**
 * Data Seeder for database `q155`
 *
 * - Reads INSERT statements from q155.sql
 * - Replays them idempotently using ON DUPLICATE KEY UPDATE for non-PK columns
 * - Runs inside a transaction with FK checks temporarily disabled
 *
 * Requirements:
 *   - admin/config.php must define DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME
 *   - q155.sql must exist at project root (same directory as this script)
 *
 * Usage:
 *   - CLI: php data_seeder.php
 *   - Browser: http://your-host/data_seeder.php
 */

require_once __DIR__ . '/admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($db->connect_errno) {
    http_response_code(500);
    die("DB connection failed ({$db->connect_errno}): {$db->connect_error}\n");
}
$db->set_charset('utf8mb4');

$dumpPath = __DIR__ . DIRECTORY_SEPARATOR . 'q155.sql';
if (!file_exists($dumpPath)) {
    http_response_code(500);
    die("Dump file not found: {$dumpPath}\n");
}

$dump = file_get_contents($dumpPath);
if ($dump === false) {
    http_response_code(500);
    die("Failed to read dump file: {$dumpPath}\n");
}

function execQuery(mysqli $db, string $sql): void {
    if ($db->query($sql) === true) {
        return;
    }
    $errno = $db->errno;
    $error = $db->error;
    throw new RuntimeException("SQL error {$errno}: {$error}\nSQL: {$sql}\n");
}

/**
 * Build idempotent INSERT ... ON DUPLICATE KEY UPDATE statement
 * - Uses the original column list
 * - Updates all columns except the primary key candidate 'id'
 */
function buildUpsert(string $table, string $columnsCsv, string $valuesPart): string {
    // columnsCsv example: `id`, `name`, `status`
    $rawCols = array_map('trim', explode(',', $columnsCsv));
    $cols = [];
    foreach ($rawCols as $c) {
        // strip outer backticks/spaces
        $c = trim($c);
        if ($c[0] === '`' && substr($c, -1) === '`') {
            $c = substr($c, 1, -1);
        }
        $cols[] = $c;
    }

    // Update list excludes 'id' (common PK) if present
    $updateCols = array_filter($cols, function ($c) {
        return mb_strtolower($c, 'UTF-8') !== 'id';
    });

    // If no updatable columns (edge case), do nothing on duplicate
    if (empty($updateCols)) {
        $updateClause = 'id = id';
    } else {
        $assignments = array_map(function ($c) {
            return '`' . $c . '`=VALUES(`' . $c . '`)';
        }, $updateCols);
        $updateClause = implode(', ', $assignments);
    }

    // Recreate columns with backticks
    $colsQuoted = implode(', ', array_map(fn($c) => '`' . $c . '`', $cols));

    // Final UPSERT
    return "INSERT INTO `{$table}` ({$colsQuoted}) VALUES {$valuesPart} ON DUPLICATE KEY UPDATE {$updateClause}";
}

try {
    $db->begin_transaction();
    execQuery($db, "SET FOREIGN_KEY_CHECKS = 0");

    // Extract all INSERT INTO ... (...) VALUES ...; statements from the dump
    // - Non-greedy match for values until the next semicolon
    // - Case-insensitive
    // - Dotall to span multiple lines
    $pattern = '/INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?\s*\(([^)]+)\)\s*VALUES\s*([\s\S]*?);/i';
    if (!preg_match_all($pattern, $dump, $matches, PREG_SET_ORDER)) {
        throw new RuntimeException("No INSERT statements found in q155.sql\n");
    }

    $executed = 0;
    foreach ($matches as $m) {
        $table = $m[1];
        $columnsCsv = trim($m[2]);
        $valuesPart = trim($m[3]);

        // Build idempotent upsert and execute
        $sql = buildUpsert($table, $columnsCsv, $valuesPart);
        execQuery($db, $sql);
        $executed++;
    }

    execQuery($db, "SET FOREIGN_KEY_CHECKS = 1");
    $db->commit();

    echo "Data seeding completed successfully.\n";
    echo "Database: " . DB_NAME . "\n";
    echo "INSERT statements processed: {$executed}\n";
    echo "Source dump: {$dumpPath}\n";
    echo "Note: Idempotent upserts were used for non-PK columns.\n";
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo "Data seeding failed:\n" . $e->getMessage() . "\n";
    exit(1);
}
