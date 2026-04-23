<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cyber HRMS | <?php echo $pageTitle ?? 'Dashboard'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php include('styles.php'); ?>
</head>
<body data-page="<?php echo $activePage ?? 'dashboard'; ?>">
    <?php include('includes/navbar.php'); ?>
    <div class="layout">
        <?php include('sidebar.php'); ?>
        <div class="main-content">
            <div class="page-content">
                <div class="page-title"><?php echo $pageTitle ?? 'Dashboard'; ?></div>
                <div class="page-subtitle"><?php echo $pageSubtitle ?? ''; ?></div>
                
                <!-- Main Content Area -->
                <?php echo $content ?? ''; ?>
                
            </div>
            <?php include('footer.php'); ?>
        </div>
    </div>
    
    <script src="helper/toast.js"></script>
    <script src="helper/common.js"></script>
</body>
</html>