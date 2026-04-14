<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
}
require_once '../../config/db_config.php';

$data = json_decode(file_get_contents('php://input'), true);
$attendanceId = (int)($data['attendance_id'] ?? 0);
$message      = trim($data['message'] ?? '');
$employeeId   = $_SESSION['user_id'];

if (!$attendanceId || !$message) {
    echo json_e