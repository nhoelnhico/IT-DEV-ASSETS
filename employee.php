<?php
// Include the database connection script
require_once 'includes/config.php'; // Adjust path if necessary

$message = ''; // Variable to store success or error messages

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_employee'])) {
    // 1. Gather and sanitize input data
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING);

    // 2. Validate required fields
    if (empty($employee_id) || empty($name) || empty($department) || empty($position)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } else {
        try {
            // 3. Prepare the SQL INSERT statement using Prepared Statements (Security!)
            $sql = "INSERT INTO employees (employee_id, name, department, position) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // 4. Execute the statement with the input variables
            $stmt->execute([$employee_id, $name, $department, $position]);

            $message = '<div class="alert alert-success">Employee **' . htmlspecialchars($name) . '** added successfully!</div>';

        } catch (\PDOException $e) {
            // Check for duplicate entry error (error code 23000 is common for unique constraint violation)
            if ($e->getCode() == 23000) {
                $message = '<div class="alert alert-warning">Error: Employee ID **' . htmlspecialchars($employee_id) . '** already exists.</div>';
            } else {
                // General error message
                $message = '<div class="alert alert-danger">Database Error: Could not add employee.</div>';
                // For debugging: echo $e->getMessage();
            }
        }
    }
}

// 5. Fetch all employees for display table
$stmt = $pdo->query('SELECT employee_id, name, department, position FROM employees ORDER BY employee_id ASC');
$employees = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<body>

<div class="d-flex" id="wrapper">
    <div id="page-content-wrapper">
        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4">🧑‍💻 Employee Management</h1>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-5">
                <div class="card-header bg-primary text-white">
                    Add New Employee
                </div>
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
                <div class="card-header bg-white">
                    Current Employees List
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Assets Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <td><span class="badge bg-secondary">0</span></td> 
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
</body>
</html>