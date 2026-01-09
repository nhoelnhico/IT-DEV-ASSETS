<?php
require_once 'includes/config.php';

$message = ''; 
$search_term = '';
$search_condition = '';
$search_params = [];

// --- 1. Handle Employee Search Query ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    // Use LIKE for global search across Name, ID, Department, or Position
    $search_condition = " WHERE e.name LIKE ? OR e.employee_id LIKE ? OR e.department LIKE ? OR e.position LIKE ?";
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term, $like_term];
}

// --- 2. Handle ADD NEW EMPLOYEE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_employee'])) {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING);

    if (empty($employee_id) || empty($name) || empty($department) || empty($position)) {
        $message = '<div class="alert alert-danger shadow-sm border-0"><i class="bi bi-exclamation-circle-fill me-2"></i> All fields are required.</div>';
    } else {
        try {
            $sql = "INSERT INTO employees (employee_id, name, department, position) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employee_id, $name, $department, $position]);
            $message = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> Employee <strong>' . htmlspecialchars($name) . '</strong> added successfully!</div>';
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '<div class="alert alert-warning shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Error: Employee ID <strong>' . htmlspecialchars($employee_id) . '</strong> already exists.</div>';
            } else {
                $message = '<div class="alert alert-danger shadow-sm border-0">Database Error: Could not add employee.</div>';
            }
        }
    }
}

// --- 3. Fetch all employees (with search filter) ---
$sql_fetch = "
    SELECT 
        e.employee_id, e.name, e.department, e.position, 
        (
            SELECT COUNT(a.asset_id) 
            FROM assets a 
            WHERE a.current_user_id = e.employee_id
        ) AS assigned_assets_hardware,
        (
            SELECT COUNT(sa.assignment_id) 
            FROM software_assignments sa 
            WHERE sa.employee_id = e.employee_id AND sa.status = 'Active'
        ) AS assigned_assets_software
    FROM 
        employees e
    {$search_condition} 
    ORDER BY 
        e.employee_id ASC
";
$stmt = $pdo->prepare($sql_fetch);
$stmt->execute($search_params);
$employees = $stmt->fetchAll();
$employee_count = count($employees);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Employees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            /* Consistent Palette with Index.php */
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

        /* Sidebar Styling (Matches Index) */
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
        
        #page-content-wrapper { min-width: 100vw; }
        @media (min-width: 768px) {
            #sidebar-wrapper { margin-left: 0; }
            #page-content-wrapper { min-width: 0; width: 100%; }
        }

        /* Card Styles */
        .content-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            background: white;
            overflow: hidden;
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

        /* Form Styling */
        .form-label {
            font-weight: 600;
            color: #5a5c69;
            font-size: 0.9rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            border: 1px solid #d1d3e2;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        /* Table Styling */
        .table-custom {
            margin-bottom: 0;
        }
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
        .table-custom tbody tr:hover {
            background-color: #f8f9fc;
        }
        
        /* Employee Avatar Placeholder */
        .avatar-circle {
            width: 40px;
            height: 40px;
            background-color: var(--info-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }

        /* Badges */
        .badge-pill {
            border-radius: 50rem;
            padding: 0.5em 1em;
            font-weight: 600;
        }
        .bg-gradient-primary {
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
        }

    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div id="sidebar-wrapper">
        <div class="sidebar-heading">IT Asset Manager</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="employees.php" class="active"><i class="bi bi-people"></i> Employees</a>
            <a href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a>
            <a href="software_inventory.php"><i class="bi bi-disc"></i> Software</a> 
            <a href="software_assignment.php"><i class="bi bi-key"></i> Licenses</a>
            <a href="transmittal.php"><i class="bi bi-arrow-left-right"></i> Transmittals</a>
            <a href="employee_clearance.php"><i class="bi bi-file-earmark-check"></i> Clearance</a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i> Menu</button>
            <div class="ms-auto text-secondary small fw-bold">Employee Directory</div>
        </nav>

        <div class="container-fluid p-4">
            <h3 class="mb-4 text-dark fw-bold">Employee Management</h3>
            
            <?php echo $message; ?>

            <div class="content-card mb-4">
                <div class="card-header">
                    <span><i class="bi bi-person-plus-fill me-2"></i> Add New Employee</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="employees.php">
                        <input type="hidden" name="add_employee" value="1"> 
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="employee_id" class="form-label">Employee ID</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-card-heading"></i></span>
                                    <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="e.g. 10234" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="name" class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="e.g. John Doe" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="department" class="form-label">Department</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-building"></i></span>
                                    <input type="text" class="form-control" id="department" name="department" placeholder="e.g. Marketing" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="position" class="form-label">Position</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-briefcase"></i></span>
                                    <input type="text" class="form-control" id="position" name="position" placeholder="e.g. Manager" required>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-save me-2"></i> Save Employee</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <span><i class="bi bi-list-ul me-2"></i> Employee Directory (<?php echo $employee_count; ?>)</span>
                    
                    <form method="GET" action="employees.php" class="d-flex" style="max-width: 300px;">
                        <div class="input-group input-group-sm">
                            <input 
                                class="form-control" 
                                type="search" 
                                placeholder="Search employees..." 
                                aria-label="Search" 
                                name="search"
                                value="<?php echo htmlspecialchars($search_term); ?>"
                            >
                            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
                            <?php if (!empty($search_term)): ?>
                                <a href="employees.php" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover">
                            <thead>
                                <tr>
                                    <th class="ps-4">Employee Details</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th class="text-center">Assigned Hardware</th>
                                    <th class="text-center">Assigned Software</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($employee_count > 0): ?>
                                    <?php foreach ($employees as $employee): 
                                        // Generate initials for avatar
                                        $initials = strtoupper(substr($employee['name'], 0, 1));
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle shadow-sm">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($employee['name']); ?></div>
                                                    <div class="small text-muted">ID: <?php echo htmlspecialchars($employee['employee_id']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="text-secondary"><?php echo htmlspecialchars($employee['department']); ?></span></td>
                                        <td><span class="text-secondary"><?php echo htmlspecialchars($employee['position']); ?></span></td>
                                        
                                        <td class="text-center">
                                            <?php if ($employee['assigned_assets_hardware'] > 0): ?>
                                                <span class="badge badge-pill bg-primary shadow-sm">
                                                    <i class="bi bi-pc-display me-1"></i> <?php echo $employee['assigned_assets_hardware']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="text-center">
                                            <?php if ($employee['assigned_assets_software'] > 0): ?>
                                                <span class="badge badge-pill bg-info text-dark shadow-sm">
                                                    <i class="bi bi-microsoft me-1"></i> <?php echo $employee['assigned_assets_software']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="text-end pe-4">
                                            <a href="employee_details.php?id=<?php echo $employee['employee_id']; ?>" class="btn btn-sm btn-outline-primary shadow-sm" title="View Profile">
                                                View Profile <i class="bi bi-arrow-right ms-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-person-x display-4 d-block mb-3 opacity-25"></i>
                                            <?php if (!empty($search_term)): ?>
                                                No employees found matching "<?php echo htmlspecialchars($search_term); ?>".
                                            <?php else: ?>
                                                No employees found. Start by adding one above.
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });
</script>

</body>
</html>