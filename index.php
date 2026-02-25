<?php
// 1. Include the database connection
require_once 'includes/config.php';

// Initialize variables
$total_employees = 0;
$assets_in_use = 0;
$assets_available = 0;
$assets_broken = 0;
$transmittal_history = [];

// Matches your SQL device_type values
$device_types = ['Laptop', 'Desktop', 'Company Phone', 'Monitor', 'Tablet', 'Other'];
$device_stats = array_fill_keys($device_types, 0);

// Matches your SQL status ENUM
$asset_status_data = [
    'In Use' => 0,
    'Available' => 0,
    'Broken' => 0
];

try {
    // 2. Fetch key metrics
    $total_employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $assets_in_use = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'In Use'")->fetchColumn();
    $assets_available = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
    $assets_broken = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Broken'")->fetchColumn();
    
    // 3. Fetch Device Type counts
    $type_stmt = $pdo->query("SELECT device_type, COUNT(*) as count FROM assets GROUP BY device_type");
    $raw_types = $type_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($raw_types as $type => $count) {
        if (array_key_exists($type, $device_stats)) {
            $device_stats[$type] = (int)$count;
        } else {
            $device_stats['Other'] += (int)$count;
        }
    }

    // 4. Fetch recent transmittals
    $sql_history = "
        SELECT 
            t.transmittal_date, t.transaction_type, 
            a.fam_tag_number, 
            ef.name AS from_name, et.name AS to_name
        FROM 
            transmittals t
        JOIN 
            assets a ON t.asset_id = a.asset_id
        LEFT JOIN 
            employees ef ON t.from_id = ef.employee_id
        LEFT JOIN 
            employees et ON t.to_id = et.employee_id
        ORDER BY 
            t.transmittal_date DESC LIMIT 5
    ";
    $history_stmt = $pdo->query($sql_history);
    $transmittal_history = $history_stmt->fetchAll();

    // 5. Fetch chart data
    $chart_data_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status");
    $raw_chart_data = $chart_data_stmt->fetchAll();

    foreach ($raw_chart_data as $row) {
        if (array_key_exists($row['status'], $asset_status_data)) {
            $asset_status_data[$row['status']] = (int)$row['count'];
        }
    }

} catch (\PDOException $e) {
    // Error handling
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #4e73df; 
            --success-color: #1cc88a; 
            --danger-color: #e74a3b;  
            --info-color: #36b9cc;    
            --dark-sidebar: #2c3e50;
            --light-bg: #f3f4f6;
            --sidebar-width: 250px;
        }

        body { 
            background-color: var(--light-bg); 
            font-family: 'Segoe UI', sans-serif;
            overflow-x: hidden;
        }

        /* --- SIDEBAR FIXES --- */
        #wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #sidebar-wrapper {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            min-height: 100vh;
            background-color: var(--dark-sidebar);
            transition: all 0.3s;
        }

        #sidebar-wrapper.toggled {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        #sidebar-wrapper .sidebar-heading {
            padding: 1.5rem 1.25rem;
            font-size: 1.2rem;
            font-weight: bold;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-nav a {
            color: #bdc3c7;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: 0.3s;
        }

        .sidebar-nav a i { margin-right: 12px; font-size: 1.1rem; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-nav a.active { background: rgba(255,255,255,0.1); color: #fff; border-left: 4px solid var(--info-color); }

        /* --- CONTENT FIXES --- */
        #page-content-wrapper {
            width: 100%;
            transition: all 0.3s;
        }

        /* Mobile specific sidebar behavior */
        @media (max-width: 768px) {
            #sidebar-wrapper { margin-left: calc(-1 * var(--sidebar-width)); }
            #sidebar-wrapper.toggled { margin-left: 0; }
        }

        /* Cards & Styling */
        .stat-card {
            border: none; border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            background: #fff; position: relative;
        }
        .border-left-primary { border-left: 5px solid var(--primary-color); }
        .border-left-success { border-left: 5px solid var(--success-color); }
        .border-left-danger  { border-left: 5px solid var(--danger-color); }
        .border-left-info    { border-left: 5px solid var(--info-color); }

        .text-xs { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #858796; }
        .h5-number { font-size: 1.5rem; font-weight: 700; color: #5a5c69; }
        
        .icon-box {
            opacity: 0.15; position: absolute;
            right: 15px; top: 15px; font-size: 2.5rem;
        }

        .mini-card {
            background: #fff; border-radius: 10px; padding: 12px; text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

<div id="wrapper">
    <div id="sidebar-wrapper">
        <div class="sidebar-heading">IT Asset Manager</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a href="index.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="employees.php"><i class="bi bi-people"></i> Employees</a>
            <a href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a>
            <a href="software_inventory.php"><i class="bi bi-disc"></i> Software</a> 
            <a href="software_assignment.php"><i class="bi bi-key"></i> Licenses</a>
            <a href="transmittal.php"><i class="bi bi-arrow-left-right"></i> Transmittals</a>
            <a href="employee_clearance.php"><i class="bi bi-file-earmark-check"></i> Clearance</a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-3">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <span class="ms-3 fw-bold text-secondary"><?php echo date('F j, Y'); ?></span>
        </nav>

        <div class="container-fluid p-4">
            <h4 class="mb-4 fw-bold text-dark">Executive Dashboard</h4>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card p-3 border-left-info">
                        <div class="text-xs">Employees</div>
                        <div class="h5-number"><?php echo $total_employees; ?></div>
                        <i class="bi bi-people-fill icon-box text-info"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card p-3 border-left-primary">
                        <div class="text-xs">In Use</div>
                        <div class="h5-number"><?php echo $assets_in_use; ?></div>
                        <i class="bi bi-pc-display icon-box text-primary"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card p-3 border-left-success">
                        <div class="text-xs">Available</div>
                        <div class="h5-number"><?php echo $assets_available; ?></div>
                        <i class="bi bi-check-circle-fill icon-box text-success"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card p-3 border-left-danger">
                        <div class="text-xs">Broken</div>
                        <div class="h5-number"><?php echo $assets_broken; ?></div>
                        <i class="bi bi-exclamation-triangle-fill icon-box text-danger"></i>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12"><h6 class="text-uppercase small fw-bold text-muted">Inventory Breakdown</h6></div>
                <?php 
                $icons = [
                    'Laptop' => 'bi-laptop', 'Desktop' => 'bi-pc-display',
                    'Company Phone' => 'bi-phone', 'Monitor' => 'bi-display',
                    'Tablet' => 'bi-tablet', 'Other' => 'bi-cpu'
                ];
                foreach($device_stats as $type => $count): 
                ?>
                <div class="col-6 col-md-2">
                    <div class="mini-card">
                        <div class="small fw-bold text-muted"><?php echo $type; ?></div>
                        <div class="h5 mb-0 fw-bold"><?php echo $count; ?></div>
                        <i class="bi <?php echo $icons[$type] ?? 'bi-box'; ?> text-secondary opacity-50"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white fw-bold text-primary py-3">Asset Status Distribution</div>
                        <div class="card-body" style="min-height: 300px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between py-3">
                            <span class="fw-bold text-primary">Recent Activity</span>
                            <a href="transmittal.php" class="btn btn-sm btn-link p-0 text-decoration-none">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="small text-muted">
                                        <th class="ps-3">Date</th>
                                        <th>Action</th>
                                        <th>Asset Tag</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transmittal_history as $log): 
                                        $badge = 'bg-primary';
                                        if($log['transaction_type'] == 'Return') $badge = 'bg-warning text-dark';
                                        if($log['transaction_type'] == 'Repair') $badge = 'bg-danger';
                                    ?>
                                    <tr>
                                        <td class="ps-3 small"><?php echo date('M d', strtotime($log['transmittal_date'])); ?></td>
                                        <td><span class="badge <?php echo $badge; ?>"><?php echo $log['transaction_type']; ?></span></td>
                                        <td class="fw-bold"><?php echo $log['fam_tag_number']; ?></td>
                                        <td class="small"><?php echo $log['to_name'] ?? $log['from_name'] ?? 'System'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div> </div> </div> <script>
    // FIXED TOGGLE SCRIPT
    document.getElementById("sidebarToggle").onclick = function() {
        document.getElementById("sidebar-wrapper").classList.toggle("toggled");
    };

    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['In Use', 'Available', 'Broken'],
            datasets: [{
                data: [
                    <?php echo $asset_status_data['In Use']; ?>, 
                    <?php echo $asset_status_data['Available']; ?>, 
                    <?php echo $asset_status_data['Broken']; ?>
                ],
                backgroundColor: ['#4e73df', '#1cc88a', '#e74a3b'],
                borderWidth: 5
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>
</body>
</html>