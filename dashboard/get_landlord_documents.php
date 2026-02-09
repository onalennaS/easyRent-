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

// Create landlord_documents table if it doesn't exist
$check_table = "SHOW TABLES LIKE 'landlord_documents'";
$table_result = mysqli_query($conn, $check_table);
if (!$table_result || mysqli_num_rows($table_result) == 0) {
    $create_documents_table = "
        CREATE TABLE IF NOT EXISTS landlord_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            landlord_id INT NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_doc (landlord_id, document_type),
            INDEX idx_landlord_id (landlord_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    mysqli_query($conn, $create_documents_table);
}

$landlord_id = (int)($_GET['landlord_id'] ?? 0);

if ($landlord_id <= 0) {
    echo '<div class="no-documents"><i class="fas fa-exclamation-triangle"></i><h3>Error</h3><p>Invalid landlord ID</p></div>';
    exit;
}

// Get documents with prepared statement for security
$documents_query = "SELECT * FROM landlord_documents WHERE landlord_id = ? ORDER BY uploaded_at DESC";
$stmt = mysqli_prepare($conn, $documents_query);
mysqli_stmt_bind_param($stmt, "i", $landlord_id);
mysqli_stmt_execute($stmt);
$documents_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($documents_result) == 0) {
    echo '<div class="no-documents">
            <i class="fas fa-folder-open"></i>
            <h3>No Documents Found</h3>
            <p>This landlord has not uploaded any documents yet.</p>
          </div>';
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}

// Document type labels
$document_labels = [
    'id_document' => 'Identity Document',
    'proof_of_address' => 'Proof of Address',
    'bank_statement' => 'Bank Statement',
    'tax_clearance' => 'Tax Clearance Certificate',
    'business_license' => 'Business License',
    'property_deed' => 'Property Deed/Title',
    'insurance_certificate' => 'Insurance Certificate',
    'credit_report' => 'Credit Report'
];

echo '<div class="documents-grid">';

while ($doc = mysqli_fetch_assoc($documents_result)) {
    // Use the status column consistently
    $approval_status = $doc['status'] ?? 'pending';
    $status_class = 'status-' . $approval_status;
    $doc_label = $document_labels[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
    $file_extension = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
    $is_pdf = strtolower($file_extension) === 'pdf';
    
    echo '<div class="document-item">
            <div class="document-item-header">
                <div class="document-name">
                    <i class="fas fa-' . ($is_pdf ? 'file-pdf' : 'file-image') . '"></i>
                    ' . htmlspecialchars($doc_label) . '
                </div>
                <span class="status-badge ' . $status_class . '">
                    ' . ucfirst($approval_status) . '
                </span>
            </div>
            <div class="document-info">
                <strong>Uploaded:</strong> ' . date('M j, Y g:i A', strtotime($doc['uploaded_at'])) . '<br>
                <strong>File:</strong> ' . htmlspecialchars($doc['file_path']) . '
            </div>';
    
    // Show rejection reason if document is rejected
    if ($approval_status === 'rejected' && !empty($doc['rejection_reason'])) {
        echo '<div class="rejection-reason">
                <strong>Reason for rejection: </strong>' . htmlspecialchars($doc['rejection_reason']) . '
              </div>';
    }
    
    echo '<div class="document-actions">
                <a href="../uploads/documents/' . htmlspecialchars($doc['file_path']) . '" 
                   target="_blank" class="btn-view">
                    <i class="fas fa-eye"></i> View
                </a>';
    
    // Show approve/reject buttons based on status
    if ($approval_status !== 'approved') {
        echo '<button onclick="approveDocument(' . $doc['id'] . ')" class="btn-view btn-approve-doc">
                <i class="fas fa-check"></i> Approve
              </button>';
    }
    
    if ($approval_status !== 'rejected') {
        echo '<button onclick="rejectDocument(' . $doc['id'] . ')" class="btn-view btn-reject-doc">
                <i class="fas fa-times"></i> Reject
              </button>';
    }
    
    echo '    </div>
          </div>';
}

echo '</div>';

// Add the CSS for status badges
echo '<style>
.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #dcfce7;
    color: #166534;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.document-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

.btn-approve-doc {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-reject-doc {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.rejection-reason {
    margin-top: 0.5rem;
    padding: 0.75rem;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 6px;
    font-size: 0.875rem;
    border-left: 4px solid #ef4444;
}

.rejection-reason strong {
    color: #7f1d1d;
}
</style>';

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>