<?php
// Include your database connection
include "app/database.php";

// Generate a proper bcrypt hash for 'admin123'
$password = 'HelloWorld123!'; // The new password you want to set
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "New hash for 'HelloWorld123!': " . $hash . "<br>";

// Update the existing admin user with the new hash
$updateQuery = "UPDATE users SET password = :password WHERE user_id = 'US-00002' AND login_id = 'ST-00001'";
$stmt = $pdo->prepare($updateQuery);
$result = $stmt->execute(['password' => $hash]);

if ($result) {
    echo "Password updated successfully. You can now login with ID: ST-00001 and password: HelloWorld123!";
} else {
    echo "Failed to update password.";
}
?>