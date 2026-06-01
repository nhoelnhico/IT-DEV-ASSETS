<?php
require_once 'includes/config.php';

$message = '';
$search_term = '';
$search_condition = '';
$search_params = [];
$sort_by = 'a.fam_tag_number';
$sort_order = 'ASC';

// --- 1. HANDLE SEARCH QUERY ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS));
    $search_condition = " WHERE a.fam_tag_number LIKE ? OR a.serial_number LIKE ? OR a.device_type LIKE ? OR a.device_name LIKE ?";
    $like_term = '%' . $search_term . '%';
    $search_params = [$like_term, $like_term, $like_term, $like_term];
}

// --- 2. HANDLE SORTING PARAMETERS ---
if (isset($_GET['sort_by'])) {
    $requested_sort = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS);

    $valid_columns = [
        'tag' => 'a.fam_tag_number',
        'type' => 'a.device_type',
        'status' => 'a.status',
        'date_received' => 'a.date_received'
    ];

    if (isset($valid_columns[$requested_sort])) {
        $sort_by = $valid_columns[$requested_sort];
    }
}

if (isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC'], true)) {
    $sort_order = strtoupper($_GET['order']);
}

// --- 3. HANDLE ADD NEW ASSET ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_asset'])) {
    $fam_tag_number = trim(filter_input(INPUT_POST, 'fam_tag_number', FILTER_SANITIZE_SPECIAL_CHARS));
    $device_type = trim(filter_input(INPUT_POST, 'device_type', FILTER_SANITIZE_SPECIAL_CHARS));
    $device_name = trim(filter_input(INPUT_POST, 'device_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $serial_number = trim(filter_input(INPUT_POST, 'serial_number', FILTER_SANITIZE_SPECIAL_CHARS));
    $date_received = filter_input(INPUT_POST, 'date_received', FILTER_SANITIZE_SPECIAL_CHARS);
    $initial_status = 'Available';

    if (empty($fam_tag_number) || empty($device_type) || empty($device_name) || empty($serial_number) || empty($date_received)) {
        $message = '<div class="alert alert-danger shadow-sm border-0"><i class="bi bi-exclamation-circle-fill me-2"></i> All fields, including Date Received, are required.</div>';
    } else {
        try {
            $sql = "INSERT INTO assets (fam_tag_number, device_type, device_name, serial_number, date_received, status)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fam_tag_number, $device_type, $device_name, $serial_number, $date_received, $initial_status]);

            $message = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> Asset <strong>' . htmlspecialchars($fam_tag_number) . '</strong> added successfully!</div>';
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '<div class="alert alert-warning shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Error: FAM Tag or Serial Number already exists.</div>';
            } else {
                $message = '<div class="alert alert-danger shadow-sm border-0">Database Error: Could not add asset.</div>';
            }
        }
    }
}

// --- 4. HANDLE EDIT/UPDATE ASSET ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_asset'])) {
    $asset_id = filter_input(INPUT_POST, 'edit_asset_id', FILTER_SANITIZE_NUMBER_INT);
    $fam_tag_number = trim(filter_input(INPUT_POST, 'edit_fam_tag_number', FILTER_SANITIZE_SPECIAL_CHARS));
    $device_type = trim(filter_input(INPUT_POST, 'edit_device_type', FILTER_SANITIZE_SPECIAL_CHARS));
    $device_name = trim(filter_input(INPUT_POST, 'edit_device_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $serial_number = trim(filter_input(INPUT_POST, 'edit_serial_number', FILTER_SANITIZE_SPECIAL_CHARS));
    $date_received = filter_input(INPUT_POST, 'edit_date_received', FILTER_SANITIZE_SPECIAL_CHARS);
    $status = trim(filter_input(INPUT_POST, 'edit_status', FILTER_SANITIZE_SPECIAL_CHARS));

    $allowed_statuses = ['Available', 'Broken', 'Repairing'];

    if (
        empty($asset_id) || empty($fam_tag_number) || empty($device_type) ||
        empty($device_name) || empty($serial_number) || empty($date_received) ||
        empty($status)
    ) {
        $message = '<div class="alert alert-danger shadow-sm border-0">All fields are required for update.</div>';
    } elseif (!in_array($status, $allowed_statuses, true)) {
        $message = '<div class="alert alert-danger shadow-sm border-0">Invalid asset status selected.</div>';
    } else {
        try {
            // Prevent manually setting "Available" while still assigned
            $check_stmt = $pdo->prepare("SELECT current_user_id, status FROM assets WHERE asset_id = ?");
            $check_stmt->execute([$asset_id]);
            $existing_asset = $check_stmt->fetch();

            if (!$existing_asset) {
                $message = '<div class="alert alert-danger shadow-sm border-0">Asset not found.</div>';
            } elseif (!empty($existing_asset['current_user_id'])) {
                $message = '<div class="alert alert-danger shadow-sm border-0">Cannot manually update status of an assigned asset here. Use Transmittal to Return, Issue, or Repair assigned items.</div>';
            } else {
                $sql = "UPDATE assets
                        SET fam_tag_number = ?, device_type = ?, device_name = ?, serial_number = ?, date_received = ?, status = ?
                        WHERE asset_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fam_tag_number, $device_type, $device_name, $serial_number, $date_received, $status, $asset_id]);

                $message = '<div class="alert alert-success shadow-sm border-0">Asset updated successfully.</div>';
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '<div class="alert alert-warning shadow-sm border-0">Error: FAM Tag or Serial Number already exists.</div>';
            } else {
                $message = '<div class="alert alert-danger shadow-sm border-0">Database Error: Could not update asset.</div>';
            }
        }
    }
}

// --- 5. HANDLE DELETE ASSET ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_asset'])) {
    $asset_id_to_delete = filter_input(INPUT_POST, 'delete_asset_id', FILTER_SANITIZE_NUMBER_INT);

    if (empty($asset_id_to_delete)) {
        $message = '<div class="alert alert-danger shadow-sm border-0">Error: No asset ID provided.</div>';
    } else {
        try {
            $check_sql = "SELECT fam_tag_number, status, current_user_id FROM assets WHERE asset_id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$asset_id_to_delete]);
            $asset_info = $check_stmt->fetch();

            if (!$asset_info) {
                $message = '<div class="alert alert-warning shadow-sm border-0">Error: Asset not found.</div>';
            } elseif (!empty($asset_info['current_user_id']) || $asset_info['status'] === 'In Use') {
                $message = '<div class="alert alert-danger shadow-sm border-0">Cannot delete asset. It is currently assigned.</div>';
            } elseif (!in_array($asset_info['status'], ['Available', 'Broken', 'Repairing'], true)) {
                $message = '<div class="alert alert-danger shadow-sm border-0">Cannot delete asset because of invalid status.</div>';
            } elseif ($asset_info['status'] !== 'Available') {
                $message = '<div class="alert alert-danger shadow-sm border-0">Cannot delete asset. Status must be <strong>Available</strong>.</div>';
            } else {
                $delete_sql = "DELETE FROM assets WHERE asset_id = ?";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([$asset_id_to_delete]);

                header("Location: inventory.php?message=" . urlencode("Asset " . $asset_info['fam_tag_number'] . " deleted successfully."));
                exit;
            }
        } catch (\PDOException $e) {
            $message = '<div class="alert alert-danger shadow-sm border-0">Database Error: Could not delete asset.</div>';
        }
    }
}

if (isset($_GET['message'])) {
    $message = '<div class="alert alert-success shadow-sm border-0">' . htmlspecialchars($_GET['message']) . '</div>';
}

// --- 6. FETCH ALL ASSETS ---
$sql_fetch = "
    SELECT
        a.asset_id,
        a.fam_tag_number,
        a.device_type,
        a.device_name,
        a.serial_number,
        a.date_received,
        a.status,
        e.name AS current_user_name
    FROM
        assets a
    LEFT JOIN
        employees e ON a.current_user_id = e.employee_id
    {$search_condition}
    ORDER BY
        {$sort_by} {$sort_order}
";
$stmt_fetch = $pdo->prepare($sql_fetch);
$stmt_fetch->execute($search_params);
$assets = $stmt_fetch->fetchAll();
$asset_count = count($assets);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #5a5c69;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            border: 1px solid #d1d3e2;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        /* SCROLLABLE TABLE & STICKY HEADERS CSS */
        .table-wrapper {
            max-height: 550px;
            overflow-y: auto;
        }
        .table-custom { margin-bottom: 0; }
        .table-custom thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fc;
            color: #858796;
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: 700;
            border-top: none;
            padding: 1rem;
            z-index: 10;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .table-custom tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e3e6f0;
        }
        .table-custom tbody tr:hover { background-color: #f8f9fc; }
        .sort-icon { font-size: 0.8rem; margin-left: 5px; color: #d1d3e2; }
        .sort-icon.active { color: var(--primary-color); }

        /* Filter inputs sizing */
        .col-search { font-size: 0.8rem; font-weight: normal; text-transform: none; }

        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff !important; }
            .content-card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .table-wrapper { max-height: none !important; overflow: visible !important; }
            .table-custom thead th { position: static !important; background-color: #ddd !important; color: #000 !important; box-shadow: none; }
        }

        .badge-status {
            padding: 0.5em 0.8em;
            font-weight: 600;
            border-radius: 0.35rem;
        }
        .badge-avail { background-color: rgba(28, 200, 138, 0.1); color: var(--success-color); border: 1px solid rgba(28, 200, 138, 0.2); }
        .badge-use   { background-color: rgba(78, 115, 223, 0.1); color: var(--primary-color); border: 1px solid rgba(78, 115, 223, 0.2); }
        .badge-broke { background-color: rgba(231, 74, 59, 0.1); color: var(--danger-color); border: 1px solid rgba(231, 74, 59, 0.2); }
        .badge-fix   { background-color: rgba(246, 194, 62, 0.1); color: #dda20a; border: 1px solid rgba(246, 194, 62, 0.2); }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    <div class="border-end bg-dark no-print" id="sidebar-wrapper">
        <div class="sidebar-heading">IT Asset Manager</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="employees.php"><i class="bi bi-people"></i> Employees</a>
            <a href="inventory.php" class="active"><i class="bi bi-box-seam"></i> Inventory</a>
            <a href="software_inventory.php"><i class="bi bi-disc"></i> Software</a>
            <a href="software_assignment.php"><i class="bi bi-key"></i> Licenses</a>
            <a href="transmittal.php"><i class="bi bi-arrow-left-right"></i> Transmittals</a>
            <a href="employee_clearance.php"><i class="bi bi-file-earmark-check"></i> Clearance</a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3 no-print">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i> Menu</button>
            <div class="ms-auto text-secondary small fw-bold">Hardware Inventory</div>
        </nav>

        <div class="container-fluid p-4">
            <h3 class="mb-4 text-dark fw-bold no-print">Hardware Assets</h3>

            <?php echo $message; ?>

            <div class="content-card no-print">
                <div class="card-header">
                    <span><i class="bi bi-plus-circle-fill me-2"></i> Register New Device</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="inventory.php">
                        <input type="hidden" name="add_asset" value="1">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="fam_tag_number" class="form-label">Asset Tag / ID</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-tag"></i></span>
                                    <input type="text" class="form-control" id="fam_tag_number" name="fam_tag_number" placeholder="e.g. FAM-001" required>
                                </div>
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
                                <label for="device_name" class="form-label">Model Name</label>
                                <input type="text" class="form-control" id="device_name" name="device_name" placeholder="e.g. Dell Latitude 5420" required>
                            </div>
                            <div class="col-md-3">
                                <label for="serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number" placeholder="S/N" required>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-3">
                                <label for="date_received" class="form-label">Date Received</label>
                                <input type="date" class="form-control" id="date_received" name="date_received" required>
                            </div>
                            <div class="col-md-9 d-flex align-items-end justify-content-end">
                                <button type="submit" class="btn btn-success px-4 shadow-sm"><i class="bi bi-save me-2"></i> Add to Inventory</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header no-print">
                    <span><i class="bi bi-list-check me-2"></i> Master List (<?php echo $asset_count; ?> Items)</span>

                    <div class="d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-secondary me-3" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Print / PDF
                        </button>

                        <form method="GET" action="inventory.php" class="d-flex" style="width: 280px;">
                            <div class="input-group input-group-sm">
                                <input
                                    class="form-control"
                                    type="search"
                                    id="globalSearch"
                                    placeholder="Search assets..."
                                    aria-label="Search"
                                    name="search"
                                    value="<?php echo htmlspecialchars($search_term); ?>"
                                >
                                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
                                <?php if (!empty($search_term)): ?>
                                    <a href="inventory.php" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive table-wrapper">
                        <table class="table table-custom table-hover align-middle" id="inventoryTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="min-width: 150px;">
                                        Asset Tag
                                        <?php
                                            $new_order = ($sort_by == 'a.fam_tag_number' && $sort_order == 'ASC') ? 'DESC' : 'ASC';
                                            $active = ($sort_by == 'a.fam_tag_number') ? 'active' : '';
                                            $icon = ($sort_by == 'a.fam_tag_number' && $sort_order == 'DESC') ? 'bi-sort-down' : 'bi-sort-up';
                                        ?>
                                        <a href="inventory.php?sort_by=tag&order=<?php echo $new_order; ?>" class="text-decoration-none no-print float-end">
                                            <i class="bi <?php echo $icon . ' ' . $active; ?> sort-icon"></i>
                                        </a>
                                        <input type="text" class="form-control form-control-sm mt-1 col-search no-print" data-col="0" placeholder="Filter tag...">
                                    </th>
                                    <th style="min-width: 150px;">
                                        Type
                                        <input type="text" class="form-control form-control-sm mt-1 col-search no-print" data-col="1" placeholder="Filter type...">
                                    </th>
                                    <th style="min-width: 200px;">
                                        Model & Serial
                                        <input type="text" class="form-control form-control-sm mt-1 col-search no-print" data-col="2" placeholder="Filter model/serial...">
                                    </th>
                                    <th style="min-width: 150px;">
                                        Received
                                        <?php
                                            $new_order = ($sort_by == 'a.date_received' && $sort_order == 'ASC') ? 'DESC' : 'ASC';
                                            $active = ($sort_by == 'a.date_received') ? 'active' : '';
                                        ?>
                                        <a href="inventory.php?sort_by=date_received&order=<?php echo $new_order; ?>" class="text-decoration-none no-print float-end">
                                            <i class="bi bi-arrow-down-up <?php echo $active; ?> sort-icon"></i>
                                        </a>
                                        <input type="text" class="form-control form-control-sm mt-1 col-search no-print" data-col="3" placeholder="Filter date...">
                                    </th>
                                    <th style="min-width: 150px;">
                                        Status
                                        <?php
                                            $new_order = ($sort_by == 'a.status' && $sort_order == 'ASC') ? 'DESC' : 'ASC';
                                            $active = ($sort_by == 'a.status') ? 'active' : '';
                                        ?>
                                        <a href="inventory.php?sort_by=status&order=<?php echo $new_order; ?>" class="text-decoration-none no-print float-end">
                                            <i class="bi bi-arrow-down-up <?php echo $active; ?> sort-icon"></i>
                                        </a>
                                        <select class="form-select form-select-sm mt-1 col-search no-print" data-col="4">
                                            <option value="">All Statuses</option>
                                            <option value="Available">Available</option>
                                            <option value="In Use">In Use</option>
                                            <option value="Broken">Broken</option>
                                            <option value="Repairing">Repairing</option>
                                        </select>
                                    </th>
                                    <th style="min-width: 150px;">
                                        Assigned To
                                        <input type="text" class="form-control form-control-sm mt-1 col-search no-print" data-col="5" placeholder="Filter assigned...">
                                    </th>
                                    <th class="text-end pe-4 no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($asset_count > 0): ?>
                                    <?php foreach ($assets as $asset):
                                        $statusClass = 'badge-avail';
                                        if ($asset['status'] === 'In Use') $statusClass = 'badge-use';
                                        if ($asset['status'] === 'Broken') $statusClass = 'badge-broke';
                                        if ($asset['status'] === 'Repairing') $statusClass = 'badge-fix';
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($asset['fam_tag_number']); ?></td>
                                        <td><?php echo htmlspecialchars($asset['device_type']); ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($asset['device_name']); ?></div>
                                            <div class="small text-muted">S/N: <?php echo htmlspecialchars($asset['serial_number']); ?></div>
                                        </td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($asset['date_received'] ? date('M d, Y', strtotime($asset['date_received'])) : '-'); ?></td>
                                        <td><span class="badge-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($asset['status']); ?></span></td>
                                        <td>
                                            <?php if ($asset['current_user_name']): ?>
                                                <i class="bi bi-person-fill text-secondary me-1"></i> <?php echo htmlspecialchars($asset['current_user_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Inventory</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 no-print">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button
                                                    class="btn btn-outline-warning"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editAssetModal"
                                                    data-id="<?php echo $asset['asset_id']; ?>"
                                                    data-tag="<?php echo htmlspecialchars($asset['fam_tag_number']); ?>"
                                                    data-type="<?php echo htmlspecialchars($asset['device_type']); ?>"
                                                    data-name="<?php echo htmlspecialchars($asset['device_name']); ?>"
                                                    data-serial="<?php echo htmlspecialchars($asset['serial_number']); ?>"
                                                    data-date="<?php echo htmlspecialchars($asset['date_received']); ?>"
                                                    data-status="<?php echo htmlspecialchars($asset['status']); ?>"
                                                    title="Edit"
                                                >
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>

                                                <?php if ($asset['status'] === 'Available' && empty($asset['current_user_name'])): ?>
                                                <button
                                                    class="btn btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteAssetModal"
                                                    data-id="<?php echo $asset['asset_id']; ?>"
                                                    data-tag="<?php echo htmlspecialchars($asset['fam_tag_number']); ?>"
                                                    title="Delete"
                                                >
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-outline-secondary" disabled title="Only unassigned Available assets can be deleted">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr id="noDataRow">
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-box-seam display-4 d-block mb-3 opacity-25"></i>
                                            <?php if (!empty($search_term)): ?>
                                                No assets found matching "<?php echo htmlspecialchars($search_term); ?>".
                                            <?php else: ?>
                                                Inventory is empty. Add your first device above.
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

<div class="modal fade" id="editAssetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Asset Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="inventory.php">
        <div class="modal-body p-4">
            <input type="hidden" name="update_asset" value="1">
            <input type="hidden" name="edit_asset_id" id="edit_asset_id">

            <div class="mb-3">
                <label for="edit_fam_tag_number" class="form-label">Asset Tag</label>
                <input type="text" class="form-control" id="edit_fam_tag_number" name="edit_fam_tag_number" required>
            </div>
            <div class="mb-3">
                <label for="edit_device_type" class="form-label">Device Type</label>
                <select class="form-select" id="edit_device_type" name="edit_device_type" required>
                    <option value="Desktop">Desktop</option>
                    <option value="Laptop">Laptop</option>
                    <option value="Monitor">Monitor</option>
                    <option value="Company Phone">Company Phone</option>
                    <option value="Tablet">Tablet</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="edit_device_name" class="form-label">Model Name</label>
                <input type="text" class="form-control" id="edit_device_name" name="edit_device_name" required>
            </div>
            <div class="mb-3">
                <label for="edit_serial_number" class="form-label">Serial Number</label>
                <input type="text" class="form-control" id="edit_serial_number" name="edit_serial_number" required>
            </div>
            <div class="mb-3">
                <label for="edit_date_received" class="form-label">Date Received</label>
                <input type="date" class="form-control" id="edit_date_received" name="edit_date_received" required>
            </div>
            <div class="mb-3">
                <label for="edit_status" class="form-label">Status</label>
                <select class="form-select" id="edit_status" name="edit_status" required>
                    <option value="Available">Available</option>
                    <option value="Broken">Broken</option>
                    <option value="Repairing">Repairing</option>
                </select>
                <div class="form-text text-muted small"><i class="bi bi-info-circle"></i> Assigned assets must be changed through Transmittal. This form is for unassigned inventory items.</div>
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

<div class="modal fade" id="deleteAssetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" action="inventory.php">
        <div class="modal-body text-center p-4">
            <input type="hidden" name="delete_asset" value="1">
            <input type="hidden" name="delete_asset_id" id="delete_asset_id">

            <i class="bi bi-exclamation-octagon text-danger display-3 mb-3"></i>
            <p class="mb-2">Permanently delete asset:</p>
            <h4 id="delete_fam_tag" class="fw-bold mb-3"></h4>
            <p class="small text-muted">This action cannot be undone.</p>
        </div>
        <div class="modal-footer border-0 justify-content-center">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger shadow-sm">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });

    var editAssetModal = document.getElementById('editAssetModal');
    editAssetModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;

        editAssetModal.querySelector('#edit_asset_id').value = button.getAttribute('data-id');
        editAssetModal.querySelector('#edit_fam_tag_number').value = button.getAttribute('data-tag');
        editAssetModal.querySelector('#edit_device_type').value = button.getAttribute('data-type');
        editAssetModal.querySelector('#edit_device_name').value = button.getAttribute('data-name');
        editAssetModal.querySelector('#edit_serial_number').value = button.getAttribute('data-serial');
        editAssetModal.querySelector('#edit_date_received').value = button.getAttribute('data-date');
        editAssetModal.querySelector('#edit_status').value = button.getAttribute('data-status');
    });

    var deleteAssetModal = document.getElementById('deleteAssetModal');
    deleteAssetModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        deleteAssetModal.querySelector('#delete_asset_id').value = button.getAttribute('data-id');
        deleteAssetModal.querySelector('#delete_fam_tag').textContent = button.getAttribute('data-tag');
    });

    // --- CLIENT SIDE FILTERING LOGIC ---
    $(document).ready(function() {
        function filterTable() {
            // We read the global search field to allow JS to filter it instantly before form submission
            const globalVal = $('#globalSearch').val().toLowerCase();

            $('#inventoryTable tbody tr').each(function() {
                const row = $(this);
                if (row.attr('id') === 'noDataRow') return;

                const rowText = row.text().toLowerCase();
                let showRow = true;

                // Global search check
                if (globalVal !== '' && rowText.indexOf(globalVal) === -1) {
                    showRow = false;
                }

                // Column search checks
                if (showRow) {
                    $('.col-search').each(function() {
                        const colIdx = $(this).data('col');
                        const filterVal = $(this).val().toLowerCase();
                        
                        if (filterVal !== '') {
                            const cellText = row.find('td').eq(colIdx).text().toLowerCase();
                            if (cellText.indexOf(filterVal) === -1) {
                                showRow = false;
                            }
                        }
                    });
                }

                row.toggle(showRow);
            });
        }

        $('#globalSearch').on('keyup', filterTable);
        $('.col-search').on('keyup change', filterTable);
    });
</script>

</body>
</html>