<?php
require_once 'includes/config.php'; 

$message = ''; 
$error_message = '';
$employees = [];
$software_items = [];

// --- 1. Fetch Employee and Software Data for Dropdowns ---
try {
    // Fetch all employees
    $employees_stmt = $pdo->query('SELECT employee_id, name FROM employees ORDER BY name ASC');
    $employees = $employees_stmt->fetchAll();

    // Fetch all software items and calculate available licenses
    $sql_software = "
        SELECT 
            s.software_id, s.name, s.version, s.total_licenses,
            COALESCE(SUM(CASE WHEN sa.status = 'Active' THEN 1 ELSE 0 END), 0) AS licenses_in_use
        FROM 
            software_items s
        LEFT JOIN 
            software_assignments sa ON s.software_id = sa.software_id
        GROUP BY
            s.software_id, s.name, s.version, s.total_licenses
        ORDER BY 
            s.name ASC
    ";
    $software_stmt = $pdo->query($sql_software);
    $software_items = $software_stmt->fetchAll();

} catch (\PDOException $e) {
    $error_message = "Error loading initial data: " . htmlspecialchars($e->getMessage());
}


// --- 2. Handle License ASSIGNMENT (OUT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_license'])) {
    
    // Sanitize and collect data
    $software_id = filter_input(INPUT_POST, 'software_id', FILTER_SANITIZE_NUMBER_INT);
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_NUMBER_INT);
    $license_key = filter_input(INPUT_POST, 'license_key', FILTER_SANITIZE_STRING);
    $date_assigned = date('Y-m-d'); // Current date

    // Basic Validation
    if (empty($software_id) || empty($employee_id) || empty($license_key)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } else {
        try {
            // Check license availability (Crucial step)
            $selected_software = array_filter($software_items, fn($s) => $s['software_id'] == $software_id);
            $selected_software = reset($selected_software);

            if ($selected_software) {
                $available = $selected_software['total_licenses'] - $selected_software['licenses_in_use'];

                if ($available <= 0) {
                    $message = '<div class="alert alert-danger">Assignment Failed: No available licenses for ' . htmlspecialchars($selected_software['name']) . '.</div>';
                } else {
                    // Perform the assignment transaction
                    $sql = "INSERT INTO software_assignments (software_id, employee_id, license_key, status, date_assigned) VALUES (?, ?, ?, 'Active', ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$software_id, $employee_id, $license_key, $date_assigned]);

                    // Reload the page to reflect updated counts
                    header("Location: software_assignment.php?msg=" . urlencode("License assigned successfully!"));
                    exit;
                }
            } else {
                $message = '<div class="alert alert-danger">Invalid software selected.</div>';
            }

        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation (e.g., duplicate license key)
                $message = '<div class="alert alert-danger">Assignment Failed: This license key already exists or is assigned.</div>';
            } else {
                $message = '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// --- 3. Handle License REVOCATION (IN) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['revoke_license'])) {
    
    $assignment_id = filter_input(INPUT_POST, 'assignment_id', FILTER_SANITIZE_NUMBER_INT);

    try {
        // Update the status of the assignment to 'Revoked'
        $sql = "UPDATE software_assignments SET status = 'Revoked' WHERE assignment_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$assignment_id]);

        // Reload the page to reflect updated counts
        header("Location: software_assignment.php?msg=" . urlencode("License revoked successfully!"));
        exit;

    } catch (\PDOException $e) {
        $message = '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Check for successful message from a redirect
if (isset($_GET['msg'])) {
    $message = '<div class="alert alert-success">' . htmlspecialchars($_GET['msg']) . '</div>';
}

// --- 4. Fetch Active Assignments for Revocation List ---
$active_assignments = [];
try {
    $sql_active = "
        SELECT 
            sa.assignment_id, s.name AS software_name, s.license_type, 
            e.name AS employee_name, e.employee_id, sa.license_key, sa.date_assigned
        FROM 
            software_assignments sa
        JOIN 
            software_items s ON sa.software_id = s.software_id
        JOIN
            employees e ON sa.employee_id = e.employee_id
        WHERE
            sa.status = 'Active'
        ORDER BY
            sa.date_assigned DESC
    ";
    $active_assignments = $pdo->query($sql_active)->fetchAll();
} catch (\PDOException $e) {
    $error_message .= " | Error loading active assignments: " . htmlspecialchars($e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software License Management - IT AM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Base Layout Styles (consistent across all pages) */
        #sidebar-wrapper { 
            min-height: 100vh; 
            margin-left: -15rem; 
            transition: margin .25s ease-out; 
            background-color: #343a40; 
        }
        #sidebar-wrapper .sidebar-heading { 
            padding: 0.875rem 1.25rem; 
            font-size: 1.2rem; 
            color: #ffffff; 
        }
        #page-content-wrapper { 
            min-width: 100vw; 
        }
        .sidebar-nav a { 
            color: #adb5bd; 
            padding: 1rem 1.25rem; 
            display: block; 
            text-decoration: none; 
        }
        .sidebar-nav a:hover { 
            background-color: #495057; 
            color: #ffffff; 
        }
        .sidebar-nav a[href="software_assignment.php"] { 
            background-color: #0d6efd; 
            color: #ffffff; 
            border-left: 5px solid #ffc107; 
        } 
        @media (min-width: 768px) { 
            #sidebar-wrapper { 
                margin-left: 0; 
            } 
            #page-content-wrapper { 
                min-width: 0; 
                width: 100%; 
            } 
        }
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
            <h1 class="mt-4">🔑 Software License Management</h1>
            <p class="text-muted">Assign and revoke licenses to employees.</p>
            
            <?php 
                if (!empty($message)) { echo $message; }
                if (!empty($error_message)) { echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>'; }
            ?>

            <div class="card shadow mb-5">
                <div class="card-header bg-primary text-white fw-bold">Assign New License (License OUT)</div>
                <div class="card-body">
                    <form method="POST" action="software_assignment.php">
                        <input type="hidden" name="assign_license" value="1">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="software_id" class="form-label">Software Title</label>
                                <select class="form-select" id="software_id" name="software_id" required>
                                    <option value="">Select Software...</option>
                                    <?php foreach ($software_items as $software): 
                                        $available = $software['total_licenses'] - $software['licenses_in_use'];
                                        $disabled = $available <= 0 ? 'disabled' : '';
                                        $label = htmlspecialchars($software['name']) . ' (' . $available . ' available)';
                                    ?>
                                    <option value="<?php echo $software['software_id']; ?>" data-available="<?php echo $available; ?>" <?php echo $disabled; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-danger" id="availability-warning"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="employee_id" class="form-label">Assign To Employee</label>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee...</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>">
                                        <?php echo htmlspecialchars($employee['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="license_key" class="form-label">License Key / ID</label>
                                <input type="text" class="form-control" id="license_key" name="license_key" placeholder="Enter unique license key/code" required>
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success"><i class="bi bi-person-plus"></i> Assign License</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header bg-danger text-white fw-bold">Active License Assignments (License IN / Revoke)</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Software</th>
                                    <th>Employee</th>
                                    <th>Employee ID</th>
                                    <th>License Key / ID</th>
                                    <th>Date Assigned</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($active_assignments) > 0): ?>
                                    <?php foreach ($active_assignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['software_name'] . ' (' . $assignment['license_type'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['employee_id']); ?></td>
                                        <td><code><?php echo htmlspecialchars($assignment['license_key']); ?></code></td>
                                        <td><?php echo htmlspecialchars($assignment['date_assigned']); ?></td>
                                        <td class="text-center">
                                            <button 
                                                class="btn btn-sm btn-outline-danger revoke-btn"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#revokeModal"
                                                data-id="<?php echo htmlspecialchars($assignment['assignment_id']); ?>"
                                                data-name="<?php echo htmlspecialchars($assignment['software_name']); ?>"
                                                data-employee="<?php echo htmlspecialchars($assignment['employee_name']); ?>">
                                                <i class="bi bi-box-arrow-in-left"></i> Revoke
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No active software licenses are currently assigned.</td>
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

<div class="modal fade" id="revokeModal" tabindex="-1" aria-labelledby="revokeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="software_assignment.php">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="revokeModalLabel">Confirm License Revocation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="revoke_license" value="1">
            <input type="hidden" id="revoke_assignment_id" name="assignment_id">
            <p>Are you sure you want to revoke the **<span id="revoke_software_name" class="fw-bold"></span>** license from **<span id="revoke_employee_name" class="fw-bold"></span>**?</p>
            <p class="text-danger small">This action moves the license back to the available pool.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Yes, Revoke License</button>
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
    
    // Logic for the REVOKE modal to populate fields when opened
    var revokeModal = document.getElementById('revokeModal');
    revokeModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Button that triggered the modal
        var assignmentId = button.getAttribute('data-id');
        var softwareName = button.getAttribute('data-name');
        var employeeName = button.getAttribute('data-employee');
        
        // Populate form fields
        revokeModal.querySelector('#revoke_assignment_id').value = assignmentId;
        revokeModal.querySelector('#revoke_software_name').textContent = softwareName;
        revokeModal.querySelector('#revoke_employee_name').textContent = employeeName;
    });

    // Optional: Add warning if user tries to select software with 0 available licenses
    document.getElementById('software_id').addEventListener('change', function() {
        const select = this;
        const selectedOption = select.options[select.selectedIndex];
        const warningDiv = document.getElementById('availability-warning');

        if (selectedOption && selectedOption.hasAttribute('data-available')) {
            const available = parseInt(selectedOption.getAttribute('data-available'));
            if (available <= 0) {
                warningDiv.textContent = 'WARNING: No licenses available. Cannot assign this software.';
            } else {
                warningDiv.textContent = '';
            }
        } else {
            warningDiv.textContent = '';
        }
    });
</script>

</body>
</html>