<?php
require_once 'includes/config.php';

$message = ''; 
$search_term = '';
$search_condition = " WHERE sa.status = 'Active' "; // Default to showing Active only? Or all? Let's show Active by default or all with status.
// Let's show ALL but order by Active first
$search_params = [];

// --- 1. HANDLE SEARCH ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    $search_condition .= " AND (e.name LIKE ? OR s.name LIKE ? OR sa.license_key LIKE ?) ";
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term];
}

// --- 2. HANDLE ASSIGNMENT (ADD) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_license'])) {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_NUMBER_INT);
    $software_id = filter_input(INPUT_POST, 'software_id', FILTER_SANITIZE_NUMBER_INT);
    $license_key = filter_input(INPUT_POST, 'license_key', FILTER_SANITIZE_STRING);
    $date_assigned = date('Y-m-d'); // Today

    if (empty($employee_id) || empty($software_id)) {
        $message = '<div class="alert alert-danger shadow-sm border-0">Employee and Software selection are required.</div>';
    } else {
        // Check availability logic could go here, but for now we trust the user or the UI
        try {
            $sql = "INSERT INTO software_assignments (employee_id, software_id, license_key, date_assigned, status) VALUES (?, ?, ?, ?, 'Active')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employee_id, $software_id, $license_key, $date_assigned]);
            $message = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> License assigned successfully!</div>';
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger shadow-sm border-0">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- 3. HANDLE REVOKE (UPDATE STATUS) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['revoke_license'])) {
    $assignment_id = filter_input(INPUT_POST, 'revoke_assignment_id', FILTER_SANITIZE_NUMBER_INT);
    
    try {
        // We don't delete, we set to 'Inactive' so we keep the history
        $sql = "UPDATE software_assignments SET status = 'Inactive' WHERE assignment_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$assignment_id]);
        $message = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-archive-fill me-2"></i> License access revoked/archived.</div>';
    } catch (\PDOException $e) {
        $message = '<div class="alert alert-danger shadow-sm border-0">Error revoking license.</div>';
    }
}

// --- 4. FETCH DATA FOR DROPDOWNS ---
// Fetch Employees
$emp_stmt = $pdo->query("SELECT employee_id, name FROM employees ORDER BY name ASC");
$employees_list = $emp_stmt->fetchAll();

// Fetch Software
$soft_stmt = $pdo->query("SELECT software_id, name, version FROM software_items ORDER BY name ASC");
$software_list = $soft_stmt->fetchAll();

// --- 5. FETCH ASSIGNMENTS LIST ---
$sql_fetch = "
    SELECT 
        sa.assignment_id, sa.license_key, sa.date_assigned, sa.status,
        e.name AS employee_name, e.department,
        s.name AS software_name, s.version
    FROM 
        software_assignments sa
    JOIN 
        employees e ON sa.employee_id = e.employee_id
    JOIN 
        software_items s ON sa.software_id = s.software_id
    {$search_condition}
    ORDER BY 
        sa.status ASC, sa.date_assigned DESC
";
$stmt = $pdo->prepare($sql_fetch);
$stmt->execute($search_params);
$assignments = $stmt->fetchAll();
$count = count($assignments);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | License Assignment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        :root {
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

        /* Sidebar & Layout */
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
        .sidebar-nav a:hover { background-color: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-nav a.active { background-color: rgba(255,255,255,0.1); color: #fff; border-left: 4px solid var(--info-color); }
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }

        /* Card Styles */
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

        /* Table Styles */
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
        .table-custom tbody tr:hover { background-color: #f8f9fc; }

        /* Avatar */
        .avatar-circle {
            width: 35px;
            height: 35px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
            margin-right: 12px;
        }

        /* License Key Code Style */
        .license-key-box {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f8f9fc;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #e3e6f0;
            color: #e74a3b;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Status Badges */
        .badge-active { background-color: rgba(28, 200, 138, 0.1); color: var(--success-color); padding: 0.5em 0.8em; border-radius: 0.35rem; }
        .badge-inactive { background-color: rgba(133, 135, 150, 0.1); color: #858796; padding: 0.5em 0.8em; border-radius: 0.35rem; }

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
            <a href="software_inventory.php"><i class="bi bi-disc"></i> Software</a> 
            <a href="software_assignment.php" class="active"><i class="bi bi-key"></i> Licenses</a>
            <a href="transmittal.php"><i class="bi bi-arrow-left-right"></i> Transmittals</a>
            <a href="employee_clearance.php"><i class="bi bi-file-earmark-check"></i> Clearance</a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i> Menu</button>
            <div class="ms-auto text-secondary small fw-bold">License Management</div>
        </nav>

        <div class="container-fluid p-4">
            <h3 class="mb-4 text-dark fw-bold">License Assignments</h3>
            
            <?php echo $message; ?>

            <div class="content-card">
                <div class="card-header">
                    <span><i class="bi bi-person-fill-add me-2"></i> Grant License Access</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="software_assignment.php">
                        <input type="hidden" name="assign_license" value="1">
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Select Employee</label>
                                <select class="form-select select2" name="employee_id" required>
                                    <option value="">Search Employee...</option>
                                    <?php foreach ($employees_list as $emp): ?>
                                        <option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Select Software</label>
                                <select class="form-select select2" name="software_id" required>
                                    <option value="">Search Software...</option>
                                    <?php foreach ($software_list as $soft): ?>
                                        <option value="<?php echo $soft['software_id']; ?>">
                                            <?php echo htmlspecialchars($soft['name']); ?> 
                                            <?php echo $soft['version'] ? '(' . htmlspecialchars($soft['version']) . ')' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">License Key (Optional)</label>
                                <input type="text" class="form-control font-monospace" name="license_key" placeholder="XXXX-XXXX-XXXX-XXXX">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                    <i class="bi bi-link-45deg me-1"></i> Assign License
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <span><i class="bi bi-list-columns-reverse me-2"></i> Allocation Registry (<?php echo $count; ?>)</span>
                    
                    <form method="GET" action="software_assignment.php" class="d-flex" style="width: 280px;">
                        <div class="input-group input-group-sm">
                            <input 
                                class="form-control" 
                                type="search" 
                                placeholder="Search Employee, Software..." 
                                aria-label="Search" 
                                name="search"
                                value="<?php echo htmlspecialchars($search_term); ?>"
                            >
                            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
                            <?php if (!empty($search_term)): ?>
                                <a href="software_assignment.php" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">Employee</th>
                                    <th>Software Title</th>
                                    <th>License Key / ID</th>
                                    <th>Date Assigned</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($count > 0): ?>
                                    <?php foreach ($assignments as $row): 
                                        $initials = strtoupper(substr($row['employee_name'], 0, 1));
                                        $is_active = $row['status'] === 'Active';
                                        $status_badge = $is_active ? 'badge-active' : 'badge-inactive';
                                    ?>
                                    <tr class="<?php echo !$is_active ? 'bg-light opacity-75' : ''; ?>">
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle shadow-sm" style="<?php echo !$is_active ? 'background-color:#858796;' : ''; ?>">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['employee_name']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($row['department']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['software_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['version']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($row['license_key']): ?>
                                                <span class="license-key-box"><?php echo htmlspecialchars($row['license_key']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">No Key / Floating</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-secondary small"><?php echo date('M d, Y', strtotime($row['date_assigned'])); ?></td>
                                        <td>
                                            <span class="<?php echo $status_badge; ?> fw-bold small">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($is_active): ?>
                                            <button 
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#revokeModal"
                                                data-id="<?php echo $row['assignment_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($row['employee_name']); ?>"
                                                data-soft="<?php echo htmlspecialchars($row['software_name']); ?>"
                                                title="Revoke License"
                                            >
                                                <i class="bi bi-x-circle"></i> Revoke
                                            </button>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Revoked</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
                                            <?php if (!empty($search_term)): ?>
                                                No assignments found matching "<?php echo htmlspecialchars($search_term); ?>".
                                            <?php else: ?>
                                                No licenses assigned yet. Use the form above.
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

<div class="modal fade" id="revokeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 bg-danger text-white">
        <h5 class="modal-title fw-bold">Revoke Access</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="software_assignment.php">
        <div class="modal-body p-4 text-center">
            <input type="hidden" name="revoke_license" value="1">
            <input type="hidden" id="revoke_assignment_id" name="revoke_assignment_id">
            
            <i class="bi bi-person-dash-fill text-danger display-4 mb-3"></i>
            <p class="mb-2">Revoke license for:</p>
            <h5 class="fw-bold" id="revoke_soft_name"></h5>
            <p class="mb-3">from <strong id="revoke_emp_name"></strong>?</p>
            
            <div class="alert alert-light border small text-muted text-start">
                <i class="bi bi-info-circle me-1"></i> This will mark the license as 'Inactive' and free it up for reassignment. History is preserved.
            </div>
        </div>
        <div class="modal-footer border-0 justify-content-center">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger shadow-sm">Confirm Revoke</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // Sidebar Toggle
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });

    // Initialize Select2 for searchable dropdowns
    $(document).ready(function() {
        $('.select2').select2({
            theme: "bootstrap-5",
            width: '100%'
        });
    });

    // Revoke Modal Logic
    var revokeModal = document.getElementById('revokeModal');
    revokeModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; 
        
        revokeModal.querySelector('#revoke_assignment_id').value = button.getAttribute('data-id');
        revokeModal.querySelector('#revoke_emp_name').textContent = button.getAttribute('data-name');
        revokeModal.querySelector('#revoke_soft_name').textContent = button.getAttribute('data-soft');
    });
</script>

</body>
</html>