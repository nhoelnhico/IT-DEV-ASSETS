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
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_GET['id'])) {
    // Allows direct linking/testing with ?id=123
    $employee_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
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
            $sql_assets = "
                SELECT 
                    fam_tag_number, device_type, device_name, serial_number, status
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Employee Clearance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        /* SIDEBAR STYLES (for screen view) */
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="employee_clearance.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } 
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }

        /* --- PRINT STYLES (for PDF/Hard Copy) --- */
        @media print {
            body { 
                margin: 0; 
                padding: 0; 
                color: #000; 
                background-color: #fff;
            }
            #wrapper { 
                display: block; 
                width: 100%; 
            }
            #sidebar-wrapper, #search-form-container, #print-controls, .navbar, .alert { 
                display: none !important; 
            }
            #page-content-wrapper { 
                padding: 0;
            }
            .container-fluid { 
                width: 95%; 
                padding: 0; 
                margin: auto; 
            }
            .card { 
                border: 1px solid #000 !important; 
                box-shadow: none !important;
                margin-bottom: 20px;
            }
            .table-striped > tbody > tr:nth-of-type(odd) > * { 
                background-color: #f2f2f2 !important; 
            }
            .signature-box { 
                margin-top: 50px; 
                border-top: 1px solid #000; 
                width: 300px;
                text-align: center;
                padding-top: 5px;
            }
            .asset-status {
                border: 1px solid #000; 
                padding: 2px 5px;
                display: inline-block;
                min-width: 80px;
                text-align: center;
                background-color: #f2f2f2;
                font-size: 0.8rem;
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
            <h1 class="mt-4 mb-4">📄 Employee Asset Clearance Form</h1>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="card shadow-sm mb-5" id="search-form-container">
                <div class="card-header bg-info text-dark fw-bold">Select Employee</div>
                <div class="card-body">
                    <form method="POST" action="employee_clearance.php">
                        <input type="hidden" name="select_employee" value="1"> 
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label for="employee_id" class="form-label">Employee Name or ID</label>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee...</option>
                                    <?php foreach ($employees_list as $emp): ?>
                                    <option 
                                        value="<?php echo $emp['employee_id']; ?>" 
                                        <?php echo ($emp['employee_id'] == $employee_id) ? 'selected' : ''; ?>
                                    >
                                        <?php echo htmlspecialchars($emp['name']) . ' (' . htmlspecialchars($emp['employee_id']) . ')'; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-info w-100">Load Clearance Form</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($employee_data): ?>

                <div id="print-controls" class="mb-4">
                    <button class="btn btn-success" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
                    <?php if (count($assigned_assets) > 0): ?>
                        <span class="text-danger ms-3 fw-bold">NOTE: <?php echo count($assigned_assets); ?> asset(s) are still assigned.</span>
                    <?php else: ?>
                        <span class="text-success ms-3 fw-bold">Clearance Ready: No assets currently assigned.</span>
                    <?php endif; ?>
                </div>

                <div class="card shadow-lg mb-5">
                    <div class="card-header bg-white border-bottom text-center">
                        <h3 class="mb-0">ASSET CLEARANCE CHECKLIST</h3>
                        <p class="text-muted mb-0">Issued on: <?php echo date('Y-m-d'); ?></p>
                    </div>
                    <div class="card-body">
                        
                        <h5 class="mb-3 text-primary">Employee Information</h5>
                        <div class="row mb-4 border p-3 rounded">
                            <div class="col-md-6"><strong>Name:</strong> <?php echo htmlspecialchars($employee_data['name']); ?></div>
                            <div class="col-md-6"><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee_data['employee_id']); ?></div>
                            <div class="col-md-6"><strong>Department:</strong> <?php echo htmlspecialchars($employee_data['department']); ?></div>
                            <div class="col-md-6"><strong>Position:</strong> <?php echo htmlspecialchars($employee_data['position']); ?></div>
                        </div>

                        <h5 class="mb-3 text-primary">Assigned Assets (Current Status)</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>FAM Tag Number</th>
                                        <th>Device Type</th>
                                        <th>Device Model</th>
                                        <th>Serial Number</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">IT Staff Check</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; if (count($assigned_assets) > 0): ?>
                                        <?php foreach ($assigned_assets as $asset): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['device_type']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['device_name']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                            <td class="text-center">
                                                <span class="asset-status"><?php echo htmlspecialchars($asset['status']); ?></span>
                                            </td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-success fw-bold">NO ASSETS CURRENTLY ASSIGNED. Clearance may proceed.</td>
                                        </tr>
                                        <?php for ($i = 1; $i <= 3; $i++): // Add empty rows for formality ?>
                                            <tr>
                                                <td><?php echo $i; ?></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td class="text-center"></td>
                                                <td class="text-center"></td>
                                            </tr>
                                        <?php endfor; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <h5 class="mt-5 mb-3 text-primary">Clearance Signatures</h5>
                        <div class="row">
                            <div class="col-6">
                                <div class="signature-box">Employee Signature</div>
                                <small>I confirm the return of all listed assets.</small>
                            </div>
                            <div class="col-6">
                                <div class="signature-box">IT Department Signature</div>
                                <small>All listed assets have been returned/accounted for and inventoried.</small>
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