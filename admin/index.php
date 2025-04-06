<?php
// admin/index.php
require_once __DIR__ . '/layout.php';

echo admin_layout('Dashboard', <<<HTML
    <h2 class="section-title">Welcome, Admin</h2>
    <p class="text-content">Select an option from the sidebar to get started.</p>
HTML);
?>
