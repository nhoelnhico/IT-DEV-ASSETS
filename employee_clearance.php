<?php

require_once 'includes/config.php';

$employee_data = null;
$assigned_assets = [];
$employee_id = '';
$employees_list = [];

// Fetch list of all employees for the dropdown/search suggestions
try {
    $employees_stmt = $pdo->query('SELECT employee_id, name FROM employees ORDER BY name ASC');
    $employees_list = $employees_stmt->fetchAll();
} catch (\PDOException $e) {
    // Handle error quietly
}

// Check for selected employee ID from the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['select_employee'])) {
    // UPDATED: Use FILTER_SANITIZE_STRING
    $employee_id = filter_input(INPUT_POST, 'employee_id', **FILTER_SANITIZE_STRING**);
} elseif (isset($_GET['id'])) {
    // Allows direct linking/testing with ?id=123
    // UPDATED: Use FILTER_SANITIZE_STRING
    $employee_id = filter_input(INPUT_GET, 'id', **FILTER_SANITIZE_STRING**);
}

if (!empty($employee_id)) {
    try {
        // 1. Fetch Employee Details
        $sql_employee = "SELECT employee_id, name, department, position FROM employees WHERE employee_id = ?";
        $stmt_employee = $pdo->prepare($sql_employee);
        $stmt_employee->execute([$employee_id]);
        $employee_data = $stmt_employee->fetch();

        if ($employee_data) {
            // 2. Fetch Assigned Assets
            // The current_user_id column is now VARCHAR, which is compatible
            $sql_assets = "SELECT fam_tag_number, device_name, serial_number FROM assets WHERE current_user_id = ?";
            $stmt_assets = $pdo->prepare($sql_assets);
            $stmt_assets->execute([$employee_id]);
            $assigned_assets = $stmt_assets->fetchAll();
        }

    } catch (\PDOException $e) {
        // Handle database error
        $employee_data = null; 
        // You might want to display this error, but for now, we just ensure $employee_data is null
    }
}

$pageTitle = "Employee Clearance";
include 'includes/header.php';
?>

<div id="wrapper" class="toggled">
    <?php include 'includes/sidebar.php'; ?>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
            <div class="container-fluid">
                </div>
        </nav>
        
        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4">Employee Asset Clearance Form</h1>

            <div class="card shadow mb-4 bg-dark text-white">
                <div class="card-header bg-secondary text-white">
                    <h5 class="m-0 font-weight-bold">Select Employee for Clearance</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="select_employee" value="1">
                        <div class="col-md-9">
                            <label for="employee_id" class="form-label">Employee ID</label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee...</option>
                                <?php foreach ($employees_list as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>" <?php echo $employee_id == $emp['employee_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['name']) . ' (' . htmlspecialchars($emp['employee_id']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($employee_data): ?>
                <div class="card shadow mb-4 clearance-report bg-light text-dark">
                    <div class="card-body">
                        <div class="row mb-5">
                            <div class="col-12 text-center">
                                <h2>IT ASSET CLEARANCE REPORT</h2>
                                <hr>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Employee Name:</strong> <?php echo htmlspecialchars($employee_data['name']); ?></p>
                                <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee_data['employee_id']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($employee_data['department']); ?></p>
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($employee_data['position']); ?></p>
                            </div>
                            <div class="col-12 mt-3">
                                <p><strong>Clearance Date:</strong> ____________________________________</p>
                            </div>
                        </div>
                        
                        <h4 class="mb-3">Assigned Assets Checklist (<?php echo count($assigned_assets); ?> items)</h4>
                        
                        <?php if (!empty($assigned_assets)): ?>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>FAM Tag Number</th>
                                        <th>Device Name</th>
                                        <th>Serial Number</th>
                                        <th class="text-center" style="width: 100px;">Returned</th>
                                        <th class="text-center" style="width: 100px;">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_assets as $asset): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['device_name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="text-danger small">NOTE: All listed assets **MUST** be returned or accounted for before final clearance is granted.</p>
                        <?php else: ?>
                            <div class="alert alert-success text-center">
                                **Employee has no assets currently assigned.** Clearance can proceed immediately.
                            </div>
                        <?php endif; ?>

                        <h4 class="mt-5 mb-3">Clearance Signatures</h4>
                        <div class="row text-center mt-4">
                            <div class="col-lg-4 col-md-12">
                                <div class="signature-line"></div>
                                <div class="signature-box">Employee Signature</div>
                                <small class="text-muted">Acknowledging asset return/transfer.</small>
                            </div>
                            <div class="col-lg-4 col-md-12">
                                <div class="signature-line"></div>
                                <div class="signature-box">Noted by: IT Department</div>
                                <small class="text-muted">All listed assets have been returned/accounted for.</small>
                            </div>

                            <div class="col-lg-4 col-md-12">
                                <div class="signature-line"></div>
                                <div class="signature-box">Approved by: (IT MANAGER)</div>
                                <small class="text-muted">Final approval for asset clearance.</small>
                            </div>
                        </div>

                        <p class="mt-5 text-muted small">Clearance Report generated by the IT Inventory System on <?php echo date('Y-m-d H:i:s'); ?>.</p>

                    </div>
                </div>

            <?php elseif ($employee_id): ?>
                <div class="alert alert-warning">No employee found with ID: **<?php echo htmlspecialchars($employee_id); ?>**. Please select a valid employee.</div>
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
</body>
</html>