<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "easyrent_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get landlord ID from session
$landlord_id = $_SESSION['user_id'] ?? 1;

// Get property ID from request
$property_id = intval($_GET['property_id'] ?? 0);

if ($property_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid property ID']);
    exit;
}

// Query to get rent amount for the property, ensuring it belongs to the landlord
$query = "SELECT rent_amount FROM properties WHERE id = $property_id AND landlord_id = $landlord_id";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database query failed']);
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Property not found or access denied']);
    exit;
}

$row = mysqli_fetch_assoc($result);
$rent_amount = $row['rent_amount'];

echo json_encode(['success' => true, 'rent_amount' => $rent_amount]);

mysqli_close($conn);
?>
