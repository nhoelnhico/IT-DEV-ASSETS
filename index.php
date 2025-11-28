<?php
// 1. Include the database connection
require_once 'includes/config.php'; // Adjust path if necessary

// Initialize variables with default values in case of DB error
$total_employees = 0;
$assets_in_use = 0;
$assets_available = 0;
$assets_broken = 0;
$transmittal_history = [];
$asset_status_data = [
    'In Use' => 0,
    'Available' => 0,
    'Broken' => 0
];

try {
    // 2. Fetch key metrics for the cards
    $total_employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $assets_in_use = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'In Use'")->fetchColumn();
    $assets_available = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
    $assets_broken = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Broken'")->fetchColumn();
    
    // 3. Fetch recent transmittals for the table
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

    // 4. Fetch data for Chart.js - Asset Status Breakdown
    $chart_data_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status");
    $raw_chart_data = $chart_data_stmt->fetchAll();

    foreach ($raw_chart_data as $row) {
        // Ensure keys exist, especially if you add a 'Repairing' status later
        $status_key = $row['status'];
        if (isset($asset_status_data[$status_key])) {
            $asset_status_data[$status_key] = (int)$row['count'];
        }
    }

} catch (\PDOException $e) {
    // Error handling goes here
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa; /* Light gray background */
        }
        #sidebar-wrapper {
            min-height: 100vh;
            margin-left: -15rem;
            transition: margin .25s ease-out;
            background-color: #343a40; /* Dark sidebar */
        }
        #sidebar-wrapper .sidebar-heading {
            padding: 0.875rem 1.25rem;
            font-size: 1.2rem;
            color: #ffffff;
        }
        #page-content-wrapper {
            min-width: 100vw;
        }
        .sidebar-nav a {
            color: #adb5bd; /* Light gray text */
            padding: 1rem 1.25rem;
            display: block;
            text-decoration: none;
        }
        .sidebar-nav a:hover {
            background-color: #495057; /* Slightly lighter hover */
            color: #ffffff;
        }
        /* Style for active page */
        .sidebar-nav a.active {
            background-color: #0d6efd; /* Bootstrap primary color */
            color: #ffffff;
            border-left: 5px solid #ffc107; /* Highlight with a secondary color */
        }
        /* Show sidebar on larger screens and when toggled */
        @media (min-width: 768px) {
            #sidebar-wrapper {
                margin-left: 0;
            }
            #page-content-wrapper {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">

    <div class="border-end bg-dark" id="sidebar-wrapper">
        <div class="sidebar-heading">IT Inventory System</div>
        <div class="list-group list-group-flush sidebar-nav">
            <a class="list-group-item list-group-item-action bg-dark active" href="index.php">📊 Dashboard</a>
            <a class="list-group-item list-group-item-action bg-dark" href="employees.php">🧑‍💻 Employees</a>
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
            <h1 class="mt-4 mb-4">Dashboard Overview</h1>

            <div class="row g-4">
                
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-primary text-white shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title">Total Employees</h5>
                            <h2 class="card-text display-4">
                                <?php echo $total_employees; ?>
                            </h2>
                        </div>
                        <div class="card-footer bg-primary border-0">
                            <a href="employees.php" class="text-white small">View Details →</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card bg-success text-white shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title">Assets In Use</h5>
                            <h2 class="card-text display-4">
                                <?php echo $assets_in_use; ?>
                            </h2>
                        </div>
                        <div class="card-footer bg-success border-0">
                            <a href="inventory.php" class="text-white small">View Inventory →</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card bg-warning text-dark shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title">Assets Available</h5>
                            <h2 class="card-text display-4">
                                <?php echo $assets_available; ?>
                            </h2>
                        </div>
                        <div class="card-footer bg-warning border-0">
                            <a href="inventory.php" class="text-dark small">Ready for Assignment →</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card bg-danger text-white shadow-lg">
                        <div class="card-body">
                            <h5 class="card-title">Broken Devices</h5>
                            <h2 class="card-text display-4">
                                <?php echo $assets_broken; ?>
                            </h2>
                        </div>
                        <div class="card-footer bg-danger border-0">
                            <a href="inventory.php" class="text-white small">Needs Repair →</a>
                        </div>
                    </div>
                </div>

            </div>
            <div class="row mt-5 g-4">
                <div class="col-lg-7">
                    <div class="card shadow-lg">
                        <div class="card-header bg-white">
                            Asset Status Breakdown
                        </div>
                        <div class="card-body">
                            <canvas id="assetStatusChart" class="p-3"></canvas> 
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow-lg">
                        <div class="card-header bg-white">
                            Recent Transmittals (Log)
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Asset Tag</th>
                                            <th>Type</th>
                                            <th>To Employee</th>
                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transmittal_history as $log): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($log['transmittal_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['fam_tag_number']); ?></td>
                                            <td><span class="badge <?php echo $log['transaction_type'] == 'OUT' ? 'bg-danger' : 'bg-success'; ?>"><?php echo $log['transaction_type']; ?></span></td>
                                            <td><?php echo $log['to_name'] ? htmlspecialchars($log['to_name']) : 'Inventory'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($transmittal_history)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No recent transmittals recorded.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="transmittal.php" class="btn btn-sm btn-outline-primary float-end">View All</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Custom JavaScript for Toggling the Sidebar
    document.getElementById("sidebarToggle").addEventListener("click", function() {
        var wrapper = document.getElementById("wrapper");
        wrapper.classList.toggle("toggled");
    });

    // Chart.js Data and Configuration
    const ctx = document.getElementById('assetStatusChart').getContext('2d');
    const assetStatusChart = new Chart(ctx, {
        type: 'doughnut', // Or 'pie'
        data: {
            labels: ['In Use', 'Available', 'Broken'],
            datasets: [{
                // PHP to JS: Pass the data from the backend
                data: [
                    <?php echo $asset_status_data['In Use']; ?>, 
                    <?php echo $asset_status_data['Available']; ?>, 
                    <?php echo $asset_status_data['Broken']; ?>
                ],
                backgroundColor: [
                    'rgba(13, 110, 253, 0.8)', // Bootstrap primary blue
                    'rgba(25, 135, 84, 0.8)',  // Bootstrap success green
                    'rgba(220, 53, 69, 0.8)'   // Bootstrap danger red
                ],
                borderColor: [
                    '#fff',
                    '#fff',
                    '#fff'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Allow chart to resize more freely
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Current Asset Status Breakdown'
                }
            }
        }
    });
</script>

</body>
</html>