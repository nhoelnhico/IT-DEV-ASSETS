<?php
require_once 'includes/config.php';

$employee_data = null;
$assigned_assets = [];
$assigned_software = [];
$employee_id = '';
$employees_list = [];
$error_message = '';

// Fetch all employees for dropdown
try {
    $employees_stmt = $pdo->query('SELECT employee_id, name FROM employees ORDER BY name ASC');
    $employees_list = $employees_stmt->fetchAll();
} catch (\PDOException $e) { }

// Check for selected employee
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['select_employee'])) {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_GET['id'])) {
    $employee_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
}

if (!empty($employee_id)) {
    try {
        // 1. Employee Details
        $stmt = $pdo->prepare("SELECT employee_id, name, department, position FROM employees WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $employee_data = $stmt->fetch();

        if ($employee_data) {
            // 2. Hardware
            $stmt_assets = $pdo->prepare("SELECT fam_tag_number, device_type, device_name, serial_number, status FROM assets WHERE current_user_id = ? ORDER BY device_type");
            $stmt_assets->execute([$employee_id]);
            $assigned_assets = $stmt_assets->fetchAll();
            
            // 3. Software
            $stmt_soft = $pdo->prepare("SELECT s.name, s.version, s.license_type, sa.license_key FROM software_assignments sa JOIN software_items s ON sa.software_id = s.software_id WHERE sa.employee_id = ? AND sa.status = 'Active'");
            $stmt_soft->execute([$employee_id]);
            $assigned_software = $stmt_soft->fetchAll();
        }
    } catch (\PDOException $e) {
        $error_message = "Error fetching data.";
    }
}

$total_items = count($assigned_assets) + count($assigned_software);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Form | <?php echo $employee_data ? htmlspecialchars($employee_data['name']) : 'Select Employee'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        :root {
            --primary-color: #4e73df;
            --dark-sidebar: #2c3e50;
            --light-bg: #f3f4f6;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #5a5c69;
        }

        /* SCREEN ONLY STYLES */
        @media screen {
            #sidebar-wrapper {
                min-height: 100vh;
                margin-left: -15rem;
                transition: margin .25s ease-out;
                background-color: var(--dark-sidebar);
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
            .sidebar-nav a.active { background-color: rgba(255,255,255,0.1); color: #fff; border-left: 4px solid #36b9cc; }
            
            @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }

            .paper-sheet {
                background: white;
                box-shadow: 0 0 15px rgba(0,0,0,0.1);
                padding: 40px;
                min-height: 800px;
                max-width: 210mm; /* A4 width */
                margin: 0 auto;
                position: relative;
            }
        }

        /* PRINT STYLES - CRITICAL FOR CLEARANCE FORM */
        @media print {
            @page { margin: 0.5cm; size: A4 portrait; }
            body { background: white; -webkit-print-color-adjust: exact; }
            #sidebar-wrapper, .navbar, .no-print { display: none !important; }
            .container-fluid { padding: 0 !important; margin: 0 !important; }
            .paper-sheet {
                box-shadow: none;
                padding: 0;
                margin: 0;
                width: 100%;
                max-width: 100%;
            }
            .btn, form { display: none; }
            .card { border: none !important; }
            .bg-dark { background-color: #000 !important; color: white !important; }
        }

        /* Common Table Styles for the Form */
        .form-table th { background-color: #eee !important; color: #000; text-transform: uppercase; font-size: 0.8rem; }
        .form-table td { font-size: 0.9rem; }
        .signature-line { border-top: 1px solid #000; width: 80%; margin: 40px auto 5px auto; }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div id="sidebar-wrapper">
        <div class="sidebar-heading">IT Asset Manager</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="employees.php"><i class="bi bi-people"></i> Employees</a>
            <a href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a>
            <a href="software_inventory.php"><i class="bi bi-disc"></i> Software</a> 
            <a href="software_assignment.php"><i class="bi bi-key"></i> Licenses</a>
            <a href="transmittal.php"><i class="bi bi-arrow-left-right"></i> Transmittals</a>
            <a href="employee_clearance.php" class="active"><i class="bi bi-file-earmark-check"></i> Clearance</a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3 no-print">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i> Menu</button>
            <div class="ms-auto text-secondary small fw-bold">Generate Clearance</div>
        </nav>

        <div class="container-fluid p-4">
            
            <div class="card shadow-sm mb-4 border-0 no-print">
                <div class="card-body bg-white rounded">
                    <form method="POST" action="employee_clearance.php" class="row align-items-end g-3">
                        <input type="hidden" name="select_employee" value="1">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Select Employee for Clearance</label>
                            <select class="form-select select2" name="employee_id" required>
                                <option value="">Search Employee Name or ID...</option>
                                <?php foreach ($employees_list as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>" <?php echo ($emp['employee_id'] == $employee_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="bi bi-file-earmark-text me-2"></i> Generate Form</button>
                        </div>
                        <?php if ($employee_data): ?>
                        <div class="col-md-3">
                            <button type="button" onclick="window.print()" class="btn btn-success w-100 shadow-sm"><i class="bi bi-printer me-2"></i> Print / PDF</button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if ($employee_data): ?>
            <div class="paper-sheet">
                <div class="text-center mb-5 border-bottom pb-3">
                    <h2 class="fw-bold mb-0">IT CLEARANCE FORM</h2>
                    <p class="text-muted small mb-0">CHROMAESTHETICS INC. | IT DEPARTMENT</p>
                    <p class="text-muted small">Generated: <?php echo date('F d, Y'); ?></p>
                </div>

                <div class="row mb-4">
                    <div class="col-6 mb-2"><strong>Employee Name:</strong> <span class="border-bottom border-dark px-2 d-inline-block w-75"><?php echo htmlspecialchars($employee_data['name']); ?></span></div>
                    <div class="col-6 mb-2"><strong>Employee ID:</strong> <span class="border-bottom border-dark px-2 d-inline-block w-75"><?php echo htmlspecialchars($employee_data['employee_id']); ?></span></div>
                    <div class="col-6 mb-2"><strong>Department:</strong> <span class="border-bottom border-dark px-2 d-inline-block w-75"><?php echo htmlspecialchars($employee_data['department']); ?></span></div>
                    <div class="col-6 mb-2"><strong>Position:</strong> <span class="border-bottom border-dark px-2 d-inline-block w-75"><?php echo htmlspecialchars($employee_data['position']); ?></span></div>
                </div>

                <?php if ($total_items > 0): ?>
                    <div class="alert alert-warning border-dark text-center fw-bold no-print">
                        <i class="bi bi-exclamation-triangle"></i> WARNING: This employee still has <?php echo $total_items; ?> items assigned. They must be returned before signing.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success border-success text-center fw-bold no-print">
                        <i class="bi bi-check-circle"></i> CLEAR: No active assets found. Ready for clearance signature.
                    </div>
                <?php endif; ?>

                <h5 class="fw-bold mt-4 text-uppercase border-bottom border-2 border-dark pb-1">I. Hardware Assets</h5>
                <table class="table table-bordered form-table border-dark">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="20%">Asset Tag</th>
                            <th width="35%">Description / Model</th>
                            <th width="20%">Serial No.</th>
                            <th width="20%" class="text-center">Status / Return Check</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; foreach ($assigned_assets as $asset): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                            <td><?php echo htmlspecialchars($asset['device_type'] . ' - ' . $asset['device_name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($asset['status']); ?> <span class="ms-2">⬜</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assigned_assets)): ?>
                        <tr><td colspan="5" class="text-center fst-italic">No hardware currently assigned.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h5 class="fw-bold mt-4 text-uppercase border-bottom border-2 border-dark pb-1">II. Software Licenses</h5>
                <table class="table table-bordered form-table border-dark">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="40%">Software Title</th>
                            <th width="35%">License Key / Account</th>
                            <th width="20%" class="text-center">Revoke Check</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $j=1; foreach ($assigned_software as $soft): ?>
                        <tr>
                            <td><?php echo $j++; ?></td>
                            <td><?php echo htmlspecialchars($soft['name'] . ' ' . $soft['version']); ?></td>
                            <td class="font-monospace"><?php echo htmlspecialchars($soft['license_key'] ? $soft['license_key'] : 'Assigned Account'); ?></td>
                            <td class="text-center">Active <span class="ms-2">⬜</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assigned_software)): ?>
                        <tr><td colspan="4" class="text-center fst-italic">No software licenses assigned.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="row mt-5 pt-5 text-center">
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($employee_data['name']); ?></p>
                        <small class="text-muted">Employee Signature</small>
                    </div>
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <p class="mb-0 fw-bold">IT Personnel</p>
                        <small class="text-muted">Checked By</small>
                    </div>
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <p class="mb-0 fw-bold">Department Head</p>
                        <small class="text-muted">Approved By</small>
                    </div>
                </div>

                <div class="text-center mt-5 pt-5 text-muted small">
                    <p>By signing this form, the employee acknowledges the return/surrender of all listed company properties.</p>
                </div>

            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
    $(document).ready(function() {
        $('.select2').select2({ theme: "bootstrap-5", width: '100%' });
    });
</script>
</body>
</html>