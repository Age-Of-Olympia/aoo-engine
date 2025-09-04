<?php
use App\Service\AdminAuthorizationService;

AdminAuthorizationService::DoAdminCheck();

require_once __DIR__ . '/layout.php';
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
ob_start();
include ($_SERVER['DOCUMENT_ROOT'].'/src/Form/upload_image_form.php');

$content = ob_get_clean();
echo admin_layout('Image Upload ', $content);