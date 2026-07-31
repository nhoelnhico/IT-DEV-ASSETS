<?php
/**
 * Shared sidebar + topbar, then opens #page-content-wrapper.
 * Set before include:  $active_page (e.g. 'inventory'), optional $topbar_label.
 */
$active_page  = isset($active_page) ? $active_page : '';
$topbar_label = isset($topbar_label) ? $topbar_label : '';

$nav_items = [
    'index.php'              => ['Dashboard',    'bi-speedometer2',        'dashboard'],
    'employees.php'          => ['Employees',    'bi-people',              'employees'],
    'inventory.php'          => ['Inventory',    'bi-box-seam',            'inventory'],
    'software_inventory.php' => ['Software',     'bi-disc',                'software'],
    'software_assignment.php'=> ['Licenses',     'bi-key',                 'licenses'],
    'transmittal.php'        => ['Transmittals', 'bi-arrow-left-right',    'transmittal'],
    'employee_clearance.php' => ['Clearance',    'bi-file-earmark-check',  'clearance'],
];
?>
    <div class="sidebar-backdrop"></div>
    <div id="sidebar-wrapper" class="no-print">
        <div class="sidebar-heading">
            <span class="logo-badge"><i class="bi bi-hdd-stack"></i></span>
            <span>IT Asset Manager</span>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($nav_items as $href => $item):
                $is_active = ($active_page === $item[2]) ? 'active' : ''; ?>
                <a href="<?php echo $href; ?>" class="<?php echo $is_active; ?>">
                    <i class="bi <?php echo $item[1]; ?>"></i> <span><?php echo $item[0]; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot no-print">
            <img src="assets/animation/it.svg" alt="" class="sidebar-anim" loading="lazy">
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar app-topbar no-print">
            <button class="icon-btn app-hamburger" id="sidebarToggle" type="button" aria-label="Toggle menu">
                <i class="bi bi-list"></i>
            </button>
            <span class="topbar-title"><?php echo $topbar_label; ?></span>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button class="icon-btn" id="themeToggle" type="button" aria-label="Switch theme" title="Dark mode">
                    <i class="bi bi-moon-stars-fill"></i>
                </button>
            </div>
        </nav>
