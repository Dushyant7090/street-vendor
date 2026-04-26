<?php
require_once __DIR__ . '/../config/database.php';

if (!function_exists('requireVendor')) {
    function requireVendor(): void
    {
        if (($_SESSION['role'] ?? '') !== 'vendor' || empty($_SESSION['user_id'])) {
            redirect('/street_vendor/login.php');
        }
    }
}

if (!function_exists('currentUserVendorId')) {
    function currentUserVendorId(mysqli $conn): int
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $conn->prepare('SELECT id FROM vendors WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('zoneCapacityExpression')) {
    function zoneCapacityExpression(): string
    {
        return 'COALESCE(NULLIF(z.current_capacity, 0), (SELECT COUNT(*) FROM locations loc WHERE loc.zone_id = z.id AND loc.is_active = 1))';
    }
}

if (!function_exists('fetchZoneById')) {
    function fetchZoneById(mysqli $conn, int $zoneId): ?array
    {
        $occupiedExpr = zoneCapacityExpression();
        $sql = "
            SELECT z.*,
                   COALESCE(z.max_capacity, z.max_vendors, 0) AS effective_max_capacity,
                   {$occupiedExpr} AS effective_current_capacity,
                   COALESCE(z.description, z.area_description, '') AS effective_description,
                   COALESCE(z.geometry_json, z.geometry) AS effective_geometry,
                   CASE
                       WHEN COALESCE(z.status, IF(z.is_active = 1, 'available', 'not_available')) IN ('available', 'Available') THEN 'available'
                       ELSE 'not_available'
                   END AS effective_status
            FROM zones z
            WHERE z.id = ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $zoneId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        $row['available_slots'] = max((int) $row['effective_max_capacity'] - (int) $row['effective_current_capacity'], 0);
        return $row;
    }
}

if (!function_exists('zoneColorStatus')) {
    function zoneColorStatus(int $current, int $max, string $status): string
    {
        if ($status !== 'available' || $max <= 0 || $current >= $max) {
            return 'red';
        }
        if (($current / max($max, 1)) >= 0.7) {
            return 'yellow';
        }
        return 'green';
    }
}

if (!function_exists('saveVendorDocument')) {
    function vendorUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The file is larger than the server upload limit. Please upload a file under 10 MB.',
            UPLOAD_ERR_FORM_SIZE => 'The file is larger than the allowed form upload size. Please upload a smaller file.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload temp folder is missing. Please contact the administrator.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file. Please contact the administrator.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload. Please contact the administrator.',
            default => 'Upload failed. Please try again.',
        };
    }

    function saveVendorDocument(string $field, int $vendorId, array &$errors, bool $required = true): ?string
    {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
            return null;
        }

        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . vendorUploadErrorMessage((int) $_FILES[$field]['error']);
            return null;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $originalName = (string) $_FILES[$field]['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be JPG, JPEG, PNG, or PDF.';
            return null;
        }

        $maxBytes = 10 * 1024 * 1024;
        if ((int) $_FILES[$field]['size'] > $maxBytes) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be 10 MB or smaller.';
            return null;
        }

        $uploadDir = __DIR__ . '/../uploads/vendor_documents';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        if (!is_writable($uploadDir)) {
            $errors[] = 'Upload folder is not writable. Please check permissions for uploads/vendor_documents.';
            return null;
        }

        $safeName = $field . '_v' . $vendorId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $uploadDir . '/' . $safeName;

        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            $errors[] = 'Could not save ' . str_replace('_', ' ', $field) . '.';
            return null;
        }

        return 'uploads/vendor_documents/' . $safeName;
    }
}
?>
