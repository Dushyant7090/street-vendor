<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit();
}

$id = intval($data['id']);

try {
    $stmt = $conn->prepare("DELETE FROM zones WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Zone deleted']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
