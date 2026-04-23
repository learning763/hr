<?php
$pageTitle = "Settings";
$pageSubtitle = "Manage your preferences and account.";
$activePage = "settings";

// Prepare the content
ob_start();
?>

<div class="profile-card">
    <div class="info-row">
        <div class="info-label">Language</div>
        <div class="info-value">English (Default)</div>
    </div>
    <div class="info-row">
        <div class="info-label">Theme</div>
        <div class="info-value">Light Mode</div>
    </div>
    <div class="info-row">
        <div class="info-label">Notifications</div>
        <div class="info-value">Email & SMS alerts enabled</div>
    </div>
    <div class="info-row">
        <div class="info-label">Two-Factor Auth</div>
        <div class="info-value">Disabled</div>
    </div>
    <div class="info-row">
        <div class="info-label">Session Timeout</div>
        <div class="info-value">30 minutes</div>
    </div>
</div>

<?php
$content = ob_get_clean();
include('includes/layout.php');
?>