<?php
// Simple migration to add show_ab_selection column to transactions table
// Usage: open this file in your browser: http://localhost/free/ticket-system/admin/migrate_show_ab.php

include_once 'db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Checking transactions table for column show_ab_selection...\n";
$res = $conn->query("SHOW COLUMNS FROM transactions LIKE 'show_ab_selection'");
if ($res && $res->num_rows > 0) {
    echo "Column already exists. Nothing to do.\n";
    exit;
}

echo "Column not found. Attempting to add column...\n";
$alter = $conn->query("ALTER TABLE transactions ADD COLUMN show_ab_selection ENUM('on','off') DEFAULT 'off'");
if ($alter) {
    echo "Column show_ab_selection added successfully.\n";
} else {
    echo "Failed to add column. MySQL error: " . $conn->error . "\n";
}

?>