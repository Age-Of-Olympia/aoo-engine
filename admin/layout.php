<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
use App\Service\AdminAuthorizationService;
AdminAuthorizationService::DoAdminCheck();



// admin/layout.php
function admin_layout($title, $content) {
    // Get current page for active menu highlighting
    $currentPage = basename($_SERVER['PHP_SELF']);

    // Helper function to add active class and star
    $navLink = function($page, $label, $href) use ($currentPage) {
        $isActive = ($currentPage === $page) ||
                    ($page === 'tutorial.php' && $currentPage === 'tutorial-step-editor.php');
        $activeClass = $isActive ? ' active' : '';
        $star = $isActive ? '⭐ ' : '';
        return "<a href=\"$href\" class=\"nav-link$activeClass\">$star$label</a>";
    };

    $navigation =
        $navLink('index.php', 'Dashboard', '/admin/index.php') . "\n                " .
        $navLink('tutorial.php', 'Tutorial Config', '/admin/tutorial.php') . "\n                " .
        $navLink('tutorial-settings.php', 'Tutorial Flags', '/admin/tutorial-settings.php') . "\n                " .
        $navLink('upload_image.php', 'Upload Images', '/admin/upload_image.php') . "\n                " .
        "<!-- <a href=\"/admin/players.php\" class=\"nav-link\">Manage Players</a> -->\n                " .
        $navLink('world_map.php', 'Manage World Map', '/admin/world_map.php') . "\n                " .
        $navLink('local_maps.php', 'Manage Local Maps', '/admin/local_maps.php') . "\n                " .
        $navLink('screenshots.php', 'Manage Screenshots', '/admin/screenshots.php');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title - Admin of Olympia</title>
    <link href="/css/main.min.css?v=20251128" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Reset body constraints from main.min.css */
        body {
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            font-size: 14px;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
            max-width: 100%;
            width: 100%;
        }

        .admin-sidebar {
            width: 180px;
            min-width: 180px;
            background: #2c3e50;
            padding: 15px 10px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            font-size: 13px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .admin-sidebar .main-title {
            color: #4a90e2;
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 1.3em;
        }

        .vertical-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .vertical-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: #bdc3c7;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-size: 13px;
        }

        .vertical-nav .nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: #4a90e2;
            transform: translateX(3px);
        }

        .vertical-nav .nav-link.active {
            background: rgba(74, 144, 226, 0.2);
            color: #4a90e2;
            font-weight: 600;
        }

        .admin-main {
            flex: 1;
            padding: 20px;
            background: #ecf0f1;
            overflow-x: auto;
            font-size: 14px;
            margin-left: 180px;
        }

        .admin-main h1 {
            font-size: 1.8em;
            margin-bottom: 15px;
        }

        .admin-main h2 {
            font-size: 1.5em;
            margin-bottom: 12px;
        }

        .admin-main h3 {
            font-size: 1.3em;
            margin-bottom: 10px;
        }

        .container {
            max-width: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* Admin-specific components */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            color: white;
        }

        .btn-primary { background: #3498db; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-secondary { background: #95a5a6; }
        .btn-secondary:hover { background: #7f8c8d; }
        .btn-warning { background: #f39c12; }
        .btn-info { background: #3498db; }

        /* Outline buttons with proper contrast */
        .btn-outline-primary {
            background: transparent;
            border: 1px solid #3498db;
            color: #2471a3;
        }
        .btn-outline-primary:hover {
            background: #3498db;
            color: white;
        }
        .btn-outline-secondary {
            background: transparent;
            border: 1px solid #7f8c8d;
            color: #5d6d7e;
        }
        .btn-outline-secondary:hover {
            background: #7f8c8d;
            color: white;
        }
        .btn-outline-success {
            background: transparent;
            border: 1px solid #27ae60;
            color: #1e8449;
        }
        .btn-outline-success:hover {
            background: #27ae60;
            color: white;
        }
        .btn-outline-warning {
            background: transparent;
            border: 1px solid #d68910;
            color: #9c6d0b;
        }
        .btn-outline-warning:hover {
            background: #f39c12;
            color: white;
        }
        .btn-outline-danger {
            background: transparent;
            border: 1px solid #e74c3c;
            color: #c0392b;
        }
        .btn-outline-danger:hover {
            background: #e74c3c;
            color: white;
        }
        .btn-outline-info {
            background: transparent;
            border: 1px solid #3498db;
            color: #2471a3;
        }
        .btn-outline-info:hover {
            background: #3498db;
            color: white;
        }

        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .btn-lg { padding: 12px 24px; font-size: 16px; }
        .btn-group { display: inline-flex; gap: 4px; }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid transparent;
        }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }

        .card {
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
            margin-bottom: 15px;
        }

        .card-header {
            padding: 12px 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 6px 6px 0 0;
            margin: -15px -15px 15px -15px;
        }

        .card-body { padding: 0; }
        .card-title { margin: 0; font-size: 1.1em; }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .table th,
        .table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            font-size: 13px;
        }

        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 13px;
        }

        .table-striped tbody tr:nth-child(odd) { background: #f8f9fa; }
        .table-hover tbody tr:hover { background: #e9ecef; }
        .table-secondary { opacity: 0.6; }
        .table-responsive { overflow-x: auto; }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-primary { background: #4a90e2; color: white; }
        .badge-success { background: #27ae60; color: white; }
        .badge-info { background: #3498db; color: white; }
        .badge-warning { background: #f39c12; color: white; }

        .form-group { margin-bottom: 1rem; }

        .form-control {
            display: block;
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            line-height: 1.5;
            color: #495057;
            background: white;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #4a90e2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }

        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .form-check-input { margin-right: 8px; }
        .form-check-label { margin-bottom: 0; font-weight: normal; }

        textarea.form-control { resize: vertical; }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
        }

        .form-text {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #6c757d;
        }

        /* Navigation tabs (for step editor) */
        .nav-tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
            gap: 4px;
            list-style: none;
            padding: 0;
        }

        .nav-tabs .nav-item { list-style: none; }

        .nav-tabs .nav-link {
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            color: #495057;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
            background: rgba(255,255,255,0.5);
        }

        .nav-tabs .nav-link:hover { background: #f8f9fa; }
        .nav-tabs .nav-link.active {
            background: #4a90e2;
            color: white;
            border-color: #4a90e2;
        }

        .tab-content { display: block; }
        .tab-pane { display: none; }
        .tab-pane.fade { opacity: 0; transition: opacity 0.15s; }
        .tab-pane.show { opacity: 1; }
        .tab-pane.show.active { display: block; }

        /* Utility classes */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .col-md-3 { flex: 0 0 25%; padding: 0 10px; margin-bottom: 20px; }
        .col-md-4 { flex: 0 0 33.333%; padding: 0 10px; margin-bottom: 20px; }
        .col-md-6 { flex: 0 0 50%; padding: 0 10px; margin-bottom: 20px; }

        .display-4 { font-size: 2em; font-weight: 300; line-height: 1.2; }
        .text-muted { color: #6c757d; font-size: 0.9em; }
        .text-center { text-align: center; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .align-items-center { align-items: center; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 1rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .mt-4 { margin-top: 1.5rem; }

        @media (max-width: 768px) {
            .col-md-3, .col-md-4, .col-md-6 { flex: 0 0 100%; }
        }

        @media (max-width: 768px) {
            .admin-layout {
                flex-direction: column;
            }

            .admin-sidebar {
                width: 100%;
                padding: 15px;
                position: relative;
                height: auto;
            }

            .admin-main {
                padding: 20px;
                max-width: 100%;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="admin-sidebar">
            <h1 class="main-title">Admin of Olympia</h1>
            <nav class="vertical-nav">
                $navigation
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