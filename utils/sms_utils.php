<?php
/**
 * Simple SMS Utility Functions
 * Provides easy-to-use functions for sending SMS via Africa's Talking
 */

require_once dirname(__DIR__) . '/config/db_config.php';

/**
 * Send SMS to a phone number
 *
 * @param string $phone Phone number (Ethiopian format: +2519XXXXXXXX or 0912345678)
 * @param string $message SMS message content
 * @return array ['success' => bool, 'message' => string, 'error' => string|null]
 */
function send_sms($phone, $message) {
    $result = [
        'success' => false,
        'message' => '',
        'error' => null
    ];

    try {
        // Validate Ethiopian phone number
        $formattedPhone = format_ethiopian_phone($phone);
        if (!$formattedPhone) {
            $result['error'] = 'Invalid Ethiopian phone number format';
            return $result;
        }

        // Truncate message if too long (SMS limit is 160 chars, but we allow multipart)
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477) . '...';
        }

        // Check if we're in live mode
        $mode = defined('SMS_MODE') ? SMS_MODE : 'test';

        if ($mode === 'live') {
            // Send actual SMS
            $apiResult = send_africastalking_sms($formattedPhone, $message);
            $result['success'] = $apiResult['success'];
            $result['message'] = $apiResult['success'] ? 'SMS sent successfully' : 'Failed to send SMS';
            $result['error'] = $apiResult['error'];
        } else {
            // Test mode - log to file
            $logResult = log_sms_to_file($formattedPhone, $message);
            $result['success'] = $logResult;
            $result['message'] = 'SMS logged (test mode)';
        }

    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log('SMS send_sms error: ' . $e->getMessage());
    }

    return $result;
}

/**
 * Format Ethiopian phone number to international format
 *
 * @param string $phone Phone number
 * @return string|false Formatted phone number or false if invalid
 */
function format_ethiopian_phone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // If starts with 0, replace with Ethiopia country code
    if (strpos($phone, '0') === 0) {
        $phone = '251' . substr($phone, 1);
    }

    // If doesn't start with 251, add it
    if (strpos($phone, '251') !== 0) {
        $phone = '251' . $phone;
    }

    // Add + prefix
    $phone = '+' . $phone;

    // Validate Ethiopian phone number format (+2519XXXXXXXX)
    if (!preg_match('/^\+251[0-9]{9}$/', $phone)) {
        return false;
    }

    return $phone;
}

/**
 * Send SMS via Africa's Talking API
 *
 * @param string $phone Formatted phone number
 * @param string $message SMS content
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_africastalking_sms($phone, $message) {
    $result = ['success' => false, 'error' => null];

    $username = defined('AFRICASTALKING_USERNAME') ? AFRICASTALKING_USERNAME : '';
    $apiKey = defined('AFRICASTALKING_API_KEY') ? AFRICASTALKING_API_KEY : '';
    $senderId = defined('AFRICASTALKING_SENDER_ID') ? AFRICASTALKING_SENDER_ID : 'TEST';
    $apiUrl = defined('AFRICASTALKING_API_URL') ? AFRICASTALKING_API_URL : 'https://api.sandbox.africastalking.com';

    if (empty($username) || empty($apiKey)) {
        $result['error'] = 'Africa\'s Talking credentials not configured';
        return $result;
    }

    try {
        $url = $apiUrl . '/version1/messaging';

        $postData = [
            'username' => $username,
            'to' => $phone,
            'message' => $message
        ];
        
        // Add sender ID if configured (works for both sandbox and live)
        if (!empty($senderId)) {
            $postData['from'] = $senderId;
        }
        
        $postData = http_build_query($postData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'apiKey: ' . $apiKey
        ]);
        // For sandbox, disable SSL verification
        if (strpos($apiUrl, 'sandbox') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $result['error'] = "cURL Error: $curlError";
            return $result;
        }

        if ($httpCode === 201) {
            $responseData = json_decode($response, true);
            if (isset($responseData['SMSMessageData']['Recipients'][0]['status']) &&
                $responseData['SMSMessageData']['Recipients'][0]['status'] === 'Success') {
                $result['success'] = true;
                return $result;
            }
        }

        $result['error'] = "API Error: HTTP $httpCode - $response";

    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
    }

    return $result;
}

/**
 * Log SMS to file (for testing)
 *
 * @param string $phone Phone number
 * @param string $message SMS content
 * @return bool Success status
 */
function log_sms_to_file($phone, $message) {
    try {
        $logFile = dirname(__DIR__) . '/logs/sms.log';
        $logDir = dirname($logFile);

        // Ensure logs directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = "[" . date('Y-m-d H:i:s') . "] MODE: TEST | TO: $phone | MSG: $message\n";
        $logEntry .= str_repeat("-", 80) . "\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also log to PHP error log
        error_log("SMS TEST: Would send to $phone: $message");

        return true;
    } catch (Exception $e) {
        error_log('SMS logging error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send new order notification SMS
 *
 * @param string $phone Customer phone number
 * @param int $orderId Order ID
 * @param string $customerName Customer name (optional)
 * @return array SMS result
 */
function send_order_notification($phone, $orderId, $customerName = '') {
    $greeting = $customerName ? "Hi $customerName, " : "Hi, ";
    $message = $greeting . "Your order #$orderId has been received. We'll review it and send you a cost estimate soon. - SmartWorkshop";

    return send_sms($phone, $message);
}

/**
 * Send payment receipt notification SMS
 *
 * @param string $phone Customer phone number
 * @param int $orderId Order ID
 * @param float $amount Payment amount
 * @param string $customerName Customer name (optional)
 * @return array SMS result
 */
function send_payment_notification($phone, $orderId, $amount, $customerName = '') {
    $greeting = $customerName ? "Hi $customerName, " : "Hi, ";
    $message = $greeting . "We've received your payment of ETB $amount for order #$orderId. Thank you! - SmartWorkshop";

    return send_sms($phone, $message);
}

/**
 * Send order status update notification SMS
 *
 * @param string $phone Customer phone number
 * @param int $orderId Order ID
 * @param string $status New order status
 * @param string $customerName Customer name (optional)
 * @return array SMS result
 */
function send_status_notification($phone, $orderId, $status, $customerName = '') {
    $greeting = $customerName ? "Hi $customerName, " : "Hi, ";

    switch ($status) {
        case 'cost_estimated':
            $message = $greeting . "Your order #$orderId cost estimate is ready! Please login to review and approve. - SmartWorkshop";
            break;
        case 'approved':
            $message = $greeting . "Your order #$orderId has been approved! Please submit your deposit payment to start production. - SmartWorkshop";
            break;
        case 'in_production':
            $message = $greeting . "Great news! Your order #$orderId is now in production. We'll notify you when it's ready. - SmartWorkshop";
            break;
        case 'completed':
            $message = $greeting . "Your order #$orderId is complete! Please submit the remaining payment for delivery. - SmartWorkshop";
            break;
        case 'delivered':
            $message = $greeting . "Your order #$orderId has been delivered. Thank you for choosing SmartWorkshop!";
            break;
        default:
            $message = $greeting . "Your order #$orderId status has been updated to: $status. - SmartWorkshop";
    }

    return send_sms($phone, $message);
}
?>