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

// Get landlord ID from session
$landlord_id = $_SESSION['user_id'] ?? 1; // Fallback for testing

// Handle form submissions
$success_message = $error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_template'])) {
        $template_name = mysqli_real_escape_string($conn, $_POST['template_name']);
        $content = mysqli_real_escape_string($conn, $_POST['content']);
        
        $insert_query = "INSERT INTO lease_templates (landlord_id, template_name, content) 
                         VALUES ($landlord_id, '$template_name', '$content')";
        
        if (mysqli_query($conn, $insert_query)) {
            $success_message = "Lease template created successfully!";
        } else {
            $error_message = "Error creating template: " . mysqli_error($conn);
        }
    } 
    elseif (isset($_POST['update_template'])) {
        $template_id = (int)$_POST['template_id'];
        $template_name = mysqli_real_escape_string($conn, $_POST['template_name']);
        $content = mysqli_real_escape_string($conn, $_POST['content']);
        
        $update_query = "UPDATE lease_templates 
                         SET template_name = '$template_name', content = '$content'
                         WHERE id = $template_id AND landlord_id = $landlord_id";
        
        if (mysqli_query($conn, $update_query)) {
            $success_message = "Lease template updated successfully!";
        } else {
            $error_message = "Error updating template: " . mysqli_error($conn);
        }
    } 
    elseif (isset($_POST['delete_template'])) {
        $template_id = (int)$_POST['template_id'];
        
        $delete_query = "DELETE FROM lease_templates 
                         WHERE id = $template_id AND landlord_id = $landlord_id";
        
        if (mysqli_query($conn, $delete_query)) {
            $success_message = "Lease template deleted successfully!";
        } else {
            $error_message = "Error deleting template: " . mysqli_error($conn);
        }
    }
}

// Fetch lease templates
$templates_query = "SELECT * FROM lease_templates WHERE landlord_id = $landlord_id ORDER BY created_at DESC";
$templates_result = mysqli_query($conn, $templates_query);

// Get template for editing
$edit_template = null;
if (isset($_GET['edit'])) {
    $template_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM lease_templates WHERE id = $template_id AND landlord_id = $landlord_id";
    $edit_result = mysqli_query($conn, $edit_query);
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $edit_template = mysqli_fetch_assoc($edit_result);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lease Templates - L&T Connect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        }

        /* Top Navigation */
        .top-nav {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 0 2rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            margin-top: 70px;
            padding: 2rem;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Template Cards */
        .template-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .template-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .template-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .template-actions {
            display: flex;
            gap: 0.5rem;
        }

        .template-content {
            flex: 1;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            overflow: hidden;
            position: relative;
            max-height: 200px;
        }

        .template-content::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(to bottom, rgba(255,255,255,0), white);
        }

        .template-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* Template Form */
        .template-form-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #1e293b;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .note-editor {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            overflow: hidden;
        }

        .note-editor .note-toolbar {
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .empty-state p {
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .template-cards {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .nav-menu {
                gap: 0.75rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .template-actions {
                flex-direction: column;
            }
            
            .template-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="logo">
                <img src="../logo.png" alt="L&T Connect" style="max-height: 36px; width: auto;">
            </div>
            
            <ul class="nav-menu">
                <li><a href="landlord_dashboard.php">Dashboard</a></li>
                <li><a href="my_properties.php">My Properties</a></li>
                <li><a href="applications.php">Applications</a></li>
                <li><a href="add_property.php">Add Property</a></li>
                <li><a href="maintenance.php">Maintenance</a></li>
                <li><a href="tenants.php">Tenants</a></li>
                <li><a href="lease_templates.php" class="active">Lease Templates</a></li>
            </ul>
            
            <div class="user-profile">
                <span>Welcome, <?php echo $_SESSION['user_name'] ?? 'Landlord'; ?></span>
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?>
                </div>
                <a href="../auth/logout.php" style="color: white; margin-left: 1rem;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-file-contract"></i>
                Lease Template Management
            </h1>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Template Form -->
        <div class="template-form-container">
            <h2 style="margin-bottom: 1.5rem; color: #1e293b;">
                <?php echo $edit_template ? 'Edit Lease Template' : 'Create New Lease Template'; ?>
            </h2>
            
            <form method="POST">
                <?php if ($edit_template): ?>
                    <input type="hidden" name="template_id" value="<?php echo $edit_template['id']; ?>">
                    <input type="hidden" name="update_template" value="1">
                <?php else: ?>
                    <input type="hidden" name="create_template" value="1">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Template Name</label>
                    <input type="text" name="template_name" class="form-control" 
                           value="<?php echo $edit_template ? htmlspecialchars($edit_template['template_name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Template Content</label>
                    <textarea id="template-content" name="content" class="form-control" rows="10" required>
                        <?php echo $edit_template ? htmlspecialchars($edit_template['content']) : ''; ?>
                    </textarea>
                </div>
                
                <div class="form-actions">
                    <?php if ($edit_template): ?>
                        <a href="lease_templates.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Template
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Create Template
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Template List -->
        <h2 style="margin: 2rem 0 1rem; color: #1e293b;">Your Lease Templates</h2>
        
        <div class="template-cards">
            <?php if ($templates_result && mysqli_num_rows($templates_result) > 0): ?>
                <?php while ($template = mysqli_fetch_assoc($templates_result)): ?>
                    <div class="template-card">
                        <div class="template-header">
                            <div class="template-name"><?php echo htmlspecialchars($template['template_name']); ?></div>
                            <div class="template-actions">
                                <a href="lease_templates.php?edit=<?php echo $template['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                    <input type="hidden" name="delete_template" value="1">
                                    <button type="submit" class="btn btn-danger" 
                                            onclick="return confirm('Are you sure you want to delete this template?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="template-content">
                            <?php echo substr(strip_tags($template['content']), 0, 300); ?>...
                        </div>
                        
                        <div class="template-footer">
                            <span>Created: <?php echo date('M j, Y', strtotime($template['created_at'])); ?></span>
                            <span>
                                <?php if ($template['updated_at']): ?>
                                    Updated: <?php echo date('M j, Y', strtotime($template['updated_at'])); ?>
                                <?php else: ?>
                                    Not updated
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Lease Templates Found</h3>
                    <p>You haven't created any lease templates yet. Create your first template to streamline the leasing process.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Summernote editor
            $('#template-content').summernote({
                height: 300,
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['height', ['height']],
                    ['insert', ['link', 'picture', 'video', 'table', 'hr']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                placeholder: 'Enter your lease agreement template here...',
                callbacks: {
                    onImageUpload: function(files) {
                        for (let i = 0; i < files.length; i++) {
                            uploadImage(files[i]);
                        }
                    }
                }
            });

            // Function to handle image uploads
            function uploadImage(file) {
                // Create form data
                const formData = new FormData();
                formData.append('image', file);
                
                // Send to server
                fetch('../upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Insert image into editor
                        const image = $('<img>').attr('src', data.url);
                        $('#template-content').summernote('insertNode', image[0]);
                    } else {
                        console.error('Image upload failed:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error uploading image:', error);
                });
            }

            // Confirm before deleting template
            $('form[action=""]').on('submit', function(e) {
                if ($(this).find('input[name="delete_template"]').length) {
                    if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>