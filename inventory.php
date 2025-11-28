<?php
require_once 'includes/config.php';

$message = ''; 
$search_term = '';
$search_condition = '';
$search_params = [];
$sort_by = 'a.fam_tag_number'; // Default sort
$sort_order = 'ASC'; // Default order
$assets = [];

// --- 1. HANDLE SEARCH AND SORTING (Existing Logic) ---

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    $search_condition = " WHERE a.fam_tag_number LIKE ? OR a.serial_number LIKE ? OR a.device_type LIKE ? OR a.device_name LIKE ?";
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term, $like_term];
}

if (isset($_GET['sort_by'])) {
    $requested_sort = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_STRING);
    $valid_columns = [
        'tag' => 'a.fam_tag_number',
        'type' => 'a.device_type',
        'status' => 'a.status',
        'date_received' => 'a.date_received',
        'user' => 'e.name'
    ];
    if (isset($valid_columns[$requested_sort])) {
        $sort_by = $valid_columns[$requested_sort];
    }
}
if (isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC'])) {
    $sort_order = strtoupper(filter_input(INPUT_GET, 'sort_order', FILTER_SANITIZE_STRING));
}


// --- 2. HANDLE DELETE ASSET (Existing Logic) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_asset'])) {
    $asset_id_to_delete = filter_input(INPUT_POST, 'delete_asset_id', FILTER_SANITIZE_NUMBER_INT);

    // Check if the asset is currently assigned (current_user_id is NOT NULL)
    $check_stmt = $pdo->prepare("SELECT current_user_id, fam_tag_number FROM assets WHERE asset_id = ?");
    $check_stmt->execute([$asset_id_to_delete]);
    $asset_info = $check_stmt->fetch();

    if ($asset_info && $asset_info['current_user_id'] !== null) {
        $message = '<div class="alert alert-danger">ERROR: Cannot delete asset **' . htmlspecialchars($asset_info['fam_tag_number']) . '** because it is currently assigned (status: In Use). Revoke the asset via the Transmittal page first.</div>';
    } elseif ($asset_info) {
        try {
            // Delete the asset (Transmittal records linked via foreign keys will also be deleted)
            $sql = "DELETE FROM assets WHERE asset_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$asset_id_to_delete]);

            $message = '<div class="alert alert-success">Asset **' . htmlspecialchars($asset_info['fam_tag_number']) . '** deleted successfully.</div>';
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger">Database Error: Could not delete asset. ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Error: Asset not found for deletion.</div>';
    }
}

// --- 3. HANDLE ADD/EDIT ASSET (Existing Logic, omitted for brevity) ---
// ...

// --- 4. FETCH ASSETS (Updated SQL) ---
try {
    $sql = "
        SELECT 
            a.asset_id, a.fam_tag_number, a.device_type, a.device_name, a.serial_number, a.date_received, a.status,
            a.current_user_id, 
            e.employee_id, e.name AS current_user_name
        FROM 
            assets a
        LEFT JOIN 
            employees e ON a.current_user_id = e.employee_id
        {$search_condition}
        ORDER BY {$sort_by} {$sort_order}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($search_params);
    $assets = $stmt->fetchAll();
} catch (\PDOException $e) {
    $message = '<div class="alert alert-danger">Database Error: Could not load assets.</div>';
    error_log("Inventory Fetch Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Hardware Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="inventory.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } 
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
            <a class="list-group-item list-group-item-action bg-dark" href="software_inventory.php">💾 Software</a>
            <a class="list-group-item list-group-item-action bg-dark" href="transmittal.php">📝 Transmittal Log</a>
            <a class="list-group-item list-group-item-action bg-dark" href="employee_clearance.php">📄 Clearance Form</a>
        </div>
    </div>
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-primary" id="sidebarToggle">Toggle Menu</button>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4">📦 IT Asset Inventory</h1>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-5">
                <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                    Hardware List (<?php echo count($assets); ?> Total)
                    <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                        <i class="bi bi-plus-circle"></i> Add New Asset
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>FAM Tag</th>
                                    <th>Device Type</th>
                                    <th>Model/Name</th>
                                    <th>Serial Number</th>
                                    <th>Date Received</th>
                                    <th>Status</th>
                                    <th>Current User</th>
                                    <th style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($assets)): ?>
                                    <?php foreach ($assets as $asset): 
                                        $badge_class = match($asset['status']) {
                                            'In Use' => 'bg-primary',
                                            'Available' => 'bg-success',
                                            'Broken' => 'bg-danger',
                                            default => 'bg-secondary',
                                        };
                                        $current_user_display = $asset['current_user_name'] ? htmlspecialchars($asset['current_user_name']) . ' (' . htmlspecialchars($asset['employee_id']) . ')' : 'None';
                                        $is_assigned = $asset['current_user_id'] !== null; // <<< THIS LINE NOW WORKS
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['device_type']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['device_name']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['date_received']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($asset['status']); ?></span>
                                        </td>
                                        <td><?php echo $current_user_display; ?></td>
                                        <td>
                                            <button 
                                                class="btn btn-sm btn-outline-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editAssetModal"
                                                data-id="<?php echo htmlspecialchars($asset['asset_id']); ?>"
                                                data-tag="<?php echo htmlspecialchars($asset['fam_tag_number']); ?>"
                                                data-type="<?php echo htmlspecialchars($asset['device_type']); ?>"
                                                data-name="<?php echo htmlspecialchars($asset['device_name']); ?>"
                                                data-serial="<?php echo htmlspecialchars($asset['serial_number']); ?>"
                                                data-date="<?php echo htmlspecialchars($asset['date_received']); ?>"
                                                data-status="<?php echo htmlspecialchars($asset['status']); ?>"
                                                title="Edit Asset Details"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <button 
                                                class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteAssetModal"
                                                data-id="<?php echo htmlspecialchars($asset['asset_id']); ?>"
                                                data-tag="<?php echo htmlspecialchars($asset['fam_tag_number']); ?>"
                                                <?php echo $is_assigned ? 'disabled title="Asset must be revoked from the employee via Transmittal before deletion"' : 'title="Delete Asset"'; ?>
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No assets found.</td>
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

<div class="modal fade" id="deleteAssetModal" tabindex="-1" aria-labelledby="deleteAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="inventory.php">
                <input type="hidden" name="delete_asset" value="1">
                <input type="hidden" name="delete_asset_id" id="delete_asset_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAssetModalLabel">Confirm Asset Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete the asset: <strong><span id="delete_asset_tag"></span></strong>?</p>
                    <p class="text-danger fw-bold">This action cannot be undone and will also delete its transmittal history!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
    
    // Logic for the DELETE modal to populate fields when opened
    var deleteAssetModal = document.getElementById('deleteAssetModal');
    deleteAssetModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Button that triggered the modal
        var assetId = button.getAttribute('data-id');
        var famTag = button.getAttribute('data-tag');
        
        // Populate form fields
        deleteAssetModal.querySelector('#delete_asset_id').value = assetId;
        deleteAssetModal.querySelector('#delete_asset_tag').textContent = famTag;
    });

    // ... (Your existing JavaScript for the Edit Modal should be here) ...

</script>

</body>
</html>