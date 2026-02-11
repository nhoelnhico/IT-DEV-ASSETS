<?php
require_once 'includes/config.php';

$employee_data = null;
$assigned_assets = [];
$assigned_software = []; // Added to hold software
$error_message = '';
$success_message = '';

// Check for success message from redirects
if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}

$employee_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Redirect if no ID provided and not a POST action
if (empty($employee_id) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: employees.php');
    exit;
}

// --- 1. HANDLE UPDATE EMPLOYEE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_employee'])) {
    $id = filter_input(INPUT_POST, 'edit_employee_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
    $dept = filter_input(INPUT_POST, 'edit_department', FILTER_SANITIZE_STRING);
    $pos = filter_input(INPUT_POST, 'edit_position', FILTER_SANITIZE_STRING);

    if (empty($id) || empty($name) || empty($dept) || empty($pos)) {
        $error_message = 'All fields are required.';
    } else {
        try {
            $sql = "UPDATE employees SET name = ?, department = ?, position = ? WHERE employee_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $dept, $pos, $id]);
            $success_message = "Profile updated successfully.";
            // Refresh ID in case it was passed
            $employee_id = $id; 
        } catch (\PDOException $e) {
            $error_message = "Database Error: Could not update profile.";
        }
    }
}

// --- 2. HANDLE DELETE EMPLOYEE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_employee'])) {
    $del_id = filter_input(INPUT_POST, 'delete_employee_id', FILTER_SANITIZE_NUMBER_INT);
    $del_name = filter_input(INPUT_POST, 'delete_employee_name', FILTER_SANITIZE_STRING);

    try {
        // Check for assigned assets first
        $check_hw = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE current_user_id = ?");
        $check_hw->execute([$del_id]);
        $hw_count = $check_hw->fetchColumn();

        $check_sw = $pdo->prepare("SELECT COUNT(*) FROM software_assignments WHERE employee_id = ? AND status = 'Active'");
        $check_sw->execute([$del_id]);
        $sw_count = $check_sw->fetchColumn();

        if ($hw_count > 0 || $sw_count > 0) {
            $error_message = "Cannot delete <strong>$del_name</strong>. They still have <strong>$hw_count</strong> hardware assets and <strong>$sw_count</strong> active software licenses assigned.";
            $employee_id = $del_id; // Keep on page
        } else {
            $del_stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
            $del_stmt->execute([$del_id]);
            header('Location: employees.php?message=' . urlencode("Employee $del_name deleted successfully."));
            exit;
        }
    } catch (\PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
        $employee_id = $del_id;
    }
}

// --- 3. FETCH DATA ---
if ($employee_id) {
    try {
        // Employee Info
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $employee_data = $stmt->fetch();

        if ($employee_data) {
            // Hardware Assets
            $stmt_hw = $pdo->prepare("SELECT * FROM assets WHERE current_user_id = ? ORDER BY device_type");
            $stmt_hw->execute([$employee_id]);
            $assigned_assets = $stmt_hw->fetchAll();

            // Software Assets (NEW)
            $stmt_sw = $pdo->prepare("
                SELECT sa.*, s.name, s.version 
                FROM software_assignments sa 
                JOIN software_items s ON sa.software_id = s.software_id 
                WHERE sa.employee_id = ? AND sa.status = 'Active'
                ORDER BY s.name
            ");
            $stmt_sw->execute([$employee_id]);
            $assigned_software = $stmt_sw->fetchAll();
        }
    } catch (\PDOException $e) {
        $error_message = "Error loading profile data.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | <?php echo $employee_data ? htmlspecialchars($employee_data['name']) : 'Error'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-sidebar: #2c3e50;
            --light-bg: #f3f4f6;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #5a5c69;
        }

        /* Sidebar */
        #sidebar-wrapper {
            min-height: 100vh;
            margin-left: -15rem;
            transition: margin .25s ease-out;
            background-color: var(--dark-sidebar);
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        #sidebar-wrapper .sidebar-heading {
            padding: 1.5rem 1.25rem;
            font-size: 1.4rem;
            font-weight: bold;
            color: #ecf0f1;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-nav a {
            color: #bdc3c7;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .sidebar-nav a i { margin-right: 10px; font-size: 1.1rem; }
        .sidebar-nav a:hover { background-color: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-nav a.active { background-color: rgba(255,255,255,0.1); color: #fff; border-left: 4px solid var(--info-color); }
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }

        /* Card Styles */
        .content-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            background: white;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .content-card .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Profile Header */
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 20px;
        }

        /* Table */
        .table-custom { margin-bottom: 0; }
        .table-custom thead th {
            background-color: #f8f9fc;
            color: #858796;
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: 700;
            border-top: none;
            padding: 1rem;
        }
        .table-custom tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #e3e6f0; }

        /* Badges */
        .badge-soft-primary { background-color: rgba(78, 115, 223, 0.1); color: var(--primary-color); padding: 0.5em 0.8em; border-radius: 0.35rem; }
        .badge-soft-danger { background-color: rgba(231, 74, 59, 0.1); color: var(--danger-color); padding: 0.5em 0.8em; border-radius: 0.35rem; }
        .badge-soft-info { background-color: rgba(54, 185, 204, 0.1); color: var(--info-color); padding: 0.5em 0.8em; border-radius: 0.35rem; }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div id="sidebar-wrapper">
        <div class="sidebar-heading">IT Asset Manager</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="employees.php" class="active"><i class="bi bi-people"></i> Employees</a>
            <a href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a>
            <a href="software_inventory.php"><i class="bi bi-disc"></i> Software</a> 
            <a href="software_assignment.php"><i class="bi bi-key"></i> Licenses</a>
            <a href="transmittal.php"><i class="bi bi-arrow-left-right"></i> Transmittals</a>
            <a href="employee_clearance.php"><i class="bi bi-file-earmark-check"></i> Clearance</a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i> Menu</button>
            <div class="ms-auto text-secondary small fw-bold">Employee Profile</div>
        </nav>

        <div class="container-fluid p-4">
            
            <a href="employees.php" class="btn btn-sm btn-outline-secondary mb-3 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>

            <?php 
                if ($error_message) echo '<div class="alert alert-danger shadow-sm border-0">' . $error_message . '</div>';
                if ($success_message) echo '<div class="alert alert-success shadow-sm border-0">' . $success_message . '</div>';
            ?>

            <?php if ($employee_data): 
                $initials = strtoupper(substr($employee_data['name'], 0, 1));
            ?>
            
            <div class="content-card">
                <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center">
                        <div class="profile-avatar shadow-sm">
                            <?php echo $initials; ?>
                        </div>
                        <div>
                            <h2 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($employee_data['name']); ?></h2>
                            <div class="text-secondary mb-1">
                                <i class="bi bi-card-heading me-1"></i> ID: <?php echo htmlspecialchars($employee_data['employee_id']); ?>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark border me-2"><?php echo htmlspecialchars($employee_data['department']); ?></span>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($employee_data['position']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#editModal">
                            <i class="bi bi-pencil-square me-2"></i> Edit Profile
                        </button>
                        <button class="btn btn-outline-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash me-2"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="content-card h-100">
                        <div class="card-header">
                            <span><i class="bi bi-pc-display me-2"></i> Assigned Hardware (<?php echo count($assigned_assets); ?>)</span>
                            <a href="transmittal.php" class="btn btn-sm btn-light text-primary">Log Movement</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Asset Tag</th>
                                            <th>Device</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_assets as $asset): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($asset['device_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($asset['device_type']); ?></div>
                                            </td>
                                            <td>
                                                <?php if ($asset['status'] == 'Broken'): ?>
                                                    <span class="badge badge-soft-danger">Broken</span>
                                                <?php else: ?>
                                                    <span class="badge badge-soft-primary">In Use</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($assigned_assets)): ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">No hardware assigned.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="content-card h-100">
                        <div class="card-header">
                            <span><i class="bi bi-microsoft me-2"></i> Assigned Software (<?php echo count($assigned_software); ?>)</span>
                            <a href="software_assignment.php" class="btn btn-sm btn-light text-primary">Manage Licenses</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Software</th>
                                            <th>License Key</th>
                                            <th>Assigned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_software as $sw): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($sw['name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($sw['version']); ?></div>
                                            </td>
                                            <td>
                                                <code class="text-secondary bg-light px-2 py-1 rounded small">
                                                    <?php echo $sw['license_key'] ? htmlspecialchars($sw['license_key']) : 'N/A'; ?>
                                                </code>
                                            </td>
                                            <td class="text-secondary small"><?php echo date('M d, Y', strtotime($sw['date_assigned'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($assigned_software)): ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">No software assigned.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title fw-bold">Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="employee_details.php?id=<?php echo $employee_id; ?>">
                <div class="modal-body p-4">
                    <input type="hidden" name="update_employee" value="1">
                    <input type="hidden" name="edit_employee_id" value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>" disabled>
                        <div class="form-text">ID cannot be changed.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="edit_name" value="<?php echo htmlspecialchars($employee_data['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-control" name="edit_department" value="<?php echo htmlspecialchars($employee_data['department']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="edit_position" value="<?php echo htmlspecialchars($employee_data['position']); ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 bg-danger text-white">
                <h5 class="modal-title fw-bold">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="employee_details.php?id=<?php echo $employee_id; ?>">
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="delete_employee" value="1">
                    <input type="hidden" name="delete_employee_id" value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>">
                    <input type="hidden" name="delete_employee_name" value="<?php echo htmlspecialchars($employee_data['name']); ?>">
                    
                    <i class="bi bi-person-x-fill text-danger display-4 mb-3"></i>
                    <p>Permanently delete profile for <br><strong><?php echo htmlspecialchars($employee_data['name']); ?></strong>?</p>
                    <p class="small text-muted">This action is irreversible.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger shadow-sm">Delete Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
</script>

</body>
</html>