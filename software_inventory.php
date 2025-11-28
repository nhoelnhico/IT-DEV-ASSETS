<?php
require_once 'includes/config.php'; 

// Initialize all required variables 
$message = ''; 
$error_message = ''; 
$search_term = '';
$search_condition = '';
$search_params = [];
$software_items = []; 

// FIX: Initialize metric variables to prevent "Undefined variable" warnings
$total_items = 0; 
$total_licenses_all = 0;
$total_in_use_licenses = 0;


// --- 1. HANDLE SEARCH QUERY ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    // Search by Name, Details, or License Type
    $search_condition = " WHERE s.name LIKE ? OR s.details LIKE ? OR s.license_type LIKE ?"; 
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term]; 
}

// --- 2. HANDLE ADD NEW SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_software'])) {
    // Sanitize and collect data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $details = filter_input(INPUT_POST, 'details', FILTER_SANITIZE_STRING); // Added details
    $license_type = filter_input(INPUT_POST, 'license_type', FILTER_SANITIZE_STRING);
    $total_licenses = filter_input(INPUT_POST, 'total_licenses', FILTER_SANITIZE_NUMBER_INT);

    if (empty($name) || empty($license_type) || $total_licenses === false) {
        $message = '<div class="alert alert-danger">Name, License Type, and Total Licenses are required.</div>';
    } else {
        try {
            $sql = "INSERT INTO software_licenses (name, details, license_type, total_licenses) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $details, $license_type, $total_licenses]);
            $message = '<div class="alert alert-success">Software **' . htmlspecialchars($name) . '** added successfully with ' . $total_licenses . ' licenses.</div>';
        } catch (\PDOException $e) {
            // Error code 23000 is usually for duplicate entry (UNIQUE constraint)
            if ($e->getCode() == 23000) {
                 $message = '<div class="alert alert-warning">A software entry with this name and license type already exists.</div>';
            } else {
                 $message = '<div class="alert alert-danger">Database Error: Could not add software. ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// --- 3. HANDLE EDIT SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_software'])) {
    $software_id = filter_input(INPUT_POST, 'edit_software_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
    $details = filter_input(INPUT_POST, 'edit_details', FILTER_SANITIZE_STRING); // Added details
    $license_type = filter_input(INPUT_POST, 'edit_license_type', FILTER_SANITIZE_STRING);
    $total_licenses = filter_input(INPUT_POST, 'edit_total_licenses', FILTER_SANITIZE_NUMBER_INT);
    
    // Fetch licenses in use for validation
    $in_use_check = $pdo->prepare("SELECT licenses_in_use FROM software_licenses WHERE software_id = ?");
    $in_use_check->execute([$software_id]);
    $licenses_in_use = $in_use_check->fetchColumn();

    if ($total_licenses < $licenses_in_use) {
        $message = '<div class="alert alert-danger">ERROR: Total licenses cannot be less than the ' . $licenses_in_use . ' licenses currently in use.</div>';
    } elseif (empty($software_id) || empty($name) || empty($license_type) || $total_licenses === false) {
        $message = '<div class="alert alert-danger">All fields are required for update.</div>';
    } else {
        try {
            $sql = "UPDATE software_licenses SET name = ?, details = ?, license_type = ?, total_licenses = ? WHERE software_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $details, $license_type, $total_licenses, $software_id]);
            $message = '<div class="alert alert-success">Software **' . htmlspecialchars($name) . '** updated successfully.</div>';
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger">Database Error: Could not update software. ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- 4. HANDLE DELETE SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_software'])) {
    $software_id_to_delete = filter_input(INPUT_POST, 'delete_software_id', FILTER_SANITIZE_NUMBER_INT);
    $software_name = filter_input(INPUT_POST, 'delete_software_name_hidden', FILTER_SANITIZE_STRING);

    // Check if any licenses are currently in use
    $check_stmt = $pdo->prepare("SELECT licenses_in_use FROM software_licenses WHERE software_id = ?");
    $check_stmt->execute([$software_id_to_delete]);
    $licenses_in_use = $check_stmt->fetchColumn();

    if ($licenses_in_use > 0) {
        $message = '<div class="alert alert-danger">ERROR: Cannot delete **' . htmlspecialchars($software_name) . '** because ' . $licenses_in_use . ' license(s) are currently in use. Revoke all licenses first.</div>';
    } else {
        try {
            // Delete the software record
            $sql = "DELETE FROM software_licenses WHERE software_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$software_id_to_delete]);

            $message = '<div class="alert alert-success">Software **' . htmlspecialchars($software_name) . '** deleted successfully.</div>';
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger">Database Error: Could not delete software.</div>';
        }
    }
}


// --- 5. FETCH SOFTWARE DATA ---
try {
    // Fetch metrics
    $metrics_stmt = $pdo->query("SELECT COUNT(*) AS total_items, SUM(total_licenses) AS total_licenses, SUM(licenses_in_use) AS in_use FROM software_licenses");
    $metrics = $metrics_stmt->fetch();
    $total_items = $metrics['total_items'] ?? 0;
    $total_licenses_all = $metrics['total_licenses'] ?? 0;
    $total_in_use_licenses = $metrics['in_use'] ?? 0;

    // Fetch list of software items
    $sql = "
        SELECT 
            s.software_id, s.name, s.details, s.license_type, s.total_licenses, s.licenses_in_use
        FROM 
            software_licenses s
        {$search_condition}
        ORDER BY s.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($search_params);
    $software_items = $stmt->fetchAll();

} catch (\PDOException $e) {
    $error_message = '<div class="alert alert-danger">Database Error: Could not load data.</div>';
    error_log("Software Fetch Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Software Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Replicate the sidebar and main content wrapper styles from other pages */
        body { background-color: #f8f9fa; }
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        /* Add active styling for this new page */
        .sidebar-nav a[href="software_inventory.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } 
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
            <a class="list-group-item list-group-item-action bg-dark" href="inventory.php">📦 Inventory</a>
            <a class="list-group-item list-group-item-action bg-dark" href="software_inventory.php">💾 Software</a> <a class="list-group-item list-group-item-action bg-dark" href="transmittal.php">📝 Transmittal Log</a>
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
            <h1 class="mt-4 mb-4">💾 Software License Management</h1>
            
            <?php echo $message; ?>
            <?php echo $error_message; ?>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-info text-white shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Total Software Items</h5>
                                <i class="bi bi-gear-fill display-6"></i>
                            </div>
                            <p class="card-text fs-3 fw-bold"><?php echo $total_items; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-primary text-white shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Total Licenses Owned</h5>
                                <i class="bi bi-bookmark-fill display-6"></i>
                            </div>
                            <p class="card-text fs-3 fw-bold"><?php echo $total_licenses_all; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Licenses Available</h5>
                                <i class="bi bi-check-circle-fill display-6"></i>
                            </div>
                            <p class="card-text fs-3 fw-bold"><?php echo $total_licenses_all - $total_in_use_licenses; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6 d-flex">
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addSoftwareModal">
                        <i class="bi bi-plus-circle"></i> Add New Software
                    </button>
                    <a href="software_allocation.php" class="btn btn-warning">
                        <i class="bi bi-person-lines-fill"></i> Allocate Licenses
                    </a>
                </div>
                <div class="col-md-6">
                    <form method="GET" action="software_inventory.php" class="d-flex">
                        <input type="search" name="search" class="form-control me-2" placeholder="Search by Name, Details, or License Type" value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        <a href="software_inventory.php" class="btn btn-outline-danger ms-2" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-5">
                <div class="card-header bg-secondary text-white fw-bold">Software List</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Software Name</th>
                                    <th>Details/Purpose</th>
                                    <th>Payment Type</th>
                                    <th class="text-center">Total Licenses</th>
                                    <th class="text-center">Licenses In Use</th>
                                    <th class="text-center">Licenses Available</th>
                                    <th style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($software_items)): ?>
                                    <?php foreach ($software_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['software_id']); ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['details'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-dark"><?php echo htmlspecialchars($item['license_type']); ?></span>
                                        </td>
                                        <td class="text-center"><?php echo htmlspecialchars($item['total_licenses']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($item['licenses_in_use']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?php echo $item['total_licenses'] - $item['licenses_in_use']; ?></span>
                                        </td>
                                        <td>
                                            <button 
                                                class="btn btn-sm btn-outline-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editSoftwareModal"
                                                data-id="<?php echo htmlspecialchars($item['software_id']); ?>"
                                                data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                data-details="<?php echo htmlspecialchars($item['details']); ?>"
                                                data-type="<?php echo htmlspecialchars($item['license_type']); ?>"
                                                data-total="<?php echo htmlspecialchars($item['total_licenses']); ?>"
                                                data-inuse="<?php echo htmlspecialchars($item['licenses_in_use']); ?>"
                                                title="Edit Software Details"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <button 
                                                class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteSoftwareModal"
                                                data-id="<?php echo htmlspecialchars($item['software_id']); ?>"
                                                data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                <?php echo ($item['licenses_in_use'] > 0) ? 'disabled title="Cannot delete: licenses in use"' : 'title="Delete Software"'; ?>
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No software found.</td>
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

<div class="modal fade" id="addSoftwareModal" tabindex="-1" aria-labelledby="addSoftwareModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="software_inventory.php">
                <input type="hidden" name="add_software" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addSoftwareModalLabel">Add New Software License</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Software Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="details" class="form-label">Details or Purpose</label>
                        <textarea class="form-control" id="details" name="details" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="license_type" class="form-label">Payment Type</label>
                        <select class="form-select" id="license_type" name="license_type" required>
                            <option value="">Select Type...</option>
                            <option value="Subscription">Subscription</option>
                            <option value="Fixed Payment">Fixed Payment</option>
                            <option value="Perpetual">Perpetual</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="total_licenses" class="form-label">Total Licenses Purchased</label>
                        <input type="number" class="form-control" id="total_licenses" name="total_licenses" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Software</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSoftwareModal" tabindex="-1" aria-labelledby="editSoftwareModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="software_inventory.php">
                <input type="hidden" name="edit_software" value="1">
                <input type="hidden" name="edit_software_id" id="edit_software_id">
                <input type="hidden" id="edit_licenses_in_use"> <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editSoftwareModalLabel">Edit Software License</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Software Name</label>
                        <input type="text" class="form-control" id="edit_name" name="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_details" class="form-label">Details or Purpose</label>
                        <textarea class="form-control" id="edit_details" name="edit_details" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_license_type" class="form-label">Payment Type</label>
                        <select class="form-select" id="edit_license_type" name="edit_license_type" required>
                            <option value="Subscription">Subscription</option>
                            <option value="Fixed Payment">Fixed Payment</option>
                            <option value="Perpetual">Perpetual</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_total_licenses" class="form-label">Total Licenses Purchased</label>
                        <p class="text-muted small mb-1">Currently in Use: <strong id="current_in_use_display">0</strong></p>
                        <input type="number" class="form-control" id="edit_total_licenses" name="edit_total_licenses" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark">Update Software</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteSoftwareModal" tabindex="-1" aria-labelledby="deleteSoftwareModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="software_inventory.php">
                <input type="hidden" name="delete_software" value="1">
                <input type="hidden" name="delete_software_id" id="delete_software_id">
                <input type="hidden" name="delete_software_name_hidden" id="delete_software_name_hidden">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteSoftwareModalLabel">Confirm Software Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete the software: <strong><span id="delete_software_name"></span></strong>?</p>
                    <p class="text-danger fw-bold">This will remove the software from the inventory and all its allocation records!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete Software</button>
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
    
    // Logic for the EDIT modal to populate fields when opened
    var editSoftwareModal = document.getElementById('editSoftwareModal');
    editSoftwareModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Button that triggered the modal
        var softwareId = button.getAttribute('data-id');
        var name = button.getAttribute('data-name');
        var details = button.getAttribute('data-details');
        var type = button.getAttribute('data-type');
        var total = button.getAttribute('data-total');
        var inUse = button.getAttribute('data-inuse');
        
        // Populate form fields
        editSoftwareModal.querySelector('#edit_software_id').value = softwareId;
        editSoftwareModal.querySelector('#edit_name').value = name;
        editSoftwareModal.querySelector('#edit_details').value = details;
        editSoftwareModal.querySelector('#edit_license_type').value = type;
        editSoftwareModal.querySelector('#edit_total_licenses').value = total;
        editSoftwareModal.querySelector('#edit_licenses_in_use').value = inUse; // Hidden field to check against
        
        // Update display text
        editSoftwareModal.querySelector('.modal-title').textContent = 'Edit Software: ' + name;
        editSoftwareModal.querySelector('#current_in_use_display').textContent = inUse;
        
        // Set min attribute for safety
        editSoftwareModal.querySelector('#edit_total_licenses').min = inUse;
    });

    // Logic for the DELETE modal to populate fields when opened
    var deleteSoftwareModal = document.getElementById('deleteSoftwareModal');
    deleteSoftwareModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Button that triggered the modal
        var softwareId = button.getAttribute('data-id');
        var name = button.getAttribute('data-name');
        
        // Populate form fields
        deleteSoftwareModal.querySelector('#delete_software_id').value = softwareId;
        deleteSoftwareModal.querySelector('#delete_software_name_hidden').value = name;
        deleteSoftwareModal.querySelector('#delete_software_name').textContent = name;
    });

</script>

</body>
</html>