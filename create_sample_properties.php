<?php
/**
 * Script to create 8 sample properties with images and approve them
 * Run this script via browser or command line
 */

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "easyrent_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Function to get or create a landlord
function getOrCreateLandlord($conn) {
    // Try to get an existing landlord
    $query = "SELECT id FROM users WHERE user_type = 'landlord' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['id'];
    }
    
    // If no landlord exists, create one
    $username = 'sample_landlord_' . time();
    $email = 'landlord' . time() . '@example.com';
    $password_hash = password_hash('password123', PASSWORD_DEFAULT);
    
    $insert_query = "INSERT INTO users (username, email, password_hash, user_type, is_active) 
                     VALUES (?, ?, ?, 'landlord', 1)";
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "sss", $username, $email, $password_hash);
    
    if (mysqli_stmt_execute($stmt)) {
        $landlord_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $landlord_id;
    }
    
    mysqli_stmt_close($stmt);
    return null;
}

// Function to copy an existing image or create a placeholder
function getImageForProperty($conn, $property_id, $index) {
    $upload_dir = __DIR__ . '/uploads/properties/';
    
    // Check if upload directory exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Get existing images from the directory
    $existing_images = glob($upload_dir . '*.{jpg,jpeg,png}', GLOB_BRACE);
    
    if (!empty($existing_images)) {
        // Use an existing image (cycle through them)
        $image_path = $existing_images[$index % count($existing_images)];
        $extension = pathinfo($image_path, PATHINFO_EXTENSION);
        $new_filename = 'img_' . uniqid() . '_' . $property_id . '.' . $extension;
        $new_path = $upload_dir . $new_filename;
        
        // Copy the image
        if (copy($image_path, $new_path)) {
            return $new_filename;
        }
    }
    
    // If no existing images, create a simple placeholder image using GD
    if (function_exists('imagecreatetruecolor')) {
        $width = 800;
        $height = 600;
        $image = imagecreatetruecolor($width, $height);
        
        // Background color (light blue)
        $bg_color = imagecolorallocate($image, 230, 240, 255);
        imagefill($image, 0, 0, $bg_color);
        
        // Text color
        $text_color = imagecolorallocate($image, 50, 50, 50);
        
        // Add text
        $text = "Property #$property_id";
        $font_size = 5;
        $text_x = ($width - imagefontwidth($font_size) * strlen($text)) / 2;
        $text_y = ($height - imagefontheight($font_size)) / 2;
        imagestring($image, $font_size, $text_x, $text_y, $text, $text_color);
        
        $filename = 'img_' . uniqid() . '_' . $property_id . '.jpg';
        $filepath = $upload_dir . $filename;
        
        if (imagejpeg($image, $filepath, 85)) {
            imagedestroy($image);
            return $filename;
        }
        imagedestroy($image);
    }
    
    return null;
}

// Function to save image to database
function saveImageToDB($conn, $property_id, $image_url, $is_primary) {
    $query = "INSERT INTO property_images (property_id, image_url, is_primary) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isi", $property_id, $image_url, $is_primary);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Sample properties data
$properties = [
    [
        'title' => 'Modern 2-Bedroom Apartment in City Center',
        'description' => 'Beautifully renovated apartment in the heart of the city. Features modern finishes, large windows, and a spacious balcony with city views. Close to shopping, restaurants, and public transport.',
        'property_type' => 'apartment',
        'address' => '123 Main Street',
        'city' => 'Cape Town',
        'state' => 'Western Cape',
        'postal_code' => '8001',
        'bedrooms' => 2,
        'bathrooms' => 1.5,
        'square_meters' => 75,
        'rent_amount' => 12000,
        'deposit_amount' => 24000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 0,
        'furnished' => 1,
        'available_from' => date('Y-m-d', strtotime('+1 month')),
        'lease_duration_months' => 12
    ],
    [
        'title' => 'Spacious 3-Bedroom House with Garden',
        'description' => 'Charming family home with a large garden, perfect for families. Features include a modern kitchen, open-plan living area, and secure parking. Located in a quiet, family-friendly neighborhood.',
        'property_type' => 'house',
        'address' => '456 Oak Avenue',
        'city' => 'Johannesburg',
        'state' => 'Gauteng',
        'postal_code' => '2196',
        'bedrooms' => 3,
        'bathrooms' => 2,
        'square_meters' => 150,
        'rent_amount' => 18000,
        'deposit_amount' => 36000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+2 weeks')),
        'lease_duration_months' => 12
    ],
    [
        'title' => 'Luxury Studio Apartment Near Beach',
        'description' => 'Stylish studio apartment just 200m from the beach. Modern design with high-end finishes, fully furnished, and includes all utilities. Perfect for professionals or couples.',
        'property_type' => 'studio',
        'address' => '789 Beach Road',
        'city' => 'Durban',
        'state' => 'KwaZulu-Natal',
        'postal_code' => '4001',
        'bedrooms' => 0,
        'bathrooms' => 1,
        'square_meters' => 40,
        'rent_amount' => 8500,
        'deposit_amount' => 17000,
        'utilities_included' => 1,
        'parking_available' => 1,
        'pet_friendly' => 0,
        'furnished' => 1,
        'available_from' => date('Y-m-d', strtotime('+1 week')),
        'lease_duration_months' => 6
    ],
    [
        'title' => 'Elegant 4-Bedroom Townhouse',
        'description' => 'Stunning townhouse in an upscale complex with 24/7 security. Features include a private garden, double garage, and modern open-plan design. Close to top schools and shopping centers.',
        'property_type' => 'townhouse',
        'address' => '321 Pine Street',
        'city' => 'Pretoria',
        'state' => 'Gauteng',
        'postal_code' => '0081',
        'bedrooms' => 4,
        'bathrooms' => 3,
        'square_meters' => 200,
        'rent_amount' => 25000,
        'deposit_amount' => 50000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+3 weeks')),
        'lease_duration_months' => 24
    ],
    [
        'title' => 'Cozy 1-Bedroom Condo with Mountain Views',
        'description' => 'Beautiful condo with breathtaking mountain views. Recently renovated with new appliances and fixtures. Secure building with gym and pool facilities included.',
        'property_type' => 'condo',
        'address' => '654 Mountain View Drive',
        'city' => 'Cape Town',
        'state' => 'Western Cape',
        'postal_code' => '7800',
        'bedrooms' => 1,
        'bathrooms' => 1,
        'square_meters' => 55,
        'rent_amount' => 9500,
        'deposit_amount' => 19000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 0,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+2 weeks')),
        'lease_duration_months' => 12
    ],
    [
        'title' => 'Modern Duplex with Rooftop Terrace',
        'description' => 'Contemporary duplex unit with a private rooftop terrace. Perfect for entertaining, with modern finishes throughout. Located in a trendy neighborhood with great nightlife.',
        'property_type' => 'duplex',
        'address' => '987 Urban Lane',
        'city' => 'Johannesburg',
        'state' => 'Gauteng',
        'postal_code' => '2194',
        'bedrooms' => 2,
        'bathrooms' => 2,
        'square_meters' => 90,
        'rent_amount' => 15000,
        'deposit_amount' => 30000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 1,
        'available_from' => date('Y-m-d', strtotime('+1 month')),
        'lease_duration_months' => 12
    ],
    [
        'title' => 'Luxury Villa with Private Pool',
        'description' => 'Stunning villa with private pool and landscaped gardens. Features include a modern kitchen, spacious living areas, and multiple outdoor entertainment spaces. Perfect for families seeking luxury living.',
        'property_type' => 'villa',
        'address' => '147 Luxury Estate',
        'city' => 'Cape Town',
        'state' => 'Western Cape',
        'postal_code' => '7806',
        'bedrooms' => 5,
        'bathrooms' => 4,
        'square_meters' => 350,
        'rent_amount' => 45000,
        'deposit_amount' => 90000,
        'utilities_included' => 0,
        'parking_available' => 1,
        'pet_friendly' => 1,
        'furnished' => 1,
        'available_from' => date('Y-m-d', strtotime('+2 months')),
        'lease_duration_months' => 24
    ],
    [
        'title' => 'Affordable 2-Bedroom Apartment',
        'description' => 'Well-maintained apartment in a secure complex. Great value for money with all essential amenities nearby. Ideal for first-time renters or students.',
        'property_type' => 'apartment',
        'address' => '258 Student Street',
        'city' => 'Durban',
        'state' => 'KwaZulu-Natal',
        'postal_code' => '4000',
        'bedrooms' => 2,
        'bathrooms' => 1,
        'square_meters' => 65,
        'rent_amount' => 7500,
        'deposit_amount' => 15000,
        'utilities_included' => 0,
        'parking_available' => 0,
        'pet_friendly' => 0,
        'furnished' => 0,
        'available_from' => date('Y-m-d', strtotime('+1 week')),
        'lease_duration_months' => 12
    ]
];

// Get or create landlord
$landlord_id = getOrCreateLandlord($conn);

if (!$landlord_id) {
    die("Error: Could not get or create landlord.\n");
}

echo "Using landlord ID: $landlord_id\n\n";

// Create properties
$created_properties = [];
$image_index = 0;

foreach ($properties as $index => $property) {
    // Insert property
    $insert_query = "
        INSERT INTO properties (
            landlord_id, title, description, property_type, address, city, state, 
            postal_code, bedrooms, bathrooms, square_meters, rent_amount, deposit_amount,
            utilities_included, parking_available, pet_friendly, furnished, available_from,
            lease_duration_months, is_available, is_featured, view_count, created_at, updated_at,
            admin_approved
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, NOW(), NOW(), 0
        )
    ";
    
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "issssssiidddiiiissi", 
        $landlord_id, 
        $property['title'], 
        $property['description'], 
        $property['property_type'], 
        $property['address'], 
        $property['city'], 
        $property['state'],
        $property['postal_code'], 
        $property['bedrooms'], 
        $property['bathrooms'], 
        $property['square_meters'], 
        $property['rent_amount'], 
        $property['deposit_amount'],
        $property['utilities_included'], 
        $property['parking_available'], 
        $property['pet_friendly'], 
        $property['furnished'], 
        $property['available_from'],
        $property['lease_duration_months']
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $property_id = mysqli_insert_id($conn);
        
        if ($property_id == 0) {
            // Try to get the ID another way
            $check_query = "SELECT id FROM properties WHERE landlord_id = ? AND title = ? ORDER BY id DESC LIMIT 1";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "is", $landlord_id, $property['title']);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            if ($check_result && mysqli_num_rows($check_result) > 0) {
                $row = mysqli_fetch_assoc($check_result);
                $property_id = $row['id'];
            }
            mysqli_stmt_close($check_stmt);
        }
        
        if ($property_id > 0) {
            $created_properties[] = $property_id;
            
            echo "Created property #$property_id: {$property['title']}\n";
            
            // Add images (1 primary + 2 additional)
            $main_image = getImageForProperty($conn, $property_id, $image_index++);
            if ($main_image) {
                saveImageToDB($conn, $property_id, $main_image, 1);
                echo "  - Added main image: $main_image\n";
            }
            
            // Add 2 additional images
            for ($i = 0; $i < 2; $i++) {
                $additional_image = getImageForProperty($conn, $property_id, $image_index++);
                if ($additional_image) {
                    saveImageToDB($conn, $property_id, $additional_image, 0);
                    echo "  - Added additional image: $additional_image\n";
                }
            }
        } else {
            echo "Error: Could not get property ID for: {$property['title']}\n";
            echo "  MySQL Error: " . mysqli_error($conn) . "\n";
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo "Error creating property: " . mysqli_error($conn) . "\n";
        echo "  Statement Error: " . mysqli_stmt_error($stmt) . "\n";
        mysqli_stmt_close($stmt);
    }
}

echo "\n";

// Approve all properties
if (!empty($created_properties)) {
    $property_ids = implode(',', array_map('intval', $created_properties));
    
    // Check if status column exists, otherwise use admin_approved
    $check_status = "SHOW COLUMNS FROM properties LIKE 'status'";
    $status_result = mysqli_query($conn, $check_status);
    $has_status = mysqli_num_rows($status_result) > 0;
    
    if ($has_status) {
        $approve_query = "UPDATE properties SET status = 'approved', approved_at = NOW() WHERE id IN ($property_ids)";
    } else {
        $approve_query = "UPDATE properties SET admin_approved = 1, updated_at = NOW() WHERE id IN ($property_ids)";
    }
    
    if (mysqli_query($conn, $approve_query)) {
        echo "Successfully approved " . count($created_properties) . " properties!\n";
    } else {
        echo "Error approving properties: " . mysqli_error($conn) . "\n";
    }
}

echo "\nDone! Created and approved " . count($created_properties) . " properties.\n";

mysqli_close($conn);
?>
