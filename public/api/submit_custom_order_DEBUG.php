<?php
session_start();
require_once '../../config/db_config.php';

header('Content-Type: application/json');

// ============================================
// DEBUG MODE - Shows all received data
// ============================================

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'debug' => 'Not logged in or not a customer']);
    exit;
}

try {
    $customerId = $_SESSION['user_id'];
    
    // ============================================
    // DEBUG: Show all POST data received
    // ============================================
    $debugInfo = [
        'POST_data' => $_POST,
        'FILES_data' => $_FILES,
        'session_user_id' => $customerId,
        'session_role' => $_SESSION['user_role']
    ];
    
    // Get form data - Basic fields
    $orderNumber = $_POST['order_number'] ?? '';
    $furnitureType = $_POST['furniture_type'] ?? '';
    $furnitureName = $_POST['furniture_name'] ?? '';
    $length = floatval($_POST['length'] ?? 0);
    $width = floatval($_POST['width'] ?? 0);
    $height = floatval($_POST['height'] ?? 0);
    $material = $_POST['material'] ?? '';
    $color = $_POST['color'] ?? '';
    $designDescription = $_POST['design_description'] ?? '';
    $specialNotes = $_POST['special_notes'] ?? '';
    
    // NEW ERP FIELDS
    $quantity = intval($_POST['quantity'] ?? 1);
    $budgetRange = $_POST['budget_range'] ?? '';
    $preferredDeliveryDate = $_POST['preferred_delivery_date'] ?? null;
    
    // ============================================
    // DEBUG: Show parsed values
    // ============================================
    $parsedValues = [
        'order_number' => $orderNumber,
        'furniture_type' => $furnitureType,
        'furniture_name' => $furnitureName,
        'length' => $length,
        'width' => $width,
        'height' => $height,
        'material' => $material,
        'color' => $color,
        'design_description' => $designDescription,
        'special_notes' => $specialNotes,
        'quantity' => $quantity,
        'budget_range' => $budgetRange,
        'preferred_delivery_date' => $preferredDeliveryDate
    ];
    
    // ============================================
    // DEBUG: Check each validation
    // ============================================
    $validationChecks = [];
    
    // Check: furniture_type
    if (!$furnitureType) {
        $validationChecks[] = '❌ furniture_type is EMPTY';
    } else {
        $validationChecks[] = '✅ furniture_type = "' . $furnitureType . '"';
    }
    
    // Check: color
    if (!$color) {
        $validationChecks[] = '❌ color is EMPTY';
    } else {
        $validationChecks[] = '✅ color = "' . $color . '"';
    }
    
    // Check: design_description
    if (!$designDescription) {
        $validationChecks[] = '❌ design_description is EMPTY';
    } else {
        $validationChecks[] = '✅ design_description = "' . substr($designDescription, 0, 50) . '..."';
    }
    
    // Check: dimensions
    if ($length <= 0) {
        $validationChecks[] = '❌ length is INVALID (value: ' . $length . ')';
    } else {
        $validationChecks[] = '✅ length = ' . $length;
    }
    
    if ($width <= 0) {
        $validationChecks[] = '❌ width is INVALID (value: ' . $width . ')';
    } else {
        $validationChecks[] = '✅ width = ' . $width;
    }
    
    if ($height <= 0) {
        $validationChecks[] = '❌ height is INVALID (value: ' . $height . ')';
    } else {
        $validationChecks[] = '✅ height = ' . $height;
    }
    
    // Check: quantity
    if ($quantity < 1) {
        $validationChecks[] = '❌ quantity is INVALID (value: ' . $quantity . ')';
    } else {
        $validationChecks[] = '✅ quantity = ' . $quantity;
    }
    
    // Check: budget_range
    if (!$budgetRange) {
        $validationChecks[] = '❌ budget_range is EMPTY';
    } else {
        $validationChecks[] = '✅ budget_range = "' . $budgetRange . '"';
    }
    
    // ============================================
    // DEBUG: Determine which validation fails
    // ============================================
    $failedValidations = [];
    
    // Validation - Basic fields (furniture_name and material removed from form)
    if (!$furnitureType || !$color || !$designDescription) {
        if (!$furnitureType) $failedValidations[] = 'furniture_type is required';
        if (!$color) $failedValidations[] = 'color is required';
        if (!$designDescription) $failedValidations[] = 'design_description is required';
    }
    
    if ($length <= 0 || $width <= 0 || $height <= 0) {
        if ($length <= 0) $failedValidations[] = 'length must be greater than 0';
        if ($width <= 0) $failedValidations[] = 'width must be greater than 0';
        if ($height <= 0) $failedValidations[] = 'height must be greater than 0';
    }
    
    // NEW ERP VALIDATIONS
    if ($quantity < 1) {
        $failedValidations[] = 'quantity must be at least 1';
    }
    
    if (!$budgetRange) {
        $failedValidations[] = 'budget_range is required';
    }
    
    // ============================================
    // DEBUG: Output all debug information
    // ============================================
    echo json_encode([
        'success' => false,
        'message' => 'DEBUG MODE - Showing all validation details',
        'debug' => [
            'raw_POST_data' => $debugInfo['POST_data'],
            'raw_FILES_data' => $debugInfo['FILES_data'],
            'parsed_values' => $parsedValues,
            'validation_checks' => $validationChecks,
            'failed_validations' => $failedValidations,
            'error_message' => empty($failedValidations) ? 'All validations passed!' : 'Validation failed: ' . implode(', ', $failedValidations)
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Exception: ' . $e->getMessage(),
        'debug' => [
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'exception_trace' => $e->getTraceAsString()
        ]
    ], JSON_PRETTY_PRINT);
}
