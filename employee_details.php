<?php

require_once 'includes/config.php';

$employee_data = null;
$assigned_assets = [];
$error_message = '';
$success_message = '';

// Check if a success message was passed via URL parameter (e.g., after deletion)
if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}

$employee_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (empty($employee_id) && (!isset($_POST['update_employee']) && !isset($_POST['delete_employee']))) {
    // Redirect if no ID in GET, and not processing an update or delete
    header('Location: employees.php');
    exit;
}


// --- 1. HANDLE EMPLOYEE UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_employee'])) {
    $employee_id = filter_input(INPUT_POST, 'edit_employee_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'edit_department', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'edit_position', FILTER_SANITIZE_STRING);

    if (empty($employee_id) || empty($name) || empty($department) || empty($position)) {
        $error_message = 'All fields are required for the update.';
    } else {
        try {
            $sql = "UPDATE employees SET name = ?, department = ?, position = ? WHERE employee_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $department, $position, $employee_id]);
            $success_message = "Employee details updated successfully!";
        } catch (\PDOException $e) {
            $error_message = "Database Error: Could not update employee details.";
        }
    }
    // Set the GET ID back to the updated employee ID to reload the page with the latest data
    $_GET['id'] = $employee_id; 
}


// --- 2. HANDLE EMPLOYEE DELETION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_employee'])) {
    // We must use the ID from the POST data for deletion
    $employee_id_to_delete = filter_input(INPUT_POST, 'delete_employee_id', FILTER_SANITIZE_NUMBER_INT);
    $employee_name_to_delete = filter_input(INPUT_POST, 'delete_employee_name', FILTER_SANITIZE_STRING);

    if (empty($employee_id_to_delete)) {
        $error_message = 'Invalid employee ID provided for deletion.';
    } else {
        $pdo->beginTransaction();
        try {
            // CRITICAL CHECK: Count assigned assets
            $sql_check = "SELECT COUNT(asset_id) FROM assets WHERE current_user_id = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$employee_id_to_delete]);
            $asset_count = $stmt_check->fetchColumn();

            if ($asset_count > 0) {
                // Rollback the transaction to prevent delete
                $pdo->rollBack();
                // Throw an exception to be caught below
                throw new Exception("Cannot delete employee **{$employee_name_to_delete}**. They currently have **{$asset_count}** asset(s) assigned. Please use the Transmittal Log to return all assets to Inventory (0) first.");
            }

            // If no assets, proceed with deletion
            $sql_delete = "DELETE FROM employees WHERE employee_id = ?";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute([$employee_id_to_delete]);

            $pdo->commit();

            // Redirect to the employee list page upon success
            header('Location: employees.php?message=' . urlencode('Employee ' . $employee_name_to_delete . ' was successfully deleted.'));
            exit;

        } catch (Exception $e) {
            // If the error was from our check (e.g., assets assigned)
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'Deletion Failed: ' . $e->getMessage();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Database Error: Could not delete employee.";
        }
    }
    // If deletion fails, we set the ID back to attempt to load the details page
    $_GET['id'] = $employee_id_to_delete;
}


// --- 3. FETCH EMPLOYEE AND ASSET DATA ---
// Use the ID from GET (or the ID used in the update/failed delete attempt)
$current_id_to_fetch = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) : null;

if ($current_id_to_fetch) {
    try {
        // Fetch Employee Details
        $sql_employee = "SELECT employee_id, name, department, position FROM employees WHERE employee_id = ?";
        $stmt_employee = $pdo->prepare($sql_employee);
        $stmt_employee->execute([$current_id_to_fetch]);
        $employee_data = $stmt_employee->fetch();

        if (!$employee_data) {
            $error_message = "Employee not found.";
        } else {
            // Fetch Assigned Assets
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
            $stmt_assets->execute([$current_id_to_fetch]);
            $assigned_assets = $stmt_assets->fetchAll();
        }

    } catch (\PDOException $e) {
        $error_message = "Database Error: Could not retrieve data.";
    }
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
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="employees.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } 
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
            <a class="list-group-item list-group-item-action bg-dark active" href="employee_clearance.php">📄 Clearance Form</a>
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

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($employee_data): ?>
                
                <h1 class="mt-4 mb-4">Employee Profile: <?php echo htmlspecialchars($employee_data['name']); ?></h1>

                <div class="card shadow-sm mb-5 border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="bi bi-person-vcard"></i> Details</h4>
                        <div>
                            <button class="btn btn-sm btn-outline-light me-2" id="editButton"><i class="bi bi-pencil"></i> Edit Details</button>
                            <button class="btn btn-sm btn-danger" id="deleteButton"><i class="bi bi-trash"></i> Delete Employee</button>
                        </div>
                    </div>
                    
                    <div class="card-body" id="viewMode">
                        <div class="row">
                            <div class="col-md-3"><strong>Employee ID:</strong></div>
                            <div class="col-md-9"><?php echo htmlspecialchars($employee_data['employee_id']); ?></div>
                            <div class="col-md-3"><strong>Name:</strong></div>
                            <div class="col-md-9"><?php echo htmlspecialchars($employee_data['name']); ?></div>
                            <div class="col-md-3"><strong>Department:</strong></div>
                            <div class="col-md-9"><?php echo htmlspecialchars($employee_data['department']); ?></div>
                            <div class="col-md-3"><strong>Position:</strong></div>
                            <div class="col-md-9"><?php echo htmlspecialchars($employee_data['position']); ?></div>
                            <div class="col-md-3 mt-2"><strong>Total Assets:</strong></div>
                            <div class="col-md-9 mt-2"><span class="badge bg-success fs-6"><?php echo count($assigned_assets); ?></span></div>
                        </div>
                    </div>

                    <div class="card-body" id="editMode" style="display: none;">
                        <form method="POST" action="employee_details.php">
                            <input type="hidden" name="update_employee" value="1">
                            <input type="hidden" name="edit_employee_id" value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>">

                            <div class="mb-3">
                                <label for="edit_id" class="form-label">Employee ID (Not Editable)</label>
                                <input type="text" class="form-control" id="edit_id" value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="edit_name" name="edit_name" value="<?php echo htmlspecialchars($employee_data['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="edit_department" name="edit_department" value="<?php echo htmlspecialchars($employee_data['department']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_position" class="form-label">Position</label>
                                <input type="text" class="form-control" id="edit_position" name="edit_position" value="<?php echo htmlspecialchars($employee_data['position']); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Save Changes</button>
                            <button type="button" class="btn btn-secondary" id="cancelEditButton">Cancel</button>
                        </form>
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
                                            $badge_class = 'bg-primary'; 
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
                
                <form id="deleteForm" method="POST" action="employee_details.php" style="display: none;">
                    <input type="hidden" name="delete_employee" value="1">
                    <input type="hidden" name="delete_employee_id" value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>">
                    <input type="hidden" name="delete_employee_name" value="<?php echo htmlspecialchars($employee_data['name']); ?>">
                </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
    
    // Toggle between View and Edit modes for employee details
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editButton = document.getElementById('editButton');
    const deleteButton = document.getElementById('deleteButton');
    const cancelButton = document.getElementById('cancelEditButton');
    const deleteForm = document.getElementById('deleteForm');

    // Edit Mode Toggle
    editButton.addEventListener('click', () => {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
        editButton.textContent = 'Editing...';
        editButton.classList.remove('btn-outline-light');
        editButton.classList.add('btn-light', 'text-dark');
    });

    cancelButton.addEventListener('click', () => {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
        editButton.textContent = 'Edit Details';
        editButton.classList.remove('btn-light', 'text-dark');
        editButton.classList.add('btn-outline-light');
    });
    
    // Delete Confirmation
    deleteButton.addEventListener('click', (e) => {
        const employeeName = '<?php echo $employee_data ? htmlspecialchars(addslashes($employee_data['name'])) : ''; ?>';
        if (confirm(`ARE YOU SURE you want to permanently delete the employee: ${employeeName}? This action cannot be undone.`)) {
            // Submit the hidden delete form
            deleteForm.submit();
        }
    });
</script>

</body>
</html>