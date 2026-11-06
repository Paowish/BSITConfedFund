<?php
require_once __DIR__ . '/config.php';

function requireAdviser() {
    if (!isset($_SESSION['adviser_id'])) {
        header('Location: /bsit-fund-system-v2/login.php');
        exit;
    }
}

function requireOfficer() {
    if (!isset($_SESSION['officer_id'])) {
        header('Location: /bsit-fund-system-v2/login.php');
        exit;
    }
}

function requireAuditor() {
    if (!isset($_SESSION['officer_id']) || $_SESSION['officer_position'] !== 'Auditor') {
        header('Location: /bsit-fund-system-v2/login.php');
        exit;
    }
}
?>