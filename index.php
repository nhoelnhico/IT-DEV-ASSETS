<?php
// 1. Include the database connection
require_once 'includes/config.php';

// Initialize variables
$total_employees = 0;
$assets_in_use = 0;
$assets_available = 0;
$assets_broken = 0;
$transmittal_history = [];
// Default chart data to 0 to prevent JS errors if DB is empty
$asset_status_data = [
    'In Use' => 0,
    'Available' => 0,
    'Broken' => 0,
    'Repairing' => 0
];

try {
    // 2. Fetch key metrics
    $total_employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $assets_in_use = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'In Use'")->fetchColumn();
    $assets_available = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
    $assets_broken = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Broken'")->fetchColumn();
    
    // 3. Fetch recent transmittals
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

    // 4. Fetch data for Chart.js
    $chart_data_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status");
    $raw_chart_data = $chart_data_stmt->fetchAll();

    foreach ($raw_chart_data as $row) {
        $asset_status_data[$row['status']] = (int)$row['count'];
    }

} catch (\PDOException $e) {
    // Handle error silently or log it
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
            /* Professional Color Palette matching Cards & Graph */
            --primary-color: #4e73df; /* Blue - In Use */
            --success-color: #1cc88a; /* Green - Available */
            --danger-color: #e74a3b;  /* Red - Broken */
            --info-color: #36b9cc;    /* Cyan - Employees/Total */
            --dark-sidebar: #2c3e50;
            --light-bg: #f3f4f6;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar Styling */
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
        
        /* Main Content */
        #page-content-wrapper { min-width: 100vw; }
        @media (min-width: 768px) {
            #sidebar-wrapper { margin-left: 0; }
            #page-content-wrapper { min-width: 0; width: 100%; }
        }

        /* Dashboard Cards */
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease-in-out;
            background: #fff;
            overflow: hidden;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .card-body { padding: 1.5rem; position: relative; z-index: 2; }
        
        /* Decorative Side Borders */
        .border-left-primary { border-left: 5px solid var(--primary-color) !important; }
        .border-left-success { border-left: 5px solid var(--success-color) !important; }
        .border-left-danger  { border-left: 5px solid var(--danger-color) !important; }
        .border-left-info    { border-left: 5px solid var(--info-color) !important; }

        /* Typography */
        .text-xs { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
        .h5-number { font-size: 1.8rem; font-weight: 700; color: #5a5c69; margin-bottom: 0; }
        
        /* Icons in Cards */
        .icon-box {
            opacity: 0.3;
            transform: rotate(-15deg);
            position: absolute;
            right: 15px;
            top: 20px;
            font-size: 3rem;
        }

        /* Content Sections */
        .content-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            background: white;
            margin-bottom: 2rem;
        }
        .content-card .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            border-radius: 12px 12px 0 0;
        }

        /* Table Styling */
        .table-custom th {
            background-color: #f8f9fc;
            color: #858796;
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: 700;
            border-top: none;
        }
        .badge-status { font-weight: 500; padding: 0.5em 0.75em; }

    </style>
</head>
<body>

<div class="d-flex" id="wrapper">

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

        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle"><i class="bi bi-list"></i> Menu</button>
            <div class="ms-auto fw-bold text-secondary small">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <h3 class="mb-4 text-dark fw-bold">Dashboard Overview</h3>

            <div class="row g-4 mb-5">
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card border-left-info">
                        <div class="card-body">
                            <div class="text-xs text-info">Total Employees</div>
                            <div class="h5-number"><?php echo number_format($total_employees); ?></div>
                            <i class="bi bi-people-fill icon-box text-info"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="stat-card border-left-primary">
                        <div class="card-body">
                            <div class="text-xs text-primary">Assets In Use</div>
                            <div class="h5-number"><?php echo number_format($assets_in_use); ?></div>
                            <i class="bi bi-pc-display icon-box text-primary"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="stat-card border-left-success">
                        <div class="card-body">
                            <div class="text-xs text-success">Available Stock</div>
                            <div class="h5-number"><?php echo number_format($assets_available); ?></div>
                            <i class="bi bi-box-seam-fill icon-box text-success"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="stat-card border-left-danger">
                        <div class="card-body">
                            <div class="text-xs text-danger">Broken / Repair</div>
                            <div class="h5-number"><?php echo number_format($assets_broken); ?></div>
                            <i class="bi bi-tools icon-box text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <div class="col-lg-5">
                    <div class="content-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-pie-chart-fill me-2"></i>Asset Status Distribution</span>
                        </div>
                        <div class="card-body d-flex justify-content-center align-items-center position-relative">
                            <div style="height: 300px; width: 100%;">
                                <canvas id="assetStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="content-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-clock-history me-2"></i>Recent Activity Log</span>
                            <a href="transmittal.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-custom mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Date</th>
                                            <th>Transaction</th>
                                            <th>Asset</th>
                                            <th>Involved</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transmittal_history as $log): 
                                            // Style the badge based on transaction type
                                            $badgeClass = ($log['transaction_type'] == 'Return') ? 'bg-warning text-dark' : 'bg-primary';
                                            if ($log['transaction_type'] == 'Repair') $badgeClass = 'bg-danger';
                                            
                                            // Determine who was involved
                                            $involved = $log['transaction_type'] == 'Issue' ? $log['to_name'] : $log['from_name'];
                                        ?>
                                        <tr>
                                            <td class="ps-4 text-muted small"><?php echo date('M d, Y', strtotime($log['transmittal_date'])); ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?> badge-status"><?php echo htmlspecialchars($log['transaction_type']); ?></span></td>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($log['fam_tag_number']); ?></td>
                                            <td class="text-secondary"><?php echo $involved ? htmlspecialchars($involved) : 'Inventory'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($transmittal_history)): ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No recent activity found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle Script
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });

    // Chart.js Configuration
    // HEX CODES MUST MATCH THE CSS VARIABLES ABOVE
    const colorPalette = {
        inUse: '#4e73df',    // Blue
        available: '#1cc88a', // Green
        broken: '#e74a3b',    // Red
        repairing: '#f6c23e', // Yellow
        hover: '#858796'
    };

    const ctx = document.getElementById('assetStatusChart').getContext('2d');
    const assetStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['In Use', 'Available', 'Broken', 'Repairing'],
            datasets: [{
                data: [
                    <?php echo $asset_status_data['In Use'] ?? 0; ?>, 
                    <?php echo $asset_status_data['Available'] ?? 0; ?>, 
                    <?php echo $asset_status_data['Broken'] ?? 0; ?>,
                    <?php echo $asset_status_data['Repairing'] ?? 0; ?>
                ],
                backgroundColor: [
                    colorPalette.inUse,
                    colorPalette.available,
                    colorPalette.broken,
                    colorPalette.repairing
                ],
                hoverBackgroundColor: [
                    '#2e59d9', // Darker Blue
                    '#17a673', // Darker Green
                    '#e02d1b', // Darker Red
                    '#dda20a'  // Darker Yellow
                ],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
                borderWidth: 4
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '75%', // Makes the donut thinner for a modern look
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    titleColor: '#6e707e',
                    displayColors: true,
                    caretPadding: 10,
                }
            }
        }
    });
</script>

</body>
</html>