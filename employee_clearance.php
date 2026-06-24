<?php
require_once 'includes/config.php';

$employee_data = null;
$assigned_assets = [];
$assigned_software = [];
$employee_id = '';
$employees_list = [];
$error_message = '';

// Fetch all employees for dropdown
try {
    $employees_stmt = $pdo->query('SELECT employee_id, name FROM employees ORDER BY name ASC');
    $employees_list = $employees_stmt->fetchAll();
} catch (\PDOException $e) { }

// Check for selected employee
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['select_employee'])) {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_GET['id'])) {
    $employee_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
}

if (!empty($employee_id)) {
    try {
        // 1. Employee Details
        $stmt = $pdo->prepare("SELECT employee_id, name, department, position FROM employees WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $employee_data = $stmt->fetch();

        if ($employee_data) {
            // 2. Hardware
            $stmt_assets = $pdo->prepare("SELECT fam_tag_number, device_type, device_name, serial_number, status FROM assets WHERE current_user_id = ? ORDER BY device_type");
            $stmt_assets->execute([$employee_id]);
            $assigned_assets = $stmt_assets->fetchAll();

            // 3. Software
            $stmt_soft = $pdo->prepare("SELECT s.name, s.version, s.license_type, sa.license_key FROM software_assignments sa JOIN software_items s ON sa.software_id = s.software_id WHERE sa.employee_id = ? AND sa.status = 'Active'");
            $stmt_soft->execute([$employee_id]);
            $assigned_software = $stmt_soft->fetchAll();
        }
    } catch (\PDOException $e) {
        $error_message = "Error fetching data.";
    }
}

$total_items = count($assigned_assets) + count($assigned_software);

$page_title   = 'Clearance Form | ' . ($employee_data ? htmlspecialchars($employee_data['name']) : 'Select Employee');
$active_page  = 'clearance';
$topbar_label = 'Generate Clearance';
$extra_head   = '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />'
              . '<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />'
              . '<style>
                    @media print {
                        @page { margin: 0.5cm; size: A4 portrait; }
                        .container-fluid { padding: 0 !important; margin: 0 !important; }
                        .paper-sheet { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; min-height: 0 !important; }
                        .btn, form { display: none !important; }
                    }
                </style>';
include 'includes/head.php';
include 'includes/sidebar.php';
?>

        <div class="container-fluid p-4">

            <div class="content-card mb-4 no-print reveal">
                <div class="card-body p-4">
                    <form method="POST" action="employee_clearance.php" class="row align-items-end g-3">
                        <input type="hidden" name="select_employee" value="1">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Select Employee for Clearance</label>
                            <select class="form-select select2" name="employee_id" required>
                                <option value="">Search Employee Name or ID...</option>
                                <?php foreach ($employees_list as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>" <?php echo ($emp['employee_id'] == $employee_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="bi bi-file-earmark-text me-2"></i> Generate Form</button>
                        </div>
                        <?php if ($employee_data): ?>
                        <div class="col-md-3">
                            <button type="button" onclick="window.print()" class="btn btn-success w-100 shadow-sm"><i class="bi bi-printer me-2"></i> Print / PDF</button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if ($employee_data): ?>
            <div class="paper-sheet">
                <div class="text-center mb-5 border-bottom pb-3">
                    <h2 class="fw-bold mb-0">IT CLEARANCE FORM</h2>
                    <p class="text-muted small mb-0">CHROMAESTHETICS INC. | IT DEPARTMENT</p>
                    <p class="text-muted small">Generated: <?php echo date('F d, Y'); ?></p>
                </div>

                <div class="row mb-4">
                    <div class="col-6 mb-2"><strong>Employee Name:</strong> <span class="border-bottom border-dark px-2 d-inline-block w-75"><?php echo htmlspecialchars($employee_data['name']); ?></span></div>
                    <div class="col-6 mb-2"><strong>Employee ID:</strong> <span class="border-bottom border-dark px-2 d-inline-block w-75"><?php echo htmlspecialchars($employee_data['employee_id']); ?></span></div>
                    <div class="col-6 mb-2"><strong>Department:</strong> <span class="border-bottom border-dark px-2 d-inline-block w-75"><?php echo htmlspecialchars($employee_data['department']); ?></span></div>
                    <div class="col-6 mb-2"><strong>Position:</strong> <span class="border-bottom border-dark px-2 d-inline-block w-75"><?php echo htmlspecialchars($employee_data['position']); ?></span></div>
                </div>

                <?php if ($total_items > 0): ?>
                    <div class="alert alert-warning border-dark text-center fw-bold no-print">
                        <i class="bi bi-exclamation-triangle"></i> WARNING: This employee still has <?php echo $total_items; ?> items assigned. They must be returned before signing.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success border-success text-center fw-bold no-print">
                        <i class="bi bi-check-circle"></i> CLEAR: No active assets found. Ready for clearance signature.
                    </div>
                <?php endif; ?>

                <h5 class="fw-bold mt-4 text-uppercase border-bottom border-2 border-dark pb-1">I. Hardware Assets</h5>
                <table class="table table-bordered form-table border-dark">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="20%">Asset Tag</th>
                            <th width="35%">Description / Model</th>
                            <th width="20%">Serial No.</th>
                            <th width="20%" class="text-center">Status / Return Check</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; foreach ($assigned_assets as $asset): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                            <td><?php echo htmlspecialchars($asset['device_type'] . ' - ' . $asset['device_name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($asset['status']); ?> <span class="ms-2">⬜</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assigned_assets)): ?>
                        <tr><td colspan="5" class="text-center fst-italic">No hardware currently assigned.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h5 class="fw-bold mt-4 text-uppercase border-bottom border-2 border-dark pb-1">II. Software Licenses</h5>
                <table class="table table-bordered form-table border-dark">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="40%">Software Title</th>
                            <th width="35%">License Key / Account</th>
                            <th width="20%" class="text-center">Revoke Check</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $j=1; foreach ($assigned_software as $soft): ?>
                        <tr>
                            <td><?php echo $j++; ?></td>
                            <td><?php echo htmlspecialchars($soft['name'] . ' ' . $soft['version']); ?></td>
                            <td class="font-monospace"><?php echo htmlspecialchars($soft['license_key'] ? $soft['license_key'] : 'Assigned Account'); ?></td>
                            <td class="text-center">Active <span class="ms-2">⬜</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assigned_software)): ?>
                        <tr><td colspan="4" class="text-center fst-italic">No software licenses assigned.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="row mt-5 pt-5 text-center">
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($employee_data['name']); ?></p>
                        <small class="text-muted">Employee Signature</small>
                    </div>
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <p class="mb-0 fw-bold">IT Personnel</p>
                        <small class="text-muted">Checked By</small>
                    </div>
                    <div class="col-4">
                        <div class="signature-line"></div>
                        <p class="mb-0 fw-bold">Department Head</p>
                        <small class="text-muted">Approved By</small>
                    </div>
                </div>

                <div class="text-center mt-5 pt-5 text-muted small">
                    <p>By signing this form, the employee acknowledges the return/surrender of all listed company properties.</p>
                </div>

            </div>
            <?php endif; ?>

        </div>

<?php
$extra_scripts = <<<'HTML'
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({ theme: "bootstrap-5", width: '100%' });
    });
</script>
HTML;
include 'includes/footer.php';
?>
