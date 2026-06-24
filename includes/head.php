<?php
/**
 * Shared page head + opening shell.
 * Set before include:  $page_title (string), optional $extra_head (string of <link>/<script>).
 */
$page_title = isset($page_title) ? $page_title : 'IT Asset Manager';
$extra_head = isset($extra_head) ? $extra_head : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t !== 'light' && t !== 'dark') t = 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
            document.documentElement.classList.add('js');
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php echo $extra_head; ?>
    <link rel="stylesheet" href="assets/css/theme.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/theme.css') ?: '1'; ?>">
</head>
<body>
    <div class="bg-aurora" aria-hidden="true">
        <span class="blob blob-1"></span>
        <span class="blob blob-2"></span>
        <span class="blob blob-3"></span>
        <span class="blob blob-4"></span>
    </div>
    <div id="wrapper">
