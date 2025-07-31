<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

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

// Get lease ID from URL
if (!isset($_GET['id'])) {
    header("Location: tenants.php");
    exit();
}

$lease_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];

// Fetch lease details
$lease_query = "
    SELECT 
        l.*,
        p.title AS property_title,
        p.address AS property_address,
        lt.template_name,
        lt.content AS template_content,
        CONCAT(landlord.first_name, ' ', landlord.last_name) AS landlord_name,
        landlord.email AS landlord_email,
        landlord.phone AS landlord_phone,
        CONCAT(tenant.first_name, ' ', tenant.last_name) AS tenant_name,
        tenant.email AS tenant_email,
        tenant.phone AS tenant_phone
    FROM leases l
    JOIN properties p ON l.property_id = p.id
    JOIN lease_templates lt ON l.template_id = lt.id
    JOIN users landlord ON l.landlord_id = landlord.id
    JOIN users tenant ON l.tenant_id = tenant.id
    WHERE l.id = $lease_id
    AND (l.landlord_id = $user_id OR l.tenant_id = $user_id)
";

$lease_result = mysqli_query($conn, $lease_query);

if (!$lease_result || mysqli_num_rows($lease_result) === 0) {
    header("Location: tenants.php");
    exit();
}

$lease = mysqli_fetch_assoc($lease_result);

// Replace placeholders in template content
$content = $lease['template_content'];
$replacements = [
    '[DATE]' => date('F j, Y', strtotime($lease['created_at'])),
    '[LANDLORD_NAME]' => $lease['landlord_name'],
    '[LANDLORD_ADDRESS]' => 'Not specified',
    '[TENANT_NAME]' => $lease['tenant_name'],
    '[TENANT_ADDRESS]' => 'Not specified',
    '[PROPERTY_ADDRESS]' => $lease['property_address'],
    '[START_DATE]' => date('F j, Y', strtotime($lease['lease_start_date'])),
    '[END_DATE]' => date('F j, Y', strtotime($lease['lease_end_date'])),
    '[RENT_AMOUNT]' => number_format($lease['monthly_rent'], 2),
    '[DEPOSIT_AMOUNT]' => number_format($lease['security_deposit'], 2)
];

foreach ($replacements as $placeholder => $value) {
    $content = str_replace($placeholder, $value, $content);
}

// Add additional terms if they exist
if (!empty($lease['terms'])) {
    $content .= "\n\n<h4>Additional Terms</h4>\n<p>" . nl2br(htmlspecialchars($lease['terms'])) . "</p>";
}

// Check if the current user is the landlord
$is_landlord = ($_SESSION['user_id'] == $lease['landlord_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lease - EasyRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .lease-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .meta-card {
            flex: 1;
            min-width: 200px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid #e2e8f0;
        }

        .meta-title {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .meta-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .lease-content {
            padding: 2rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            line-height: 1.8;
        }

        .signature-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }

        .signature-line {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        .signature-block {
            width: 45%;
        }

        .signature-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .signature-img {
            height: 80px;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid #94a3b8;
        }

        .signature-date {
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .lease-meta {
                flex-direction: column;
            }
            
            .signature-line {
                flex-direction: column;
            }
            
            .signature-block {
                width: 100%;
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Lease Agreement</h1>
            <div>
                <a href="download_lease.php?id=<?php echo $lease_id; ?>" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download Lease
                </a>
                <a href="tenants.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Tenants
                </a>
            </div>
        </div>
        
        <div class="lease-meta">
            <div class="meta-card">
                <div class="meta-title">Property</div>
                <div class="meta-value"><?php echo htmlspecialchars($lease['property_title']); ?></div>
            </div>
            
            <div class="meta-card">
                <div class="meta-title">Tenant</div>
                <div class="meta-value"><?php echo htmlspecialchars($lease['tenant_name']); ?></div>
            </div>
            
            <div class="meta-card">
                <div class="meta-title">Lease Period</div>
                <div class="meta-value">
                    <?php echo date('M j, Y', strtotime($lease['lease_start_date'])); ?> - 
                    <?php echo date('M j, Y', strtotime($lease['lease_end_date'])); ?>
                </div>
            </div>
            
            <div class="meta-card">
                <div class="meta-title">Monthly Rent</div>
                <div class="meta-value">R<?php echo number_format($lease['monthly_rent'], 2); ?></div>
            </div>
        </div>
        
        <div class="lease-content">
            <?php echo $content; ?>
            
            <div class="signature-section">
                <h3>Signatures</h3>
                <div class="signature-line">
                    <div class="signature-block">
                        <div class="signature-label">Landlord Signature</div>
                        <?php if (!empty($lease['signature_path'])): ?>
                            <div class="signature-img">
                                <img src="<?php echo htmlspecialchars($lease['signature_path']); ?>" alt="Landlord Signature" style="max-height: 80px;">
                            </div>
                            <div class="signature-date">
                                Signed on: <?php echo date('M j, Y', strtotime($lease['signed_at'])); ?>
                            </div>
                        <?php else: ?>
                            <div style="height: 80px; border-bottom: 1px solid #94a3b8;"></div>
                            <div class="signature-date">Not signed yet</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="signature-block">
                        <div class="signature-label">Tenant Signature</div>
                        <?php if (!empty($lease['tenant_signature_path'])): ?>
                            <div class="signature-img">
                                <img src="<?php echo htmlspecialchars($lease['tenant_signature_path']); ?>" alt="Tenant Signature" style="max-height: 80px;">
                            </div>
                            <div class="signature-date">
                                Signed on: <?php echo date('M j, Y', strtotime($lease['tenant_signed_at'])); ?>
                            </div>
                        <?php else: ?>
                            <div style="height: 80px; border-bottom: 1px solid #94a3b8;"></div>
                            <div class="signature-date">Not signed yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>