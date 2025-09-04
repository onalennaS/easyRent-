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

// Get tenant ID from request
$tenant_id = $_GET['tenant_id'] ?? 0;

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Tenant ID is required']);
    exit;
}

// Get tenant profile data
$profile_query = "SELECT * FROM tenant_profiles WHERE tenant_id = $tenant_id";
$profile_result = mysqli_query($conn, $profile_query);

if (!$profile_result || mysqli_num_rows($profile_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Tenant profile not found']);
    exit;
}

$profile = mysqli_fetch_assoc($profile_result);

// Get tenant documents
$documents_query = "SELECT * FROM tenant_documents WHERE tenant_id = $tenant_id";
$documents_result = mysqli_query($conn, $documents_query);
$documents = [];

if ($documents_result) {
    while ($doc = mysqli_fetch_assoc($documents_result)) {
        $documents[] = $doc;
    }
}

// Return JSON response
echo json_encode([
    'success' => true,
    'profile' => $profile,
    'documents' => $documents
]);

mysqli_close($conn);
?>