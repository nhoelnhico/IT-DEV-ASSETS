<?php
require_once 'includes/config.php';

// Initialize variables
$total_employees = 0;
$assets_in_use = 0;
$assets_available = 0;
$assets_broken = 0;
$assets_repairing = 0;
$transmittal_history = [];

// Matches your SQL device_type values
$device_types = ['Laptop', 'Desktop', 'Company Phone', 'Monitor', 'Tablet', 'Other'];
$device_stats = array_fill_keys($device_types, 0);

// Matches your SQL status ENUM
$asset_status_data = [
    'In Use' => 0,
    'Available' => 0,
    'Broken' => 0,
    'Repairing' => 0
];

try {
    // Fetch key metrics
    $total_employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $assets_in_use = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'In Use'")->fetchColumn();
    $assets_available = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
    $assets_broken = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Broken'")->fetchColumn();
    $assets_repairing = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Repairing'")->fetchColumn();

    // Fetch Device Type counts
    $type_stmt = $pdo->query("SELECT device_type, COUNT(*) as count FROM assets GROUP BY device_type");
    $raw_types = $type_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($raw_types as $type => $count) {
        if (array_key_exists($type, $device_stats)) {
            $device_stats[$type] = (int)$count;
        } else {
            $device_stats['Other'] += (int)$count;
        }
    }

    // Fetch recent transmittals
    $sql_history = "
        SELECT
            t.transmittal_date,
            t.transaction_type,
            a.fam_tag_number,
            ef.name AS from_name,
            et.name AS to_name
        FROM transmittals t
        JOIN assets a ON t.asset_id = a.asset_id
        LEFT JOIN employees ef ON t.from_id = ef.employee_id
        LEFT JOIN employees et ON t.to_id = et.employee_id
        ORDER BY t.transmittal_date DESC
        LIMIT 5
    ";
    $history_stmt = $pdo->query($sql_history);
    $transmittal_history = $history_stmt->fetchAll();

    // Fetch chart data
    $chart_data_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status");
    $raw_chart_data = $chart_data_stmt->fetchAll();

    foreach ($raw_chart_data as $row) {
        if (array_key_exists($row['status'], $asset_status_data)) {
            $asset_status_data[$row['status']] = (int)$row['count'];
        }
    }

} catch (\PDOException $e) {
    // Optional: log error
    // error_log($e->getMessage());
}

$page_title   = 'IT Inventory | Dashboard';
$active_page  = 'dashboard';
$topbar_label = date('F j, Y');
$extra_head   = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
include 'includes/head.php';
include 'includes/sidebar.php';
?>

        <div class="container-fluid p-4">
            <h4 class="mb-4 fw-bold">Executive Dashboard</h4>

            <div class="row g-4 mb-4">
                <div class="col-lg col-md-4 col-sm-6 reveal">
                    <div class="stat-card p-3 border-left-info">
                        <div class="text-xs">Employees</div>
                        <div class="h5-number"><?php echo $total_employees; ?></div>
                        <i class="bi bi-people-fill icon-box text-info"></i>
                    </div>
                </div>
                <div class="col-lg col-md-4 col-sm-6 reveal">
                    <div class="stat-card p-3 border-left-primary">
                        <div class="text-xs">In Use</div>
                        <div class="h5-number"><?php echo $assets_in_use; ?></div>
                        <i class="bi bi-pc-display icon-box text-primary"></i>
                    </div>
                </div>
                <div class="col-lg col-md-4 col-sm-6 reveal">
                    <div class="stat-card p-3 border-left-success">
                        <div class="text-xs">Available</div>
                        <div class="h5-number"><?php echo $assets_available; ?></div>
                        <i class="bi bi-check-circle-fill icon-box text-success"></i>
                    </div>
                </div>
                <div class="col-lg col-md-4 col-sm-6 reveal">
                    <div class="stat-card p-3 border-left-danger">
                        <div class="text-xs">Broken</div>
                        <div class="h5-number"><?php echo $assets_broken; ?></div>
                        <i class="bi bi-exclamation-triangle-fill icon-box text-danger"></i>
                    </div>
                </div>
                <div class="col-lg col-md-4 col-sm-6 reveal">
                    <div class="stat-card p-3 border-left-warning">
                        <div class="text-xs">Repairing</div>
                        <div class="h5-number"><?php echo $assets_repairing; ?></div>
                        <i class="bi bi-tools icon-box text-warning"></i>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12"><h6 class="text-uppercase small fw-bold text-muted">Inventory Breakdown</h6></div>
                <?php
                $icons = [
                    'Laptop' => 'bi-laptop',
                    'Desktop' => 'bi-pc-display',
                    'Company Phone' => 'bi-phone',
                    'Monitor' => 'bi-display',
                    'Tablet' => 'bi-tablet',
                    'Other' => 'bi-cpu'
                ];
                foreach($device_stats as $type => $count):
                ?>
                <div class="col-6 col-md-2 reveal">
                    <a href="inventory.php?search=<?php echo urlencode($type); ?>" class="text-decoration-none text-dark">
                        <div class="mini-card">
                            <div class="small fw-bold text-muted"><?php echo htmlspecialchars($type); ?></div>
                            <div class="h5 mb-0 fw-bold"><?php echo $count; ?></div>
                            <i class="bi <?php echo $icons[$type] ?? 'bi-box'; ?> text-secondary opacity-50"></i>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-4">
                <div class="col-lg-5 reveal">
                    <div class="content-card h-100">
                        <div class="card-header fw-bold text-primary py-3">Asset Status Distribution</div>
                        <div class="card-body" style="min-height: 300px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 reveal">
                    <div class="content-card h-100">
                        <div class="card-header d-flex justify-content-between py-3">
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
                                        $label = $log['transaction_type'];

                                        if ($log['transaction_type'] == 'IN') {
                                            $badge = 'bg-warning text-dark';
                                            $label = 'Return';
                                        }
                                        if ($log['transaction_type'] == 'OUT') {
                                            $badge = 'bg-primary';
                                            $label = 'Issue';
                                        }
                                        if ($log['transaction_type'] == 'Repair') {
                                            $badge = 'bg-danger';
                                            $label = 'Repair';
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-3 small"><?php echo date('M d', strtotime($log['transmittal_date'])); ?></td>
                                        <td><span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($log['fam_tag_number']); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($log['to_name'] ?? $log['from_name'] ?? 'System'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($transmittal_history)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No recent activity found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php
$extra_scripts = <<<'HTML'
<script>
    const ctx = document.getElementById('statusChart').getContext('2d');
    const themeText = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#5a5c69';
    Chart.defaults.color = themeText;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['In Use', 'Available', 'Broken', 'Repairing'],
            datasets: [{
                data: [STATUS_IN_USE, STATUS_AVAILABLE, STATUS_BROKEN, STATUS_REPAIRING],
                backgroundColor: ['#4e73df', '#1cc88a', '#e74a3b', '#f6c23e'],
                borderColor: 'rgba(0,0,0,0)',
                borderWidth: 4
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: { position: 'bottom', labels: { color: themeText, usePointStyle: true, padding: 16 } }
            }
        }
    });
</script>
HTML;
$extra_scripts = str_replace(
    ['STATUS_IN_USE', 'STATUS_AVAILABLE', 'STATUS_BROKEN', 'STATUS_REPAIRING'],
    [(int)$asset_status_data['In Use'], (int)$asset_status_data['Available'], (int)$asset_status_data['Broken'], (int)$asset_status_data['Repairing']],
    $extra_scripts
);
include 'includes/footer.php';
?>
