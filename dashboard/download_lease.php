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
        tenant.phone AS tenant_phone,
        l.signed_date,
        l.tenant_signed_date
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

// Check if we want to force HTML output (for testing or if no PDF library available)
$force_html = isset($_GET['format']) && $_GET['format'] === 'html';

// Try to use mPDF if available (easier to install than TCPDF)
if (!$force_html && class_exists('Mpdf\Mpdf')) {
    generateMPDFDocument($lease, $content);
} elseif (!$force_html && function_exists('wkhtmltopdf')) {
    generateWKHTMLToPDF($lease, $content);
} else {
    // Fallback to HTML with print-friendly styling
    generatePrintableHTML($lease, $content);
}

function generateMPDFDocument($lease, $content) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_left' => 20,
        'margin_right' => 20,
        'margin_top' => 30,
        'margin_bottom' => 25,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);
    
    // Convert image paths to absolute paths for PDF generation
    $lease_for_pdf = $lease;
    if (!empty($lease['signature_path']) && !filter_var($lease['signature_path'], FILTER_VALIDATE_URL)) {
        $lease_for_pdf['signature_path'] = $_SERVER['DOCUMENT_ROOT'] . $lease['signature_path'];
    }
    if (!empty($lease['tenant_signature_path']) && !filter_var($lease['tenant_signature_path'], FILTER_VALIDATE_URL)) {
        $lease_for_pdf['tenant_signature_path'] = $_SERVER['DOCUMENT_ROOT'] . $lease['tenant_signature_path'];
    }
    
    $html = generateLeaseHTML($lease_for_pdf, $content, true);
    
    $mpdf->SetTitle('Lease Agreement - ' . $lease['property_title']);
    $mpdf->SetAuthor('EasyRent Property Management');
    $mpdf->WriteHTML($html);
    
    $filename = sanitizeFilename('Lease_Agreement_' . $lease['property_title'] . '_' . date('Y-m-d') . '.pdf');
    $mpdf->Output($filename, 'D');
}

function generateWKHTMLToPDF($lease, $content) {
    $html = generateLeaseHTML($lease, $content, true);
    $htmlFile = tempnam(sys_get_temp_dir(), 'lease_') . '.html';
    file_put_contents($htmlFile, $html);
    
    $filename = sanitizeFilename('Lease_Agreement_' . $lease['property_title'] . '_' . date('Y-m-d') . '.pdf');
    $pdfFile = tempnam(sys_get_temp_dir(), 'lease_') . '.pdf';
    
    $command = "wkhtmltopdf --page-size A4 --margin-top 20mm --margin-bottom 20mm --margin-left 20mm --margin-right 20mm '$htmlFile' '$pdfFile'";
    exec($command);
    
    if (file_exists($pdfFile)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdfFile));
        readfile($pdfFile);
        unlink($pdfFile);
    }
    
    unlink($htmlFile);
}

function generatePrintableHTML($lease, $content) {
    $html = generateLeaseHTML($lease, $content, false);
    echo $html;
}

function generateLeaseHTML($lease, $content, $isPDF = false) {
    $printButtonStyle = $isPDF ? 'display: none;' : '';
    
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lease Agreement - ' . htmlspecialchars($lease['property_title']) . '</title>
    <style>
        @page {
            margin: 2cm;
            size: A4;
        }
        
        body {
            font-family: "Times New Roman", serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
            margin: 0;
            padding: 20px;
            background: white;
        }
        
        .print-button {
            ' . $printButtonStyle . '
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #000;
            padding-bottom: 20px;
        }
        
        .title {
            font-size: 28pt;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .subtitle {
            font-size: 12pt;
            color: #666;
        }
        
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            text-transform: uppercase;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 12px;
        }
        
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .content-text {
            text-align: justify;
            margin-bottom: 15px;
        }
        
        .signature-section {
            margin-top: 60px;
            page-break-inside: avoid;
        }
        
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
        }
        
        .signature-block {
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 2px solid #000;
            height: 50px;
            margin-bottom: 10px;
            margin-top: 30px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        
        .signature-image {
            max-height: 45px;
            max-width: 200px;
            margin-bottom: 5px;
            object-fit: contain;
        }
        
        .signature-placeholder {
            height: 50px;
            border-bottom: 2px solid #000;
            margin-top: 30px;
            margin-bottom: 10px;
        }
        
        .signature-status {
            font-size: 10pt;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        
        .signature-name {
            font-weight: bold;
            font-size: 11pt;
        }
        
        .signature-title {
            font-size: 10pt;
            color: #666;
            margin-bottom: 5px;
        }
        
        .date-line {
            margin-top: 20px;
            font-size: 10pt;
        }
        
        @media print {
            body { 
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .print-button { display: none !important; }
            .container { padding: 0; }
        }
        
        @media screen and (max-width: 768px) {
            .container { padding: 20px; }
            .details-grid { grid-template-columns: 1fr; }
            .signature-grid { grid-template-columns: 1fr; gap: 30px; }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print / Save as PDF</button>
    
    <div class="container">
        <div class="header">
            <div class="title">LEASE AGREEMENT</div>
            <div class="subtitle">Residential Rental Agreement</div>
        </div>
        
        <div class="section">
            <div class="section-title">Property & Lease Information</div>
            <div class="details-grid">
                <div>
                    <div class="detail-item">
                        <span class="detail-label">Property:</span>
                        ' . htmlspecialchars($lease['property_title']) . '
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Address:</span>
                        ' . htmlspecialchars($lease['property_address']) . '
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Landlord:</span>
                        ' . htmlspecialchars($lease['landlord_name']) . '
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Tenant:</span>
                        ' . htmlspecialchars($lease['tenant_name']) . '
                    </div>
                </div>
                <div>
                    <div class="detail-item">
                        <span class="detail-label">Start Date:</span>
                        ' . date('F j, Y', strtotime($lease['lease_start_date'])) . '
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">End Date:</span>
                        ' . date('F j, Y', strtotime($lease['lease_end_date'])) . '
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Monthly Rent:</span>
                        R' . number_format($lease['monthly_rent'], 2) . '
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Security Deposit:</span>
                        R' . number_format($lease['security_deposit'], 2) . '
                    </div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Terms and Conditions</div>
            <div class="content-text">
                ' . $content . '
            </div>
        </div>
        
        <div class="signature-section">
            <div class="section-title">Signatures</div>
            <p>By signing below, both parties agree to the terms and conditions outlined in this lease agreement.</p>
            
            <div class="signature-grid">
                <div class="signature-block">
                    <div class="signature-title">Landlord</div>
                    ' . (!empty($lease['signature_path']) ? 
                        '<div class="signature-line">
                            <img src="' . htmlspecialchars($lease['signature_path']) . '" alt="Landlord Signature" class="signature-image">
                        </div>
                        <div class="signature-status">✓ Signed</div>' : 
                        '<div class="signature-placeholder"></div>
                        <div class="signature-status">⚠ Not signed yet</div>') . '
                    <div class="signature-name">' . htmlspecialchars($lease['landlord_name']) . '</div>
                    <div class="date-line">
                        Date: ' . (!empty($lease['signed_date']) ? 
                            date('F j, Y', strtotime($lease['signed_date'])) : 
                            '_____________________') . '
                    </div>
                </div>
                
                <div class="signature-block">
                    <div class="signature-title">Tenant</div>
                    ' . (!empty($lease['tenant_signature_path']) ? 
                        '<div class="signature-line">
                            <img src="' . htmlspecialchars($lease['tenant_signature_path']) . '" alt="Tenant Signature" class="signature-image">
                        </div>
                        <div class="signature-status">✓ Signed</div>' : 
                        '<div class="signature-placeholder"></div>
                        <div class="signature-status">⚠ Not signed yet</div>') . '
                    <div class="signature-name">' . htmlspecialchars($lease['tenant_name']) . '</div>
                    <div class="date-line">
                        Date: ' . (!empty($lease['tenant_signed_date']) ? 
                            date('F j, Y', strtotime($lease['tenant_signed_date'])) : 
                            '_____________________') . '
                    </div>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 40px; font-size: 10pt; color: #666; text-align: center;">
            Generated by EasyRent Property Management System on ' . date('F j, Y') . '
        </div>
    </div>
    
    <script>
        // Keyboard shortcut for printing
        document.addEventListener("keydown", function(e) {
            if (e.ctrlKey && e.key === "p") {
                e.preventDefault();
                window.print();
            }
        });
        
        // Auto-focus print button for accessibility
        document.addEventListener("DOMContentLoaded", function() {
            const printBtn = document.querySelector(".print-button");
            if (printBtn) {
                printBtn.focus();
            }
        });
    </script>
</body>
</html>';
}

function sanitizeFilename($filename) {
    // Remove or replace invalid characters
    $filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $filename);
    // Remove multiple underscores
    $filename = preg_replace('/_+/', '_', $filename);
    return $filename;
}

// Close database connection
mysqli_close($conn);
?>