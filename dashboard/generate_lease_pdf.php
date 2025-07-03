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

$lease_id = (int)($_GET['lease_id'] ?? 0);

// Fetch lease details
$lease_query = "SELECT * FROM leases WHERE id = $lease_id";
$lease_result = mysqli_query($conn, $lease_query);
$lease = mysqli_fetch_assoc($lease_result);

// Fetch property details
$property_id = $lease['property_id'];
$prop_query = "SELECT * FROM properties WHERE id = $property_id";
$prop_result = mysqli_query($conn, $prop_query);
$property = mysqli_fetch_assoc($prop_result);

// Fetch landlord details
$landlord_id = $lease['landlord_id'];
$landlord_query = "SELECT * FROM users WHERE id = $landlord_id";
$landlord_result = mysqli_query($conn, $landlord_query);
$landlord = mysqli_fetch_assoc($landlord_result);

// Fetch tenant details
$tenant_id = $lease['tenant_id'];
$tenant_query = "SELECT * FROM users WHERE id = $tenant_id";
$tenant_result = mysqli_query($conn, $tenant_query);
$tenant = mysqli_fetch_assoc($tenant_result);

// Format phone numbers to start with 0
function formatPhoneNumber($phone) {
    if (empty($phone)) return 'N/A';
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (substr($clean, 0, 1) !== '0') {
        return '0' . $clean;
    }
    return $clean;
}

$landlord_phone = formatPhoneNumber($landlord['phone']);
$tenant_phone = formatPhoneNumber($tenant['phone']);

// Generate HTML content
ob_start(); // Start output buffering
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lease Agreement - EasyRent</title>
    <style>
        body { 
            font-family: 'Times New Roman', Times, serif; 
            line-height: 1.6;
            color: #000;
            max-width: 700px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
        }
        
        .contract-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .contract-title {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0;
        }
        
        .section {
            margin-bottom: 20px;
            text-align: justify;
        }
        
        .signature-area {
            margin-top: 80px;
            padding-top: 30px;
            border-top: 1px solid #000;
        }
        
        .party-info {
            margin: 15px 0;
        }
        
        .party-info strong {
            display: block;
            margin-bottom: 5px;
            text-decoration: underline;
        }
        
        .clause-title {
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 8px;
        }
        
        .signature-block {
            width: 45%;
            float: left;
            margin-top: 40px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 80%;
            margin: 60px 0 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 120px;
            color: #555;
            font-size: 12px;
        }
        
        .terms-list {
            padding-left: 20px;
        }
        
        .terms-list li {
            margin-bottom: 8px;
        }
        
        .clear {
            clear: both;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-underline {
            text-decoration: underline;
        }
        
        @media print {
            body {
                padding: 20px;
                margin: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="contract-header">
        <h1 class="contract-title">LEASE AGREEMENT</h1>
        <p>Lease #: <?= $lease_id ?></p>
        <p>Date: <?= date('F j, Y') ?></p>
    </div>
    
    <div class="section">
        <p>THIS LEASE AGREEMENT ("Agreement") made this <?= date('j') ?> day of <?= date('F') ?>, <?= date('Y') ?> between:</p>
        
        <div class="party-info">
            <strong>LANDLORD:</strong>
            <?= $landlord['first_name'] ?> <?= $landlord['last_name'] ?><br>
            <?= $landlord['email'] ?><br>
            Phone: <?= $landlord_phone ?>
        </div>
        
        <div class="party-info">
            <strong>TENANT:</strong>
            <?= $tenant['first_name'] ?> <?= $tenant['last_name'] ?><br>
            <?= $tenant['email'] ?><br>
            Phone: <?= $tenant_phone ?>
        </div>
    </div>
    
    <div class="section">
        <p class="clause-title">1. PROPERTY</p>
        <p>The Landlord hereby leases to the Tenant the property known as:</p>
        <p><strong><?= $property['title'] ?></strong><br>
        <?= $property['address'] ?></p>
    </div>
    
    <div class="section">
        <p class="clause-title">2. TERM</p>
        <p>The term of this Lease shall commence on <span class="text-underline"><?= date('F j, Y', strtotime($lease['lease_start_date'])) ?></span> and 
        terminate on <span class="text-underline"><?= date('F j, Y', strtotime($lease['lease_end_date'])) ?></span>.</p>
    </div>
    
    <div class="section">
        <p class="clause-title">3. RENT AND PAYMENT</p>
        <p>The monthly rent shall be R<?= number_format($lease['monthly_rent'], 2) ?> (Rands). Rent shall be due on the first day of each calendar month. 
        Late payments shall incur a penalty fee of R150.00.</p>
    </div>
    
    <div class="section">
        <p class="clause-title">4. SECURITY DEPOSIT</p>
        <p>Upon execution of this Agreement, Tenant has deposited with Landlord the sum of R<?= number_format($lease['security_deposit'], 2) ?> 
        (Rands) as security for the performance of Tenant's obligations hereunder.</p>
    </div>
    
    <div class="section">
        <p class="clause-title">5. UTILITIES</p>
        <p>Tenant shall be responsible for payment of all utilities and services including electricity, water, and internet services.</p>
    </div>
    
    <div class="section">
        <p class="clause-title">6. TERMS AND CONDITIONS</p>
        <ol class="terms-list">
            <li>The Tenant shall maintain the premises in good condition and repair</li>
            <li>No pets shall be kept on the premises without Landlord's written consent</li>
            <li>Tenant shall be responsible for all utility payments as outlined in Section 5</li>
            <li>Either party must provide sixty (60) days written notice for lease termination</li>
            <li>Landlord reserves the right to inspect premises with twenty-four (24) hours notice</li>
            <li>Tenant must obtain and maintain renters insurance throughout the lease term</li>
            <li>Subletting of premises is prohibited without Landlord's written consent</li>
        </ol>
    </div>
    
    <div class="section">
        <p class="clause-title">7. GOVERNING LAW</p>
        <p>This Agreement shall be governed by and construed in accordance with the laws of South Africa.</p>
    </div>
    
    <div class="signature-area">
        <div class="signature-block">
            <p class="text-center">LANDLORD SIGNATURE</p>
            <div class="signature-line"></div>
            <p class="text-center"><?= $landlord['first_name'] ?> <?= $landlord['last_name'] ?></p>
            <p class="text-center">Date: ____________________</p>
        </div>
        
        <div class="signature-block">
            <p class="text-center">TENANT SIGNATURE</p>
            <div class="signature-line"></div>
            <p class="text-center"><?= $tenant['first_name'] ?> <?= $tenant['last_name'] ?></p>
            <p class="text-center">Date: ____________________</p>
        </div>
        <div class="clear"></div>
    </div>
    
    <div class="footer">
        <p>This document was executed electronically via EasyRent on <?= date('F j, Y') ?></p>
        <p>EasyRent Property Management System</p>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 40px;">
        <button onclick="window.print()" style="padding: 10px 25px; background: #333; color: white; border: 1px solid #000; cursor: pointer; font-family: 'Times New Roman';">
            Print Lease Agreement
        </button>
        <a href="tenants.php" style="display: inline-block; margin-left: 15px; padding: 10px 25px; background: #eee; color: #000; text-decoration: none; border: 1px solid #000; font-family: 'Times New Roman';">
            Back to Tenants
        </a>
    </div>
</body>
</html>
<?php
$html = ob_get_clean(); // Get the buffered HTML content

// Close database connection
mysqli_close($conn);

// Output HTML to browser
echo $html;
?>