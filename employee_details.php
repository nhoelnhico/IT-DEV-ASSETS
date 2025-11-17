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

// UPDATED: Use FILTER_SANITIZE_STRING
$employee_id = filter_input(INPUT_GET, 'id', **FILTER_SANITIZE_STRING**);

if (empty($employee_id) && (!isset($_POST['update_employee']) && !isset($_POST['delete_employee']))) {
    // Redirect if no ID in GET, and not processing an update or delete
    header('Location: employees.php');
    exit;
}


// --- 1. HANDLE EMPLOYEE UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_employee'])) {
    // UPDATED: Use FILTER_SANITIZE_STRING
    $employee_id = filter_input(INPUT_POST, 'edit_employee_id', **FILTER_SANITIZE_STRING**);
    $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'edit_department', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'edit_position', FILTER_SANITIZE_STRING);

    if (empty($employee_id) || empty($name) || empty($department) || empty($position)) {
        $error_message = 'Please ensure all fields are filled out for the update.';
    } else {
        try {
            $sql = "UPDATE employees SET name = ?, department = ?, position = ? WHERE employee_id = ?";
            $stmt = $pdo->prepare($sql);
            // $employee_id is the key, kept constant in the form
            $stmt->execute([$name, $department, $position, $employee_id]);
            $success_message = "Employee details updated successfully!";

            // Refresh data after successful update
            // Since the ID in GET might be old if the ID itself was changed (though discouraged), 
            // we use the ID from the POST data for fetching the latest data.
            $_GET['id'] = $employee_id; 

        } catch (\PDOException $e) {
            $error_message = "Database Error: Could not update employee. " . $e->getMessage();
        }
    }
}


// --- 2. HANDLE EMPLOYEE DELETE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_employee'])) {
    // UPDATED: Use FILTER_SANITIZE_STRING
    $employee_id_to_delete = filter_input(INPUT_POST, 'delete_employee_id', **FILTER_SANITIZE_STRING**);
    
    try {
        $pdo->beginTransaction();

        // Check for assigned assets (a business logic check, not strictly a DB constraint violation)
        $asset_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE current_user_id = ?");
        $asset_count_stmt->execute([$employee_id_to_delete]);
        $asset_count = $asset_count_stmt->fetchColumn();

        if ($asset_count > 0) {
            // If assets are assigned, prevent deletion
            throw new Exception("This employee has **{$asset_count}** assigned asset(s) and cannot be deleted. Please clear their assigned assets first.");
        }

        // Delete the employee
        $sql_delete = "DELETE FROM employees WHERE employee_id = ?";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute([$employee_id_to_delete]);

        $pdo->commit();
        
        // Redirect to employee list with success message
        $message_text = urlencode("Employee ID **" . htmlspecialchars($employee_id_to_delete) . "** successfully deleted.");
        header("Location: employees.php?message={$message_text}");
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
        // If it was a real DB error (like a strict foreign key violation if asset check was bypassed)
        $error_message = "Database Error: Could not delete employee. " . $e->getMessage();
    }
}

// --- 3. Fetch Employee Details and Assigned Assets ---

// Use the ID from GET or the successful POST (via $_GET['id'] update)
// UPDATED: Use FILTER_SANITIZE_STRING
$current_id_to_fetch = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', **FILTER_SANITIZE_STRING**) : null;

if ($current_id_to_fetch) {
    try {
        // Fetch Employee Details
        $sql_employee = "SELECT employee_id, name, department, position FROM employees WHERE employee_id = ?";
        $stmt_employee = $pdo->prepare($sql_employee);
        $stmt_employee->execute([$current_id_to_fetch]);
        $employee_data = $stmt_employee->fetch();

        // Fetch Assigned Assets
        if ($employee_data) {
            $sql_assets = "SELECT asset_id, fam_tag_number, device_name, serial_number, status FROM assets WHERE current_user_id = ?";
            $stmt_assets = $pdo->prepare($sql_assets);
            $stmt_assets->execute([$current_id_to_fetch]);
            $assigned_assets = $stmt_assets->fetchAll();
        }

    } catch (\PDOException $e) {
        $error_message = "Error fetching data: " . $e->getMessage();
    }
}

// Redirect if no employee data found and we aren't processing a form submission
if (!$employee_data && empty($error_message) && !isset($_POST['update_employee']) && !isset($_POST['delete_employee'])) {
    $error_message = "No employee found with ID: **" . htmlspecialchars($current_id_to_fetch) . "**";
    // Optional: Redirect back to employees list after showing error
    // header('Location: employees.php?error=' . urlencode($error_message)); exit;
}


$pageTitle = $employee_data ? htmlspecialchars($employee_data['name']) . ' Details' : 'Employee Details';
include 'includes/header.php';
?>

<div id="wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div id="page-content-wrapper">
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <a href="employees.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Employees</a>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($employee_data): ?>
                <h1 class="mt-4 mb-4 text-white">Employee Details: <?php echo htmlspecialchars($employee_data['name']); ?></h1>

                <div class="card shadow mb-4 bg-dark text-white">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <h5 class="m-0 font-weight-bold">Employee Information</h5>
                        <div class="d-flex">
                            <button class="btn btn-outline-light me-2" id="editButton">Edit Details</button>
                            <form id="deleteForm" method="POST" style="display: none;">
                                <input type="hidden" name="delete_employee" value="1">
                                <input type="hidden" name="delete_employee_id" value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>">
                            </form>
                            <button class="btn btn-danger" id="deleteButton"><i class="fas fa-trash-alt"></i> Delete</button>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div id="viewMode">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee_data['employee_id']); ?></p>
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($employee_data['name']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($employee_data['department']); ?></p>
                                    <p><strong>Position:</strong> <?php echo htmlspecialchars($employee_data['position']); ?></p>
                                </div>
                            </div>
                        </div>

                        <div id="editMode" style="display: none;">
                            <form method="POST">
                                <input type="hidden" name="update_employee" value="1">
                                <div class="mb-3">
                                    <label for="edit_employee_id" class="form-label">Employee ID</label>
                                    <input type="text" class="form-control" id="edit_employee_id" name="edit_employee_id" value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>" readonly>
                                    <small class="form-text text-muted">Employee ID cannot be changed directly.</small>
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
                                <button type="submit" class="btn btn-success me-2">Save Changes</button>
                                <button type="button" class="btn btn-secondary" id="cancelEditButton">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 bg-dark text-white">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="m-0 font-weight-bold">Assigned Assets (<?php echo count($assigned_assets); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <?php if (!empty($assigned_assets)): ?>
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>FAM Tag Number</th>
                                            <th>Device Name</th>
                                            <th>Serial Number</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_assets as $asset): ?>
                                        <tr onclick="window.location='inventory.php?search=<?php echo urlencode($asset['fam_tag_number']); ?>'" style="cursor: pointer;">
                                            <td><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['device_name']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                            <td><span class="badge <?php echo $asset['status'] == 'In Use' ? 'bg-primary' : ($asset['status'] == 'Available' ? 'bg-success' : 'bg-danger'); ?>"><?php echo htmlspecialchars($asset['status']); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted text-center">No assets currently assigned to this employee.</p>
                                <p class="text-center"><a href="transmittal.php" class="btn btn-sm btn-outline-success">Assign Asset</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="alert alert-danger">Error: Employee details could not be retrieved.</div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editButton = document.getElementById('editButton');
    const deleteButton = document.getElementById('deleteButton');
    const cancelButton = document.getElementById('cancelEditButton');
    const deleteForm = document.getElementById('deleteForm');

    // Edit Mode Toggle
    if(editButton) {
        editButton.addEventListener('click', () => {
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
            editButton.textContent = 'Editing...';
            editButton.classList.remove('btn-outline-light');
            editButton.classList.add('btn-light', 'text-dark');
        });
    }

    if(cancelButton) {
        cancelButton.addEventListener('click', () => {
            viewMode.style.display = 'block';
            editMode.style.display = 'none';
            editButton.textContent = 'Edit Details';
            editButton.classList.remove('btn-light', 'text-dark');
            editButton.classList.add('btn-outline-light');
        });
    }
    
    // Delete Confirmation
    if(deleteButton) {
        deleteButton.addEventListener('click', (e) => {
            const employeeName = '<?php echo $employee_data ? htmlspecialchars(addslashes($employee_data['name'])) : ''; ?>';
            if (confirm(`ARE YOU SURE you want to permanently delete the employee: ${employeeName}? This action cannot be undone.`)) {
                // Submit the hidden delete form
                deleteForm.submit();
            }
        });
    }

    // Sidebar toggle (Assuming the logic is in your included script or you need it here)
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
</script>

</body>
</html>