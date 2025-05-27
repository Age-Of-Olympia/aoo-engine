<?php
include ($_SERVER['DOCUMENT_ROOT'].'/checks/admin-check.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

// admin/layout.php
function admin_layout($title, $content) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title - Admin of Olympia</title>
    <link rel="stylesheet" href="/css/main.css">
    <style>
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            width: 280px;
            background: var(--dark-bg-color);
            padding: 30px 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .admin-sidebar .main-title {
            color: var(--primary-color);
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .vertical-nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--light-text-color);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: var(--primary-color);
            transform: translateX(5px);
        }
        
        .admin-main {
            flex: 1;
            padding: 30px;
            background: var(--light-bg-color);
        }
        
        @media (max-width: 768px) {
            .admin-layout {
                flex-direction: column;
            }
            
            .admin-sidebar {
                width: 100%;
                padding: 15px;
            }
            
            .admin-main {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="admin-sidebar">
            <h1 class="main-title">Admin of Olympia</h1>
            <nav class="vertical-nav">
                <a href="/admin/upload_image.php" class="nav-link">Upload Images</a>
                <!-- <a href="/admin/players.php" class="nav-link">Manage Players</a> -->
                <a href="/admin/world_map.php" class="nav-link">Manage World Map</a>
                <a href="/admin/local_maps.php" class="nav-link">Manage Local Maps</a>
                <a href="/admin/screenshots.php" class="nav-link">Manage Screenshots</a>
            </nav>
        </div>
        
        <div class="admin-main">
            $content
        </div>
    </div>
</body>
</html>
HTML;
}
?>