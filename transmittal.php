<?php
require_once 'includes/config.php'; // Ensure your config file is correctly included

$message = ''; 

// 1. Fetch data for dropdowns
try {
    $employees_stmt = $pdo->query('SELECT employee_id, name FROM employees ORDER BY name ASC');
    $employees = $employees_stmt->fetchAll();
    // Assets: Fetch all relevant assets along with their status and current user ID
    $assets_stmt = $pdo->query("SELECT asset_id, fam_tag_number, device_name, serial_number, status, current_user_id FROM assets WHERE status IN ('Available', 'In Use') ORDER BY fam_tag_number ASC");
    $assets = $assets_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Error fetching initial data: " . $e->getMessage());
}

// 2. Handle Form Submission (The Core Transaction)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_transmittal'])) {
    
    // Sanitize and collect data
    $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_SANITIZE_NUMBER_INT);
    $transaction_type = filter_input(INPUT_POST, 'transaction_type', FILTER_SANITIZE_STRING);
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING);
    $signature_data = $_POST['signature_data']; // Base64 signature data
    $qty = 1;

    // ** CRITICAL FIX: Ensure 0 (Inventory ID) is correctly captured as an integer and not NULL **
    $from_id = filter_input(INPUT_POST, 'from_id', FILTER_SANITIZE_NUMBER_INT);
    $to_id = filter_input(INPUT_POST, 'to_id', FILTER_SANITIZE_NUMBER_INT);
    
    // PHP interprets empty form fields as empty strings. If '0' is disabled/selected, ensure we treat empty as 0.
    $from_id = ($from_id === false || $from_id === '') ? 0 : (int)$from_id;
    $to_id = ($to_id === false || $to_id === '') ? 0 : (int)$to_id;
    // ------------------------------------------------------------------------------------------

    // Retrieve current asset status
    $current_asset_stmt = $pdo->prepare("SELECT status FROM assets WHERE asset_id = ?");
    $current_asset_stmt->execute([$asset_id]);
    $current_status = $current_asset_stmt->fetchColumn();

    $pdo->beginTransaction();

    try {
        $new_asset_status = '';
        $new_user_id = NULL; // Assets returning to inventory have NULL for current_user_id

        if ($transaction_type === 'OUT') {
            if ($current_status !== 'Available') { throw new Exception("Asset is currently **$current_status**. Cannot issue OUT transmittal."); }
            if ($from_id != 0 || $to_id == 0) { throw new Exception("OUT Transmittal must be FROM Inventory (0) TO an Employee."); }
            $new_asset_status = 'In Use';
            $new_user_id = $to_id; // Assign to the employee
            
        } elseif ($transaction_type === 'IN') {
            if ($current_status !== 'In Use') { throw new Exception("Asset is currently **$current_status**. Only 'In Use' assets can be returned."); }
            if ($from_id == 0 || $to_id != 0) { throw new Exception("IN Transmittal must be FROM an Employee TO Inventory (0)."); }
            $new_asset_status = 'Available';
            $new_user_id = NULL; // Clear assignment
        } else {
            throw new Exception("Invalid transaction type specified.");
        }

        // 1. INSERT into Transmittal Log
        // Note: $from_id and $to_id are guaranteed to be integers (0 or employee ID)
        $sql_transmittal = "INSERT INTO transmittals (asset_id, transaction_type, from_id, to_id, remarks, qty, signature_data) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_transmittal = $pdo->prepare($sql_transmittal);
        $stmt_transmittal->execute([$asset_id, $transaction_type, $from_id, $to_id, $remarks, $qty, $signature_data]);
        
        // 2. UPDATE the Assets table
        $sql_asset_update = "UPDATE assets SET status = ?, current_user_id = ? WHERE asset_id = ?";
        $stmt_asset_update = $pdo->prepare($sql_asset_update);
        // Note: $new_user_id is either an Employee ID (for OUT) or NULL (for IN)
        $stmt_asset_update->execute([$new_asset_status, $new_user_id, $asset_id]);

        $pdo->commit();

        $message = '<div class="alert alert-success">Transmittal recorded successfully! Asset status updated to **' . $new_asset_status . '**.</div>';

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Transaction Failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    } catch (\PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Database Error: Could not complete transaction.</div>';
    }
}

// 3. Fetch the transmittal history for display with date range
// Initialize date range variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Default to start of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');     // Default to current date

// Basic validation and formatting for SQL
$start_date_sql = $start_date ? $start_date . ' 00:00:00' : null;
$end_date_sql = $end_date ? $end_date . ' 23:59:59' : null;

$where_clause = ' WHERE 1=1 ';
$params = [];

if ($start_date_sql) {
    $where_clause .= ' AND t.transmittal_date >= ? ';
    $params[] = $start_date_sql;
}

if ($end_date_sql) {
    $where_clause .= ' AND t.transmittal_date <= ? ';
    $params[] = $end_date_sql;
}

$sql_history = "
    SELECT 
        t.transmittal_id, t.transmittal_date, t.transaction_type, t.remarks, 
        a.fam_tag_number, a.device_name, 
        ef.name AS from_name, et.name AS to_name
    FROM 
        transmittals t
    JOIN 
        assets a ON t.asset_id = a.asset_id
    LEFT JOIN 
        employees ef ON t.from_id = ef.employee_id
    LEFT JOIN 
        employees et ON t.to_id = et.employee_id
    {$where_clause}
    ORDER BY 
        t.transmittal_date DESC
";

try {
    $history_stmt = $pdo->prepare($sql_history);
    $history_stmt->execute($params);
    $transmittal_history = $history_stmt->fetchAll();
} catch (\PDOException $e) {
    $message = '<div class="alert alert-danger">Error fetching history: ' . $e->getMessage() . '</div>';
    $transmittal_history = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Transmittal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f8f9fa; }
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="transmittal.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } 
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }
        
        /* Select2 Fixes for Bootstrap 5 */
        .select2-container .select2-selection--single {
            height: 38px !important;
        }
        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 38px !important;
            padding-left: 0.75rem !important; /* Match Bootstrap padding */
        }
        .select2-container .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }
        
        /* === Print Styles Fix === */
        @media print {
            /* Hide non-essential elements for printing */
            .d-print-none, #sidebar-wrapper, nav, .alert, .card.border-warning { 
                display: none !important; 
            }

            /* Ensure the body and print-area display */
            body { 
                margin-top: 0; 
                padding-top: 0; 
            }
            
            #print-area { 
                display: block !important; 
                width: 100%; 
                margin: 0; 
                padding: 0; 
            }
            
            /* Ensure table content is visible and readable */
            #print-area .card-header, #print-area .card-body { 
                border: none !important; 
                padding: 0; 
            }
            .table-responsive { 
                overflow: visible !important; 
            }
            .table, .table td, .table th {
                font-size: 10pt; /* Smaller font for printing */
                border-color: #ccc !important;
            }
            .badge { 
                border: 1px solid #000; 
                padding: 3px; 
            }
            h2 { 
                font-size: 1.5rem; 
                margin-top: 10px; 
                margin-bottom: 10px;
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
            <a class="list-group-item list-group-item-action bg-dark active" href="transmittal.php">📝 Transmittal Log</a>
            <a class="list-group-item list-group-item-action bg-dark" href="employee_clearance.php">📄 Clearance Form</a>
        </div>
    </div>
    <div id="page-content-wrapper">
        

        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4">IT Department - Asset Transmittal</h1>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-5 border-warning">
                <div class="card-header bg-warning text-dark">Record New Transmittal (IN / OUT)</div>
                <div class="card-body">
                    <form id="transmittalForm" method="POST" action="transmittal.php">
                        <input type="hidden" name="record_transmittal" value="1"> 
                        <input type="hidden" name="signature_data" id="signature_data"> 

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Transaction Type</label>
                                <select class="form-select" id="transaction_type" name="transaction_type" required>
                                    <option value="">Select Type</option>
                                    <option value="OUT">OUT (Assigning to Employee)</option>
                                    <option value="IN">IN (Returning to Inventory)</option>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label for="asset_id" class="form-label">Device FAM Tag / Serial</label>
                                <select class="form-select" id="asset_id" name="asset_id" required>
                                    <option value="">Select Device...</option>
                                    <?php foreach ($assets as $asset): ?>
                                    <option 
                                        value="<?php echo $asset['asset_id']; ?>" 
                                        data-status="<?php echo $asset['status']; ?>"
                                        data-current-user="<?php echo $asset['current_user_id'] ? $asset['current_user_id'] : ''; ?>" 
                                    >
                                        <?php echo htmlspecialchars($asset['fam_tag_number']) . ' - ' . htmlspecialchars($asset['device_name']); ?> 
                                        (Status: <?php echo $asset['status']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="from_id" class="form-label">FROM</label>
                                <select class="form-select select2-employee" id="from_id" name="from_id" style="width: 100%;" required>
                                    <option value="">Select...</option>
                                    <option value="0">Inventory</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="to_id" class="form-label">TO</label>
                                <select class="form-select select2-employee" id="to_id" name="to_id" style="width: 100%;" required>
                                    <option value="">Select...</option>
                                    <option value="0">Inventory</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mt-4">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                            </div>

                            <div class="col-md-6 mt-4">
                                <label class="form-label d-block">Employee Signature (Required)</label>
                                <canvas id="signatureCanvas" class="border border-secondary rounded" width="450" height="150" style="background-color: #f7f7f7; cursor: crosshair;"></canvas>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-1" id="clearSignature">Clear Signature</button>
                            </div>

                        </div>
                        <button type="submit" class="btn btn-warning mt-4 text-dark fw-bold">Record Transmittal</button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-lg" id="print-area">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"> Transmittal History</h2>
                    <button class="btn btn-outline-secondary btn-sm d-print-none" onclick="window.print()">🖨️ Print History</button>
                </div>
                <div class="card-body">
                    
                    <form method="GET" action="transmittal.php" class="row g-3 align-items-end mb-4 d-print-none">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Filter History</button>
                        </div>
                    </form>
                    
                    <p class="text-muted d-print-none">Displaying transmittals from **<?php echo htmlspecialchars($start_date); ?>** to **<?php echo htmlspecialchars($end_date); ?>**.</p>
                    <hr class="d-print-none">

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr><th>Date/Time</th><th>Type</th><th>Asset Tag</th><th>From</th><th>To</th><th>Remarks</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transmittal_history as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log['transmittal_date'])); ?></td>
                                    <td><span class="badge <?php echo $log['transaction_type'] == 'OUT' ? 'bg-danger' : 'bg-success'; ?>"><?php echo $log['transaction_type']; ?></span></td>
                                    <td><?php echo htmlspecialchars($log['fam_tag_number']); ?></td>
                                    <td><?php echo $log['from_name'] ? htmlspecialchars($log['from_name']) : 'Inventory'; ?></td>
                                    <td><?php echo $log['to_name'] ? htmlspecialchars($log['to_name']) : 'Inventory'; ?></td>
                                    <td><?php echo htmlspecialchars($log['remarks']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($transmittal_history)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No transmittals recorded in this date range.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> 
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    const canvas = document.getElementById('signatureCanvas');
    const signature_data_input = document.getElementById('signature_data');
    const clearButton = document.getElementById('clearSignature');
    const transmittalForm = document.getElementById('transmittalForm');
    const transactionType = document.getElementById('transaction_type');
    const assetSelect = document.getElementById('asset_id');
    const fromSelect = document.getElementById('from_id');
    const toSelect = document.getElementById('to_id');
    
    // Store original asset options for filtering
    const initialAssetOptions = Array.from(assetSelect.options).slice(1);
    
    // --- Select2 Initialization ---
    $(document).ready(function() {
        $('.select2-employee').select2({
            placeholder: 'Search for Employee or Inventory...',
            allowClear: false // Since selection is required
        });
    });

    // --- Signature Pad Logic ---
    const ctx = canvas.getContext('2d');
    let drawing = false;

    canvas.addEventListener('mousedown', (e) => { drawing = true; ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); });
    canvas.addEventListener('mousemove', (e) => { if (!drawing) return; ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); });
    canvas.addEventListener('mouseup', () => { drawing = false; });
    clearButton.addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); signature_data_input.value = ''; });

    transmittalForm.addEventListener('submit', function(e) {
        // Validation check for Select2 fields *before* checking signature
        if ($('#from_id').val() === null || $('#from_id').val() === '' || $('#to_id').val() === null || $('#to_id').val() === '') {
             // Select2 handles visual required warning, but this is a fallback.
             e.preventDefault();
             return;
        }

        const dataURL = canvas.toDataURL('image/png');
        if (dataURL.length < 2000) { 
            alert("Please provide a signature before recording the transmittal.");
            e.preventDefault();
            return;
        }
        signature_data_input.value = dataURL;
    });

    // --- Core Transmittal Filtering and Lookup Logic ---
    transactionType.addEventListener('change', filterTransmittalForm);
    assetSelect.addEventListener('change', lookupAssetUser); 

    /**
     * Filters asset and employee dropdowns based on IN/OUT type and resets selections.
     */
    function filterTransmittalForm() {
        const type = transactionType.value;
        
        // Reset asset options
        assetSelect.innerHTML = '<option value="">Select Device...</option>';
        
        // Reset and clear Select2 fields
        $('#from_id').val(null).trigger('change');
        $('#to_id').val(null).trigger('change');

        if (type === 'OUT') {
            // OUT: FROM must be Inventory (0). TO must be an Employee.
            $('#from_id').val('0').trigger('change');
            fromSelect.disabled = true;
            toSelect.disabled = false;

            // Filter assets: only show 'Available' assets
            initialAssetOptions.forEach(option => {
                if (option.dataset.status === 'Available') {
                    assetSelect.appendChild(option.cloneNode(true));
                }
            });
            
        } else if (type === 'IN') {
            // IN: FROM must be an Employee. TO must be Inventory (0).
            $('#to_id').val('0').trigger('change');
            toSelect.disabled = true;
            fromSelect.disabled = false;

            // Filter assets: only show 'In Use' assets
            initialAssetOptions.forEach(option => {
                if (option.dataset.status === 'In Use') {
                    assetSelect.appendChild(option.cloneNode(true));
                }
            });
            
        } else {
            // If "Select Type..." is chosen
            initialAssetOptions.forEach(option => assetSelect.appendChild(option.cloneNode(true)));
            fromSelect.disabled = false;
            toSelect.disabled = false;
        }
        
        // Update Select2 disabled state to match the select element's disabled property
        $('#from_id').select2().prop('disabled', fromSelect.disabled);
        $('#to_id').select2().prop('disabled', toSelect.disabled);
        
        // Ensure lookup is run after the type changes and assets are filtered
        lookupAssetUser(); 
    }
    
    /**
     * Looks up the current user of a selected asset and populates the FROM field for IN transmittals.
     */
    function lookupAssetUser() {
        const type = transactionType.value;
        const selectedOption = assetSelect.options[assetSelect.selectedIndex];
        
        // Reset the FROM field unless we are processing an IN transmittal
        if (type === 'IN') {
            $('#from_id').val(null).trigger('change');
        }

        if (type === 'IN' && selectedOption.value) {
            // Read the data-current-user attribute from the selected asset option
            const currentUserId = selectedOption.dataset.currentUser;
            
            if (currentUserId && currentUserId !== '0') {
                // Auto-select the employee who currently holds the asset (Select2 compatible)
                $('#from_id').val(currentUserId).trigger('change');
            } else if (currentUserId === '0' || !currentUserId) {
                 // Asset is selected but user data is missing or user ID is 0
                 // Prompt user to select manually, ensuring the field is enabled.
                 fromSelect.disabled = false;
                 $('#from_id').select2().prop('disabled', false);
                 alert("Warning: Asset is 'In Use' but current user data is missing. Please select FROM employee manually.");
            }
        }
    }


    // Initial load setup 
    filterTransmittalForm(); 
    
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
</script>

</body>
</html>