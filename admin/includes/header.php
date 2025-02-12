<?php
include ($_SERVER['DOCUMENT_ROOT'].'/checks/admin-check.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Age of Olympia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Ensure menu is fixed */
        .admin-menu {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        .admin-content {
            margin-left: 250px; /* Same as menu width */
            padding: 20px;
            width: calc(100% - 250px);
        }
        @media (max-width: 768px) {
            .admin-menu {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .admin-menu.active {
                transform: translateX(0);
            }
            .admin-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Mobile Menu Toggle -->
        <button class="menu-toggle">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <!-- Left Menu -->
        <div class="admin-menu">
            <h2>Admin Of Olympia</h2>
            <ul>
                <li class="menu-item">
                    <a href="/admin/index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Dashboard</a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-header">Notifications</a>
                    <ul class="submenu" style="display: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['players.php', 'notifications.php']) ? 'block' : 'none'; ?>">
                        <li><a href="/admin/players.php" <?php echo basename($_SERVER['PHP_SELF']) == 'players.php' ? 'class="active"' : ''; ?>>Joueurs</a></li>
                        <li><a href="/admin/notifications.php" <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'class="active"' : ''; ?>>Notifications</a></li>
                    </ul>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-header">Images</a>
                    <ul class="submenu" style="display: <?php echo basename($_SERVER['PHP_SELF']) == 'upload_image.php' ? 'block' : 'none'; ?>">
                        <li><a href="/admin/upload_image.php" <?php echo basename($_SERVER['PHP_SELF']) == 'upload_image.php' ? 'class="active"' : ''; ?>>Upload Images</a></li>
                    </ul>
                </li>
            </ul>
        </div>
        <div class="admin-content">