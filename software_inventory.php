<?php
require_once 'includes/config.php'; 

// Initialize all required variables 
$message = ''; 
$error_message = '';
$search_term = '';
$search_condition = '';
$search_params = [];
$software_items = []; 

// Initialize metric variables
$total_items = 0; 
$total_licenses_all = 0;
$total_in_use_licenses = 0;


// --- 1. HANDLE SEARCH QUERY ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    // Search by Name, Version, or License Type
    $search_condition = " WHERE s.name LIKE ? OR s.version LIKE ? OR s.license_type LIKE ?"; 
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term]; 
}

// --- 2. HANDLE ADD NEW SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_software'])) {
    // Sanitize and collect data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $version = filter_input(INPUT_POST, 'version', FILTER_SANITIZE_STRING);
    $license_type = filter_input(INPUT_POST, 'license_type', FILTER_SANITIZE_STRING);
    $total_licenses = filter_input(INPUT_POST, 'total_licenses', FILTER_SANITIZE_NUMBER_INT);

    if (empty($name) || empty($license_type) || $total_licenses === null) {
        $message = '<div class="alert alert-danger">All fields except Version are required.</div>';
    } else {
        try {
            // Prepare the SQL INSERT statement
            $sql = "INSERT INTO software_items (name, version, license_type, total_licenses) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // Execute the statement
            $stmt->execute([$name, $version, $license_type, $total_licenses]);

            $message = '<div class="alert alert-success">Software <strong>' . htmlspecialchars($name) . '</strong> added successfully!</div>';
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger">Error adding software: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- 3. HANDLE UPDATE SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_software'])) {
    // Sanitize and collect data
    $software_id = filter_input(INPUT_POST, 'edit_software_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
    $version = filter_input(INPUT_POST, 'edit_version', FILTER_SANITIZE_STRING);
    $license_type = filter_input(INPUT_POST, 'edit_license_type', FILTER_SANITIZE_STRING);
    $total_licenses = filter_input(INPUT_POST, 'edit_total_licenses', FILTER_SANITIZE_NUMBER_INT);
    $licenses_in_use = filter_input(INPUT_POST, 'edit_licenses_in_use', FILTER_SANITIZE_NUMBER_INT);

    if (empty($name) || empty($license_type) || $total_licenses === null || $total_licenses < $licenses_in_use) {
        $message = '<div class="alert alert-danger">Invalid input or total licenses cannot be less than licenses in use (' . $licenses_in_use . ').</div>';
    } else {
        try {
            // Prepare the SQL UPDATE statement
            $sql = "UPDATE software_items SET name = ?, version = ?, license_type = ?, total_licenses = ? WHERE software_id = ?";
            $stmt = $pdo->prepare($sql);
            
            // Execute the statement
            $stmt->execute([$name, $version, $license_type, $total_licenses, $software_id]);

            $message = '<div class="alert alert-success">Software <strong>' . htmlspecialchars($name) . '</strong> updated successfully!</div>';
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger">Error updating software: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- 4. HANDLE DELETE SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_software'])) {
    $software_id = filter_input(INPUT_POST, 'delete_software_id', FILTER_SANITIZE_NUMBER_INT);
    
    try {
        // Check against the new software_assignments table
        $check_sql = "SELECT COUNT(*) FROM software_assignments WHERE software_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$software_id]);
        $active_assignments = $check_stmt->fetchColumn();

        if ($active_assignments > 0) {
            $message = '<div class="alert alert-danger">Cannot delete software. It has ' . $active_assignments . ' active assignments. Please unassign licenses first.</div>';
        } else {
            // Prepare the SQL DELETE statement
            $sql = "DELETE FROM software_items WHERE software_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$software_id]);
            $message = '<div class="alert alert-success">Software deleted successfully!</div>';
        }
    } catch (\PDOException $e) {
        $message = '<div class="alert alert-danger">Error deleting software: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}


// --- 5. Database Fetch Block (Metrics & Main List) ---
try {
    // Total Software Titles 
    $total_items = $pdo->query("SELECT COUNT(software_id) FROM software_items")->fetchColumn();
    
    // Total Licenses Owned 
    $total_licenses_all = $pdo->query("SELECT COALESCE(SUM(total_licenses), 0) FROM software_items")->fetchColumn();
    
    // Licenses In Use - Must use the software_assignments table
    $total_in_use_licenses = $pdo->query("SELECT COUNT(*) FROM software_assignments WHERE status = 'Active'")->fetchColumn();


    // 3. Fetch all software items
    $sql = "
        SELECT 
            s.software_id, s.name, s.version, s.license_type, s.total_licenses,
            COALESCE(SUM(CASE WHEN sa.status = 'Active' THEN 1 ELSE 0 END), 0) AS licenses_in_use
        FROM 
            software_items s
        LEFT JOIN 
            software_assignments sa ON s.software_id = sa.software_id
        {$search_condition}
        GROUP BY
            s.software_id, s.name, s.version, s.license_type, s.total_licenses
        ORDER BY 
            s.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($search_params);
    $software_items = $stmt->fetchAll();

} catch (\PDOException $e) {
    // Catch the SQL error and store it in $error_message
    $error_message = "Error fetching software: SQLSTATE[" . $e->getCode() . "] " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Inventory - IT Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Base Layout Styles (consistent across all pages) */
        #sidebar-wrapper { 
            min-height: 100vh; 
            margin-left: -15rem; 
            transition: margin .25s ease-out; 
            background-color: #343a40; 
        }
        #sidebar-wrapper .sidebar-heading { 
            padding: 0.875rem 1.25rem; 
            font-size: 1.2rem; 
            color: #ffffff; 
        }
        #page-content-wrapper { 
            min-width: 100vw; 
        }
        .sidebar-nav a { 
            color: #adb5bd; 
            padding: 1rem 1.25rem; 
            display: block; 
            text-decoration: none; 
        }
        .sidebar-nav a:hover { 
            background-color: #495057; 
            color: #ffffff; 
        }
        .sidebar-nav a[href="software_inventory.php"] { 
            background-color: #0d6efd; 
            color: #ffffff; 
            border-left: 5px solid #ffc107; 
        } 
        @media (min-width: 768px) { 
            #sidebar-wrapper { 
                margin-left: 0; 
            } 
            #page-content-wrapper { 
                min-width: 0; 
                width: 100%; 
            } 
        }

        /* Custom Card Styles for Metrics */
        .custom-card {
            border-left: 5px solid;
            border-radius: 0.375rem;
        }
        .custom-card-primary { border-left-color: #0d6efd; }
        .custom-card-success { border-left-color: #198754; }
        .custom-card-warning { border-left-color: #ffc107; }
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
          <a class="list-group-item list-group-item-action bg-dark" href="software_assignment.php">🔑 License Assignment</a>
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
            <h1 class="mt-4">Software Inventory</h1>
            
            <?php 
                // Display the main action message (success/error from POST operations)
                if (!empty($message)) {
                    echo $message;
                }
                // Display the database fetching error if one occurred
                if (!empty($error_message)) {
                    echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($error_message) . '</div>';
                }
            ?>

            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card shadow-sm h-100 custom-card custom-card-primary">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Total Software Titles</h5>
                            <h2 class="card-text"><?php echo number_format($total_items); ?></h2> 
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card shadow-sm h-100 custom-card custom-card-success">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Total Licenses Owned</h5>
                            <h2 class="card-text"><?php echo number_format($total_licenses_all); ?></h2> 
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card shadow-sm h-100 custom-card custom-card-warning">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Licenses In Use</h5>
                            <h2 class="card-text"><?php echo number_format($total_in_use_licenses); ?></h2> 
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSoftwareModal">
                    <i class="bi bi-plus-circle"></i> Add New Software
                </button>
                <form class="d-flex" method="GET" action="software_inventory.php">
                    <input class="form-control me-2" type="search" placeholder="Search Name, Version, Type" aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    <?php if (!empty($search_term)): ?>
                        <a href="software_inventory.php" class="btn btn-outline-danger ms-2"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header">
                    Software List
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Version</th>
                                    <th>License Type</th>
                                    <th>Total Licenses</th>
                                    <th>Licenses In Use</th>
                                    <th>Available Licenses</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($software_items) > 0): ?>
                                    <?php foreach ($software_items as $software): 
                                        $available_licenses = $software['total_licenses'] - $software['licenses_in_use'];
                                        $license_badge_class = $available_licenses > 0 ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($software['name']); ?></td>
                                        <td><?php echo htmlspecialchars($software['version']); ?></td>
                                        <td><?php echo htmlspecialchars($software['license_type']); ?></td>
                                        <td><?php echo number_format($software['total_licenses']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo number_format($software['licenses_in_use']); ?></span></td>
                                        <td><span class="badge <?php echo $license_badge_class; ?>"><?php echo number_format($available_licenses); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editSoftwareModal"
                                                data-id="<?php echo htmlspecialchars($software['software_id']); ?>"
                                                data-name="<?php echo htmlspecialchars($software['name']); ?>"
                                                data-version="<?php echo htmlspecialchars($software['version']); ?>"
                                                data-type="<?php echo htmlspecialchars($software['license_type']); ?>"
                                                data-total="<?php echo htmlspecialchars($software['total_licenses']); ?>"
                                                data-inuse="<?php echo htmlspecialchars($software['licenses_in_use']); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteSoftwareModal"
                                                data-id="<?php echo htmlspecialchars($software['software_id']); ?>"
                                                data-name="<?php echo htmlspecialchars($software['name']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            <?php if (!empty($search_term)): ?>
                                                No software found matching "<?php echo htmlspecialchars($search_term); ?>".
                                            <?php else: ?>
                                                No software titles recorded.
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

<div class="modal fade" id="addSoftwareModal" tabindex="-1" aria-labelledby="addSoftwareModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="software_inventory.php">
        <div class="modal-header">
          <h5 class="modal-title" id="addSoftwareModalLabel">Add New Software Title</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="add_software" value="1">
            <div class="mb-3">
                <label for="name" class="form-label">Software Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="version" class="form-label">Version (Optional)</label>
                <input type="text" class="form-control" id="version" name="version">
            </div>
            <div class="mb-3">
                <label for="license_type" class="form-label">License Type</label>
                <select class="form-select" id="license_type" name="license_type" required>
                    <option value="">Select Type</option>
                    <option value="Perpetual">Perpetual</option>
                    <option value="Subscription">Subscription</option>
                    <option value="Volume">Volume License</option>
                    <option value="OEM">OEM</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="total_licenses" class="form-label">Total Licenses Purchased</label>
                <input type="number" class="form-control" id="total_licenses" name="total_licenses" min="1" value="1" required>
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
        <div class="modal-header">
          <h5 class="modal-title" id="editSoftwareModalLabel">Edit Software: [Name (Version)]</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="update_software" value="1">
            <input type="hidden" id="edit_software_id" name="edit_software_id">
            <input type="hidden" id="edit_licenses_in_use" name="edit_licenses_in_use"> 
            <p class="alert alert-info small">Currently **<span id="current_in_use_display">0</span>** licenses are in use. Total licenses cannot be set below this number.</p>

            <div class="mb-3">
                <label for="edit_name" class="form-label">Software Name</label>
                <input type="text" class="form-control" id="edit_name" name="edit_name" required>
            </div>
            <div class="mb-3">
                <label for="edit_version" class="form-label">Version (Optional)</label>
                <input type="text" class="form-control" id="edit_version" name="edit_version">
            </div>
            <div class="mb-3">
                <label for="edit_license_type" class="form-label">License Type</label>
                <select class="form-select" id="edit_license_type" name="edit_license_type" required>
                    <option value="Perpetual">Perpetual</option>
                    <option value="Subscription">Subscription</option>
                    <option value="Volume">Volume License</option>
                    <option value="OEM">OEM</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="edit_total_licenses" class="form-label">Total Licenses Purchased</label>
                <input type="number" class="form-control" id="edit_total_licenses" name="edit_total_licenses" min="1" required>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteSoftwareModal" tabindex="-1" aria-labelledby="deleteSoftwareModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="software_inventory.php">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteSoftwareModalLabel">Confirm Deletion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="delete_software" value="1">
            <input type="hidden" id="delete_software_id" name="delete_software_id">
            <p>Are you sure you want to permanently delete the software title: <strong><span id="delete_software_name"></span></strong>?</p>
            <p class="text-danger small">**WARNING:** This will fail if there are any active licenses assigned to employees.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete Software</button>
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
        var version = button.getAttribute('data-version');
        var type = button.getAttribute('data-type');
        var total = button.getAttribute('data-total');
        var inUse = button.getAttribute('data-inuse');

        // Populate form fields
        editSoftwareModal.querySelector('#edit_software_id').value = softwareId;
        editSoftwareModal.querySelector('#edit_name').value = name;
        editSoftwareModal.querySelector('#edit_version').value = version;
        editSoftwareModal.querySelector('#edit_license_type').value = type;
        editSoftwareModal.querySelector('#edit_total_licenses').value = total;
        editSoftwareModal.querySelector('#edit_licenses_in_use').value = inUse; // Hidden field to check against
        
        // Update display text
        editSoftwareModal.querySelector('.modal-title').textContent = 'Edit Software: ' + name + ' (' + version + ')';
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
        deleteSoftwareModal.querySelector('#delete_software_name').textContent = name;
    });

</script>

</body>
</html>