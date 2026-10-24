<?php
/**
 * SchoPlus Daleel - Seed export disabled per zero-seed policy
 */
if (!function_exists('exportMasterSeed')) {
    function exportMasterSeed(?PDO $pdo = null): int {
        return 0;
    }
}
