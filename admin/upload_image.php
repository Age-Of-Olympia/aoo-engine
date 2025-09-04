<?php
use App\Service\AdminAuthorizationService;
use App\Form\UploadImageForm;

require_once __DIR__ . '/layout.php';

ob_start();
UploadImageForm::renderForm();

$content = ob_get_clean();
echo admin_layout('Image Upload ', $content);