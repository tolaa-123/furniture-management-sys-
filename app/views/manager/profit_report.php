<?php
// Redirected — profit summary is now part of the unified Reports page
if (session_status() === PHP_SESSION_NONE) session_start();
header('Location: ' . BASE_URL . '/public/manager/reports?report=profit');
exit();
