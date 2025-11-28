<?php

require_once 'includes/config.php';

$employee_data = null;
$assigned_assets = [];
$assigned_software = []; // NEW ARRAY
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
            // 2. Fetch Assigned Assets (Hardware - Existing Logic)
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

            // 3. Fetch Assigned Software Licenses (NEW LOGIC)
            $sql_software = "
                SELECT 
                    s.name AS software_name, s.license_type, es.date_allocated
                FROM 
                    employee_software es
                JOIN 
                    software_licenses s ON es.software_id = s.software_id
                WHERE 
                    es.employee_id = ? AND es.status = 'Allocated'
                ORDER BY 
                    s.name ASC
            ";
            $stmt_software = $pdo->prepare($sql_software);
            $stmt_software->execute([$employee_id]);
            $assigned_software = $stmt_software->fetchAll();
        }

    } catch (\PDOException $e) {
        $error_message = "Database Error: Could not retrieve data.";
    }
}

// Combine counts for clearance note
$total_assigned_items = count($assigned_assets) + count($assigned_software);
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
    /* ... (Existing SCREEN STYLES) ... */
    
    /* --- PRINT STYLES (PDF Design Changes) --- */
    @page {
        size: A4;
        margin: 0.5in;
    }
    @media print {
        /* ... (Existing PRINT STYLES) ... */
        .card-header {
                background-color: #8CA9FF !important; 
                color: #000 !important; 
                border-bottom: 3px solid #000 !important;
                padding: 10px 0;
                margin-bottom: 20px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        .table {
            border: 1px solid #000 !important;
            margin-top: 15px;
            margin-bottom: 30px !important; /* Added space between tables */
        }
        .table th, .table td {
            padding: 5px;
            border: 1px solid #ccc !important;
        }
        .table thead th {
            background-color: #e9ecef !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color: #000;
            font-weight: bold;
        }
        /* Hide non-print elements */
        #search-form-container, #print-controls, .d-flex .border-end, .navbar {
            display: none;
        }
        #page-content-wrapper {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .container-fluid {
            width: 100%;
            padding: 0 !important;
        }
    }
    /* Add Select2/Bootstrap Select CSS if needed for the dropdown */
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
            <a class="list-group-item list-group-item-action bg-dark" href="software_inventory.php">💾 Software</a> <a class="list-group-item list-group-item-action bg-dark" href="transmittal.php">📝 Transmittal Log</a>
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
            <h1 class="mt-4 mb-4 no-print">📄 Employee Asset Clearance Form</h1>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger no-print"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="card shadow-sm mb-5 no-print" id="search-form-container">
                <div class="card-header bg-info text-dark fw-bold">Select Employee</div>
                <div class="card-body">
                    <form method="POST" action="employee_clearance.php" class="d-flex">
                        <select name="employee_id" class="form-select me-2" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees_list as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>"
                                    <?php echo ($employee_id == $emp['employee_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name']) . ' (' . htmlspecialchars($emp['employee_id']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="select_employee" class="btn btn-primary"><i class="bi bi-search"></i> Generate</button>
                    </form>
                </div>
            </div>

            <?php if ($employee_data): ?>

                <div id="print-controls" class="mb-4 no-print">
                    <button class="btn btn-success" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
                    <?php if ($total_assigned_items > 0): ?>
                        <span class="text-danger ms-3 fw-bold">NOTE: **<?php echo $total_assigned_items; ?>** item(s) (Hardware/Software) are still assigned.</span>
                    <?php else: ?>
                        <span class="text-success ms-3 fw-bold">Clearance Ready: No assets or software currently assigned.</span>
                    <?php endif; ?>
                </div>

                <div class="card shadow-lg mb-5">
                    <div class="card-header border-bottom text-center">
                        <h3 class="mb-0 text-white">CHROMAESTHETICS INC </br> IT ASSET CLEARANCE CLEARANCE</h3>
                        <p class="text-white mb-0">Issued on: <?php echo date('Y-m-d'); ?></p>
                    </div>
                    <div class="card-body">
                        
                        <h5 class="mb-3 text-primary">Employee Information</h5>
                        <div class="row mb-4 border p-3 rounded">
                            <div class="col-md-6"><strong>Name:</strong> <?php echo htmlspecialchars($employee_data['name']); ?></div>
                            <div class="col-md-6"><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee_data['employee_id']); ?></div>
                            <div class="col-md-6"><strong>Department:</strong> <?php echo htmlspecialchars($employee_data['department']); ?></div>
                            <div class="col-md-6"><strong>Position:</strong> <?php echo htmlspecialchars($employee_data['position']); ?></div>
                        </div>

                        <h5 class="mt-4 mb-3 text-primary">Assigned Hardware Assets (Current Status)</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>FAM Tag Number</th>
                                        <th>Device Type</th>
                                        <th>Device Model</th>
                                        <th>Serial Number</th>
                                        <th class="text-center">Current Status</th>
                                        <th class="text-center">IT Check (Returned)</th>
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
                                            <td colspan="7" class="text-center text-success fw-bold">NO HARDWARE ASSETS CURRENTLY ASSIGNED.</td>
                                        </tr>
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <tr><td><?php echo $i; ?></td><td></td><td></td><td></td><td></td><td class="text-center"></td><td class="text-center"></td></tr>
                                        <?php endfor; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <h5 class="mt-5 mb-3 text-primary">Assigned Software Licenses (Current Status)</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Software Name</th>
                                        <th>License Type</th>
                                        <th>Date Allocated</th>
                                        <th class="text-center">IT Check (Revoked)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $j = 1; if (count($assigned_software) > 0): ?>
                                        <?php foreach ($assigned_software as $software): ?>
                                        <tr>
                                            <td><?php echo $j++; ?></td>
                                            <td><?php echo htmlspecialchars($software['software_name']); ?></td>
                                            <td><span class="badge bg-dark"><?php echo htmlspecialchars($software['license_type']); ?></span></td>
                                            <td><?php echo date('Y-m-d', strtotime($software['date_allocated'])); ?></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-success fw-bold">NO SOFTWARE LICENSES CURRENTLY ASSIGNED.</td>
                                        </tr>
                                        <?php for ($j = 1; $j <= 2; $j++): ?>
                                            <tr><td><?php echo $j; ?></td><td></td><td></td><td></td><td class="text-center"></td></tr>
                                        <?php endfor; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <h5 class="mt-5 mb-3 text-primary">Clearance Signatures</h5>
                        <div class="row text-center mt-5">
                            <div class="col-lg-4 col-md-12 mb-4 mb-lg-0">
                                <div class="signature-box">_________________________</div>
                                <small class="text-muted">Employee Signature / Date</small>
                            </div>

                            <div class="col-lg-4 col-md-12 mb-4 mb-lg-0">
                                <div class="signature-box">_________________________</div>
                                <small class="text-muted">Noted by: IT Department</small>
                            </div>

                            <div class="col-lg-4 col-md-12">
                                <div class="signature-box">_________________________</div>
                                <small class="text-muted">Approved by: (IT MANAGER)</small>
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

</body>
</html>