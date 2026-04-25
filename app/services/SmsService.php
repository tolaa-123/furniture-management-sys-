<?php
/**
 * SMS Service using Africa's Talking
 * Supports both TEST mode (logs to file) and LIVE mode (actual SMS)
 */
class SmsService {
    private $mode; // 'test' or 'live'
    private $username;
    private $apiKey;
    private $from;
    private $apiUrl;
    private $logFile;
    
    public function __construct($mode = null) {
        // Use constant if no mode specified
        $this->mode = $mode ?? (defined('SMS_MODE') ? SMS_MODE : 'test');
        $this->logFile = __DIR__ . '/../../logs/sms.log';
        
        if ($this->mode === 'live') {
            // Load credentials from config
            $this->username = defined('AFRICASTALKING_USERNAME') ? AFRICASTALKING_USERNAME : '';
            $this->apiKey = defined('AFRICASTALKING_API_KEY') ? AFRICASTALKING_API_KEY : '';
            $this->from = defined('AFRICASTALKING_SENDER_ID') ? AFRICASTALKING_SENDER_ID : 'SmartWorkshop';
            $this->apiUrl = defined('AFRICASTALKING_API_URL') ? AFRICASTALKING_API_URL : 'https://api.africastalking.com';
        }
    }
    
    /**
     * Send SMS to a phone number
     * @param string $phoneNumber Phone number (e.g., +251912345678)
     * @param string $message SMS message
     * @return bool Success status
     */
    public function sendSms($phoneNumber, $message) {
        // Validate phone number format
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        
        if (!$phoneNumber) {
            error_log("SMS Error: Invalid phone number");
            return false;
        }
        
        // Truncate message if too long (SMS limit is 160 chars, but we allow multipart)
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477) . '...';
        }
        
        if ($this->mode === 'test') {
            return $this->logSms($phoneNumber, $message);
        } else {
            return $this->sendLiveSms($phoneNumber, $message);
        }
    }
    
    /**
     * Send order notification SMS to customer
     */
    public function sendOrderNotification($phoneNumber, $orderId, $status, $customerName = '') {
        $greeting = $customerName ? "Hi $customerName, " : "Hi, ";
        
        switch ($status) {
            case 'created':
                $message = $greeting . "Your order #$orderId has been received. We'll review it and send you a cost estimate soon. - SmartWorkshop";
                break;
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
        
        return $this->sendSms($phoneNumber, $message);
    }
    
    /**
     * Send payment notification SMS
     */
    public function sendPaymentNotification($phoneNumber, $orderId, $amount, $type = 'received', $customerName = '') {
        $greeting = $customerName ? "Hi $customerName, " : "Hi, ";
        
        switch ($type) {
            case 'received':
                $message = $greeting . "We've received your payment of ETB $amount for order #$orderId. Thank you! - SmartWorkshop";
                break;
            case 'confirmed':
                $message = $greeting . "Your payment of ETB $amount for order #$orderId has been confirmed. Production will begin shortly! - SmartWorkshop";
                break;
            case 'reminder':
                $message = $greeting . "Reminder: Please submit your payment of ETB $amount for order #$orderId. - SmartWorkshop";
                break;
            default:
                $message = $greeting . "Payment update for order #$orderId: ETB $amount. - SmartWorkshop";
        }
        
        return $this->sendSms($phoneNumber, $message);
    }
    
    /**
     * Send notification to manager/admin
     */
    public function sendManagerNotification($phoneNumber, $type, $details) {
        switch ($type) {
            case 'new_order':
                $message = "New order #{$details['order_id']} from {$details['customer_name']}. Login to review. - SmartWorkshop";
                break;
            case 'new_payment':
                $message = "New payment ETB {$details['amount']} received for order #{$details['order_id']}. Please verify. - SmartWorkshop";
                break;
            case 'order_approved':
                $message = "Order #{$details['order_id']} approved by customer. Ready for production! - SmartWorkshop";
                break;
            case 'cost_estimated':
                $message = "Order #{$details['order_id']} has a cost estimate ready from {$details['manager_name']}. Please review and confirm payment or production. - SmartWorkshop";
                break;
            default:
                $message = "Notification: $type - " . json_encode($details) . " - SmartWorkshop";
        }
        
        return $this->sendSms($phoneNumber, $message);
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phoneNumber) {
        // Remove all non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If starts with 0, replace with Ethiopia country code
        if (strpos($phoneNumber, '0') === 0) {
            $phoneNumber = '251' . substr($phoneNumber, 1);
        }
        
        // If doesn't start with +, add it
        if (strpos($phoneNumber, '+') !== 0) {
            $phoneNumber = '+' . $phoneNumber;
        }
        
        // Validate Ethiopian phone number format (+2519XXXXXXXX)
        if (!preg_match('/^\+251[0-9]{9}$/', $phoneNumber)) {
            error_log("SMS Error: Invalid Ethiopian phone number format: $phoneNumber");
            return false;
        }
        
        return $phoneNumber;
    }
    
    /**
     * Log SMS to file (TEST mode)
     */
    private function logSms($phoneNumber, $message) {
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = "[" . date('Y-m-d H:i:s') . "] MODE: TEST | TO: $phoneNumber | MSG: $message\n";
        $logEntry .= str_repeat("-", 80) . "\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log to error log for easier debugging
        error_log("SMS TEST MODE: Would send to $phoneNumber: $message");
        
        return true;
    }
    
    /**
     * Send actual SMS via Africa's Talking API (LIVE mode)
     */
    private function sendLiveSms($phoneNumber, $message) {
        if (empty($this->username) || empty($this->apiKey)) {
            error_log("SMS Error: Africa's Talking credentials not configured");
            return false;
        }
        
        try {
            // Using Africa's Talking REST API
            $url = $this->apiUrl . '/version1/messaging';
            
            $postData = http_build_query([
                'username' => $this->username,
                'to' => $phoneNumber,
                'message' => $message,
                'from' => $this->from
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'apiKey: ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                error_log("SMS cURL Error: $curlError");
                return false;
            }
            
            if ($httpCode === 201) {
                $responseData = json_decode($response, true);
                if (isset($responseData['SMSMessageData']['Recipients'][0]['status']) && 
                    $responseData['SMSMessageData']['Recipients'][0]['status'] === 'Success') {
                    return true;
                }
            }
            
            error_log("SMS API Error: HTTP $httpCode - $response");
            return false;
            
        } catch (Exception $e) {
            error_log("SMS Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get SMS log contents (for admin viewing)
     */
    public function getSmsLog($lines = 50) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $content = file_get_contents($this->logFile);
        $entries = array_filter(explode(str_repeat("-", 80), $content));
        
        // Get last N entries
        $entries = array_slice(array_reverse($entries), 0, $lines);
        
        return array_map('trim', $entries);
    }
}
