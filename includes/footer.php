<?php
/**
 * Shared closing shell + scripts.
 * Set before include:  optional $extra_scripts (string of <script> tags, runs after
 * Bootstrap and before app.js — put page libs like jQuery/Select2/Chart.js init here).
 */
$extra_scripts = isset($extra_scripts) ? $extra_scripts : '';
?>
    </div><!-- /#page-content-wrapper -->
</div><!-- /#wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php echo $extra_scripts; ?>
<script src="assets/js/app.js"></script>
</body>
</html>
