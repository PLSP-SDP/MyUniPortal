<?php
/**
 * Utility script to sync existing staff user roles based on access levels
 * Run this once to update existing data after implementing the translation layer
 */

// Include necessary files
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/professors.backend.php';

try {
    echo "Starting staff user role synchronization...\n";
    
    $result = syncStaffUserRoles($pdo);
    
    if ($result['status']) {
        echo "✅ " . $result['message'] . "\n";
        echo "Updated " . $result['updated_count'] . " staff member(s).\n";
    } else {
        echo "❌ Error: " . $result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
}

echo "\nSync completed.\n";
?>
