<?php
require_once 'includes/config.php';

$employee_data = null;
$assigned_assets = [];
$employee_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (empty($employee_id)) {
    // Redirect or show error if no ID is provided
    header('Location: employees.php');
    exit;
}

try {
    // 1. Fetch Employee Details
    $sql_employee = "SELECT employee_id, name, department, position FROM employees WHERE employee_id = ?";
    $stmt_employee = $pdo->prepare($sql_employee);
    $stmt_employee->execute([$employee_id]);
    $employee_data = $stmt_employee->fetch();

    if (!$employee_data) {
        // Employee not found
        $error_message = "Employee not found.";
    } else {
        // 2. Fetch Assigned Assets
        $sql_assets = "
            SELECT 
                asset_id, fam_tag_number, device_type, device_name, serial_number, status
            FROM 
                assets
            WHERE 
                current_user_id = ?
            ORDER BY 
                device_type, fam_tag_number ASC
        ";
        $stmt_assets = $pdo->prepare($sql_assets);
        $stmt_assets->execute([$employee_id]);
        $assigned_assets = $stmt_assets->fetchAll();
    }

} catch (\PDOException $e) {
    $error_message = "Database Error: Could not retrieve data.";
    // In a real app, you would log the error: error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details | <?php echo $employee_data ? htmlspecialchars($employee_data['name']) : 'Error'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        /* ... (Include your standard sidebar CSS here or link a separate CSS file) ... */
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="employees.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } /* Active for the parent page */
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div class="border-end bg-dark" id="sidebar-wrapper">
        <div class="sidebar-heading">IT Inventory System</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a class="list-group-item list-group-item-action bg-dark" href="index.php">📊 Dashboard</a>
            <a class="list-group-item list-group-item-action bg-dark active" href="employees.php">🧑‍💻 Employees</a>
            <a class="list-group-item list-group-item-action bg-dark" href="inventory.php">📦 Inventory</a>
            <a class="list-group-item list-group-item-action bg-dark" href="transmittal.php">📝 Transmittal Log</a>
        </div>
    </div>
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-primary" id="sidebarToggle">Toggle Menu</button>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <a href="employees.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Employee List</a>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php elseif ($employee_data): ?>
                
                <h1 class="mt-4 mb-4">Employee Profile: <?php echo htmlspecialchars($employee_data['name']); ?></h1>

                <div class="card shadow-sm mb-5 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-person-circle"></i> Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3"><strong>Employee ID:</strong></div>
                            <div class="col-md-9"><?php echo htmlspecialchars($employee_data['employee_id']); ?></div>
                            <div class="col-md-3"><strong>Department:</strong></div>
                            <div class="col-md-9"><?php echo htmlspecialchars($employee_data['department']); ?></div>
                            <div class="col-md-3"><strong>Position:</strong></div>
                            <div class="col-md-9"><?php echo htmlspecialchars($employee_data['position']); ?></div>
                            <div class="col-md-3 mt-2"><strong>Total Assets:</strong></div>
                            <div class="col-md-9 mt-2"><span class="badge bg-success fs-6"><?php echo count($assigned_assets); ?></span></div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-lg">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="mb-0">Assets Assigned to <?php echo htmlspecialchars($employee_data['name']); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if (count($assigned_assets) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>FAM Tag</th>
                                            <th>Device Type</th>
                                            <th>Model</th>
                                            <th>Serial No.</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_assets as $asset): 
                                            // Determine badge class for status display
                                            $badge_class = 'bg-primary'; // Default for In Use
                                            if ($asset['status'] == 'Broken') { $badge_class = 'bg-danger'; }
                                            if ($asset['status'] == 'Repairing') { $badge_class = 'bg-info'; }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['device_type']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['device_name']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($asset['status']); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                This employee currently has no assets assigned.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle Sidebar
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
</script>

</body>
</html>