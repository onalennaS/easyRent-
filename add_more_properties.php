<?php
/**
 * Script to create additional sample properties for pagination testing
 * Creates 30+ properties with images and approves them
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

// Extended properties data - 35 properties
$properties = [
    // Page 1-2 properties (varied types and locations)
    ['title' => 'Modern 2-Bedroom Apartment in City Center', 'description' => 'Beautifully renovated apartment in the heart of the city. Features modern finishes, large windows, and a spacious balcony with city views.', 'property_type' => 'apartment', 'address' => '123 Main Street', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '8001', 'bedrooms' => 2, 'bathrooms' => 1.5, 'square_meters' => 75, 'rent_amount' => 12000, 'deposit_amount' => 24000],
    ['title' => 'Spacious 3-Bedroom House with Garden', 'description' => 'Charming family home with a large garden, perfect for families. Features include a modern kitchen, open-plan living area, and secure parking.', 'property_type' => 'house', 'address' => '456 Oak Avenue', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2196', 'bedrooms' => 3, 'bathrooms' => 2, 'square_meters' => 150, 'rent_amount' => 18000, 'deposit_amount' => 36000],
    ['title' => 'Luxury Studio Apartment Near Beach', 'description' => 'Stylish studio apartment just 200m from the beach. Modern design with high-end finishes, fully furnished, and includes all utilities.', 'property_type' => 'studio', 'address' => '789 Beach Road', 'city' => 'Durban', 'state' => 'KwaZulu-Natal', 'postal_code' => '4001', 'bedrooms' => 0, 'bathrooms' => 1, 'square_meters' => 40, 'rent_amount' => 8500, 'deposit_amount' => 17000],
    ['title' => 'Elegant 4-Bedroom Townhouse', 'description' => 'Stunning townhouse in an upscale complex with 24/7 security. Features include a private garden, double garage, and modern open-plan design.', 'property_type' => 'townhouse', 'address' => '321 Pine Street', 'city' => 'Pretoria', 'state' => 'Gauteng', 'postal_code' => '0081', 'bedrooms' => 4, 'bathrooms' => 3, 'square_meters' => 200, 'rent_amount' => 25000, 'deposit_amount' => 50000],
    ['title' => 'Cozy 1-Bedroom Condo with Mountain Views', 'description' => 'Beautiful condo with breathtaking mountain views. Recently renovated with new appliances and fixtures. Secure building with gym and pool.', 'property_type' => 'condo', 'address' => '654 Mountain View Drive', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '7800', 'bedrooms' => 1, 'bathrooms' => 1, 'square_meters' => 55, 'rent_amount' => 9500, 'deposit_amount' => 19000],
    ['title' => 'Modern Duplex with Rooftop Terrace', 'description' => 'Contemporary duplex unit with a private rooftop terrace. Perfect for entertaining, with modern finishes throughout.', 'property_type' => 'duplex', 'address' => '987 Urban Lane', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2194', 'bedrooms' => 2, 'bathrooms' => 2, 'square_meters' => 90, 'rent_amount' => 15000, 'deposit_amount' => 30000],
    ['title' => 'Luxury Villa with Private Pool', 'description' => 'Stunning villa with private pool and landscaped gardens. Features include a modern kitchen, spacious living areas, and multiple outdoor entertainment spaces.', 'property_type' => 'villa', 'address' => '147 Luxury Estate', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '7806', 'bedrooms' => 5, 'bathrooms' => 4, 'square_meters' => 350, 'rent_amount' => 45000, 'deposit_amount' => 90000],
    ['title' => 'Affordable 2-Bedroom Apartment', 'description' => 'Well-maintained apartment in a secure complex. Great value for money with all essential amenities nearby. Ideal for first-time renters.', 'property_type' => 'apartment', 'address' => '258 Student Street', 'city' => 'Durban', 'state' => 'KwaZulu-Natal', 'postal_code' => '4000', 'bedrooms' => 2, 'bathrooms' => 1, 'square_meters' => 65, 'rent_amount' => 7500, 'deposit_amount' => 15000],
    
    // Additional properties for pagination
    ['title' => 'Charming 2-Bedroom Cottage', 'description' => 'Quaint cottage in a peaceful neighborhood. Features original character with modern updates. Perfect for couples or small families.', 'property_type' => 'house', 'address' => '111 Garden Lane', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '8000', 'bedrooms' => 2, 'bathrooms' => 1, 'square_meters' => 80, 'rent_amount' => 11000, 'deposit_amount' => 22000],
    ['title' => 'Executive 3-Bedroom Penthouse', 'description' => 'Luxurious penthouse with panoramic city views. High-end finishes, private elevator access, and premium amenities included.', 'property_type' => 'apartment', 'address' => '222 Sky Tower', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2195', 'bedrooms' => 3, 'bathrooms' => 2.5, 'square_meters' => 180, 'rent_amount' => 32000, 'deposit_amount' => 64000],
    ['title' => 'Family-Friendly 4-Bedroom Home', 'description' => 'Spacious family home with large backyard and play area. Close to schools and parks. Perfect for growing families.', 'property_type' => 'house', 'address' => '333 Family Road', 'city' => 'Pretoria', 'state' => 'Gauteng', 'postal_code' => '0082', 'bedrooms' => 4, 'bathrooms' => 3, 'square_meters' => 220, 'rent_amount' => 22000, 'deposit_amount' => 44000],
    ['title' => 'Compact Studio in Trendy Area', 'description' => 'Modern studio in the heart of the city. Walking distance to cafes, restaurants, and nightlife. Perfect for young professionals.', 'property_type' => 'studio', 'address' => '444 Trendy Avenue', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '8001', 'bedrooms' => 0, 'bathrooms' => 1, 'square_meters' => 35, 'rent_amount' => 7200, 'deposit_amount' => 14400],
    ['title' => 'Renovated 2-Bedroom Flat', 'description' => 'Recently renovated flat with modern appliances and fixtures. Secure building with parking. Great location near public transport.', 'property_type' => 'apartment', 'address' => '555 Renovation Street', 'city' => 'Durban', 'state' => 'KwaZulu-Natal', 'postal_code' => '4001', 'bedrooms' => 2, 'bathrooms' => 1, 'square_meters' => 70, 'rent_amount' => 9800, 'deposit_amount' => 19600],
    ['title' => 'Luxury 5-Bedroom Estate Home', 'description' => 'Magnificent estate home with private pool, tennis court, and extensive gardens. Premium location with security estate access.', 'property_type' => 'villa', 'address' => '666 Estate Drive', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '7806', 'bedrooms' => 5, 'bathrooms' => 5, 'square_meters' => 450, 'rent_amount' => 55000, 'deposit_amount' => 110000],
    ['title' => 'Modern 1-Bedroom Loft', 'description' => 'Stylish loft conversion with high ceilings and exposed brick. Industrial design meets modern comfort. Perfect for creatives.', 'property_type' => 'apartment', 'address' => '777 Loft Street', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2196', 'bedrooms' => 1, 'bathrooms' => 1, 'square_meters' => 60, 'rent_amount' => 10500, 'deposit_amount' => 21000],
    ['title' => 'Spacious 3-Bedroom Townhouse', 'description' => 'Well-appointed townhouse in secure complex. Features include double garage, private courtyard, and modern finishes throughout.', 'property_type' => 'townhouse', 'address' => '888 Complex Road', 'city' => 'Pretoria', 'state' => 'Gauteng', 'postal_code' => '0081', 'bedrooms' => 3, 'bathrooms' => 2, 'square_meters' => 140, 'rent_amount' => 16500, 'deposit_amount' => 33000],
    ['title' => 'Beachfront 2-Bedroom Apartment', 'description' => 'Stunning beachfront apartment with direct beach access. Floor-to-ceiling windows with ocean views. Fully furnished and ready to move in.', 'property_type' => 'apartment', 'address' => '999 Beachfront Boulevard', 'city' => 'Durban', 'state' => 'KwaZulu-Natal', 'postal_code' => '4001', 'bedrooms' => 2, 'bathrooms' => 2, 'square_meters' => 95, 'rent_amount' => 18500, 'deposit_amount' => 37000],
    ['title' => 'Cozy 1-Bedroom Garden Flat', 'description' => 'Charming ground-floor flat with private garden entrance. Quiet location, perfect for professionals or retirees seeking tranquility.', 'property_type' => 'apartment', 'address' => '101 Garden Flat Lane', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '7800', 'bedrooms' => 1, 'bathrooms' => 1, 'square_meters' => 50, 'rent_amount' => 8800, 'deposit_amount' => 17600],
    ['title' => 'Executive 4-Bedroom Home', 'description' => 'Prestigious home in exclusive area. Features include home office, wine cellar, and landscaped gardens. Perfect for executives.', 'property_type' => 'house', 'address' => '202 Executive Drive', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2194', 'bedrooms' => 4, 'bathrooms' => 3.5, 'square_meters' => 280, 'rent_amount' => 38000, 'deposit_amount' => 76000],
    ['title' => 'Modern Studio with Balcony', 'description' => 'Contemporary studio with private balcony and city views. Modern kitchenette and bathroom. Ideal for single professionals.', 'property_type' => 'studio', 'address' => '303 Balcony Heights', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '8001', 'bedrooms' => 0, 'bathrooms' => 1, 'square_meters' => 38, 'rent_amount' => 7800, 'deposit_amount' => 15600],
    ['title' => 'Family 3-Bedroom House', 'description' => 'Comfortable family home in safe neighborhood. Large yard, double garage, and close to schools. Great for families with children.', 'property_type' => 'house', 'address' => '404 Family Circle', 'city' => 'Durban', 'state' => 'KwaZulu-Natal', 'postal_code' => '4000', 'bedrooms' => 3, 'bathrooms' => 2, 'square_meters' => 160, 'rent_amount' => 19500, 'deposit_amount' => 39000],
    ['title' => 'Luxury 2-Bedroom Apartment', 'description' => 'High-end apartment with premium finishes. Building amenities include gym, pool, and concierge service. Prime location.', 'property_type' => 'apartment', 'address' => '505 Luxury Tower', 'city' => 'Pretoria', 'state' => 'Gauteng', 'postal_code' => '0082', 'bedrooms' => 2, 'bathrooms' => 2, 'square_meters' => 110, 'rent_amount' => 19500, 'deposit_amount' => 39000],
    ['title' => 'Charming 2-Bedroom Bungalow', 'description' => 'Quaint bungalow with character features. Large veranda, established garden, and off-street parking. Perfect for couples or retirees.', 'property_type' => 'house', 'address' => '606 Bungalow Way', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '7800', 'bedrooms' => 2, 'bathrooms' => 1, 'square_meters' => 85, 'rent_amount' => 12500, 'deposit_amount' => 25000],
    ['title' => 'Modern 1-Bedroom Apartment', 'description' => 'Sleek apartment with modern design. Open-plan living, high-quality finishes, and secure parking. Great for professionals.', 'property_type' => 'apartment', 'address' => '707 Modern Street', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2195', 'bedrooms' => 1, 'bathrooms' => 1, 'square_meters' => 58, 'rent_amount' => 10200, 'deposit_amount' => 20400],
    ['title' => 'Spacious 4-Bedroom Duplex', 'description' => 'Large duplex with multiple levels. Features include rooftop terrace, double garage, and modern kitchen. Perfect for large families.', 'property_type' => 'duplex', 'address' => '808 Duplex Drive', 'city' => 'Durban', 'state' => 'KwaZulu-Natal', 'postal_code' => '4001', 'bedrooms' => 4, 'bathrooms' => 3, 'square_meters' => 240, 'rent_amount' => 28000, 'deposit_amount' => 56000],
    ['title' => 'Affordable Studio Apartment', 'description' => 'Budget-friendly studio in convenient location. Clean, well-maintained, and close to public transport. Perfect for students or young professionals.', 'property_type' => 'studio', 'address' => '909 Budget Avenue', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '8000', 'bedrooms' => 0, 'bathrooms' => 1, 'square_meters' => 32, 'rent_amount' => 6500, 'deposit_amount' => 13000],
    ['title' => 'Luxury 3-Bedroom Penthouse', 'description' => 'Exclusive penthouse with private rooftop garden. Premium finishes, smart home features, and panoramic views. Ultimate luxury living.', 'property_type' => 'apartment', 'address' => '1010 Penthouse Tower', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2194', 'bedrooms' => 3, 'bathrooms' => 3, 'square_meters' => 200, 'rent_amount' => 42000, 'deposit_amount' => 84000],
    ['title' => 'Cozy 2-Bedroom Cottage', 'description' => 'Delightful cottage with garden views. Character features combined with modern amenities. Peaceful location ideal for quiet living.', 'property_type' => 'house', 'address' => '1111 Cottage Lane', 'city' => 'Pretoria', 'state' => 'Gauteng', 'postal_code' => '0081', 'bedrooms' => 2, 'bathrooms' => 1.5, 'square_meters' => 90, 'rent_amount' => 13500, 'deposit_amount' => 27000],
    ['title' => 'Modern 2-Bedroom Townhouse', 'description' => 'Contemporary townhouse in new development. Energy-efficient design, modern appliances, and low-maintenance living. Perfect for busy professionals.', 'property_type' => 'townhouse', 'address' => '1212 New Development', 'city' => 'Durban', 'state' => 'KwaZulu-Natal', 'postal_code' => '4000', 'bedrooms' => 2, 'bathrooms' => 2, 'square_meters' => 100, 'rent_amount' => 14200, 'deposit_amount' => 28400],
    ['title' => 'Executive 1-Bedroom Suite', 'description' => 'Luxurious one-bedroom suite in premium building. Hotel-style amenities, concierge service, and prime location. Perfect for executives.', 'property_type' => 'apartment', 'address' => '1313 Executive Plaza', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '8001', 'bedrooms' => 1, 'bathrooms' => 1, 'square_meters' => 65, 'rent_amount' => 11500, 'deposit_amount' => 23000],
    ['title' => 'Family 5-Bedroom Home', 'description' => 'Large family home with multiple living areas. Features include home theater, study, and extensive gardens. Perfect for large families.', 'property_type' => 'house', 'address' => '1414 Family Estate', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2196', 'bedrooms' => 5, 'bathrooms' => 4, 'square_meters' => 380, 'rent_amount' => 48000, 'deposit_amount' => 96000],
    ['title' => 'Stylish Studio Loft', 'description' => 'Converted warehouse loft with industrial charm. High ceilings, exposed beams, and modern fixtures. Perfect for artists or creatives.', 'property_type' => 'studio', 'address' => '1515 Loft District', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '8000', 'bedrooms' => 0, 'bathrooms' => 1, 'square_meters' => 45, 'rent_amount' => 9200, 'deposit_amount' => 18400],
    ['title' => 'Modern 3-Bedroom Apartment', 'description' => 'Contemporary apartment with modern design. Open-plan living, quality finishes, and building amenities. Great for families or professionals.', 'property_type' => 'apartment', 'address' => '1616 Modern Complex', 'city' => 'Pretoria', 'state' => 'Gauteng', 'postal_code' => '0082', 'bedrooms' => 3, 'bathrooms' => 2, 'square_meters' => 130, 'rent_amount' => 17500, 'deposit_amount' => 35000],
    ['title' => 'Luxury 2-Bedroom Villa', 'description' => 'Stunning villa with private pool and garden. Premium location, high-end finishes, and privacy. Perfect for discerning tenants.', 'property_type' => 'villa', 'address' => '1717 Villa Estate', 'city' => 'Durban', 'state' => 'KwaZulu-Natal', 'postal_code' => '4001', 'bedrooms' => 2, 'bathrooms' => 2, 'square_meters' => 180, 'rent_amount' => 35000, 'deposit_amount' => 70000],
    ['title' => 'Affordable 1-Bedroom Flat', 'description' => 'Well-maintained flat in good location. Clean, comfortable, and affordable. Perfect for first-time renters or students.', 'property_type' => 'apartment', 'address' => '1818 Affordable Street', 'city' => 'Cape Town', 'state' => 'Western Cape', 'postal_code' => '7800', 'bedrooms' => 1, 'bathrooms' => 1, 'square_meters' => 48, 'rent_amount' => 8200, 'deposit_amount' => 16400],
    ['title' => 'Spacious 4-Bedroom House', 'description' => 'Large family home with multiple living spaces. Features include double garage, large yard, and modern kitchen. Ideal for growing families.', 'property_type' => 'house', 'address' => '1919 Spacious Road', 'city' => 'Johannesburg', 'state' => 'Gauteng', 'postal_code' => '2195', 'bedrooms' => 4, 'bathrooms' => 3, 'square_meters' => 260, 'rent_amount' => 29000, 'deposit_amount' => 58000],
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
    // Set default values
    $utilities_included = rand(0, 1);
    $parking_available = rand(0, 1);
    $pet_friendly = rand(0, 1);
    $furnished = rand(0, 1);
    $available_from = date('Y-m-d', strtotime('+' . rand(1, 60) . ' days'));
    $lease_duration_months = [6, 12, 18, 24][rand(0, 3)];
    
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
        $utilities_included, 
        $parking_available, 
        $pet_friendly, 
        $furnished, 
        $available_from,
        $lease_duration_months
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
echo "You should now see pagination with " . count($created_properties) . " properties (12 per page = " . ceil(count($created_properties) / 12) . " pages)\n";

mysqli_close($conn);
?>
