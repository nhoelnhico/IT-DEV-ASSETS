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

$page_title   = 'IT Inventory | Employees';
$active_page  = 'employees';
$topbar_label = 'Employee Directory';
include 'includes/head.php';
include 'includes/sidebar.php';
?>

        <div class="container-fluid p-4">
            <h3 class="mb-4 fw-bold">Employee Management</h3>

            <?php echo $message; ?>

            <div class="content-card reveal">
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
                                    <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                    <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="e.g. 10234" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="name" class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Nhico" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="department" class="form-label">Department</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <input type="text" class="form-control" id="department" name="department" placeholder="e.g. Marketing" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="position" class="form-label">Position</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-briefcase"></i></span>
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

            <div class="content-card reveal">
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

<?php include 'includes/footer.php'; ?>
