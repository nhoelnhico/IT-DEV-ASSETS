<?php
require_once 'includes/config.php';

$message = ''; 

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
                $message = '<div class="alert alert-danger">Database Error: Could not add employee.</div>';
            }
        }
    }
}

// Fetch all employees and count their assigned assets
$sql_fetch = "
    SELECT 
        e.employee_id, e.name, e.department, e.position, COUNT(a.asset_id) AS assigned_assets
    FROM 
        employees e
    LEFT JOIN 
        assets a ON e.employee_id = a.current_user_id
    GROUP BY
        e.employee_id, e.name, e.department, e.position
    ORDER BY 
        e.employee_id ASC
";
$stmt = $pdo->query($sql_fetch);
$employees = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Employees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        /* ... (CSS for sidebar/wrapper from index.php) ... */
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
            <a class="list-group-item list-group-item-action bg-dark" href="transmittal.php">📝 Transmittal Log</a>
        </div>
    </div>
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
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
                <div class="card-header bg-white border-bottom">Current Employees List</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>**Assets Assigned**</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $employee['assigned_assets']; ?></span></td> 
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>