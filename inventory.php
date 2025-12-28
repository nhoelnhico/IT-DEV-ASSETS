<?php
require_once 'includes/config.php';

$message = ''; 
$search_term = '';
$search_condition = '';
$search_params = [];
$sort_by = 'a.fam_tag_number'; // Default sort
$sort_order = 'ASC'; // Default order

// --- 1. HANDLE SEARCH QUERY ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    // Use LIKE for global search across FAM Tag, Serial, Type, or Name
    $search_condition = " WHERE a.fam_tag_number LIKE ? OR a.serial_number LIKE ? OR a.device_type LIKE ? OR a.device_name LIKE ?";
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term, $like_term];
}

// --- 2. HANDLE SORTING PARAMETERS ---
if (isset($_GET['sort_by'])) {
    $requested_sort = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_STRING);
    // Map valid column names to SQL columns
    $valid_columns = [
        'tag' => 'a.fam_tag_number',
        'type' => 'a.device_type',
        'status' => 'a.status',
        // Add sorting for the new column
        'date_received' => 'a.date_received'
    ];
    
    if (isset($valid_columns[$requested_sort])) {
        $sort_by = $valid_columns[$requested_sort];
    }
}

if (isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC'])) {
    $sort_order = strtoupper($_GET['order']);
}


// --- 3. HANDLE ADD NEW ASSET (MODIFIED) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_asset'])) {
    $fam_tag_number = filter_input(INPUT_POST, 'fam_tag_number', FILTER_SANITIZE_STRING);
    $device_type = filter_input(INPUT_POST, 'device_type', FILTER_SANITIZE_STRING);
    $device_name = filter_input(INPUT_POST, 'device_name', FILTER_SANITIZE_STRING);
    $serial_number = filter_input(INPUT_POST, 'serial_number', FILTER_SANITIZE_STRING);
    $date_received = filter_input(INPUT_POST, 'date_received', FILTER_SANITIZE_STRING); // NEW FIELD
    $initial_status = 'Available'; 

    if (empty($fam_tag_number) || empty($device_type) || empty($device_name) || empty($serial_number) || empty($date_received)) {
        $message = '<div class="alert alert-danger">All fields, including Date Received, are required.</div>';
    } else {
        try {
            // UPDATED SQL: Added date_received column
            $sql = "INSERT INTO assets (fam_tag_number, device_type, device_name, serial_number, date_received, status) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            // UPDATED EXECUTION: Added $date_received
            $stmt->execute([$fam_tag_number, $device_type, $device_name, $serial_number, $date_received, $initial_status]);

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

// --- 4. HANDLE EDIT/UPDATE ASSET (MODIFIED) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_asset'])) {
    $asset_id = filter_input(INPUT_POST, 'edit_asset_id', FILTER_SANITIZE_NUMBER_INT);
    $fam_tag_number = filter_input(INPUT_POST, 'edit_fam_tag_number', FILTER_SANITIZE_STRING);
    $device_type = filter_input(INPUT_POST, 'edit_device_type', FILTER_SANITIZE_STRING);
    $device_name = filter_input(INPUT_POST, 'edit_device_name', FILTER_SANITIZE_STRING);
    $serial_number = filter_input(INPUT_POST, 'edit_serial_number', FILTER_SANITIZE_STRING);
    $date_received = filter_input(INPUT_POST, 'edit_date_received', FILTER_SANITIZE_STRING); // NEW FIELD
    $status = filter_input(INPUT_POST, 'edit_status', FILTER_SANITIZE_STRING);

    if (empty($asset_id) || empty($fam_tag_number) || empty($device_type) || empty($device_name) || empty($serial_number) || empty($status) || empty($date_received)) {
        $message = '<div class="alert alert-danger">All fields, including Date Received, are required for the update.</div>';
    } else {
        try {
            // UPDATED SQL: Added date_received column
            $sql = "UPDATE assets 
                    SET fam_tag_number = ?, device_type = ?, device_name = ?, serial_number = ?, date_received = ?, status = ? 
                    WHERE asset_id = ?";
            $stmt = $pdo->prepare($sql);
            // UPDATED EXECUTION: Added $date_received
            $stmt->execute([$fam_tag_number, $device_type, $device_name, $serial_number, $date_received, $status, $asset_id]);

            $message = '<div class="alert alert-success">Asset **' . htmlspecialchars($fam_tag_number) . '** updated successfully. Status: **' . htmlspecialchars($status) . '**.</div>';

        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger">Database Error: Could not update asset. Check for duplicate FAM Tag or Serial Number.</div>';
        }
    }
}


// --- 5. FETCH ALL ASSETS (MODIFIED) ---
$sql_fetch = "
    SELECT 
        a.asset_id, a.fam_tag_number, a.device_type, a.device_name, a.serial_number, a.date_received, a.status, e.name AS current_user_name
    FROM 
        assets a
    LEFT JOIN 
        employees e ON a.current_user_id = e.employee_id
    {$search_condition}
    ORDER BY 
        {$sort_by} {$sort_order}
";
$stmt_fetch = $pdo->prepare($sql_fetch);
$stmt_fetch->execute($search_params);
$assets = $stmt_fetch->fetchAll();
$asset_count = count($assets);
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
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="inventory.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } /* Active for this page */
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }
        
        /* New Styles for Print/PDF */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff !important; }
            .card { border: none !important; box-shadow: none !important; }
            h1 { margin-top: 0 !important; }
            .table-responsive { overflow: visible !important; }
        }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div class="border-end bg-dark no-print" id="sidebar-wrapper">
        <div class="sidebar-heading">IT Inventory System</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a class="list-group-item list-group-item-action bg-dark" href="index.php">📊 Dashboard</a>
            <a class="list-group-item list-group-item-action bg-dark" href="employees.php">🧑‍💻 Employees</a>
            <a class="list-group-item list-group-item-action bg-dark active" href="inventory.php">📦 Inventory</a>
            <a class="list-group-item list-group-item-action bg-dark" href="transmittal.php">📝 Transmittal Log</a>
            <a class="list-group-item list-group-item-action bg-dark" href="employee_clearance.php">📄 Clearance Form</a>
        </div>
    </div>
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm no-print">
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

            <div class="card shadow-sm mb-5 border-success no-print">
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
                        <div class="row g-3 mt-1">
                            <div class="col-md-3">
                                <label for="date_received" class="form-label">Date FAM Received</label>
                                <input type="date" class="form-control" id="date_received" name="date_received" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success mt-4">Add Device</button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-lg">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center no-print">
                    <div>Master Inventory List (<?php echo $asset_count; ?> Devices Found)</div>
                    
                    <div class="d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-secondary me-2" onclick="window.print()">
                            <i class="bi bi-file-earmark-pdf"></i> Save as PDF
                        </button>
                        
                        <form method="GET" action="inventory.php" class="d-flex" style="width: 300px;">
                            <input 
                                class="form-control me-2" 
                                type="search" 
                                placeholder="Search Tag, Serial, Type, or Name" 
                                aria-label="Search" 
                                name="search"
                                value="<?php echo htmlspecialchars($search_term); ?>"
                            >
                            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
                            <?php if (!empty($search_term)): ?>
                                <a href="inventory.php" class="btn btn-outline-danger ms-1"><i class="bi bi-x"></i></a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <div class="card-header d-print-block d-none">
                     **Inventory Report** - Generated: <?php echo date('Y-m-d H:i:s'); ?> (<?php echo $asset_count; ?> Devices)
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>
                                        FAM Tag
                                        <?php 
                                            $new_order = ($sort_by == 'a.fam_tag_number' && $sort_order == 'ASC') ? 'DESC' : 'ASC';
                                            $icon = ($sort_by == 'a.fam_tag_number') ? ($sort_order == 'ASC' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-dash-lg';
                                        ?>
                                        <a href="inventory.php?sort_by=tag&order=<?php echo $new_order; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="text-decoration-none no-print">
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </a>
                                    </th>
                                    <th>
                                        Type
                                        <?php 
                                            $new_order = ($sort_by == 'a.device_type' && $sort_order == 'ASC') ? 'DESC' : 'ASC';
                                            $icon = ($sort_by == 'a.device_type') ? ($sort_order == 'ASC' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-dash-lg';
                                        ?>
                                        <a href="inventory.php?sort_by=type&order=<?php echo $new_order; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="text-decoration-none no-print">
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </a>
                                    </th>
                                    <th>Device Model</th>
                                    <th>Serial No.</th>
                                    <th>
                                        Date Received
                                        <?php 
                                            $new_order = ($sort_by == 'a.date_received' && $sort_order == 'ASC') ? 'DESC' : 'ASC';
                                            $icon = ($sort_by == 'a.date_received') ? ($sort_order == 'ASC' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-dash-lg';
                                        ?>
                                        <a href="inventory.php?sort_by=date_received&order=<?php echo $new_order; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="text-decoration-none no-print">
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </a>
                                    </th>
                                    <th>
                                        Status
                                        <?php 
                                            $new_order = ($sort_by == 'a.status' && $sort_order == 'ASC') ? 'DESC' : 'ASC';
                                            $icon = ($sort_by == 'a.status') ? ($sort_order == 'ASC' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-dash-lg';
                                        ?>
                                        <a href="inventory.php?sort_by=status&order=<?php echo $new_order; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="text-decoration-none no-print">
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </a>
                                    </th>
                                    <th>Assigned To</th>
                                    <th class="no-print">Actions</th> </tr>
                            </thead>
                            <tbody>
                                <?php if ($asset_count > 0): ?>
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
                                        <td><?php echo htmlspecialchars($asset['date_received'] ? date('M d, Y', strtotime($asset['date_received'])) : 'N/A'); ?></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($asset['status']); ?></span></td>
                                        <td>
                                            <?php echo $asset['current_user_name'] ? htmlspecialchars($asset['current_user_name']) : '<span class="text-muted">Inventory</span>'; ?>
                                        </td>
                                        <td class="no-print">
                                            <button 
                                                class="btn btn-sm btn-outline-warning edit-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editAssetModal"
                                                data-id="<?php echo $asset['asset_id']; ?>"
                                                data-tag="<?php echo htmlspecialchars($asset['fam_tag_number']); ?>"
                                                data-type="<?php echo htmlspecialchars($asset['device_type']); ?>"
                                                data-name="<?php echo htmlspecialchars($asset['device_name']); ?>"
                                                data-serial="<?php echo htmlspecialchars($asset['serial_number']); ?>"
                                                data-date="<?php echo htmlspecialchars($asset['date_received']); ?>"
                                                data-status="<?php echo htmlspecialchars($asset['status']); ?>"
                                            >
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            <?php if (!empty($search_term)): ?>
                                                No assets found matching "<?php echo htmlspecialchars($search_term); ?>".
                                            <?php else: ?>
                                                No assets recorded in the inventory.
                                            <?php endif; ?>
                                        </td>
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
                <label for="edit_date_received" class="form-label">Date FAM Received</label>
                <input type="date" class="form-control" id="edit_date_received" name="edit_date_received" required>
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

    // JavaScript to populate the Edit Modal (MODIFIED: Added Date Received Logic)
    var editAssetModal = document.getElementById('editAssetModal');
    editAssetModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; 

        var assetId = button.getAttribute('data-id');
        var famTag = button.getAttribute('data-tag');
        var type = button.getAttribute('data-type');
        var name = button.getAttribute('data-name');
        var serial = button.getAttribute('data-serial');
        var dateReceived = button.getAttribute('data-date'); // NEW DATA ATTRIBUTE
        var status = button.getAttribute('data-status');

        var modalTitle = editAssetModal.querySelector('.modal-title');
        var modalAssetId = editAssetModal.querySelector('#edit_asset_id');
        var modalFamTag = editAssetModal.querySelector('#edit_fam_tag_number');
        var modalType = editAssetModal.querySelector('#edit_device_type');
        var modalName = editAssetModal.querySelector('#edit_device_name');
        var modalSerial = editAssetModal.querySelector('#edit_serial_number');
        var modalDateReceived = editAssetModal.querySelector('#edit_date_received'); // NEW ELEMENT
        var modalStatus = editAssetModal.querySelector('#edit_status');

        modalTitle.textContent = 'Edit Asset: ' + famTag;
        modalAssetId.value = assetId;
        modalFamTag.value = famTag;
        modalType.value = type;
        modalName.value = name;
        modalSerial.value = serial;
        modalDateReceived.value = dateReceived; // SET DATE VALUE
        modalStatus.value = status; 
        
        var inUseOption = modalStatus.querySelector('option[value="In Use"]');
        if (inUseOption) {
            // Always disable 'In Use' to enforce Transmittal process
            inUseOption.disabled = true;
        }
    });
</script>

</body>
</html>