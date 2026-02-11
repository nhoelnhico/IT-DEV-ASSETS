<?php
require_once 'includes/config.php';

$message = '';

// --- 1. FETCH DROPDOWN DATA ---
// Employees
$emp_stmt = $pdo->query("SELECT employee_id, name FROM employees ORDER BY name ASC");
$employees = $emp_stmt->fetchAll();

// Assets - Fetch ALL assets but include status for filtering
$asset_stmt = $pdo->query("SELECT asset_id, fam_tag_number, device_name, status, current_user_id FROM assets ORDER BY fam_tag_number ASC");
$assets = $asset_stmt->fetchAll();

// Prepare Assets Array for JavaScript
$assets_json = [];
foreach ($assets as $a) {
    $assets_json[] = [
        'id' => $a['asset_id'],
        'text' => $a['fam_tag_number'] . ' - ' . $a['device_name'] . ' (' . $a['status'] . ')',
        'status' => $a['status'],
        'current_user_id' => $a['current_user_id']
    ];
}
$assets_js_data = json_encode($assets_json);


// --- 2. HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_transaction'])) {
    $form_type = $_POST['transaction_type']; // This is 'Issue', 'Return', or 'Repair'
    $asset_id = $_POST['asset_id'];
    $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;
    $remarks = $_POST['remarks'];
    $date = date('Y-m-d H:i:s');

    // MAP FORM VALUES TO DATABASE VALUES (IN/OUT)
    // Issue = OUT (Item goes OUT to employee)
    // Return = IN (Item comes IN to inventory)
    // Repair = Repair (or OUT depending on your DB, keeping as Repair for now)
    $db_transaction_type = $form_type; 
    if ($form_type == 'Issue') $db_transaction_type = 'OUT';
    if ($form_type == 'Return') $db_transaction_type = 'IN';

    if (empty($form_type) || empty($asset_id)) {
        $message = '<div class="alert alert-danger shadow-sm border-0">Transaction Type and Asset are required.</div>';
    } else {
        $check_stmt = $pdo->prepare("SELECT status, current_user_id FROM assets WHERE asset_id = ?");
        $check_stmt->execute([$asset_id]);
        $current_asset = $check_stmt->fetch();

        $valid_transaction = true;

        try {
            $pdo->beginTransaction();

            // LOGIC FOR ISSUING (OUT)
            if ($form_type == 'Issue') {
                if ($current_asset['status'] != 'Available') {
                    $valid_transaction = false;
                    $message = '<div class="alert alert-danger shadow-sm border-0">Error: Asset is not Available (Current Status: ' . $current_asset['status'] . ').</div>';
                } elseif (empty($employee_id)) {
                    $valid_transaction = false;
                    $message = '<div class="alert alert-danger shadow-sm border-0">Error: Please select an Employee to assign this to.</div>';
                } else {
                    $update = $pdo->prepare("UPDATE assets SET status = 'In Use', current_user_id = ? WHERE asset_id = ?");
                    $update->execute([$employee_id, $asset_id]);
                    
                    $log = $pdo->prepare("INSERT INTO transmittals (asset_id, to_id, transmittal_date, transaction_type, remarks) VALUES (?, ?, ?, ?, ?)");
                    $log->execute([$asset_id, $employee_id, $date, $db_transaction_type, $remarks]);
                }

            // LOGIC FOR RETURNING (IN)
            } elseif ($form_type == 'Return') {
                if ($current_asset['status'] != 'In Use') {
                    $valid_transaction = false;
                    $message = '<div class="alert alert-danger shadow-sm border-0">Error: Asset is not currently In Use, so it cannot be returned.</div>';
                } else {
                    $prev_user_id = $current_asset['current_user_id'];
                    $update = $pdo->prepare("UPDATE assets SET status = 'Available', current_user_id = NULL WHERE asset_id = ?");
                    $update->execute([$asset_id]);

                    $log = $pdo->prepare("INSERT INTO transmittals (asset_id, from_id, transmittal_date, transaction_type, remarks) VALUES (?, ?, ?, ?, ?)");
                    $log->execute([$asset_id, $prev_user_id, $date, $db_transaction_type, $remarks]);
                }

            // LOGIC FOR REPAIR
            } elseif ($form_type == 'Repair') {
                $from_id = $current_asset['current_user_id'];
                $update = $pdo->prepare("UPDATE assets SET status = 'Repairing', current_user_id = NULL WHERE asset_id = ?");
                $update->execute([$asset_id]);

                $log = $pdo->prepare("INSERT INTO transmittals (asset_id, from_id, transmittal_date, transaction_type, remarks) VALUES (?, ?, ?, ?, ?)");
                $log->execute([$asset_id, $from_id, $date, 'Repair', $remarks]);
            }

            if ($valid_transaction) {
                $pdo->commit();
                $message = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> Transaction saved successfully.</div>';
                
                // Refresh assets array for JS
                $asset_stmt = $pdo->query("SELECT asset_id, fam_tag_number, device_name, status, current_user_id FROM assets ORDER BY fam_tag_number ASC");
                $assets_json = [];
                foreach ($asset_stmt->fetchAll() as $a) {
                    $assets_json[] = [
                        'id' => $a['asset_id'],
                        'text' => $a['fam_tag_number'] . ' - ' . $a['device_name'] . ' (' . $a['status'] . ')',
                        'status' => $a['status'],
                        'current_user_id' => $a['current_user_id']
                    ];
                }
                $assets_js_data = json_encode($assets_json);

            } else {
                $pdo->rollBack();
            }

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger shadow-sm border-0">Database Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- 3. FETCH HISTORY ---
$history_sql = "
    SELECT 
        t.transmittal_date, t.transaction_type, t.remarks,
        a.fam_tag_number, a.device_name,
        e_from.name as from_name,
        e_to.name as to_name
    FROM 
        transmittals t
    JOIN 
        assets a ON t.asset_id = a.asset_id
    LEFT JOIN 
        employees e_from ON t.from_id = e_from.employee_id
    LEFT JOIN 
        employees e_to ON t.to_id = e_to.employee_id
    ORDER BY 
        t.transmittal_date DESC
";
$history = $pdo->query($history_sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Transmittals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

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

        .form-label { font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #858796; }
        
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
        .table-custom tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .badge-type { padding: 0.5em 0.8em; border-radius: 0.35rem; font-weight: 600; min-width: 80px; display: inline-block; text-align: center; }
        .type-issue { background-color: rgba(78, 115, 223, 0.1); color: var(--primary-color); border: 1px solid rgba(78, 115, 223, 0.2); }
        .type-return { background-color: rgba(28, 200, 138, 0.1); color: var(--success-color); border: 1px solid rgba(28, 200, 138, 0.2); }
        .type-repair { background-color: rgba(231, 74, 59, 0.1); color: var(--danger-color); border: 1px solid rgba(231, 74, 59, 0.2); }

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
            <a href="transmittal.php" class="active"><i class="bi bi-arrow-left-right"></i> Transmittals</a>
            <a href="employee_clearance.php"><i class="bi bi-file-earmark-check"></i> Clearance</a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i> Menu</button>
            <div class="ms-auto text-secondary small fw-bold">Asset Movement Log</div>
        </nav>

        <div class="container-fluid p-4">
            <h3 class="mb-4 text-dark fw-bold">Transmittal Log</h3>
            
            <?php echo $message; ?>

            <div class="content-card">
                <div class="card-header border-left-primary">
                    <span><i class="bi bi-pen-fill me-2"></i> Log New Transaction</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="transmittal.php">
                        <input type="hidden" name="log_transaction" value="1">
                        
                        <div class="row g-4">
                            <div class="col-md-3">
                                <label class="form-label">Action Type</label>
                                <select class="form-select" name="transaction_type" id="transaction_type" required>
                                    <option value="" selected disabled>Select Action...</option>
                                    <option value="Issue">Assign to Employee</option>
                                    <option value="Return">Surrender / Return</option>
                                    <option value="Repair">Send for Repair</option>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Select Asset</label>
                                <select class="form-select select2" name="asset_id" id="asset_id" required>
                                    <option value="">Select Action Type First...</option>
                                    </select>
                            </div>

                            <div class="col-md-4" id="employee_field_container">
                                <label class="form-label">Employee</label>
                                <select class="form-select select2" name="employee_id" id="employee_id">
                                    <option value="">Search Employee...</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-muted small mt-1" id="emp_help_text">Select action type to see instructions.</div>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Remarks / Notes</label>
                                <textarea class="form-control" name="remarks" rows="2" placeholder="e.g. Issued for new project, Screen cracked, etc."></textarea>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                <i class="bi bi-save me-2"></i> Save Transaction
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <span><i class="bi bi-clock-history me-2"></i> Movement History</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Type</th>
                                    <th>Asset Details</th>
                                    <th>Involved Parties</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($history) > 0): ?>
                                    <?php foreach ($history as $row): 
                                        // LOGIC TO MAP DATABASE VALUES (IN/OUT) BACK TO READABLE TEXT
                                        $display_type = $row['transaction_type'];
                                        $typeClass = 'type-issue';
                                        
                                        if ($row['transaction_type'] == 'OUT') {
                                            $display_type = 'Issue';
                                            $typeClass = 'type-issue';
                                        } 
                                        elseif ($row['transaction_type'] == 'IN') {
                                            $display_type = 'Return';
                                            $typeClass = 'type-return';
                                        }
                                        elseif ($row['transaction_type'] == 'Repair') {
                                            $display_type = 'Repair';
                                            $typeClass = 'type-repair';
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-secondary"><?php echo date('Y-m-d H:i', strtotime($row['transmittal_date'])); ?></td>
                                        <td><span class="badge-type <?php echo $typeClass; ?>"><?php echo $display_type; ?></span></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['fam_tag_number']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['device_name']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($display_type == 'Issue'): ?>
                                                <span class="text-muted small">To:</span> <span class="fw-semibold text-dark"><?php echo htmlspecialchars($row['to_name']); ?></span>
                                            <?php elseif ($display_type == 'Return'): ?>
                                                <span class="text-muted small">From:</span> <span class="fw-semibold text-dark"><?php echo htmlspecialchars($row['from_name']); ?></span>
                                            <?php elseif ($display_type == 'Repair'): ?>
                                                <span class="text-muted small">From:</span> <span class="fw-semibold text-dark"><?php echo $row['from_name'] ? htmlspecialchars($row['from_name']) : 'Inventory'; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small fst-italic"><?php echo htmlspecialchars($row['remarks']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">No transactions recorded yet.</td>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // --- 1. Pass PHP Asset Data to JavaScript ---
    const allAssets = <?php echo $assets_js_data; ?>;

    // Sidebar Toggle
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });

    // Initialize Select2
    $(document).ready(function() {
        $('.select2').select2({
            theme: "bootstrap-5",
            width: '100%'
        });

        // Trigger logic on load to reset fields
        updateAssetDropdown(); 
    });

    // --- 2. Dynamic Filtering Logic ---
    $('#transaction_type').on('change', function() {
        updateAssetDropdown();
    });

    function updateAssetDropdown() {
        const type = $('#transaction_type').val();
        const assetSelect = $('#asset_id');
        const employeeSelect = $('#employee_id');
        const helpText = document.getElementById('emp_help_text');

        // Clear current asset options
        assetSelect.empty();

        if (!type) {
            assetSelect.append(new Option('Select Action Type First...', ''));
            employeeSelect.prop('disabled', true);
            return;
        }

        assetSelect.append(new Option('Select Asset...', ''));

        // Filter assets based on type
        allAssets.forEach(asset => {
            let addOption = false;

            if (type === 'Issue') {
                // Show ONLY Available assets
                if (asset.status === 'Available') addOption = true;
            } else if (type === 'Return') {
                // Show ONLY In Use assets
                if (asset.status === 'In Use') addOption = true;
            } else if (type === 'Repair') {
                // Show Available AND In Use assets
                if (asset.status === 'In Use' || asset.status === 'Available') addOption = true;
            }

            if (addOption) {
                assetSelect.append(new Option(asset.text, asset.id, false, false));
            }
        });

        // Refresh Select2 for Assets
        assetSelect.trigger('change');

        // Employee Field Logic
        if (type === 'Issue') {
            employeeSelect.prop('disabled', false);
            employeeSelect.val(null).trigger('change');
            helpText.textContent = "Required: Select who receives the asset.";
        } else if (type === 'Return') {
            employeeSelect.prop('disabled', true);
            employeeSelect.val(null).trigger('change');
            helpText.textContent = "Auto-detected from asset assignment.";
        } else {
            employeeSelect.prop('disabled', true);
            employeeSelect.val(null).trigger('change');
            helpText.textContent = "Not applicable for Repairs.";
        }
    }
</script>

</body>
</html>