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

// Get landlord ID from session or find/create a landlord user
$landlord_id = $_SESSION['user_id'] ?? null;

// If no session, find an existing landlord or create one
if (!$landlord_id) {
    // Check if user_type column exists
    $check_user_type = "SHOW COLUMNS FROM users LIKE 'user_type'";
    $has_user_type = mysqli_num_rows(mysqli_query($conn, $check_user_type)) > 0;
    
    if ($has_user_type) {
        // Find existing landlord
        $landlord_query = "SELECT id FROM users WHERE user_type = 'landlord' LIMIT 1";
        $landlord_result = mysqli_query($conn, $landlord_query);
        
        if ($landlord_result && mysqli_num_rows($landlord_result) > 0) {
            $landlord_row = mysqli_fetch_assoc($landlord_result);
            $landlord_id = $landlord_row['id'];
        } else {
            // Create a landlord user if none exists
            $hashed_password = password_hash('landlord123', PASSWORD_DEFAULT);
            $create_landlord = "INSERT INTO users (username, email, password, user_type, created_at) 
                               VALUES ('sample_landlord', 'landlord@easyrent.com', ?, 'landlord', NOW())";
            $stmt = mysqli_prepare($conn, $create_landlord);
            mysqli_stmt_bind_param($stmt, "s", $hashed_password);
            
            if (mysqli_stmt_execute($stmt)) {
                $landlord_id = mysqli_insert_id($conn);
            } else {
                // Fallback: try to get any user
                $any_user = mysqli_query($conn, "SELECT id FROM users LIMIT 1");
                if ($any_user && mysqli_num_rows($any_user) > 0) {
                    $user_row = mysqli_fetch_assoc($any_user);
                    $landlord_id = $user_row['id'];
                } else {
                    die("Error: No users found in database. Please create a user first.");
                }
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // No user_type column, just get first user
        $any_user = mysqli_query($conn, "SELECT id FROM users LIMIT 1");
        if ($any_user && mysqli_num_rows($any_user) > 0) {
            $user_row = mysqli_fetch_assoc($any_user);
            $landlord_id = $user_row['id'];
        } else {
            die("Error: No users found in database. Please create a user first.");
        }
    }
}

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

// Check if properties table has status or admin_approved column
$check_columns = "SHOW COLUMNS FROM properties";
$columns_result = mysqli_query($conn, $check_columns);
$columns = [];
while ($row = mysqli_fetch_assoc($columns_result)) {
    $columns[] = $row['Field'];
}
$has_admin_approved = in_array('admin_approved', $columns);
$has_status = in_array('status', $columns);

// Sample properties data
$properties = [
    [
        'title' => 'Modern 2BR Apartment in Sandton',
        'description' => 'Beautiful modern apartment in the heart of Sandton. Features include spacious living areas, modern kitchen, and secure parking. Perfect for professionals working in the area.',
        'property_type' => 'apartment',
        'address' => '123 Rivonia Road',
        'city' => 'Sandton',
        'state' => 'Gauteng',
        'postal_code' => '2196',
        'bedrooms' => 2,
        'bathrooms' => 2,
        'square_meters' => 85,
        'rent_amount' => 15000,
        'deposit_amount' => 15000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 0,
        'furnished' => 1,
        'available_from' => date('Y-m-d', strtotime('+1 month')),
        'lease_duration_months' => 12,
        'admin_approved' => 1, // Approved
        'images' => [
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800',
            'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800',
            'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=800'
        ]
    ],
    [
        'title' => 'Luxury 3BR House in Cape Town',
        'description' => 'Stunning luxury house with sea views in Cape Town. Features include large garden, swimming pool, modern finishes, and close to beaches. Ideal for families.',
        'property_type' => 'house',
        'address' => '45 Ocean Drive',
        'city' => 'Cape Town',
        'state' => 'Western Cape',
        'postal_code' => '8001',
        'bedrooms' => 3,
        'bathrooms' => 2.5,
        'square_meters' => 180,
        'rent_amount' => 25000,
        'deposit_amount' => 25000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+2 weeks')),
        'lease_duration_months' => 24,
        'admin_approved' => 1, // Approved
        'images' => [
            'https://images.unsplash.com/photo-1613977257363-707ba9348227?w=800',
            'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=800',
            'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800'
        ]
    ],
    [
        'title' => 'Cozy Studio Apartment in Rosebank',
        'description' => 'Compact and well-designed studio apartment perfect for students or young professionals. Includes all essential amenities and is close to public transport.',
        'property_type' => 'studio',
        'address' => '78 Oxford Road',
        'city' => 'Rosebank',
        'state' => 'Gauteng',
        'postal_code' => '2196',
        'bedrooms' => 0,
        'bathrooms' => 1,
        'square_meters' => 35,
        'rent_amount' => 7500,
        'deposit_amount' => 7500,
        'utilities_included' => 1,
        'parking_available' => 0,
        'pet_friendly' => 0,
        'furnished' => 1,
        'available_from' => date('Y-m-d', strtotime('+3 weeks')),
        'lease_duration_months' => 6,
        'admin_approved' => 1, // Approved
        'images' => [
            'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800',
            'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800'
        ]
    ],
    [
        'title' => 'Spacious 4BR Townhouse in Durban',
        'description' => 'Large family townhouse with modern amenities. Features include large kitchen, multiple bathrooms, garden, and secure complex with pool and gym.',
        'property_type' => 'townhouse',
        'address' => '12 Beach Road',
        'city' => 'Durban',
        'state' => 'KwaZulu-Natal',
        'postal_code' => '4001',
        'bedrooms' => 4,
        'bathrooms' => 3,
        'square_meters' => 220,
        'rent_amount' => 18000,
        'deposit_amount' => 18000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+1 month')),
        'lease_duration_months' => 12,
        'admin_approved' => 1, // Approved
        'images' => [
            'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800',
            'https://images.unsplash.com/photo-1600607687644-c7171b42498b?w=800',
            'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=800',
            'https://images.unsplash.com/photo-1600585154526-990dbe4eb0f3?w=800'
        ]
    ],
    [
        'title' => 'Elegant 2BR Condo in Umhlanga',
        'description' => 'Beautifully furnished condo with ocean views. Modern design, fully equipped kitchen, and access to building amenities including pool and gym.',
        'property_type' => 'condo',
        'address' => '89 Lighthouse Road',
        'city' => 'Umhlanga',
        'state' => 'KwaZulu-Natal',
        'postal_code' => '4320',
        'bedrooms' => 2,
        'bathrooms' => 2,
        'square_meters' => 95,
        'rent_amount' => 16500,
        'deposit_amount' => 16500,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 0,
        'furnished' => 1,
        'available_from' => date('Y-m-d', strtotime('+2 weeks')),
        'lease_duration_months' => 12,
        'admin_approved' => 1, // Approved
        'images' => [
            'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?w=800',
            'https://images.unsplash.com/photo-1600566752355-35792bedcfea?w=800',
            'https://images.unsplash.com/photo-1600585152915-d0becba14223?w=800'
        ]
    ],
    [
        'title' => 'Charming 3BR House in Pretoria',
        'description' => 'Well-maintained family home in quiet neighborhood. Large yard, modern kitchen, and close to schools and shopping centers. Perfect for families.',
        'property_type' => 'house',
        'address' => '234 Church Street',
        'city' => 'Pretoria',
        'state' => 'Gauteng',
        'postal_code' => '0001',
        'bedrooms' => 3,
        'bathrooms' => 2,
        'square_meters' => 150,
        'rent_amount' => 12000,
        'deposit_amount' => 12000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+1 month')),
        'lease_duration_months' => 12,
        'admin_approved' => 0, // Pending - NOT approved
        'images' => [
            'https://images.unsplash.com/photo-1600585154084-4e5fe7c39198?w=800',
            'https://images.unsplash.com/photo-1600607688969-a5fcd326c76e?w=800',
            'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=800'
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
    
    // Add created_at and updated_at
    $fields[] = 'created_at';
    $fields[] = 'updated_at';
    
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
    
    // Build query with placeholders
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
                            $errors[] = "Failed to add image for property: " . $property['title'] . " - " . mysqli_error($conn);
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

// Output results
echo "<!DOCTYPE html>
<html>
<head>
    <title>Sample Properties Added</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #3b82f6; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; }
        ul { list-style: none; padding: 0; }
        li { padding: 5px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Sample Properties Added</h1>
        <p class='success'>Successfully inserted $inserted_count properties!</p>";
        
if (!empty($errors)) {
    echo "<h2>Errors:</h2><ul>";
    foreach ($errors as $error) {
        echo "<li class='error'>$error</li>";
    }
    echo "</ul>";
}

echo "<p><a href='landlord_dashboard.php'>Go to Dashboard</a> | <a href='my_properties.php'>View Properties</a></p>
    </div>
</body>
</html>";
?>
