<?php
require_once 'includes/config.php';

// Initialize variables 
$message = ''; 
$error_message = '';
$search_term = '';
$search_condition = '';
$search_params = [];
$software_items = []; 
$total_items = 0; 
$total_licenses_all = 0;
$total_in_use_licenses = 0;

// --- 1. HANDLE SEARCH QUERY ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    $search_condition = " WHERE s.name LIKE ? OR s.version LIKE ? OR s.license_type LIKE ?"; 
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term]; 
}

// --- 2. HANDLE ADD NEW SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_software'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $version = filter_input(INPUT_POST, 'version', FILTER_SANITIZE_STRING);
    $license_type = filter_input(INPUT_POST, 'license_type', FILTER_SANITIZE_STRING);
    $total_licenses = filter_input(INPUT_POST, 'total_licenses', FILTER_SANITIZE_NUMBER_INT);

    if (empty($name) || empty($license_type) || $total_licenses === null) {
        $message = '<div class="alert alert-danger shadow-sm border-0">All fields except Version are required.</div>';
    } else {
        try {
            $sql = "INSERT INTO software_items (name, version, license_type, total_licenses) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $version, $license_type, $total_licenses]);
            $message = '<div class="alert alert-success shadow-sm border-0">Software <strong>' . htmlspecialchars($name) . '</strong> added successfully!</div>';
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger shadow-sm border-0">Error adding software: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- 3. HANDLE UPDATE SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_software'])) {
    $software_id = filter_input(INPUT_POST, 'edit_software_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
    $version = filter_input(INPUT_POST, 'edit_version', FILTER_SANITIZE_STRING);
    $license_type = filter_input(INPUT_POST, 'edit_license_type', FILTER_SANITIZE_STRING);
    $total_licenses = filter_input(INPUT_POST, 'edit_total_licenses', FILTER_SANITIZE_NUMBER_INT);
    $licenses_in_use = filter_input(INPUT_POST, 'edit_licenses_in_use', FILTER_SANITIZE_NUMBER_INT);

    if (empty($name) || empty($license_type) || $total_licenses === null || $total_licenses < $licenses_in_use) {
        $message = '<div class="alert alert-danger shadow-sm border-0">Invalid input or total licenses cannot be less than licenses in use (' . $licenses_in_use . ').</div>';
    } else {
        try {
            $sql = "UPDATE software_items SET name = ?, version = ?, license_type = ?, total_licenses = ? WHERE software_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $version, $license_type, $total_licenses, $software_id]);
            $message = '<div class="alert alert-success shadow-sm border-0">Software <strong>' . htmlspecialchars($name) . '</strong> updated successfully!</div>';
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger shadow-sm border-0">Error updating software: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- 4. HANDLE DELETE SOFTWARE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_software'])) {
    $software_id = filter_input(INPUT_POST, 'delete_software_id', FILTER_SANITIZE_NUMBER_INT);
    
    try {
        $check_sql = "SELECT COUNT(*) FROM software_assignments WHERE software_id = ? AND status = 'Active'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$software_id]);
        $active_assignments = $check_stmt->fetchColumn();

        if ($active_assignments > 0) {
            $message = '<div class="alert alert-danger shadow-sm border-0">Cannot delete software. It has ' . $active_assignments . ' active assignments. Please unassign licenses first.</div>';
        } else {
            $sql = "DELETE FROM software_items WHERE software_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$software_id]);
            $message = '<div class="alert alert-success shadow-sm border-0">Software deleted successfully!</div>';
        }
    } catch (\PDOException $e) {
        $message = '<div class="alert alert-danger shadow-sm border-0">Error deleting software: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}


// --- 5. Database Fetch Block ---
try {
    $total_items = $pdo->query("SELECT COUNT(software_id) FROM software_items")->fetchColumn();
    $total_licenses_all = $pdo->query("SELECT COALESCE(SUM(total_licenses), 0) FROM software_items")->fetchColumn();
    $total_in_use_licenses = $pdo->query("SELECT COUNT(*) FROM software_assignments WHERE status = 'Active'")->fetchColumn();

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
    $error_message = "Error fetching software: SQLSTATE[" . $e->getCode() . "] " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Software</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            /* Consistent Palette */
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

        /* Sidebar Styling */
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
        .sidebar-nav a:hover {
            background-color: rgba(255,255,255,0.05);
            color: #fff;
        }
        .sidebar-nav a.active {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
            border-left: 4px solid var(--info-color);
        }
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }
        
        /* Metric Cards */
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            background: #fff;
            height: 100%;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .card-body { padding: 1.5rem; position: relative; }
        .border-left-primary { border-left: 5px solid var(--primary-color) !important; }
        .border-left-success { border-left: 5px solid var(--success-color) !important; }
        .border-left-warning { border-left: 5px solid var(--warning-color) !important; }
        .text-xs { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
        .h5-number { font-size: 1.8rem; font-weight: 700; color: #5a5c69; margin-bottom: 0; }
        .icon-box { opacity: 0.2; transform: rotate(-10deg); position: absolute; right: 20px; top: 25px; font-size: 2.5rem; }

        /* Content Card */
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

        /* Table Styling */
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
        
        /* Badges */
        .badge-soft { padding: 0.5em 0.8em; font-weight: 600; border-radius: 0.35rem; }
        .badge-soft-success { background-color: rgba(28, 200, 138, 0.1); color: var(--success-color); }
        .badge-soft-danger { background-color: rgba(231, 74, 59, 0.1); color: var(--danger-color); }
        .badge-soft-primary { background-color: rgba(78, 115, 223, 0.1); color: var(--primary-color); }
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
            <a href="software_inventory.php" class="active"><i class="bi bi-disc"></i> Software</a> 
            <a href="software_assignment.php"><i class="bi bi-key"></i> Licenses</a>
            <a href="transmittal.php"><i class="bi bi-arrow-left-right"></i> Transmittals</a>
            <a href="employee_clearance.php"><i class="bi bi-file-earmark-check"></i> Clearance</a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i> Menu</button>
            <div class="ms-auto text-secondary small fw-bold">Software Assets</div>
        </nav>

        <div class="container-fluid p-4">
            <h3 class="mb-4 text-dark fw-bold">Software Inventory</h3>
            
            <?php 
                if (!empty($message)) echo $message;
                if (!empty($error_message)) echo '<div class="alert alert-danger shadow-sm border-0">' . htmlspecialchars($error_message) . '</div>';
            ?>

            <div class="row g-4 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="stat-card border-left-primary">
                        <div class="card-body">
                            <div class="text-xs text-primary">Total Software Titles</div>
                            <div class="h5-number"><?php echo number_format($total_items); ?></div>
                            <i class="bi bi-disc-fill icon-box text-primary"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="stat-card border-left-success">
                        <div class="card-body">
                            <div class="text-xs text-success">Total Licenses Owned</div>
                            <div class="h5-number"><?php echo number_format($total_licenses_all); ?></div>
                            <i class="bi bi-files icon-box text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="stat-card border-left-warning">
                        <div class="card-body">
                            <div class="text-xs text-warning">Licenses Assigned</div>
                            <div class="h5-number"><?php echo number_format($total_in_use_licenses); ?></div>
                            <i class="bi bi-person-check-fill icon-box text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <span class="me-3"><i class="bi bi-table me-2"></i> Software Registry</span>
                        <button class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addSoftwareModal">
                            <i class="bi bi-plus-lg"></i> Add New
                        </button>
                    </div>
                    
                    <form method="GET" action="software_inventory.php" class="d-flex" style="width: 280px;">
                        <div class="input-group input-group-sm">
                            <input 
                                class="form-control" 
                                type="search" 
                                placeholder="Search Name, Version..." 
                                aria-label="Search" 
                                name="search"
                                value="<?php echo htmlspecialchars($search_term); ?>"
                            >
                            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
                            <?php if (!empty($search_term)): ?>
                                <a href="software_inventory.php" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover">
                            <thead>
                                <tr>
                                    <th class="ps-4">Software Name</th>
                                    <th>Version</th>
                                    <th>License Type</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Assigned</th>
                                    <th class="text-center">Available</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($software_items) > 0): ?>
                                    <?php foreach ($software_items as $software): 
                                        $available_licenses = $software['total_licenses'] - $software['licenses_in_use'];
                                        $avail_badge = $available_licenses > 0 ? 'badge-soft-success' : 'badge-soft-danger';
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($software['name']); ?></td>
                                        <td><?php echo htmlspecialchars($software['version']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($software['license_type']); ?></span></td>
                                        <td class="text-center fw-bold text-secondary"><?php echo number_format($software['total_licenses']); ?></td>
                                        <td class="text-center"><span class="badge badge-soft-primary"><?php echo number_format($software['licenses_in_use']); ?></span></td>
                                        <td class="text-center"><span class="badge <?php echo $avail_badge; ?>"><?php echo number_format($available_licenses); ?></span></td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-warning"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editSoftwareModal"
                                                    data-id="<?php echo htmlspecialchars($software['software_id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($software['name']); ?>"
                                                    data-version="<?php echo htmlspecialchars($software['version']); ?>"
                                                    data-type="<?php echo htmlspecialchars($software['license_type']); ?>"
                                                    data-total="<?php echo htmlspecialchars($software['total_licenses']); ?>"
                                                    data-inuse="<?php echo htmlspecialchars($software['licenses_in_use']); ?>">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>
                                                <button class="btn btn-outline-danger"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteSoftwareModal"
                                                    data-id="<?php echo htmlspecialchars($software['software_id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($software['name']); ?>">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <?php if (!empty($search_term)): ?>
                                                No software found matching "<?php echo htmlspecialchars($search_term); ?>".
                                            <?php else: ?>
                                                No software titles found. Click "Add New" to get started.
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

<div class="modal fade" id="addSoftwareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header border-0 bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Add Software</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="software_inventory.php">
        <div class="modal-body p-4">
            <input type="hidden" name="add_software" value="1">
            <div class="mb-3">
                <label class="form-label">Software Name</label>
                <input type="text" class="form-control" name="name" required placeholder="e.g. Microsoft Office 2021">
            </div>
            <div class="mb-3">
                <label class="form-label">Version (Optional)</label>
                <input type="text" class="form-control" name="version" placeholder="e.g. 2108 Build 14326">
            </div>
            <div class="mb-3">
                <label class="form-label">License Type</label>
                <select class="form-select" name="license_type" required>
                    <option value="">Select Type...</option>
                    <option value="Perpetual">Perpetual</option>
                    <option value="Subscription">Subscription</option>
                    <option value="Volume">Volume License</option>
                    <option value="OEM">OEM</option>
                    <option value="Free/Open Source">Free/Open Source</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Total Licenses Purchased</label>
                <input type="number" class="form-control" name="total_licenses" min="1" value="1" required>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary shadow-sm">Save Software</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editSoftwareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header border-0 bg-warning text-dark">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Software</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="software_inventory.php">
        <div class="modal-body p-4">
            <input type="hidden" name="update_software" value="1">
            <input type="hidden" id="edit_software_id" name="edit_software_id">
            <input type="hidden" id="edit_licenses_in_use" name="edit_licenses_in_use">
            
            <div class="alert alert-info py-2 small mb-3">
                <i class="bi bi-info-circle-fill me-1"></i> Licenses In Use: <strong><span id="current_in_use_display">0</span></strong>. Total cannot be lower than this.
            </div>

            <div class="mb-3">
                <label class="form-label">Software Name</label>
                <input type="text" class="form-control" id="edit_name" name="edit_name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Version</label>
                <input type="text" class="form-control" id="edit_version" name="edit_version">
            </div>
            <div class="mb-3">
                <label class="form-label">License Type</label>
                <select class="form-select" id="edit_license_type" name="edit_license_type" required>
                    <option value="Perpetual">Perpetual</option>
                    <option value="Subscription">Subscription</option>
                    <option value="Volume">Volume License</option>
                    <option value="OEM">OEM</option>
                    <option value="Free/Open Source">Free/Open Source</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Total Licenses</label>
                <input type="number" class="form-control" id="edit_total_licenses" name="edit_total_licenses" min="1" required>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning shadow-sm">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteSoftwareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 bg-danger text-white">
        <h5 class="modal-title fw-bold">Confirm Deletion</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="software_inventory.php">
        <div class="modal-body p-4 text-center">
            <input type="hidden" name="delete_software" value="1">
            <input type="hidden" id="delete_software_id" name="delete_software_id">
            
            <i class="bi bi-exclamation-triangle-fill text-danger display-4 mb-3"></i>
            <p>Delete <strong id="delete_software_name"></strong>?</p>
            <p class="small text-muted">This will fail if licenses are currently assigned.</p>
        </div>
        <div class="modal-footer border-0 justify-content-center">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger shadow-sm">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
    
    // Edit Modal Logic
    var editSoftwareModal = document.getElementById('editSoftwareModal');
    editSoftwareModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; 
        
        editSoftwareModal.querySelector('#edit_software_id').value = button.getAttribute('data-id');
        editSoftwareModal.querySelector('#edit_name').value = button.getAttribute('data-name');
        editSoftwareModal.querySelector('#edit_version').value = button.getAttribute('data-version');
        editSoftwareModal.querySelector('#edit_license_type').value = button.getAttribute('data-type');
        editSoftwareModal.querySelector('#edit_total_licenses').value = button.getAttribute('data-total');
        editSoftwareModal.querySelector('#edit_licenses_in_use').value = button.getAttribute('data-inuse');
        
        // Update UI
        editSoftwareModal.querySelector('.modal-title').innerHTML = '<i class="bi bi-pencil-square me-2"></i> Edit: ' + button.getAttribute('data-name');
        editSoftwareModal.querySelector('#current_in_use_display').textContent = button.getAttribute('data-inuse');
        editSoftwareModal.querySelector('#edit_total_licenses').min = button.getAttribute('data-inuse');
    });

    // Delete Modal Logic
    var deleteSoftwareModal = document.getElementById('deleteSoftwareModal');
    deleteSoftwareModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; 
        deleteSoftwareModal.querySelector('#delete_software_id').value = button.getAttribute('data-id');
        deleteSoftwareModal.querySelector('#delete_software_name').textContent = button.getAttribute('data-name');
    });
</script>

</body>
</html>