<?php
session_start();

// Check if user is logged in and is a tenant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tenant') {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['error' => 'Unauthorized access']));
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "easyrent_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    header("HTTP/1.1 500 Internal Server Error");
    exit(json_encode(['error' => 'Database connection failed']));
}

// Get request ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(['error' => 'Invalid request ID']));
}

$request_id = intval($_GET['id']);
$tenant_id = $_SESSION['user_id'];

// Fetch request details
$query = "SELECT mr.*, p.title AS property_title, p.address,
          CONCAT(u.first_name, ' ', u.last_name) AS landlord_name
          FROM maintenance_requests mr
          JOIN properties p ON mr.property_id = p.id
          JOIN users u ON mr.landlord_id = u.id
          WHERE mr.id = $request_id AND mr.tenant_id = $tenant_id";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    header("HTTP/1.1 404 Not Found");
    exit(json_encode(['error' => 'Request not found']));
}

$request = mysqli_fetch_assoc($result);

// Fetch images
$images = [];
$image_query = "SELECT * FROM maintenance_images 
                WHERE maintenance_request_id = $request_id";
$image_result = mysqli_query($conn, $image_query);

if ($image_result) {
    while ($image = mysqli_fetch_assoc($image_result)) {
        $images[] = [
            'id' => $image['id'],
            'image_path' => $image['image_path']
        ];
    }
}

// Format dates
$request['reported_date'] = date('M j, Y', strtotime($request['reported_date']));
$request['created_at'] = date('M j, Y H:i', strtotime($request['created_at']));
$request['updated_at'] = $request['updated_at'] 
    ? date('M j, Y H:i', strtotime($request['updated_at'])) 
    : 'N/A';

// Prepare response
$response = [
    'id' => $request['id'],
    'title' => $request['title'],
    'property_title' => $request['property_title'],
    'address' => $request['address'],
    'landlord_name' => $request['landlord_name'],
    'status' => $request['status'],
    'priority' => $request['priority'],
    'reported_date' => $request['reported_date'],
    'created_at' => $request['created_at'],
    'updated_at' => $request['updated_at'],
    'category' => $request['category'],
    'description' => $request['description'],
    'images' => $images
];

header('Content-Type: application/json');
echo json_encode($response);

mysqli_close($conn);