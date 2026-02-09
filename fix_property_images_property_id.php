<?php
/**
 * Fix Property Images Property ID
 * This script fixes the property_id values in property_images table
 * by matching images to properties based on creation order/timestamps
 */

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

echo "=== Fixing Property Images Property IDs ===\n\n";

// Get all properties ordered by ID (which should match creation order)
$properties_query = "SELECT id, created_at, landlord_id FROM properties ORDER BY id ASC";
$properties_result = mysqli_query($conn, $properties_query);

if (!$properties_result) {
    die("ERROR: Failed to fetch properties: " . mysqli_error($conn) . "\n");
}

$properties = [];
while ($prop = mysqli_fetch_assoc($properties_result)) {
    $properties[] = $prop;
}

echo "Found " . count($properties) . " properties\n";

// First, reset all property_ids to 0 so we can rematch them
echo "Resetting all property_ids to 0 for rematching...\n";
$reset_query = "UPDATE property_images SET property_id = 0";
if (!mysqli_query($conn, $reset_query)) {
    die("ERROR: Failed to reset property_ids: " . mysqli_error($conn) . "\n");
}
echo "✓ Reset complete\n\n";

// Get all property_images ordered by ID
$images_query = "SELECT id, property_id, image_url, is_primary, created_at 
                 FROM property_images 
                 ORDER BY id ASC";
$images_result = mysqli_query($conn, $images_query);

if (!$images_result) {
    die("ERROR: Failed to fetch images: " . mysqli_error($conn) . "\n");
}

$images = [];
while ($img = mysqli_fetch_assoc($images_result)) {
    $images[] = $img;
}

echo "Found " . count($images) . " total images\n\n";

if (count($images) == 0) {
    echo "✓ No images found.\n";
    mysqli_close($conn);
    exit(0);
}

if (count($properties) == 0) {
    echo "⚠ WARNING: No properties found. Cannot match images to properties.\n";
    mysqli_close($conn);
    exit(1);
}

// Strategy: Match image id to property id (1-to-1 matching)
// Image with id=1 goes to property id=1, image id=2 goes to property id=2, etc.

echo "Matching images to properties (image id = property id)...\n\n";

$matched_count = 0;

foreach ($images as $image) {
    $image_id = $image['id'];
    
    // Find property with matching id
    $property_id = null;
    foreach ($properties as $property) {
        if ($property['id'] == $image_id) {
            $property_id = $property['id'];
            break;
        }
    }
    
    // If no exact match, use the image id as property id (if property exists)
    if ($property_id === null) {
        // Check if a property with this id exists
        $check_property = "SELECT id FROM properties WHERE id = ?";
        $stmt_check = mysqli_prepare($conn, $check_property);
        mysqli_stmt_bind_param($stmt_check, "i", $image_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $property_id = $image_id;
        } else {
            // Property doesn't exist, skip this image
            echo "⚠ Image ID {$image_id}: No property with ID {$image_id} exists, skipping\n";
            mysqli_stmt_close($stmt_check);
            continue;
        }
        mysqli_stmt_close($stmt_check);
    }
    
    // Update the image with the property_id
    $update_query = "UPDATE property_images SET property_id = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $property_id, $image_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $matched_count++;
            echo "✓ Updated image ID {$image_id} -> property_id {$property_id}\n";
        } else {
            echo "✗ Failed to update image ID {$image_id}: " . mysqli_error($conn) . "\n";
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo "✗ Failed to prepare statement for image ID {$image_id}: " . mysqli_error($conn) . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total properties: " . count($properties) . "\n";
echo "Total images: " . count($images) . "\n";
echo "Images updated: $matched_count\n";

// Check if there are any remaining images with property_id = 0
$remaining_query = "SELECT COUNT(*) as count FROM property_images WHERE property_id = 0";
$remaining_result = mysqli_query($conn, $remaining_query);
$remaining_row = mysqli_fetch_assoc($remaining_result);

if ($remaining_row['count'] > 0) {
    echo "\n⚠ WARNING: {$remaining_row['count']} images still have property_id = 0\n";
    echo "These images could not be matched because no property with matching ID exists.\n";
    
    // Show remaining images
    $remaining_images_query = "SELECT id, image_url, created_at FROM property_images WHERE property_id = 0 LIMIT 10";
    $remaining_images_result = mysqli_query($conn, $remaining_images_query);
    
    if (mysqli_num_rows($remaining_images_result) > 0) {
        echo "\nRemaining images (no matching property ID):\n";
        while ($img = mysqli_fetch_assoc($remaining_images_result)) {
            echo "  - Image ID: {$img['id']}, Created: {$img['created_at']}, File: " . substr($img['image_url'], 0, 40) . "...\n";
        }
    }
} else {
    echo "\n✓ SUCCESS: All images have been matched to properties (image id = property id)!\n";
}

// Verify the fix
echo "\n=== Verification ===\n";
$verify_query = "SELECT p.id as property_id, p.title, COUNT(pi.id) as image_count 
                 FROM properties p 
                 LEFT JOIN property_images pi ON p.id = pi.property_id 
                 GROUP BY p.id 
                 ORDER BY p.id 
                 LIMIT 10";
$verify_result = mysqli_query($conn, $verify_query);

if ($verify_result && mysqli_num_rows($verify_result) > 0) {
    echo "Sample property-image relationships:\n";
    while ($row = mysqli_fetch_assoc($verify_result)) {
        echo "  - Property ID {$row['property_id']}: {$row['image_count']} image(s)\n";
    }
}

mysqli_close($conn);
echo "\n=== Done ===\n";
?>
