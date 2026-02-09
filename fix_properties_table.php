<?php
/**
 * Fix Properties Table ID Column
 * This script fixes the properties table to ensure the ID column is AUTO_INCREMENT PRIMARY KEY
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

echo "=== Fixing Properties Table ===\n\n";

// First, check if the properties table exists
$check_table = "SHOW TABLES LIKE 'properties'";
$result = mysqli_query($conn, $check_table);

if (mysqli_num_rows($result) == 0) {
    echo "ERROR: Properties table does not exist!\n";
    exit(1);
}

echo "✓ Properties table exists\n";

// Check current structure of the ID column
$check_column = "SHOW COLUMNS FROM properties LIKE 'id'";
$result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($result) == 0) {
    echo "\nID column does not exist. Creating it...\n";
    
    // Add ID column as AUTO_INCREMENT PRIMARY KEY
    $alter_query = "ALTER TABLE properties ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST";
    
    if (mysqli_query($conn, $alter_query)) {
        echo "✓ ID column created successfully\n";
    } else {
        echo "ERROR: Failed to create ID column: " . mysqli_error($conn) . "\n";
        exit(1);
    }
} else {
    $column_info = mysqli_fetch_assoc($result);
    echo "\nCurrent ID column structure:\n";
    echo "  Type: " . $column_info['Type'] . "\n";
    echo "  Null: " . $column_info['Null'] . "\n";
    echo "  Key: " . $column_info['Key'] . "\n";
    echo "  Extra: " . $column_info['Extra'] . "\n";
    
    // Check if it's already AUTO_INCREMENT PRIMARY KEY
    if ($column_info['Key'] == 'PRI' && strpos($column_info['Extra'], 'auto_increment') !== false) {
        echo "\n✓ ID column is already correctly configured as AUTO_INCREMENT PRIMARY KEY\n";
        
        // Check if there are any records with ID = 0
        $check_zero_ids = "SELECT COUNT(*) as count FROM properties WHERE id = 0";
        $result = mysqli_query($conn, $check_zero_ids);
        $row = mysqli_fetch_assoc($result);
        
        if ($row['count'] > 0) {
            echo "\n⚠ Found " . $row['count'] . " records with ID = 0. Fixing them...\n";
            
            // Get the maximum ID
            $max_id_query = "SELECT COALESCE(MAX(id), 0) as max_id FROM properties WHERE id > 0";
            $max_result = mysqli_query($conn, $max_id_query);
            $max_row = mysqli_fetch_assoc($max_result);
            $next_id = $max_row['max_id'] + 1;
            
            // Update all records with ID = 0
            $update_query = "UPDATE properties SET id = ? WHERE id = 0 LIMIT 1";
            $stmt = mysqli_prepare($conn, $update_query);
            
            $updated_count = 0;
            while (true) {
                mysqli_stmt_bind_param($stmt, "i", $next_id);
                mysqli_stmt_execute($stmt);
                
                if (mysqli_stmt_affected_rows($stmt) == 0) {
                    break; // No more records to update
                }
                
                $updated_count++;
                $next_id++;
            }
            
            mysqli_stmt_close($stmt);
            echo "✓ Updated $updated_count records with new IDs\n";
        } else {
            echo "\n✓ No records with ID = 0 found\n";
        }
    } else {
        echo "\n⚠ ID column needs to be fixed. Current status:\n";
        echo "  - Key: " . ($column_info['Key'] == 'PRI' ? 'PRIMARY KEY' : 'NOT PRIMARY KEY') . "\n";
        echo "  - Auto Increment: " . (strpos($column_info['Extra'], 'auto_increment') !== false ? 'YES' : 'NO') . "\n";
        
        // Check if there are existing records
        $count_query = "SELECT COUNT(*) as count FROM properties";
        $count_result = mysqli_query($conn, $count_query);
        $count_row = mysqli_fetch_assoc($count_result);
        $record_count = $count_row['count'];
        
        echo "\n  Found $record_count existing records\n";
        
        if ($record_count > 0) {
            echo "\n⚠ WARNING: There are existing records in the table.\n";
            echo "  We need to:\n";
            echo "  1. Create a temporary table with correct structure\n";
            echo "  2. Copy data with new auto-increment IDs\n";
            echo "  3. Drop old table and rename new one\n";
            echo "\n  Proceeding with fix...\n\n";
            
            // Step 1: Create temporary table with correct structure
            echo "Step 1: Creating temporary table...\n";
            $create_temp = "CREATE TABLE properties_temp LIKE properties";
            if (!mysqli_query($conn, $create_temp)) {
                echo "ERROR: Failed to create temp table: " . mysqli_error($conn) . "\n";
                exit(1);
            }
            
            // Modify temp table to have proper ID column
            $alter_temp = "ALTER TABLE properties_temp MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST";
            if (!mysqli_query($conn, $alter_temp)) {
                echo "ERROR: Failed to alter temp table: " . mysqli_error($conn) . "\n";
                mysqli_query($conn, "DROP TABLE properties_temp");
                exit(1);
            }
            echo "✓ Temporary table created\n";
            
            // Step 2: Get all columns except ID
            $columns_query = "SHOW COLUMNS FROM properties";
            $columns_result = mysqli_query($conn, $columns_query);
            $columns = [];
            while ($col = mysqli_fetch_assoc($columns_result)) {
                if ($col['Field'] != 'id') {
                    $columns[] = $col['Field'];
                }
            }
            $columns_str = implode(', ', $columns);
            
            // Step 3: Copy data (without ID, letting auto-increment handle it)
            echo "\nStep 2: Copying data to temporary table...\n";
            $copy_data = "INSERT INTO properties_temp ($columns_str) SELECT $columns_str FROM properties";
            if (!mysqli_query($conn, $copy_data)) {
                echo "ERROR: Failed to copy data: " . mysqli_error($conn) . "\n";
                mysqli_query($conn, "DROP TABLE properties_temp");
                exit(1);
            }
            echo "✓ Data copied successfully\n";
            
            // Step 4: Drop old table and rename new one
            echo "\nStep 3: Replacing old table...\n";
            mysqli_query($conn, "DROP TABLE properties");
            mysqli_query($conn, "RENAME TABLE properties_temp TO properties");
            echo "✓ Table replaced successfully\n";
            
            echo "\n✓ Properties table fixed successfully!\n";
            echo "  All records now have proper auto-increment IDs\n";
        } else {
            // No records, just alter the column
            echo "\n  No existing records. Simply fixing the column structure...\n";
            
            // Drop existing ID column if it exists
            if ($column_info['Key'] == 'PRI') {
                // Remove primary key first
                $drop_pk = "ALTER TABLE properties DROP PRIMARY KEY";
                mysqli_query($conn, $drop_pk);
            }
            
            // Modify ID column to be AUTO_INCREMENT PRIMARY KEY
            $alter_query = "ALTER TABLE properties MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST";
            
            if (mysqli_query($conn, $alter_query)) {
                echo "✓ ID column fixed successfully\n";
            } else {
                echo "ERROR: Failed to fix ID column: " . mysqli_error($conn) . "\n";
                exit(1);
            }
        }
    }
}

// Verify the fix
echo "\n=== Verification ===\n";
$verify_query = "SHOW COLUMNS FROM properties WHERE Field = 'id'";
$verify_result = mysqli_query($conn, $verify_query);
$verify_row = mysqli_fetch_assoc($verify_result);

echo "Final ID column structure:\n";
echo "  Field: " . $verify_row['Field'] . "\n";
echo "  Type: " . $verify_row['Type'] . "\n";
echo "  Null: " . $verify_row['Null'] . "\n";
echo "  Key: " . $verify_row['Key'] . "\n";
echo "  Extra: " . $verify_row['Extra'] . "\n";

if ($verify_row['Key'] == 'PRI' && strpos($verify_row['Extra'], 'auto_increment') !== false) {
    echo "\n✓ SUCCESS: ID column is now properly configured!\n";
    
    // Show sample of IDs
    $sample_query = "SELECT id FROM properties ORDER BY id LIMIT 5";
    $sample_result = mysqli_query($conn, $sample_query);
    
    if (mysqli_num_rows($sample_result) > 0) {
        echo "\nSample property IDs:\n";
        while ($sample = mysqli_fetch_assoc($sample_result)) {
            echo "  - Property ID: " . $sample['id'] . "\n";
        }
    }
} else {
    echo "\n✗ ERROR: ID column is still not properly configured!\n";
    exit(1);
}

mysqli_close($conn);
echo "\n=== Done ===\n";
?>
