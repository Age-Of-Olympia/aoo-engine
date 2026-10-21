<?php
/* The actions list lives in the workbench, which shows the same rows plus
 * the editor; this address stays for old links. */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');

redirectTo('/admin/action-workbench.php');
