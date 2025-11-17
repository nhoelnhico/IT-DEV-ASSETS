<?php
require_once 'includes/config.php'; // Ensure your config file is correctly included

$message = ''; 

// 1. Fetch data for dropdowns
try {
    $employees_stmt = $pdo->query('SELECT employee_id, name FROM employees ORDER BY name ASC');
    $employees = $employees_stmt->fetchAll();
    // Assets: Fetch all relevant assets along with their status and current user ID
    // current_user_id is also fetched as a string/VARCHAR now
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

    // UPDATED: Sanitize as STRING since employee_id is now VARCHAR.
    $from_id = filter_input(INPUT_POST, 'from_id', **FILTER_SANITIZE_STRING**);
    $to_id = filter_input(INPUT_POST, 'to_id', **FILTER_SANITIZE_STRING**);

    // ** CRITICAL FIX: Ensure '0' (Inventory ID) is correctly captured as a string.
    // If the input is empty (false or ''), default to '0' to represent Inventory.
    // This handles both the blank select option and the explicit '0' value.
    $from_id = (empty($from_id) && $from_id !== '0') ? '0' : $from_id;
    $to_id = (empty($to_id) && $to_id !== '0') ? '0' : $to_id;
    // ------------------------------------------------------------------------------------------

    try {
        if (empty($asset_id) || empty($transaction_type) || empty($signature_data)) {
            throw new Exception("Asset, Transaction Type, and Signature are required.");
        }
        
        // 0. Transaction Type Validation (CRITICAL: Use strict string comparison now)
        if ($transaction_type == 'OUT') {
            // OUT: Must be FROM Inventory ('0') TO an Employee (any ID not '0')
            if ($from_id **!== '0'** || $to_id **=== '0'**) { throw new Exception("OUT Transmittal must be FROM Inventory (0) TO an Employee."); } 

            // Get the asset's current status and user for verification
            $asset_check_stmt = $pdo->prepare("SELECT status FROM assets WHERE asset_id = ?");
            $asset_check_stmt->execute([$asset_id]);
            $asset_info = $asset_check_stmt->fetch();

            if (!$asset_info || $asset_info['status'] != 'Available') {
                throw new Exception("Asset must be 'Available' in Inventory before an OUT transmittal.");
            }
            
        } elseif ($transaction_type == 'IN') {
            // IN: Must be FROM an Employee (any ID not '0') TO Inventory ('0')
            if ($from_id **=== '0'** || $to_id **!== '0'**) { throw new Exception("IN Transmittal must be FROM an Employee TO Inventory (0)."); }

            // Get the asset's current user for verification
            $asset_check_stmt = $pdo->prepare("SELECT current_user_id FROM assets WHERE asset_id = ?");
            $asset_check_stmt->execute([$asset_id]);
            $asset_info = $asset_check_stmt->fetch();

            // CRITICAL: Use string comparison here
            if (!$asset_info || $asset_info['current_user_id'] **!== $from_id**) {
                throw new Exception("Asset check failed. The asset is not currently assigned to employee ID: " . htmlspecialchars($from_id));
            }

        } else {
            throw new Exception("Invalid transaction type specified.");
        }

        // Start Transaction
        $pdo->beginTransaction();

        // 1. INSERT into Transmittal Log
        // Note: $from_id and $to_id are strings (VARCHAR) or '0'
        $sql_transmittal = "INSERT INTO transmittals (asset_id, transaction_type, from_id, to_id, remarks, qty, signature_data) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_transmittal = $pdo->prepare($sql_transmittal);
        $stmt_transmittal->execute([$asset_id, $transaction_type, $from_id, $to_id, $remarks, $qty, $signature_data]);

        // 2. UPDATE Asset Status and Current User
        if ($transaction_type == 'OUT') {
            // Asset goes to an employee (ID is not '0'), set status to 'In Use'
            $new_status = 'In Use';
            $new_user_id = $to_id; 
        } else { // 'IN'
            // Asset comes back to inventory, set status to 'Available'
            $new_status = 'Available';
            $new_user_id = null; // null for inventory (the DB column current_user_id is nullable)
        }

        $sql_update_asset = "UPDATE assets SET status = ?, current_user_id = ? WHERE asset_id = ?";
        $stmt_update_asset = $pdo->prepare($sql_update_asset);
        $stmt_update_asset->execute([$new_status, $new_user_id, $asset_id]);


        $pdo->commit();
        $message = '<div class="alert alert-success" role="alert">Transmittal recorded successfully! Asset is now **' . $new_status . '** (User: **' . htmlspecialchars($new_user_id ?? 'Inventory') . '**).</div>';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = '<div class="alert alert-danger" role="alert">Transaction Failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = '<div class="alert alert-danger" role="alert">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}


$pageTitle = "Asset Transmittal";
include 'includes/header.php';
?>

<div id="wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div id="page-content-wrapper">
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4 text-white">Record Asset Transmittal</h1>

            <?php echo $message; ?>

            <div class="card shadow mb-4 bg-dark text-white">
                <div class="card-header bg-secondary text-white">
                    <h5 class="m-0 font-weight-bold">New Transmittal Record</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="transmittalForm">
                        <input type="hidden" name="record_transmittal" value="1">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="transaction_type" class="form-label">Transaction Type</label>
                                <select class="form-select" id="transaction_type" name="transaction_type" required onchange="filterTransmittalForm()">
                                    <option value="">Select...</option>
                                    <option value="OUT">OUT (To Employee)</option>
                                    <option value="IN">IN (To Inventory)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="asset_id" class="form-label">Asset</label>
                                <select class="form-select" id="asset_id" name="asset_id" required data-live-search="true" onchange="lookupAssetUser()">
                                    <option value="">Select Asset...</option>
                                    <?php foreach ($assets as $asset): ?>
                                    <option value="<?php echo htmlspecialchars($asset['asset_id']); ?>" 
                                            data-status="<?php echo htmlspecialchars($asset['status']); ?>"
                                            data-current-user="<?php echo htmlspecialchars($asset['current_user_id'] ?? '0'); // Null means Inventory (0) ?>"
                                            >
                                        <?php echo htmlspecialchars($asset['fam_tag_number']) . ' - ' . htmlspecialchars($asset['device_name']); ?> (Status: <?php echo $asset['status']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="from_id" class="form-label">FROM</label>
                                <select class="form-select" id="from_id" name="from_id" required>
                                    <option value="**0**">Inventory (0)</option> 
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                            <?php echo htmlspecialchars($emp['name']) . ' (' . htmlspecialchars($emp['employee_id']) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="to_id" class="form-label">TO</label>
                                <select class="form-select" id="to_id" name="to_id" required>
                                    <option value="**0**">Inventory (0)</option> 
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                            <?php echo htmlspecialchars($emp['name']) . ' (' . htmlspecialchars($emp['employee_id']) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="remarks" class="form-label">Remarks (Optional)</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">Signature</label>
                            <div class="signature-pad-container border border-light p-2 rounded">
                                <canvas id="signatureCanvas" class="border" style="width: 100%; height: 200px; background: #fff;"></canvas>
                                <button type="button" id="clearSignature" class="btn btn-sm btn-outline-danger mt-2">Clear Signature</button>
                            </div>
                            <input type="hidden" name="signature_data" id="signature_data" required>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Record Transmittal</button>
                        </div>
                    </form>
                </div>
            </div>

            </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

<script>
    // --- Signature Pad Logic ---
    const canvas = document.getElementById('signatureCanvas');
    const signaturePad = new SignaturePad(canvas);
    const signatureDataInput = document.getElementById('signature_data');
    const clearButton = document.getElementById('clearSignature');

    // Make canvas responsive
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        signaturePad.clear(); // Important to clear when resizing
    }
    window.onresize = resizeCanvas;
    resizeCanvas(); // Initial call

    clearButton.addEventListener('click', () => {
        signaturePad.clear();
        signatureDataInput.value = '';
    });

    document.getElementById('transmittalForm').addEventListener('submit', function(e) {
        if (signaturePad.isEmpty()) {
            alert("Please provide a signature before recording the transmittal.");
            e.preventDefault();
        } else {
            // Save signature data to the hidden input
            signatureDataInput.value = signaturePad.toDataURL('image/png');
        }
    });

    // --- Transmittal Form Logic ---
    const transactionType = document.getElementById('transaction_type');
    const assetSelect = document.getElementById('asset_id');
    const fromSelect = document.getElementById('from_id');
    const toSelect = document.getElementById('to_id');

    // Store original asset options for filtering
    const initialAssetOptions = Array.from(assetSelect.options).slice(1); // Exclude "Select Asset..."

    /**
     * Filters asset options based on transaction type and enforces FROM/TO values.
     */
    function filterTransmittalForm() {
        const type = transactionType.value;
        const selectedAssetId = assetSelect.value;
        assetSelect.innerHTML = '<option value="">Select Asset...</option>';
        fromSelect.value = '';
        toSelect.value = '';

        if (type === 'OUT') {
            // OUT (Inventory to Employee): Only show 'Available' assets
            fromSelect.value = '**0**'; // Enforce FROM Inventory
            toSelect.disabled = false;
            fromSelect.disabled = true;

            initialAssetOptions.forEach(option => {
                if (option.dataset.status === 'Available') {
                    assetSelect.appendChild(option.cloneNode(true));
                }
            });

        } else if (type === 'IN') {
            // IN (Employee to Inventory): Only show 'In Use' assets
            toSelect.value = '**0**'; // Enforce TO Inventory
            fromSelect.disabled = false;
            toSelect.disabled = true;

            initialAssetOptions.forEach(option => {
                if (option.dataset.status === 'In Use') {
                    assetSelect.appendChild(option.cloneNode(true));
                }
            });

        } else {
            // Default/No selection: Show all relevant assets
            initialAssetOptions.forEach(option => {
                assetSelect.appendChild(option.cloneNode(true));
            });
            fromSelect.disabled = false;
            toSelect.disabled = false;
        }
        
        // Re-initialize select picker to apply changes
        $(assetSelect).selectpicker('refresh');
        
        // If the previously selected asset is still visible, keep it selected.
        if (selectedAssetId && assetSelect.querySelector(`option[value="${selectedAssetId}"]`)) {
             assetSelect.value = selectedAssetId;
             $(assetSelect).selectpicker('val', selectedAssetId);
             lookupAssetUser(); // Auto-fill FROM field if IN transaction
        }
    }


    /**
     * Auto-selects the 'FROM' employee when an asset is selected for an IN transmittal.
     */
    function lookupAssetUser() {
        const type = transactionType.value;
        const selectedOption = assetSelect.options[assetSelect.selectedIndex];
        
        // Reset the FROM field unless we are processing an IN transmittal
        if (type === 'IN') {
            fromSelect.value = '';
        }

        if (type === 'IN' && selectedOption.value) {
            // Read the data-current-user attribute from the selected asset option
            const currentUserId = selectedOption.dataset.currentUser;
            
            // CRITICAL: Check for '0' string (Inventory)
            if (currentUserId && currentUserId **!== '0'**) {
                // Auto-select the employee who currently holds the asset
                fromSelect.value = currentUserId;
            } else if (currentUserId === '0') {
                 // Should not happen if assets are filtered correctly, but good to reset.
                fromSelect.value = '';
            } else {
                 // Asset is selected but user data is missing
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