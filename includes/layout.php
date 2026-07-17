<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cyber HRMS | <?php echo $pageTitle ?? 'Dashboard'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/nepali.datepicker.v4.0.8.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="plugins/select2/css/select2-bootstrap-5-theme.min.css" rel="stylesheet">


    <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"> -->
    <?php include('styles.php'); ?>
    <?php include('css/style.css'); ?>
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