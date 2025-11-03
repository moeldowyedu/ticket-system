<?php
/**
 * Schema migration for database `q155`
 * - Creates tables with engine/charset
 * - Defines primary keys and indexes
 * - Adds foreign key constraints (idempotent)
 *
 * Usage:
 *   - CLI: php migration/migrate.php
 *   - Browser: http://your-host/migration/migrate.php
 */

require_once __DIR__ . '/../admin/config.php';

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($db->connect_errno) {
    http_response_code(500);
    die("DB connection failed ({$db->connect_errno}): {$db->connect_error}");
}
$db->set_charset('utf8mb4');

function execQuery(mysqli $db, string $sql): void {
    if ($db->query($sql) === true) {
        return;
    }
    $errno = $db->errno;
    // Ignorable errors for idempotency:
    // 1050: Table already exists
    // 1061: Duplicate key name
    // 1068: Multiple primary key defined
    // 1826: Duplicate foreign key constraint name
    $ignorable = [1050, 1061, 1068, 1826];
    if (in_array($errno, $ignorable, true)) {
        return;
    }
    throw new RuntimeException("SQL error {$errno}: {$db->error}\nSQL: {$sql}");
}

function fkExists(mysqli $db, string $constraintName): bool {
    $sql = "SELECT 1 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
              AND CONSTRAINT_NAME = ?
            LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('s', $constraintName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

try {
    $db->begin_transaction();
    execQuery($db, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    execQuery($db, "SET time_zone = '+00:00'");
    execQuery($db, "SET FOREIGN_KEY_CHECKS = 0");

    // file_uploads
    execQuery($db, "CREATE TABLE IF NOT EXISTS `file_uploads` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `file_path` text NOT NULL,
      `date_uploaded` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // queue_list
    execQuery($db, "CREATE TABLE IF NOT EXISTS `queue_list` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `transaction_id` int(30) NOT NULL,
      `window_id` int(30) DEFAULT 0,
      `queue_no` varchar(50) NOT NULL,
      `status` tinyint(1) NOT NULL DEFAULT 0,
      `type_id` int(2) DEFAULT NULL,
      `transfered` varchar(225) DEFAULT NULL,
      `recall` int(10) NOT NULL DEFAULT 0,
      `called_at` timestamp NULL DEFAULT NULL,
      `date_created` datetime NOT NULL DEFAULT current_timestamp(),
      `created_timestamp` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `type_cons` (`type_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // settings
    execQuery($db, "CREATE TABLE IF NOT EXISTS `settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `password` varchar(225) NOT NULL,
      `name` varchar(225) NOT NULL,
      `image` varchar(225) NOT NULL,
      `ticket_company` enum('on','off') NOT NULL DEFAULT 'off',
      `ticket_logo` enum('on','off') NOT NULL DEFAULT 'off',
      `ticket_date` enum('on','off') NOT NULL DEFAULT 'off',
      `ticket_note` enum('on','off') NOT NULL DEFAULT 'off',
      `note` varchar(225) NOT NULL,
      `period` int(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // staff_statistics
    execQuery($db, "CREATE TABLE IF NOT EXISTS `staff_statistics` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `staff_id` int(11) NOT NULL,
      `processed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // status
    execQuery($db, "CREATE TABLE IF NOT EXISTS `status` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `type` varchar(1) NOT NULL,
      `color` varchar(50) NOT NULL,
      `ordering` varchar(1) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // transactions
    execQuery($db, "CREATE TABLE IF NOT EXISTS `transactions` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `name` text NOT NULL,
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=Inactive,=1 Active',
      `sorting` enum('on','off') NOT NULL DEFAULT 'off',
      `active` enum('on','off') DEFAULT 'on',
      `priority` enum('on','off') NOT NULL,
      `symbol` varchar(1) DEFAULT NULL,
      `type` varchar(225) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // transaction_windows
    execQuery($db, "CREATE TABLE IF NOT EXISTS `transaction_windows` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `transaction_id` int(11) DEFAULT NULL,
      `transaction_ids` text DEFAULT NULL,
      `name` varchar(100) NOT NULL,
      `status` tinyint(100) DEFAULT 1 COMMENT '0=Inactive,1=Active',
      PRIMARY KEY (`id`),
      KEY `fk_cons` (`transaction_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // users
    execQuery($db, "CREATE TABLE IF NOT EXISTS `users` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `name` text NOT NULL,
      `window_id` int(30) NOT NULL,
      `type` tinyint(4) NOT NULL DEFAULT 2 COMMENT '1 = Admin, 2= staff',
      `transfer` enum('yes','no') NOT NULL DEFAULT 'no',
      `username` varchar(100) NOT NULL,
      `password` text NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // user_permissions
    execQuery($db, "CREATE TABLE IF NOT EXISTS `user_permissions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `transaction_id` int(11) NOT NULL,
      PRIMARY KEY (`id`),
      KEY `con1` (`transaction_id`),
      KEY `con2` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // waiting_stats (note: no FKs in dump, only indexes)
    execQuery($db, "CREATE TABLE IF NOT EXISTS `waiting_stats` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `queue_id` int(11) NOT NULL,
      `transaction_id` int(11) NOT NULL,
      `status_id` int(11) DEFAULT NULL,
      `arrival_time` datetime NOT NULL,
      `start_time` datetime DEFAULT NULL,
      `end_time` datetime DEFAULT NULL,
      `waiting_duration` int(11) DEFAULT NULL COMMENT 'in seconds',
      `service_duration` int(11) DEFAULT NULL COMMENT 'in seconds',
      PRIMARY KEY (`id`),
      KEY `queue_id` (`queue_id`),
      KEY `transaction_id` (`transaction_id`),
      KEY `status_id` (`status_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Foreign keys (only those present in dump)
    if (!fkExists($db, 'type_cons')) {
        execQuery($db, "ALTER TABLE `queue_list`
          ADD CONSTRAINT `type_cons` FOREIGN KEY (`type_id`) REFERENCES `status` (`id`)");
    }
    if (!fkExists($db, 'fk_cons')) {
        execQuery($db, "ALTER TABLE `transaction_windows`
          ADD CONSTRAINT `fk_cons` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE");
    }
    if (!fkExists($db, 'con1')) {
        execQuery($db, "ALTER TABLE `user_permissions`
          ADD CONSTRAINT `con1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE");
    }
    if (!fkExists($db, 'con2')) {
        execQuery($db, "ALTER TABLE `user_permissions`
          ADD CONSTRAINT `con2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
    }

    execQuery($db, "SET FOREIGN_KEY_CHECKS = 1");
    $db->commit();

    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration completed successfully.\n";
    echo "Database: " . DB_NAME . "\n";
    echo "You can now run the data seeder (data_seeder.php).\n";
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration failed:\n" . $e->getMessage();
    exit(1);
}
