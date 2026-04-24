<?php
require_once("config.php");

function columnExists(mysqli $conn, string $table, string $column): bool
{
	$stmt = $conn->prepare(
		"SELECT 1
		 FROM INFORMATION_SCHEMA.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = ?
		   AND COLUMN_NAME = ?
		 LIMIT 1"
	);

	if (!$stmt) {
		return false;
	}

	$stmt->bind_param('ss', $table, $column);
	$stmt->execute();
	$exists = $stmt->get_result()->num_rows > 0;
	$stmt->close();

	return $exists;
}

function addColumnIfMissing(mysqli $conn, string $table, string $columnName, string $definition): void
{
	if (!columnExists($conn, $table, $columnName)) {
		$conn->query("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
	}
}

addColumnIfMissing($conn, 'users', 'username', '`username` VARCHAR(100) DEFAULT NULL UNIQUE AFTER `name`');
addColumnIfMissing($conn, 'users', 'phone', '`phone` VARCHAR(20) DEFAULT NULL UNIQUE AFTER `email`');
addColumnIfMissing($conn, 'users', 'updated_at', '`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'vendor', 'officer') NOT NULL DEFAULT 'vendor'");

addColumnIfMissing($conn, 'vendors', 'name', '`name` VARCHAR(100) DEFAULT NULL AFTER `user_id`');
addColumnIfMissing($conn, 'vendors', 'email', '`email` VARCHAR(100) DEFAULT NULL AFTER `name`');
addColumnIfMissing($conn, 'vendors', 'location', '`location` VARCHAR(255) DEFAULT NULL AFTER `phone`');
addColumnIfMissing($conn, 'vendors', 'business_name', '`business_name` VARCHAR(150) DEFAULT NULL AFTER `location`');
addColumnIfMissing($conn, 'vendors', 'business_location', '`business_location` VARCHAR(255) DEFAULT NULL AFTER `business_name`');
addColumnIfMissing($conn, 'vendors', 'address', '`address` VARCHAR(255) DEFAULT NULL AFTER `business_location`');
addColumnIfMissing($conn, 'vendors', 'id_proof_type', '`id_proof_type` VARCHAR(50) DEFAULT NULL AFTER `address`');
addColumnIfMissing($conn, 'vendors', 'id_proof_number', '`id_proof_number` VARCHAR(100) DEFAULT NULL AFTER `id_proof_type`');
addColumnIfMissing($conn, 'vendors', 'photo', '`photo` VARCHAR(255) DEFAULT NULL AFTER `id_proof_number`');
addColumnIfMissing($conn, 'vendors', 'updated_at', '`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

addColumnIfMissing($conn, 'zones', 'location', '`location` VARCHAR(255) DEFAULT NULL AFTER `zone_name`');
addColumnIfMissing($conn, 'zones', 'area_description', '`area_description` TEXT AFTER `location`');

addColumnIfMissing($conn, 'licenses', 'license_type', '`license_type` VARCHAR(100) DEFAULT NULL AFTER `license_number`');
addColumnIfMissing($conn, 'licenses', 'business_name', '`business_name` VARCHAR(150) DEFAULT NULL AFTER `license_type`');
addColumnIfMissing($conn, 'licenses', 'business_location', '`business_location` VARCHAR(255) DEFAULT NULL AFTER `business_name`');
addColumnIfMissing($conn, 'licenses', 'contact_phone', '`contact_phone` VARCHAR(20) DEFAULT NULL AFTER `business_location`');
addColumnIfMissing($conn, 'licenses', 'contact_email', '`contact_email` VARCHAR(150) DEFAULT NULL AFTER `contact_phone`');
addColumnIfMissing($conn, 'licenses', 'applied_date', '`applied_date` DATE DEFAULT NULL AFTER `contact_email`');
addColumnIfMissing($conn, 'licenses', 'remarks', '`remarks` TEXT DEFAULT NULL AFTER `expiry_date`');
addColumnIfMissing($conn, 'licenses', 'updated_at', '`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

addColumnIfMissing($conn, 'locations', 'location_name', '`location_name` VARCHAR(100) DEFAULT NULL AFTER `zone_id`');

if (columnExists($conn, 'users', 'username')) {
	$rows = $conn->query("SELECT id, email FROM users WHERE (username IS NULL OR username = '') AND email IS NOT NULL AND email <> '' ORDER BY id ASC");
	if ($rows instanceof mysqli_result) {
		$updateStmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
		if ($updateStmt) {
			while ($row = $rows->fetch_assoc()) {
				$userId = (int) ($row['id'] ?? 0);
				$email = trim((string) ($row['email'] ?? ''));
				if ($userId <= 0 || $email === '') {
					continue;
				}

				$baseUsername = strtolower((string) strtok($email, '@'));
				$baseUsername = preg_replace('/[^a-z0-9._-]/', '', $baseUsername);
				if ($baseUsername === '') {
					$baseUsername = 'user' . $userId;
				}

				$candidate = $baseUsername;
				$suffix = 0;
				while (true) {
					$checkStmt = $conn->prepare("SELECT 1 FROM users WHERE username = ? AND id <> ? LIMIT 1");
					if (!$checkStmt) {
						break;
					}

					$checkStmt->bind_param('si', $candidate, $userId);
					$checkStmt->execute();
					$exists = $checkStmt->get_result()->num_rows > 0;
					$checkStmt->close();

					if (!$exists) {
						break;
					}

					$suffix++;
					$candidate = $baseUsername . $suffix;
				}

				$updateStmt->bind_param('si', $candidate, $userId);
				$updateStmt->execute();
			}

			$updateStmt->close();
		}
	}
}

if (columnExists($conn, 'zones', 'location') && columnExists($conn, 'zones', 'area_description')) {
	$conn->query("UPDATE zones SET area_description = COALESCE(area_description, location)");
}

if (columnExists($conn, 'vendors', 'location') && columnExists($conn, 'vendors', 'business_location')) {
	$conn->query("UPDATE vendors SET business_location = COALESCE(business_location, location)");
}

if (columnExists($conn, 'licenses', 'created_at') && columnExists($conn, 'licenses', 'applied_date')) {
	$conn->query("UPDATE licenses SET applied_date = COALESCE(applied_date, DATE(created_at))");
}

$passwordRows = $conn->query("SELECT id, password FROM users WHERE password IS NOT NULL AND password <> '' ORDER BY id ASC");
if ($passwordRows instanceof mysqli_result) {
	$passwordStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
	if ($passwordStmt) {
		while ($row = $passwordRows->fetch_assoc()) {
			$userId = (int) ($row['id'] ?? 0);
			$storedPassword = (string) ($row['password'] ?? '');
			if ($userId <= 0 || $storedPassword === '' || str_starts_with($storedPassword, '$2y$')) {
				continue;
			}

			$hashedPassword = password_hash($storedPassword, PASSWORD_DEFAULT);
			$passwordStmt->bind_param('si', $hashedPassword, $userId);
			$passwordStmt->execute();
		}

		$passwordStmt->close();
	}
}

echo "Done";
?>
