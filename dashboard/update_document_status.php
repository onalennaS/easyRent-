<?php
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin (add your authentication check here)

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "easyrent_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit;
}

$document_id = (int)($_POST['document_id'] ?? 0);
$action = $_POST['action'] ?? '';
$rejection_reason = $_POST['rejection_reason'] ?? '';

if ($document_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// First, check if the document exists
$check_query = "SELECT id FROM landlord_documents WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "i", $document_id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_stmt);

// Check what columns exist in the table
$columns_query = "SHOW COLUMNS FROM landlord_documents";
$columns_result = mysqli_query($conn, $columns_query);
$existing_columns = [];
while ($row = mysqli_fetch_assoc($columns_result)) {
    $existing_columns[] = $row['Field'];
}

// Build the update query based on existing columns
$update_fields = [];
$params = [];
$types = '';

// Always try to update status if it exists
if (in_array('status', $existing_columns)) {
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    $update_fields[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

// Update approval_status if it exists
if (in_array('approval_status', $existing_columns)) {
    $approval_status = ($action === 'approve') ? 1 : 0;
    $update_fields[] = "approval_status = ?";
    $params[] = $approval_status;
    $types .= 'i';
}

// Handle rejection reason
if ($action === 'reject' && in_array('rejection_reason', $existing_columns)) {
    $update_fields[] = "rejection_reason = ?";
    $params[] = $rejection_reason;
    $types .= 's';
} elseif ($action === 'approve' && in_array('rejection_reason', $existing_columns)) {
    $update_fields[] = "rejection_reason = NULL";
}

// Add updated_at if it exists
if (in_array('updated_at', $existing_columns)) {
    $update_fields[] = "updated_at = NOW()";
}

if (empty($update_fields)) {
    echo json_encode(['success' => false, 'message' => 'No valid columns found to update']);
    mysqli_close($conn);
    exit;
}

// Build and execute the query
$query = "UPDATE landlord_documents SET " . implode(', ', $update_fields) . " WHERE id = ?";
$params[] = $document_id;
$types .= 'i';

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . mysqli_error($conn)]);
    mysqli_close($conn);
    exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);

if (mysqli_stmt_execute($stmt)) {
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    if ($affected_rows > 0) {
        $message = ($action === 'approve') ? 'Document approved successfully!' : 'Document rejected successfully!';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made to the document']);
    }
} else {
    $error = mysqli_error($conn);
    error_log("Database error: " . $error);
    echo json_encode(['success' => false, 'message' => 'Failed to update document status: ' . $error]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>