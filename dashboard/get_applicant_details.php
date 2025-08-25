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

// Get application ID and tenant ID from request
$appId = $_GET['id'] ?? 0;
$tenantId = $_GET['tenant_id'] ?? 0;

// Verify the landlord has access to this application
$landlord_id = $_SESSION['user_id'] ?? 1;
$verify_query = "
    SELECT ra.* 
    FROM rental_applications ra
    JOIN properties p ON ra.property_id = p.id
    WHERE ra.id = $appId AND p.landlord_id = $landlord_id
";

$verify_result = mysqli_query($conn, $verify_query);

if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
    echo '<div class="alert alert-error">Access denied. Application not found.</div>';
    exit;
}

// Fetch application details
$query = "
    SELECT 
        ra.*, 
        p.title AS property_title, 
        p.description AS property_description,
        p.address AS property_address,
        u.first_name, 
        u.last_name, 
        u.email, 
        u.phone,
        u.date_of_birth,
        u.employment_status,
        u.monthly_income,
        u.credit_score,
        u.rental_history,
        u.created_at,
        u.is_verified,
        u.is_active
    FROM rental_applications ra
    JOIN properties p ON ra.property_id = p.id
    JOIN users u ON ra.tenant_id = u.id
    WHERE ra.id = $appId
";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $app = mysqli_fetch_assoc($result);
    
    // Format dates
    $dob = !empty($app['date_of_birth']) ? date('M d, Y', strtotime($app['date_of_birth'])) : 'Not provided';
    $created = date('M d, Y', strtotime($app['created_at']));
    $moveIn = date('M d, Y', strtotime($app['move_in_date']));
    
    // Format income
    $income = !empty($app['monthly_income']) ? '$' . number_format($app['monthly_income']) : 'Not provided';
    
    echo '
    <div class="profile-header">
        <img src="https://ui-avatars.com/api/?name=' . urlencode($app['first_name'] . '+' . $app['last_name']) . '&background=4e73df&color=fff&size=128" 
             alt="Profile Image" class="profile-img">
        <div class="profile-info">
            <h2 class="profile-name">' . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) . '</h2>
            <p class="profile-role">Applicant for ' . htmlspecialchars($app['property_title']) . '</p>
            <p><i class="fas fa-calendar-alt"></i> Applied on ' . date('M d, Y', strtotime($app['application_date'])) . '</p>
        </div>
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="detail-label">Application Status</div>
            <div class="detail-value">
                <span class="status-badge-modal status-' . $app['status'] . '">' . ucfirst($app['status']) . '</span>
            </div>
        </div>
        
        <div class="info-item">
            <div class="detail-label">Account Status</div>
            <div class="detail-value">
                <span class="status-badge-modal ' . ($app['is_active'] ? 'badge-success' : 'badge-warning') . '">
                    ' . ($app['is_active'] ? 'Active' : 'Inactive') . '
                </span>
            </div>
        </div>
        
        <div class="info-item">
            <div class="detail-label">Verification Status</div>
            <div class="detail-value">
                <span class="status-badge-modal ' . ($app['is_verified'] ? 'badge-success' : 'badge-warning') . '">
                    ' . ($app['is_verified'] ? 'Verified' : 'Not Verified') . '
                </span>
            </div>
        </div>
        
        <div class="info-item">
            <div class="detail-label">Member Since</div>
            <div class="detail-value">' . $created . '</div>
        </div>
    </div>
    
    <div class="detail-group">
        <h3 class="detail-label">Contact Information</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="detail-label">Email Address</div>
                <div class="detail-value">' . htmlspecialchars($app['email']) . '</div>
            </div>
            
            <div class="info-item">
                <div class="detail-label">Phone Number</div>
                <div class="detail-value">' . htmlspecialchars($app['phone'] ?? 'Not provided') . '</div>
            </div>
            
            <div class="info-item">
                <div class="detail-label">Date of Birth</div>
                <div class="detail-value">' . $dob . '</div>
            </div>
        </div>
    </div>
    
    <div class="detail-group">
        <h3 class="detail-label">Financial Information</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="detail-label">Employment Status</div>
                <div class="detail-value">' . htmlspecialchars($app['employment_status'] ?? 'Not provided') . '</div>
            </div>
            
            <div class="info-item">
                <div class="detail-label">Monthly Income</div>
                <div class="detail-value">' . $income . '</div>
            </div>
            
            <div class="info-item">
                <div class="detail-label">Credit Score</div>
                <div class="detail-value">' . ($app['credit_score'] ?? 'Not provided') . '</div>
            </div>
        </div>
    </div>
    
    <div class="detail-group">
        <h3 class="detail-label">Application Details</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="detail-label">Property</div>
                <div class="detail-value">' . htmlspecialchars($app['property_title']) . '</div>
            </div>
            
            <div class="info-item">
                <div class="detail-label">Move-in Date</div>
                <div class="detail-value">' . $moveIn . '</div>
            </div>
            
            <div class="info-item">
                <div class="detail-label">Lease Duration</div>
                <div class="detail-value">' . $app['lease_duration_months'] . ' months</div>
            </div>
            
            <div class="info-item">
                <div class="detail-label">Number of Occupants</div>
                <div class="detail-value">' . $app['number_of_occupants'] . '</div>
            </div>
        </div>
    </div>
    
    <div class="detail-group">
        <h3 class="detail-label">Rental History</h3>
        <div class="detail-value">' . (!empty($app['rental_history']) ? nl2br(htmlspecialchars($app['rental_history'])) : 'Not provided') . '</div>
    </div>
    
    <div class="detail-group">
        <h3 class="detail-label">Application Message</h3>
        <div class="detail-value">' . (!empty($app['message']) ? nl2br(htmlspecialchars($app['message'])) : 'No message provided') . '</div>
    </div>
    ';
} else {
    echo '<div class="alert alert-error">Error: Application details not found.</div>';
}
?>