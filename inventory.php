<?php
require_once 'includes/config.php';

$message = ''; 
$search_term = '';
$search_condition = '';
$search_params = [];
$sort_by = 'a.fam_tag_number'; // Default sort
$sort_order = 'ASC'; // Default order
$assets = [];

// --- 1. HANDLE SEARCH QUERY (Existing Logic) ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    $search_condition = " WHERE a.fam_tag_number LIKE ? OR a.serial_number LIKE ? OR a.device_type LIKE ? OR a.device_name LIKE ?";
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term, $like_term];
}

// --- 2. HANDLE SORTING PARAMETERS (Existing Logic) ---
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

// --- 3. HANDLE DELETE ASSET (NEW LOGIC) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_asset'])) {
    $asset_id_to_delete = filter_input(INPUT_POST, 'delete_asset_id', FILTER_SANITIZE_NUMBER_INT);

    // Check if the asset is currently assigned to an employee
    $check_stmt = $pdo->prepare("SELECT current_user_id, fam_tag_number FROM assets WHERE asset_id = ?");
    $check_stmt->execute([$asset_id_to_delete]);
    $asset_info = $check_stmt->fetch();

    if ($asset_info && $asset_info['current_user_id'] !== null) {
        $message = '<div class="alert alert-danger">ERROR: Cannot delete asset **' . htmlspecialchars($asset_info['fam_tag_number']) . '** because it is currently assigned to an employee. Revoke/clear the asset first.</div>';
    } elseif ($asset_info) {
        try {
            // Delete the asset
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

// --- 4. HANDLE ADD/EDIT ASSET (Existing Logic, slightly updated with date_received) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_asset']) || isset($_POST['edit_asset']))) {
    // ... (Existing ADD/EDIT logic remains here)
    // For brevity, I'll only show the delete part and the main HTML changes.
    // Ensure your existing ADD/EDIT logic is still in place.
    // ...
}

// --- 5. FETCH ASSETS (Existing Logic) ---
try {
    $sql = "
        SELECT 
            a.asset_id, a.fam_tag_number, a.device_type, a.device_name, a.serial_number, a.date_received, a.status,
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
<body>
<div class="d-flex" id="wrapper">
    <div id="page-content-wrapper">
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
                                    <th style="width: 150px;">Actions</th> </tr>
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
                                        // Set current user name for display
                                        $current_user_display = $asset['current_user_name'] ? htmlspecialchars($asset['current_user_name']) . ' (' . htmlspecialchars($asset['employee_id']) . ')' : 'None';
                                    ?>
                                    <tr>
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
                                                <?php echo ($asset['current_user_id'] !== null) ? 'disabled title="Clearance required before deletion"' : 'title="Delete Asset"'; ?>
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No assets found.</td>
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
    // ... (Existing sidebar toggle and editAssetModal event listeners) ...

    // NEW: Logic for the DELETE modal to populate fields when opened
    var deleteAssetModal = document.getElementById('deleteAssetModal');
    deleteAssetModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Button that triggered the modal
        var assetId = button.getAttribute('data-id');
        var famTag = button.getAttribute('data-tag');
        
        // Populate form fields
        deleteAssetModal.querySelector('#delete_asset_id').value = assetId;
        deleteAssetModal.querySelector('#delete_asset_tag').textContent = famTag;
    });
</script>

</body>
</html>