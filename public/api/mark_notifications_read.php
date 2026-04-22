<?php
// Thin wrapper — delegates to unified notifications API
$_POST['action'] = 'mark_all_read';
require __DIR__ . '/notifications.php';
