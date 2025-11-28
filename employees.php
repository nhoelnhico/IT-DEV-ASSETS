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
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } else {
        try {
            $sql = "INSERT INTO employees (employee_id, name, department, position) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employee_id, $name, $department, $position]);
            $message = '<div class="alert alert-success">Employee **' . htmlspecialchars($name) . '** added successfully!</div>';
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '<div class="alert alert-warning">Error: Employee ID **' . htmlspecialchars($employee_id) . '** already exists.</div>';
            } else {
                // error_log("Employee Add Error: " . $e->getMessage()); 
                $message = '<div class="alert alert-danger">Database Error: Could not add employee.</div>';
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
        body { background-color: #f8f9fa; }
        #sidebar-wrapper { min-height: 100vh; margin-left: -15rem; transition: margin .25s ease-out; background-color: #343a40; }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: #ffffff; }
        #page-content-wrapper { min-width: 100vw; }
        .sidebar-nav a { color: #adb5bd; padding: 1rem 1.25rem; display: block; text-decoration: none; }
        .sidebar-nav a:hover { background-color: #495057; color: #ffffff; }
        .sidebar-nav a[href="employees.php"] { background-color: #0d6efd; color: #ffffff; border-left: 5px solid #ffc107; } /* Active for this page */
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #page-content-wrapper { min-width: 0; width: 100%; } }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div class="border-end bg-dark" id="sidebar-wrapper">
        <div class="sidebar-heading">IT Inventory System</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a class="list-group-item list-group-item-action bg-dark" href="index.php">📊 Dashboard</a>
            <a class="list-group-item list-group-item-action bg-dark active" href="employees.php">🧑‍💻 Employees</a>
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
            <h1 class="mt-4 mb-4">🧑‍💻 Employee Management</h1>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-5 border-primary">
                <div class="card-header bg-primary text-white">Add New Employee</div>
                <div class="card-body">
                    <form method="POST" action="employees.php">
                        <input type="hidden" name="add_employee" value="1"> 
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="employee_id" class="form-label">Employee ID</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" required>
                            </div>
                            <div class="col-md-5">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" required>
                            </div>
                            <div class="col-md-4">
                                <label for="position" class="form-label">Position</label>
                                <input type="text" class="form-control" id="position" name="position" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-4">Add Employee</button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-lg">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>Current Employees List (<?php echo $employee_count; ?> Found)</div>
                    
                    <form method="GET" action="employees.php" class="d-flex" style="width: 300px;">
                        <input 
                            class="form-control me-2" 
                            type="search" 
                            placeholder="Search Name, ID, Dept, or Position" 
                            aria-label="Search" 
                            name="search"
                            value="<?php echo htmlspecialchars($search_term); ?>"
                        >
                        <button class="btn btn-outline-success" type="submit"><i class="bi bi-search"></i></button>
                        <?php if (!empty($search_term)): ?>
                            <a href="employees.php" class="btn btn-outline-danger ms-1"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
                    </form>
                    </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Assets (H)</th>
                                    <th>Licenses (S)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($employee_count > 0): ?>
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                        <td>
                                            <a href="employee_details.php?id=<?php echo $employee['employee_id']; ?>">
                                                <?php echo htmlspecialchars($employee['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $employee['assigned_assets_hardware']; ?></span></td> 
                                        <td><span class="badge bg-info"><?php echo $employee['assigned_assets_software']; ?></span></td> 
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <?php if (!empty($search_term)): ?>
                                                No employees found matching "<?php echo htmlspecialchars($search_term); ?>".
                                            <?php else: ?>
                                                No employees recorded.
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