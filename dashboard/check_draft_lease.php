<?php
session_start();
require_once 'db_connection.php'; // Your database connection file

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

if (!isset($_GET['tenant_id'])) {
    echo json_encode(['error' => 'Tenant ID required']);
    exit;
}

$tenant_id = (int)$_GET['tenant_id'];
$landlord_id = (int)$_SESSION['user_id'];

// Check if tenant has any draft leases
$query = "SELECT id FROM leases WHERE tenant_id = $tenant_id AND landlord_id = $landlord_id AND status = 'draft'";
$result = mysqli_query($conn, $query);

echo json_encode([
    'has_draft' => ($result && mysqli_num_rows($result) > 0)
]);

mysqli_close($conn);
?>