<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * Logout
 */

require_once __DIR__ . '/config.php';

unset($_SESSION['daleel_school_id']);
session_destroy();

header('Location: index.php');
exit;
