<?php
session_start();
// Database connection (same as before)

$lease_id = $_GET['lease_id'] ?? 0;

// Fetch lease and tenant details
$lease_query = "
    SELECT l.*, u.email AS tenant_email, 
           CONCAT(u.first_name, ' ', u.last_name) AS tenant_name
    FROM leases l
    JOIN users u ON l.tenant_id = u.id
    WHERE l.id = $lease_id
";
// Execute query and get lease data...

// Send email (using PHPMailer or similar)
$to = $lease['tenant_email'];
$subject = "Your Lease Agreement for {$lease['property_title']}";
$message = "Hello {$lease['tenant_name']},\n\n";
$message .= "Please find your lease agreement attached.\n\n";
$message .= "Sign here: [link to signing platform]\n\n";
$message .= "Regards,\nProperty Management";

// In production: Add attachment and send email

// Update lease status
mysqli_query($conn, "UPDATE leases SET status = 'pending_signature' WHERE id = $lease_id");

echo json_encode(['success' => true, 'message' => 'Lease sent successfully']);