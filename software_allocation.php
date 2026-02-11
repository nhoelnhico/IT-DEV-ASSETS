<?php
require_once 'includes/config.php'; 

$message = ''; 
$allocations = [];
// Note: We use the existing Transmittal JS/CSS, so we include Select2.

// 1. Fetch data for dropdowns
try {
    $employees_stmt = $pdo->query('SELECT employee_id, name FROM employees ORDER BY name ASC');
    $employees = $employees_stmt->fetchAll();
    
    // Fetch all software licenses (Allocation/Revocation uses all, but the dropdown visualizes availability)
    $software_stmt = $pdo->query("
        SELECT 
            software_id, name, total_licenses, licenses_in_use 
        FROM 
            software_licenses 
        ORDER BY name ASC
    ");
    $all_software = $software_stmt->fetchAll();

    // Fetch all allocated and active software for the table
    $sql_allocations = "
        SELECT 
            es.allocation_id, es.date_allocated, es.status,
            s.name AS software_name,
            e.name AS employee_name, e.employee_id
        FROM 
            employee_software es
        JOIN 
            software_licenses s ON es.software_id = s.software_id
        JOIN 
            employees e ON es.employee_id = e.employee_id
        WHERE 
            es.status = 'Allocated'
        ORDER BY 
            es.date_allocated DESC";
    $allocations_stmt = $pdo->query($sql_allocations);
    $allocations = $allocations_stmt->fetchAll();

} catch (\PDOException $e) {
    die("Error fetching initial data: " . $e->getMessage());
}

// 2. Handle Form Submission (Allocate/Revoke)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_allocation'])) {
    
    $software_id = filter_input(INPUT_POST, 'software_id', FILTER_SANITIZE_NUMBER_INT);
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_NUMBER_INT);
    $action_type = filter_input(INPUT_POST, 'action_type', FILTER_SANITIZE_STRING); // 'ALLOCATE' or 'REVOKE'
    $software_name_hidden = filter_input(INPUT_POST, 'software_name_hidden', FILTER_SANITIZE_STRING);
    
    if (empty($software_id) || empty($employee_id) || !in_array($action_type, ['ALLOCATE', 'REVOKE'])) {
        $message = '<div class="alert alert-danger">All fields are required for the transaction.</div>';
    } else {
        try {
            $pdo->beginTransaction();

            if ($action_type === 'ALLOCATE') {
                // Check license availability
                $check_licenses = $pdo->prepare("SELECT total_licenses, licenses_in_use FROM software_licenses WHERE software_id = ?");
                $check_licenses->execute([$software_id]);
                $license_data = $check_licenses->fetch();
                
                if ($license_data && $license_data['licenses_in_use'] < $license_data['total_licenses']) {
                    // Check if employee already has an active license for this software (prevent duplicates)
                    $check_active = $pdo->prepare("SELECT COUNT(*) FROM employee_software WHERE software_id = ? AND employee_id = ? AND status = 'Allocated'");
                    $check_active->execute([$software_id, $employee_id]);
                    if ($check_active->fetchColumn() > 0) {
                        $message = '<div class="alert alert-warning">WARNING: Employee ID ' . htmlspecialchars($employee_id) . ' already has an active license for **' . htmlspecialchars($software_name_hidden) . '**.</div>';
                        $pdo->rollBack();
                    } else {
                        // 1. Insert new allocation record
                        $sql_insert = "INSERT INTO employee_software (software_id, employee_id, status) VALUES (?, ?, 'Allocated')";
                        $stmt_insert = $pdo->prepare($sql_insert);
                        $stmt_insert->execute([$software_id, $employee_id]);
                        
                        // 2. Update licenses_in_use count (INCREMENT)
                        $sql_update_count = "UPDATE software_licenses SET licenses_in_use = licenses_in_use + 1 WHERE software_id = ?";
                        $stmt_update_count = $pdo->prepare($sql_update_count);
                        $stmt_update_count->execute([$software_id]);

                        $message = '<div class="alert alert-success">Successfully **Allocated** a license for **' . htmlspecialchars($software_name_hidden) . '** to employee ID ' . htmlspecialchars($employee_id) . '.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">ERROR: No licenses available for **' . htmlspecialchars($software_name_hidden) . '**.</div>';
                    $pdo->rollBack();
                }

            } elseif ($action_type === 'REVOKE') {
                // Find the allocation record to revoke
                $sql_find_allocation = "SELECT allocation_id FROM employee_software WHERE software_id = ? AND employee_id = ? AND status = 'Allocated'";
                $stmt_find = $pdo->prepare($sql_find_allocation);
                $stmt_find->execute([$software_id, $employee_id]);
                $allocation_id = $stmt_find->fetchColumn();

                if ($allocation_id) {
                    // 1. Update the allocation record to 'Revoked'
                    $sql_update_status = "UPDATE employee_software SET status = 'Revoked', date_revoked = NOW() WHERE allocation_id = ?";
                    $stmt_update_status = $pdo->prepare($sql_update_status);
                    $stmt_update_status->execute([$allocation_id]);
                    
                    // 2. Update licenses_in_use count (DECREMENT)
                    $sql_update_count = "UPDATE software_licenses SET licenses_in_use = licenses_in_use - 1 WHERE software_id = ?";
                    $stmt_update_count = $pdo->prepare($sql_update_count);
                    $stmt_update_count->execute([$software_id]);

                    $message = '<div class="alert alert-warning">Successfully **Revoked** a license for **' . htmlspecialchars($software_name_hidden) . '** from employee ID ' . htmlspecialchars($employee_id) . '.</div>';
                } else {
                    $message = '<div class="alert alert-danger">ERROR: No active license found for this employee and software combination to revoke.</div>';
                    $pdo->rollBack();
                }
            }
            
            $pdo->commit();
            // Redirect to refresh the page and clear POST data
            header('Location: software_allocation.php?status_message=' . urlencode(strip_tags($message)));
            exit;

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">Transaction Error: Could not record allocation. ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Check for status message in URL after redirect
if (isset($_GET['status_message'])) {
    $message = '<div class="alert alert-success">' . htmlspecialchars($_GET['status_message']) . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Software Allocation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        /* ... (Existing CSS for sidebar, etc. remains here) ... */
        body { background-color: #f8f9fa; }
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="software_inventory.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } 
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div class="border-end bg-dark" id="sidebar-wrapper">
        <div class="sidebar-heading">IT Inventory System</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a class="list-group-item list-group-item-action bg-dark" href="index.php">📊 Dashboard</a>
            <a class="list-group-item list-group-item-action bg-dark" href="employees.php">🧑‍💻 Employees</a>
            <a class="list-group-item list-group-item-action bg-dark" href="inventory.php">📦 Inventory</a>
            <a class="list-group-item list-group-item-action bg-dark" href="software_inventory.php">💾 Software Inventory</a> 
<a class="list-group-item list-group-item-action bg-dark active" href="software_assignment.php">🔑 License Assignment</a>
            <a class="list-group-item list-group-item-action bg-dark" href="transmittal.php">📝 Transmittal Log</a>
            <a class="list-group-item list-group-item-action bg-dark" href="employee_clearance.php">📄 Clearance Form</a>
        </div>
    </div>
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-primary" id="sidebarToggle">Toggle Menu</button>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4">📝 Software License Allocation (Transmittal)</h1>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-5">
                <div class="card-header bg-success text-white fw-bold">Record License Allocation/Revocation</div>
                <div class="card-body">
                    <form method="POST" action="software_allocation.php" id="allocation_form">
                        <input type="hidden" name="record_allocation" value="1"> 
                        <input type="hidden" name="software_name_hidden" id="software_name_hidden"> 

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="action_type" class="form-label">Action Type</label>
                                <select class="form-select" id="action_type" name="action_type" required>
                                    <option value="">Select Action...</option>
                                    <option value="ALLOCATE">Allocate License (OUT)</option>
                                    <option value="REVOKE">Revoke License (IN)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="software_id" class="form-label">Software License</label>
                                <select class="form-select select2-enabled" id="software_id" name="software_id" required>
                                    <option value="">Select Software...</option>
                                    <?php foreach ($all_software as $software): 
                                        $available_count = $software['total_licenses'] - $software['licenses_in_use'];
                                        $label = htmlspecialchars($software['name']) . 
                                                 ' (Avail: ' . $available_count . 
                                                 '/' . $software['total_licenses'] . ')';
                                    ?>
                                    <option 
                                        value="<?php echo $software['software_id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($software['name']); ?>"
                                        data-available="<?php echo $available_count; ?>"
                                    >
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted" id="license_tip">Select **ALLOCATE** to see only licenses with remaining availability.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="employee_id" class="form-label">Employee Name or ID</label>
                                <select class="form-select select2-enabled" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee...</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>">
                                        <?php echo htmlspecialchars($emp['name']) . ' (' . htmlspecialchars($emp['employee_id']) . ')'; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-journal-check"></i> Record Allocation/Revocation</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">Active Software Allocations (Licenses In Use)</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Software Name</th>
                                    <th>Employee ID</th>
                                    <th>Employee Name</th>
                                    <th>Date Allocated</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($allocations)): ?>
                                    <?php foreach ($allocations as $allocation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($allocation['allocation_id']); ?></td>
                                        <td><?php echo htmlspecialchars($allocation['software_name']); ?></td>
                                        <td><?php echo htmlspecialchars($allocation['employee_id']); ?></td>
                                        <td><?php echo htmlspecialchars($allocation['employee_name']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($allocation['date_allocated'])); ?></td>
                                        <td><span class="badge bg-primary">Allocated</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No active software licenses are currently allocated.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2 for better search/selection
        $('.select2-enabled').select2({
            theme: "bootstrap-5",
            width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
            placeholder: $(this).data('placeholder'),
            allowClear: true,
        });

        // Toggle Sidebar
        document.getElementById("sidebarToggle").addEventListener("click", function() {
            $("#wrapper").toggleClass("toggled");
        });

        // Function to filter software dropdown based on action type
        function filterSoftwareDropdown() {
            const actionType = $('#action_type').val();
            const softwareSelect = $('#software_id');
            const licenseTip = $('#license_tip');

            softwareSelect.find('option').each(function() {
                // Skip the placeholder option
                if ($(this).val() === '') return;

                const availableCount = parseInt($(this).data('available'));
                const optionElement = $(this);

                if (actionType === 'ALLOCATE') {
                    // Show only if available count is > 0
                    if (availableCount > 0) {
                        optionElement.show();
                    } else {
                        optionElement.hide();
                    }
                    licenseTip.text('Only licenses with remaining availability are shown for ALLOCATE.');
                } else if (actionType === 'REVOKE') {
                    // Show all software for revocation (you must manually select the employee)
                    optionElement.show();
                    licenseTip.text('All software is shown for REVOKE. Ensure the employee has an active license for the selected software.');
                } else {
                    // Default state
                    optionElement.show();
                    licenseTip.text('Select an Action Type first (Allocate or Revoke).');
                }
            });

            // Re-render Select2
            softwareSelect.select2('destroy');
            softwareSelect.select2({
                theme: "bootstrap-5",
                width: '100%',
                placeholder: 'Select Software...'
            });
        }

        // Event listener for action type change
        $('#action_type').on('change', filterSoftwareDropdown);
        
        // Logic to update hidden software name field for POST
        $('#software_id').on('change', function() {
            var selectedOption = $('#software_id option:selected');
            var softwareName = selectedOption.data('name');
            $('#software_name_hidden').val(softwareName);
        });

        // Initial filtering/setup
        filterSoftwareDropdown();
        $('#software_id').trigger('change');
    });
</script>

</body>
</html>