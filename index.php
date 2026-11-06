<?php
// Main entry point of the system.
// Signed in -> your dashboard. Not signed in -> the public page (which has the Login link).
require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['adviser_id']))      { header('Location: admin/adviser/dashboard.php'); exit; }
if (isset($_SESSION['officer_id']))      { header('Location: admin/officer/dashboard.php'); exit; }
header('Location: public/index.php');
exit;
