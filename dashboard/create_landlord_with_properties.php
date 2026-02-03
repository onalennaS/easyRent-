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
    die("Connection failed: " . mysqli_connect_error());
}

// Check users table structure
$check_users = "SHOW COLUMNS FROM users";
$users_result = mysqli_query($conn, $check_users);
$user_columns = [];
while ($row = mysqli_fetch_assoc($users_result)) {
    $user_columns[] = $row['Field'];
}

$has_user_type = in_array('user_type', $user_columns);
$has_first_name = in_array('first_name', $user_columns);
$has_last_name = in_array('last_name', $user_columns);
$has_full_name = in_array('full_name', $user_columns);
$has_password = in_array('password', $user_columns);
$has_password_hash = in_array('password_hash', $user_columns);

// Check properties table structure
$check_properties = "SHOW COLUMNS FROM properties";
$properties_result = mysqli_query($conn, $check_properties);
$properties_columns = [];
while ($row = mysqli_fetch_assoc($properties_result)) {
    $properties_columns[] = $row['Field'];
}
$has_admin_approved = in_array('admin_approved', $properties_columns);
$has_status = in_array('status', $properties_columns);

// Function to download and save image
function downloadAndSaveImage($url, $upload_dir, $property_id, $image_index) {
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Get file extension from URL or default to jpg
    $extension = 'jpg';
    $url_parts = parse_url($url);
    if (isset($url_parts['path'])) {
        $path_info = pathinfo($url_parts['path']);
        if (isset($path_info['extension'])) {
            $extension = $path_info['extension'];
        }
    }
    
    // Generate unique filename
    $filename = 'property_' . $property_id . '_' . $image_index . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Download image
    $image_data = @file_get_contents($url);
    if ($image_data !== false) {
        if (file_put_contents($filepath, $image_data)) {
            return $filename;
        }
    }
    
    // If download fails, return the URL as fallback
    return $url;
}

// Landlord information
$landlord_data = [
    'username' => 'john_property',
    'email' => 'john.property@easyrent.com',
    'password' => 'landlord123',
    'first_name' => 'John',
    'last_name' => 'Property',
    'full_name' => 'John Property',
    'phone' => '+27 11 123 4567'
];

// Check if landlord already exists
$check_landlord = "SELECT id FROM users WHERE email = ? OR username = ?";
$check_stmt = mysqli_prepare($conn, $check_landlord);
mysqli_stmt_bind_param($check_stmt, "ss", $landlord_data['email'], $landlord_data['username']);
mysqli_stmt_execute($check_stmt);
$existing_landlord = mysqli_stmt_get_result($check_stmt);

if ($existing_landlord && mysqli_num_rows($existing_landlord) > 0) {
    $landlord_row = mysqli_fetch_assoc($existing_landlord);
    $landlord_id = $landlord_row['id'];
    mysqli_stmt_close($check_stmt);
    $landlord_created = false;
} else {
    mysqli_stmt_close($check_stmt);
    
    // Create new landlord user
    $hashed_password = password_hash($landlord_data['password'], PASSWORD_DEFAULT);
    
    // Build INSERT query based on available columns
    $user_fields = ['username', 'email'];
    $user_values = [$landlord_data['username'], $landlord_data['email']];
    $user_types = 'ss';
    
    if ($has_password) {
        $user_fields[] = 'password';
        $user_values[] = $hashed_password;
        $user_types .= 's';
    } elseif ($has_password_hash) {
        $user_fields[] = 'password_hash';
        $user_values[] = $hashed_password;
        $user_types .= 's';
    }
    
    if ($has_user_type) {
        $user_fields[] = 'user_type';
        $user_values[] = 'landlord';
        $user_types .= 's';
    }
    
    if ($has_first_name) {
        $user_fields[] = 'first_name';
        $user_values[] = $landlord_data['first_name'];
        $user_types .= 's';
    }
    
    if ($has_last_name) {
        $user_fields[] = 'last_name';
        $user_values[] = $landlord_data['last_name'];
        $user_types .= 's';
    }
    
    if ($has_full_name) {
        $user_fields[] = 'full_name';
        $user_values[] = $landlord_data['full_name'];
        $user_types .= 's';
    }
    
    $user_fields[] = 'created_at';
    
    $placeholders = str_repeat('?,', count($user_values) - 1) . '?';
    $create_landlord_query = "INSERT INTO users (" . implode(', ', $user_fields) . ") VALUES ($placeholders, NOW())";
    
    $create_stmt = mysqli_prepare($conn, $create_landlord_query);
    if ($create_stmt) {
        mysqli_stmt_bind_param($create_stmt, $user_types, ...$user_values);
        if (mysqli_stmt_execute($create_stmt)) {
            $landlord_id = mysqli_insert_id($conn);
            $landlord_created = true;
        } else {
            die("Error creating landlord: " . mysqli_error($conn));
        }
        mysqli_stmt_close($create_stmt);
    } else {
        die("Error preparing landlord creation statement: " . mysqli_error($conn));
    }
}

// Properties for this landlord
$properties = [
    [
        'title' => 'Luxury 4BR Villa in Johannesburg',
        'description' => 'Stunning modern villa with panoramic city views. Features include a private pool, landscaped garden, modern kitchen with high-end appliances, and spacious living areas. Perfect for families seeking luxury living.',
        'property_type' => 'house',
        'address' => '456 Oak Avenue',
        'city' => 'Johannesburg',
        'state' => 'Gauteng',
        'postal_code' => '2196',
        'bedrooms' => 4,
        'bathrooms' => 3.5,
        'square_meters' => 280,
        'rent_amount' => 35000,
        'deposit_amount' => 35000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+1 month')),
        'lease_duration_months' => 24,
        'admin_approved' => 1, // Approved
        'images' => [
            'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=800',
            'https://images.unsplash.com/photo-1600607687644-c7171b42498b?w=800',
            'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=800',
            'https://images.unsplash.com/photo-1600585154526-990dbe4eb0f3?w=800'
        ]
    ],
    [
        'title' => 'Modern 1BR Apartment in Centurion',
        'description' => 'Beautifully designed apartment in a secure complex. Features modern finishes, open-plan living, and access to communal facilities including gym and pool. Ideal for young professionals.',
        'property_type' => 'apartment',
        'address' => '789 High Street',
        'city' => 'Centurion',
        'state' => 'Gauteng',
        'postal_code' => '0157',
        'bedrooms' => 1,
        'bathrooms' => 1,
        'square_meters' => 55,
        'rent_amount' => 8500,
        'deposit_amount' => 8500,
        'utilities_included' => 1,
        'parking_available' => 1,
        'pet_friendly' => 0,
        'furnished' => 1,
        'available_from' => date('Y-m-d', strtotime('+2 weeks')),
        'lease_duration_months' => 12,
        'admin_approved' => 0, // Pending
        'images' => [
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800',
            'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800'
        ]
    ],
    [
        'title' => 'Spacious 3BR Duplex in Midrand',
        'description' => 'Large duplex home with modern amenities. Features include large kitchen, multiple bathrooms, private garden, and secure parking. Close to shopping centers and schools.',
        'property_type' => 'duplex',
        'address' => '321 Main Road',
        'city' => 'Midrand',
        'state' => 'Gauteng',
        'postal_code' => '1685',
        'bedrooms' => 3,
        'bathrooms' => 2.5,
        'square_meters' => 195,
        'rent_amount' => 22000,
        'deposit_amount' => 22000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+3 weeks')),
        'lease_duration_months' => 12,
        'admin_approved' => 1, // Approved
        'images' => [
            'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800',
            'https://images.unsplash.com/photo-1600607687644-c7171b42498b?w=800',
            'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=800'
        ]
    ]
];

$inserted_count = 0;
$errors = [];

foreach ($properties as $index => $property) {
    // Build the INSERT query based on available columns
    $fields = [
        'landlord_id', 'title', 'description', 'property_type', 'address', 'city', 'state',
        'postal_code', 'bedrooms', 'bathrooms', 'square_meters', 'rent_amount', 'deposit_amount',
        'utilities_included', 'parking_available', 'pet_friendly', 'furnished', 'available_from',
        'lease_duration_months', 'is_available', 'is_featured', 'view_count'
    ];
    
    $param_values = [
        $landlord_id, $property['title'], $property['description'], $property['property_type'],
        $property['address'], $property['city'], $property['state'], $property['postal_code'],
        $property['bedrooms'], $property['bathrooms'], $property['square_meters'],
        $property['rent_amount'], $property['deposit_amount'], $property['utilities_included'],
        $property['parking_available'], $property['pet_friendly'], $property['furnished'],
        $property['available_from'], $property['lease_duration_months'], 1, 0, 0
    ];
    
    // Add admin_approved or status field
    if ($has_admin_approved) {
        $fields[] = 'admin_approved';
        $param_values[] = $property['admin_approved'];
    } elseif ($has_status) {
        $fields[] = 'status';
        $param_values[] = $property['admin_approved'] == 1 ? 'approved' : 'pending';
    }
    
    // Build parameter types
    $param_types = '';
    foreach ($param_values as $val) {
        if (is_int($val)) {
            $param_types .= 'i';
        } elseif (is_float($val)) {
            $param_types .= 'd';
        } else {
            $param_types .= 's';
        }
    }
    
    // Add created_at and updated_at
    $fields[] = 'created_at';
    $fields[] = 'updated_at';
    
    // Build query with placeholders (NOW() is not a parameter)
    $placeholders = str_repeat('?,', count($param_values) - 1) . '?';
    $query = "INSERT INTO properties (" . implode(', ', $fields) . ") VALUES ($placeholders, NOW(), NOW())";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$param_values);
        
        if (mysqli_stmt_execute($stmt)) {
            $property_id = mysqli_insert_id($conn);
            $inserted_count++;
            
            // Add images
            if (!empty($property['images'])) {
                $upload_dir = '../uploads/properties/';
                $image_count = 0;
                foreach ($property['images'] as $image_url) {
                    $is_primary = ($image_count === 0) ? 1 : 0;
                    
                    // Download and save image locally
                    $saved_filename = downloadAndSaveImage($image_url, $upload_dir, $property_id, $image_count);
                    
                    // Store the image filename (or URL if download failed)
                    $image_query = "INSERT INTO property_images (property_id, image_url, is_primary) VALUES (?, ?, ?)";
                    $image_stmt = mysqli_prepare($conn, $image_query);
                    if ($image_stmt) {
                        mysqli_stmt_bind_param($image_stmt, "isi", $property_id, $saved_filename, $is_primary);
                        if (mysqli_stmt_execute($image_stmt)) {
                            $image_count++;
                        } else {
                            $errors[] = "Failed to add image for property: " . $property['title'];
                        }
                        mysqli_stmt_close($image_stmt);
                    }
                }
            }
        } else {
            $errors[] = "Failed to insert property: " . $property['title'] . " - " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $errors[] = "Failed to prepare statement for: " . $property['title'] . " - " . mysqli_error($conn);
    }
}

// Add landlord documents
$documents_dir = '../uploads/documents/';
if (!file_exists($documents_dir)) {
    mkdir($documents_dir, 0777, true);
}

// Document types and their display names (matching profile_landlord.php)
$document_types = [
    'id_document' => 'Identity Document',
    'proof_of_address' => 'Proof of Address',
    'bank_statement' => 'Bank Statement',
    'tax_clearance' => 'Tax Clearance Certificate',
    'business_license' => 'Business License',
    'property_deed' => 'Property Deed/Title',
    'insurance_certificate' => 'Insurance Certificate',
    'credit_report' => 'Credit Report'
];

// Create sample documents for the landlord
$documents_added = 0;
$documents_errors = [];

foreach ($document_types as $doc_type => $doc_name) {
    // Generate a sample filename (in real scenario, this would be an uploaded file)
    $filename = 'landlord_' . $landlord_id . '_' . $doc_type . '_' . time() . '.pdf';
    $file_path = $filename;
    
    // Create a placeholder file (empty PDF placeholder)
    $placeholder_content = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\nxref\n0 1\ntrailer\n<<\n/Size 1\n>>\nstartxref\n9\n%%EOF";
    $full_path = $documents_dir . $filename;
    
    // Write placeholder file
    if (file_put_contents($full_path, $placeholder_content)) {
        // Insert document into database with pending status
        $doc_query = "INSERT INTO landlord_documents (landlord_id, document_type, document_name, file_path, status, uploaded_at) 
                      VALUES (?, ?, ?, ?, 'pending', NOW())
                      ON DUPLICATE KEY UPDATE
                      document_name = VALUES(document_name),
                      file_path = VALUES(file_path),
                      status = 'pending',
                      uploaded_at = NOW()";
        
        $doc_stmt = mysqli_prepare($conn, $doc_query);
        if ($doc_stmt) {
            mysqli_stmt_bind_param($doc_stmt, "isss", $landlord_id, $doc_type, $doc_name, $file_path);
            if (mysqli_stmt_execute($doc_stmt)) {
                $documents_added++;
            } else {
                $documents_errors[] = "Failed to insert document: $doc_name - " . mysqli_error($conn);
            }
            mysqli_stmt_close($doc_stmt);
        } else {
            $documents_errors[] = "Failed to prepare statement for: $doc_name - " . mysqli_error($conn);
        }
    } else {
        $documents_errors[] = "Failed to create placeholder file for: $doc_name";
    }
}

// Output results
echo "<!DOCTYPE html>
<html>
<head>
    <title>Landlord Created</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #3b82f6; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; }
        .info { background: #e0f2fe; padding: 15px; border-radius: 8px; margin: 15px 0; }
        ul { list-style: none; padding: 0; }
        li { padding: 5px 0; }
        .credentials { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #f59e0b; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Landlord Created Successfully!</h1>";
        
if ($landlord_created) {
    echo "<p class='success'>✓ New landlord user created!</p>";
} else {
    echo "<p class='info'>ℹ Using existing landlord account.</p>";
}

echo "<div class='credentials'>
            <h3>Landlord Credentials:</h3>
            <p><strong>Username:</strong> " . htmlspecialchars($landlord_data['username']) . "</p>
            <p><strong>Email:</strong> " . htmlspecialchars($landlord_data['email']) . "</p>
            <p><strong>Password:</strong> " . htmlspecialchars($landlord_data['password']) . "</p>
            <p><strong>Name:</strong> " . htmlspecialchars($landlord_data['full_name']) . "</p>
        </div>
        
        <p class='success'>✓ Successfully inserted $inserted_count properties!</p>
        
        <p class='success'>✓ Successfully added $documents_added landlord documents!</p>";
        
if (!empty($errors)) {
    echo "<h2>Property Errors:</h2><ul>";
    foreach ($errors as $error) {
        echo "<li class='error'>$error</li>";
    }
    echo "</ul>";
}

if (!empty($documents_errors)) {
    echo "<h2>Document Errors:</h2><ul>";
    foreach ($documents_errors as $error) {
        echo "<li class='error'>$error</li>";
    }
    echo "</ul>";
}

echo "<p><a href='landlord_dashboard.php'>Go to Dashboard</a> | <a href='manage_properties.php'>Manage Properties</a> | <a href='admin_dashboard.php'>Admin Dashboard</a></p>
    </div>
</body>
</html>";
?>
