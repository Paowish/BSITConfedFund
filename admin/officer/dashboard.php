<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
requireOfficer();

$position = $_SESSION['officer_position'];

if ($position === 'Treasurer') {
    require_once 'dashboard_treasurer.php';
} elseif ($position === 'Auditor') {
    header('Location: ../auditor/dashboard.php');
    exit;
} elseif ($position === 'Business Manager') {
    require_once 'dashboard_business_manager.php';
} else {
    require_once 'dashboard_viewer.php'; // Mayor, Vice Mayor
}