<?php
// Include the database connection script
require_once 'includes/config.php'; // Adjust path if necessary

$message = ''; // Variable to store success or error messages

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_asset'])) {
    // 1. Gather and sanitize input data
    $fam_tag_number = filter_input(INPUT_POST, 'fam_tag_number', FILTER_SANITIZE_STRING);
    $device_type = filter_input(INPUT_POST, 'device_type', FILTER_SANITIZE_STRING);
    $device_name = filter_input(INPUT_POST, 'device_name', FILTER_SANITIZE_STRING);
    $serial_number = filter_input(INPUT_POST, 'serial_number', FILTER_SANITIZE_STRING);
    
    // Status is set to 'Available' by default when a new asset is added
    $initial_status = 'Available'; 

    // 2. Validate required fields
    if (empty($fam_tag_number) || empty($device_type) || empty($device_name) || empty($serial_number)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } else {
        try {
            // 3. Prepare the SQL INSERT statement
            $sql = "INSERT INTO assets (fam_tag_number, device_type, device_name, serial_number, status) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // 4. Execute the statement
            $stmt->execute([$fam_tag_number, $device_type, $device_name, $serial_number, $initial_status]);

            $message = '<div class="alert alert-success">Asset **' . htmlspecialchars($fam_tag_number) . '** added successfully and is **Available**.</div>';

        } catch (\PDOException $e) {
            // Check for duplicate FAM Tag or Serial Number
            if ($e->getCode() == 23000) {
                $message = '<div class="alert alert-warning">Error: FAM Tag or Serial Number already exists.</div>';
            } else {
                $message = '<div class="alert alert-danger">Database Error: Could not add asset.</div>';
                // For debugging: echo $e->getMessage();
            }
        }
    }
}

// 5. Fetch all assets for display table
// We also join with the employees table to show the current user's name if applicable
$sql_fetch = "
    SELECT 
        a.asset_id, a.fam_tag_number, a.device_type, a.device_name, a.serial_number, a.status, e.name AS current_user_name
    FROM 
        assets a
    LEFT JOIN 
        employees e ON a.current_user_id = e.employee_id
    ORDER BY 
        a.fam_tag_number ASC
";
$stmt_fetch = $pdo->query($sql_fetch);
$assets = $stmt_fetch->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<body>

<div class="d-flex" id="wrapper">
    <div id="page-content-wrapper">
        <div class="container-fluid p-4">
            <h1 class="mt-4 mb-4">📦 IT Asset Inventory</h1>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-5">
                <div class="card-header bg-success text-white">
                    Add New Device to Inventory
                </div>
                <div class="card-body">
                    <form method="POST" action="inventory.php">
                        <input type="hidden" name="add_asset" value="1"> 
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="fam_tag_number" class="form-label">Device FAM Tag Number</label>
                                <input type="text" class="form-control" id="fam_tag_number" name="fam_tag_number" required>
                            </div>
                            <div class="col-md-3">
                                <label for="device_type" class="form-label">Device Type</label>
                                <select class="form-select" id="device_type" name="device_type" required>
                                    <option value="">Select Type...</option>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="Monitor">Monitor</option>
                                    <option value="Company Phone">Company Phone</option>
                                    <option value="Tablet">Tablet</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="device_name" class="form-label">Device Name / Model</label>
                                <input type="text" class="form-control" id="device_name" name="device_name" placeholder="e.g., Dell Latitude 5420" required>
                            </div>
                            <div class="col-md-3">
                                <label for="serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success mt-4">Add Device</button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-lg">
                <div class="card-header bg-white">
                    Master Inventory List (<?php echo count($assets); ?> Devices)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>FAM Tag</th>
                                    <th>Type</th>
                                    <th>Device Model</th>
                                    <th>Serial No.</th>
                                    <th>**Status**</th>
                                    <th>Assigned To</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['device_type']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['device_name']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                    <td>
                                        <?php 
                                            // Apply Bootstrap badges based on status for visual clarity
                                            $badge_class = 'bg-secondary';
                                            if ($asset['status'] == 'In Use') { $badge_class = 'bg-primary'; }
                                            if ($asset['status'] == 'Available') { $badge_class = 'bg-success'; }
                                            if ($asset['status'] == 'Broken') { $badge_class = 'bg-danger'; }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($asset['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo $asset['current_user_name'] ? htmlspecialchars($asset['current_user_name']) : '---'; ?>
                                    </td>
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