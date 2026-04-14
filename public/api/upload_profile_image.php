<?php
/**
 * Profile Image Upload API
 * Handles profile picture uploads for all user roles
 */

// Start session first (required for authentication)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../core/BaseController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Validate CSRF token
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || !isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
        throw new Exception('Invalid or missing security token');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Ensure profile_image column exists in furn_users table
    try {
        $pdo->exec("ALTER TABLE furn_users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
        // Column might already exist or other error - continue
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['profile_image'];
    
    // Validate upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        ];
        $errorMsg = $errors[$file['error']] ?? 'Unknown upload error';
        throw new Exception($errorMsg);
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size must not exceed 5MB.');
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($extension), $allowedExtensions)) {
        throw new Exception('Invalid file extension.');
    }
    
    $filename = 'user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../public/uploads/profile_images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Optimize image if it's too large
    if ($file['size'] > 2 * 1024 * 1024) { // If larger than 2MB
        optimizeImage($uploadPath, $mimeType);
    }
    
    // Delete old profile image if exists
    try {
        $stmt = $pdo->prepare("SELECT profile_image FROM furn_users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldImage = $stmt->fetchColumn();
        
        if ($oldImage && file_exists(__DIR__ . '/../../public/uploads/profile_images/' . $oldImage)) {
            unlink(__DIR__ . '/../../public/uploads/profile_images/' . $oldImage);
        }
    } catch (Exception $e) {
        // Ignore old image deletion errors
    }
    
    // Update database with new image
    $stmt = $pdo->prepare("UPDATE furn_users SET profile_image = ? WHERE id = ?");
    $stmt->execute([$filename, $userId]);
    
    // Get updated user data
    $stmt = $pdo->prepare("SELECT profile_image, first_name, last_name FROM furn_users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $imageUrl = BASE_URL . '/public/uploads/profile_images/' . $filename;
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile image uploaded successfully',
        'image_url' => $imageUrl,
        'filename' => $filename,
        'user' => $userData
    ]);
    
} catch (Exception $e) {
    // Clean up on error
    if (isset($uploadPath) && file_exists($uploadPath)) {
        @unlink($uploadPath);
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Optimize image by resizing if too large
 */
function optimizeImage($filepath, $mimeType) {
    list($width, $height) = getimagesize($filepath);
    
    // Only resize if dimensions are too large
    if ($width <= 800 && $height <= 800) {
        return;
    }
    
    $maxDimension = 800;
    $ratio = min($maxDimension / $width, $maxDimension / $height);
    $newWidth = intval($width * $ratio);
    $newHeight = intval($height * $ratio);
    
    $sourceImage = null;
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($filepath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($filepath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($filepath);
            break;
    }
    
    if (!$sourceImage) {
        return;
    }
    
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and WEBP
    if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save optimized image
    switch ($mimeType) {
        case 'image/jpeg':
            imagejpeg($resizedImage, $filepath, 85);
            break;
        case 'image/png':
            imagepng($resizedImage, $filepath, 6);
            break;
        case 'image/gif':
            imagegif($resizedImage, $filepath);
            break;
        case 'image/webp':
            imagewebp($resizedImage, $filepath, 80);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($resizedImage);
}
?>
