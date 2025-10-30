<?php
// Include the database connection script
require_once 'includes/config.php'; // Adjust path if necessary

$message = ''; 

// 1. Fetch all Employees for 'FROM' and 'TO' dropdowns
try {
    $employees_stmt = $pdo->query('SELECT employee_id, name FROM employees ORDER BY name ASC');
    $employees = $employees_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Error fetching employees: " . $e->getMessage());
}

// 2. Fetch Assets that are either 'Available' or 'In Use'
try {
    $assets_stmt = $pdo->query("SELECT asset_id, fam_tag_number, device_name, serial_number, status, current_user_id FROM assets WHERE status IN ('Available', 'In Use') ORDER BY fam_tag_number ASC");
    $assets = $assets_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Error fetching assets: " . $e->getMessage());
}

// 3. Handle Form Submission (The Core Transaction)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_transmittal'])) {
    
    // Sanitize and collect data
    $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_SANITIZE_NUMBER_INT);
    $transaction_type = filter_input(INPUT_POST, 'transaction_type', FILTER_SANITIZE_STRING); // 'IN' or 'OUT'
    $from_id = filter_input(INPUT_POST, 'from_id', FILTER_SANITIZE_NUMBER_INT); // Employee ID or Storage ID (0)
    $to_id = filter_input(INPUT_POST, 'to_id', FILTER_SANITIZE_NUMBER_INT);       // Employee ID or Storage ID (0)
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING);
    $qty = 1; // Assuming QTY is always 1 for single IT assets
    // Note: Signature data is handled via AJAX/JS and saved as an image or Base64 string

    // Retrieve current asset status for validation
    $current_asset_stmt = $pdo->prepare("SELECT status FROM assets WHERE asset_id = ?");
    $current_asset_stmt->execute([$asset_id]);
    $current_status = $current_asset_stmt->fetchColumn();

    // Begin PDO Transaction for atomic operations
    $pdo->beginTransaction();

    try {
        $new_asset_status = '';
        $new_user_id = NULL; // NULL for Available/Storage

        if ($transaction_type === 'OUT') {
            // Validation: Only assign assets that are currently 'Available'
            if ($current_status !== 'Available') {
                throw new Exception("Asset is currently **$current_status**. Cannot issue OUT transmittal.");
            }
            // Set new status/user for the asset
            $new_asset_status = 'In Use';
            $new_user_id = $to_id; // Assign to the 'TO' employee
            
        } elseif ($transaction_type === 'IN') {
             // Validation: Only return assets that are currently 'In Use'
            if ($current_status !== 'In Use') {
                 throw new Exception("Asset is currently **$current_status**. Only 'In Use' assets can be returned via IN transmittal.");
            }
            // Set new status/user for the asset
            $new_asset_status = 'Available';
            $new_user_id = NULL; // Return to Storage (NULL user ID)
        } else {
            throw new Exception("Invalid transaction type specified.");
        }

        // 1. INSERT into Transmittal Log
        $sql_transmittal = "INSERT INTO transmittals (asset_id, transaction_type, from_id, to_id, remarks, qty) 
                            VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_transmittal = $pdo->prepare($sql_transmittal);
        $stmt_transmittal->execute([$asset_id, $transaction_type, $from_id, $to_id, $remarks, $qty]);
        
        // 2. UPDATE the Assets table
        $sql_asset_update = "UPDATE assets SET status = ?, current_user_id = ? WHERE asset_id = ?";
        $stmt_asset_update = $pdo->prepare($sql_asset_update);
        $stmt_asset_update->execute([$new_asset_status, $new_user_id, $asset_id]);

        // Commit the transaction only if both steps succeeded
        $pdo->commit();

        $message = '<div class="alert alert-success">Transmittal recorded successfully! Asset status updated to **' . $new_asset_status . '**.</div>';

    } catch (Exception $e) {
        // Rollback on any error to revert both changes
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Transaction Failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    } catch (\PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Database Error: Could not complete transaction.</div>';
        // For debugging: echo $e->getMessage();
    }
}

// 4. Fetch the transmittal history for display
$sql_history = "
    SELECT 
        t.transmittal_date, t.transaction_type, t.remarks, 
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
    ORDER BY 
        t.transmittal_date DESC LIMIT 10
";
$history_stmt = $pdo->query($sql_history);
$transmittal_history = $history_stmt->fetchAll();

// HTML starts below
?>

<!DOCTYPE html>
<html lang="en">
<body>

<div class="d-flex" id="wrapper">
    <div id="page-content-wrapper">
        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4">📝 Asset Transmittal</h1>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-5">
                <div class="card-header bg-warning text-dark">
                    Record New Transmittal (IN / OUT)
                </div>
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
                                        data-user="<?php echo $asset['current_user_id']; ?>"
                                    >
                                        <?php echo htmlspecialchars($asset['fam_tag_number']) . ' - ' . htmlspecialchars($asset['device_name']); ?> 
                                        (Status: <?php echo $asset['status']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="from_id" class="form-label">FROM</label>
                                <select class="form-select" id="from_id" name="from_id" required>
                                    <option value="">Select...</option>
                                    <option value="0">Storage/Inventory</option> <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>">
                                        <?php echo htmlspecialchars($employee['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="to_id" class="form-label">TO</label>
                                <select class="form-select" id="to_id" name="to_id" required>
                                    <option value="">Select...</option>
                                    <option value="0">Storage/Inventory</option> <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>">
                                        <?php echo htmlspecialchars($employee['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="1"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label d-block">Signature (Required)</label>
                                <canvas id="signatureCanvas" class="border border-secondary rounded" width="400" height="150" style="background-color: #fff;"></canvas>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-1" id="clearSignature">Clear Signature</button>
                            </div>

                        </div>
                        <button type="submit" class="btn btn-warning mt-4">Record Transmittal</button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-lg">
                <div class="card-header bg-white">
                    Recent Transmittal History
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Asset Tag</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Remarks</th>
                                </tr>
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
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 7. Signature Pad Placeholder (You would integrate a library here)
    const canvas = document.getElementById('signatureCanvas');
    const signature_data_input = document.getElementById('signature_data');
    const clearButton = document.getElementById('clearSignature');
    const transmittalForm = document.getElementById('transmittalForm');

    // Basic canvas context for demonstration
    const ctx = canvas.getContext('2d');
    let drawing = false;

    // --- Placeholder Drawing Logic ---
    canvas.addEventListener('mousedown', (e) => {
        drawing = true;
        ctx.beginPath();
        ctx.moveTo(e.offsetX, e.offsetY);
    });

    canvas.addEventListener('mousemove', (e) => {
        if (!drawing) return;
        ctx.lineTo(e.offsetX, e.offsetY);
        ctx.stroke();
    });

    canvas.addEventListener('mouseup', () => {
        drawing = false;
    });

    clearButton.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        signature_data_input.value = '';
    });
    // --- End Placeholder Drawing Logic ---

    // Final step before submission: Capture signature data
    transmittalForm.addEventListener('submit', function(e) {
        // Convert the canvas content to a Base64 image string
        const dataURL = canvas.toDataURL('image/png');
        
        // Simple check to ensure a signature was drawn
        if (dataURL === canvas.toDataURL('image/png', 0)) {
            alert("Please provide a signature before recording the transmittal.");
            e.preventDefault();
            return;
        }

        // Set the hidden input value with the signature data
        signature_data_input.value = dataURL;
    });

    // 8. Basic Transmittal Logic/Validation (Client-side)
    const transactionType = document.getElementById('transaction_type');
    const assetSelect = document.getElementById('asset_id');
    const fromSelect = document.getElementById('from_id');
    const toSelect = document.getElementById('to_id');

    // Enforce Transmittal Rules
    transactionType.addEventListener('change', function() {
        const type = this.value;
        const assetOptions = assetSelect.options;

        // Reset the From/To dropdowns
        fromSelect.value = '';
        toSelect.value = '';

        if (type === 'OUT') {
            // OUT: FROM must be Inventory (ID 0). TO must be an Employee.
            fromSelect.value = 0;
            fromSelect.disabled = true; // Lock 'FROM' to Inventory
            toSelect.disabled = false;
        } else if (type === 'IN') {
            // IN: FROM must be an Employee. TO must be Inventory (ID 0).
            toSelect.value = 0;
            toSelect.disabled = true; // Lock 'TO' to Inventory
            fromSelect.disabled = false;
        } else {
            fromSelect.disabled = false;
            toSelect.disabled = false;
        }

        // You would add JavaScript logic here to filter the assetSelect
        // to only show 'Available' items for 'OUT' and 'In Use' items for 'IN'.
    });
</script>
</body>
</html>