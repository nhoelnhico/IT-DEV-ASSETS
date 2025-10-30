<?php
require_once 'includes/config.php';

$message = ''; 

// --- 1. HANDLE ADD NEW ASSET ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_asset'])) {
    // ... (Existing ADD Asset Logic remains the same) ...
    $fam_tag_number = filter_input(INPUT_POST, 'fam_tag_number', FILTER_SANITIZE_STRING);
    $device_type = filter_input(INPUT_POST, 'device_type', FILTER_SANITIZE_STRING);
    $device_name = filter_input(INPUT_POST, 'device_name', FILTER_SANITIZE_STRING);
    $serial_number = filter_input(INPUT_POST, 'serial_number', FILTER_SANITIZE_STRING);
    $initial_status = 'Available'; 

    if (empty($fam_tag_number) || empty($device_type) || empty($device_name) || empty($serial_number)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } else {
        try {
            $sql = "INSERT INTO assets (fam_tag_number, device_type, device_name, serial_number, status) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fam_tag_number, $device_type, $device_name, $serial_number, $initial_status]);

            $message = '<div class="alert alert-success">Asset **' . htmlspecialchars($fam_tag_number) . '** added successfully and is **Available**.</div>';
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '<div class="alert alert-warning">Error: FAM Tag or Serial Number already exists.</div>';
            } else {
                $message = '<div class="alert alert-danger">Database Error: Could not add asset.</div>';
            }
        }
    }
}

// --- 2. HANDLE EDIT/UPDATE ASSET ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_asset'])) {
    $asset_id = filter_input(INPUT_POST, 'edit_asset_id', FILTER_SANITIZE_NUMBER_INT);
    $fam_tag_number = filter_input(INPUT_POST, 'edit_fam_tag_number', FILTER_SANITIZE_STRING);
    $device_type = filter_input(INPUT_POST, 'edit_device_type', FILTER_SANITIZE_STRING);
    $device_name = filter_input(INPUT_POST, 'edit_device_name', FILTER_SANITIZE_STRING);
    $serial_number = filter_input(INPUT_POST, 'edit_serial_number', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'edit_status', FILTER_SANITIZE_STRING);

    if (empty($asset_id) || empty($fam_tag_number) || empty($device_type) || empty($device_name) || empty($serial_number) || empty($status)) {
        $message = '<div class="alert alert-danger">All fields are required for the update.</div>';
    } else {
        try {
            // NOTE: The current_user_id is NOT updated here; it's managed via Transmittal ONLY.
            $sql = "UPDATE assets 
                    SET fam_tag_number = ?, device_type = ?, device_name = ?, serial_number = ?, status = ? 
                    WHERE asset_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fam_tag_number, $device_type, $device_name, $serial_number, $status, $asset_id]);

            $message = '<div class="alert alert-success">Asset **' . htmlspecialchars($fam_tag_number) . '** updated successfully. Status: **' . htmlspecialchars($status) . '**.</div>';

        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger">Database Error: Could not update asset. Check for duplicate FAM Tag or Serial Number.</div>';
        }
    }
}


// --- 3. FETCH ALL ASSETS (for display) ---
$sql_fetch = "
    SELECT 
        a.asset_id, a.fam_tag_number, a.device_type, a.device_name, a.serial_number, a.status, e.name AS current_user_name
    FROM 
        assets a
    LEFT JOIN 
        employees e ON a.current_user_id = e.employee_id
    ORDER BY 
        a.fam_tag_number ASC
";
$stmt_fetch = $pdo->query($sql_fetch);
$assets = $stmt_fetch->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        /* ... (Sidebar CSS) ... */
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="inventory.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } /* Active for this page */
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
            <a class="list-group-item list-group-item-action bg-dark active" href="inventory.php">📦 Inventory</a>
            <a class="list-group-item list-group-item-action bg-dark" href="transmittal.php">📝 Transmittal Log</a>
        </div>
    </div>
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-primary" id="sidebarToggle">Toggle Menu</button>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="#">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4">📦 IT Asset Inventory</h1>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-5 border-success">
                <div class="card-header bg-success text-white">Add New Device to Inventory</div>
                <div class="card-body">
                    <form method="POST" action="inventory.php">
                        <input type="hidden" name="add_asset" value="1"> 
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="fam_tag_number" class="form-label">Device FAM Tag Number</label>
                                <input type="text" class="form-control" id="fam_tag_number" name="fam_tag_number" required>
                            </div>
                            <div class="col-md-3">
                                <label for="device_type" class="form-label">Device Type</label>
                                <select class="form-select" id="device_type" name="device_type" required>
                                    <option value="">Select Type...</option>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="Monitor">Monitor</option>
                                    <option value="Company Phone">Company Phone</option>
                                    <option value="Tablet">Tablet</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="device_name" class="form-label">Device Name / Model</label>
                                <input type="text" class="form-control" id="device_name" name="device_name" placeholder="e.g., Dell Latitude 5420" required>
                            </div>
                            <div class="col-md-3">
                                <label for="serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success mt-4">Add Device</button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-lg">
                <div class="card-header bg-white border-bottom">Master Inventory List (<?php echo count($assets); ?> Devices)</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>FAM Tag</th>
                                    <th>Type</th>
                                    <th>Device Model</th>
                                    <th>Serial No.</th>
                                    <th>**Status**</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assets as $asset): 
                                    $badge_class = 'bg-secondary';
                                    if ($asset['status'] == 'In Use') { $badge_class = 'bg-primary'; }
                                    if ($asset['status'] == 'Available') { $badge_class = 'bg-success'; }
                                    if ($asset['status'] == 'Broken') { $badge_class = 'bg-danger'; }
                                    if ($asset['status'] == 'Repairing') { $badge_class = 'bg-info'; }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['device_type']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['device_name']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($asset['status']); ?></span></td>
                                    <td>
                                        <?php echo $asset['current_user_name'] ? htmlspecialchars($asset['current_user_name']) : '<span class="text-muted">Available</span>'; ?>
                                    </td>
                                    <td>
                                        <button 
                                            class="btn btn-sm btn-outline-warning edit-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editAssetModal"
                                            data-id="<?php echo $asset['asset_id']; ?>"
                                            data-tag="<?php echo htmlspecialchars($asset['fam_tag_number']); ?>"
                                            data-type="<?php echo htmlspecialchars($asset['device_type']); ?>"
                                            data-name="<?php echo htmlspecialchars($asset['device_name']); ?>"
                                            data-serial="<?php echo htmlspecialchars($asset['serial_number']); ?>"
                                            data-status="<?php echo htmlspecialchars($asset['status']); ?>"
                                        >
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                    </td>
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

<div class="modal fade" id="editAssetModal" tabindex="-1" aria-labelledby="editAssetModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="editAssetModalLabel">Edit Asset Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="inventory.php">
        <div class="modal-body">
            <input type="hidden" name="update_asset" value="1">
            <input type="hidden" name="edit_asset_id" id="edit_asset_id">

            <div class="mb-3">
                <label for="edit_fam_tag_number" class="form-label">FAM Tag Number</label>
                <input type="text" class="form-control" id="edit_fam_tag_number" name="edit_fam_tag_number" required>
            </div>
            <div class="mb-3">
                <label for="edit_device_type" class="form-label">Device Type</label>
                <select class="form-select" id="edit_device_type" name="edit_device_type" required>
                    <option value="Desktop">Desktop</option>
                    <option value="Laptop">Laptop</option>
                    <option value="Monitor">Monitor</option>
                    <option value="Company Phone">Company Phone</option>
                    <option value="Tablet">Tablet</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="edit_device_name" class="form-label">Device Model</label>
                <input type="text" class="form-control" id="edit_device_name" name="edit_device_name" required>
            </div>
            <div class="mb-3">
                <label for="edit_serial_number" class="form-label">Serial Number</label>
                <input type="text" class="form-control" id="edit_serial_number" name="edit_serial_number" required>
            </div>
            <div class="mb-3">
                <label for="edit_status" class="form-label">Asset Status</label>
                <select class="form-select" id="edit_status" name="edit_status" required>
                    <option value="Available">Available</option>
                    <option value="In Use" disabled>In Use (Change via Transmittal)</option>
                    <option value="Broken">Broken (Needs Repair)</option>
                    <option value="Repairing">Repairing (In Workshop)</option>
                </select>
                <div class="form-text text-danger">Note: Status 'In Use' should only be changed via the Transmittal Log.</div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-warning">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Toggle Sidebar
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });

    // JavaScript to populate the Edit Modal
    var editAssetModal = document.getElementById('editAssetModal');
    editAssetModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Button that triggered the modal

        // Extract data-* attributes from the button
        var assetId = button.getAttribute('data-id');
        var famTag = button.getAttribute('data-tag');
        var type = button.getAttribute('data-type');
        var name = button.getAttribute('data-name');
        var serial = button.getAttribute('data-serial');
        var status = button.getAttribute('data-status');

        // Update the modal's fields
        var modalTitle = editAssetModal.querySelector('.modal-title');
        var modalAssetId = editAssetModal.querySelector('#edit_asset_id');
        var modalFamTag = editAssetModal.querySelector('#edit_fam_tag_number');
        var modalType = editAssetModal.querySelector('#edit_device_type');
        var modalName = editAssetModal.querySelector('#edit_device_name');
        var modalSerial = editAssetModal.querySelector('#edit_serial_number');
        var modalStatus = editAssetModal.querySelector('#edit_status');

        modalTitle.textContent = 'Edit Asset: ' + famTag;
        modalAssetId.value = assetId;
        modalFamTag.value = famTag;
        modalType.value = type;
        modalName.value = name;
        modalSerial.value = serial;
        modalStatus.value = status; 
        
        // Temporarily disable 'In Use' option if the asset is currently 'In Use'
        // This prevents manual removal of an asset from an employee without a log (transmittal)
        // Although the backend PHP prevents updating current_user_id, this adds front-end UX safety.
        var inUseOption = modalStatus.querySelector('option[value="In Use"]');
        if (inUseOption) {
            // Check if the current status allows manual change
            if (status === 'Available' || status === 'Broken' || status === 'Repairing') {
                inUseOption.disabled = true;
            } else {
                inUseOption.disabled = true; // Always disable 'In Use' for simplicity
            }
        }
    });
</script>

</body>
</html>