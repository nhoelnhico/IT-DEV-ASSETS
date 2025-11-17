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
    // UPDATED: Use FILTER_SANITIZE_STRING for employee_id
    $employee_id = filter_input(INPUT_POST, 'employee_id', **FILTER_SANITIZE_STRING**);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING);

    if (empty($employee_id) || empty($name) || empty($department) || empty($position)) {
        $message = '<div class="alert alert-danger" role="alert">All fields are required.</div>';
    } else {
        try {
            $sql = "INSERT INTO employees (employee_id, name, department, position) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employee_id, $name, $department, $position]);
            $message = '<div class="alert alert-success" role="alert">New employee **' . htmlspecialchars($name) . '** added successfully!</div>';
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation (e.g., duplicate employee_id)
                $message = '<div class="alert alert-danger" role="alert">Error: Employee ID **' . htmlspecialchars($employee_id) . '** already exists.</div>';
            } else {
                // Generic error handling
                $message = '<div class="alert alert-danger" role="alert">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}


// --- 3. Fetch All Employees with Asset Count ---
$sql_employees = "
    SELECT 
        e.employee_id, e.name, e.department, e.position,
        COUNT(a.asset_id) AS assigned_assets
    FROM employees e
    LEFT JOIN assets a ON e.employee_id = a.current_user_id
    {$search_condition}
    GROUP BY e.employee_id, e.name, e.department, e.position
    ORDER BY e.name ASC
";

$stmt_employees = $pdo->prepare($sql_employees);
$stmt_employees->execute($search_params);
$employees = $stmt_employees->fetchAll();

$pageTitle = "Employees List";
include 'includes/header.php';
?>

<div id="wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div id="page-content-wrapper">
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4 text-white">Employees Management</h1>

            <?php echo $message; ?>

            <div class="card shadow mb-4 bg-dark text-white">
                <div class="card-header bg-secondary text-white">
                    <h5 class="m-0 font-weight-bold">Add New Employee</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="add_employee" value="1">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="employee_id" class="form-label">Employee ID (VARCHAR)</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" required>
                            </div>
                            <div class="col-md-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" required>
                            </div>
                            <div class="col-md-3">
                                <label for="position" class="form-label">Position</label>
                                <input type="text" class="form-control" id="position" name="position" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Add Employee</button>
                        </div>
                    </form>
                </div>
            </div>


            <div class="card shadow mb-4 bg-dark text-white">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold">All Employees</h5>
                    <form method="GET" class="d-flex" role="search">
                        <input class="form-control me-2" type="search" placeholder="Search Name, ID, Dept, Position" aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-outline-light" type="submit">Search</button>
                        <?php if (!empty($search_term)): ?>
                            <a href="employees.php" class="btn btn-outline-danger ms-2" title="Clear Search">X</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Assigned Assets</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($employees) > 0): ?>
                                    <?php foreach ($employees as $employee): ?>
                                    <tr onclick="window.location='employee_details.php?id=<?php echo urlencode($employee['employee_id']); ?>'" style="cursor: pointer;">
                                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $employee['assigned_assets']; ?></span></td> 
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
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