<?php
/**
 * Email Notification Service
 * Sends automated emails for key order lifecycle events
 */
class EmailService {
    private $senderEmail = 'noreply@furnitureworkshop.com';
    private $senderName  = 'SmartWorkshop';

    private $smtpHost = '';
    private $smtpPort = 587;
    private $smtpUser = '';
    private $smtpPass = '';
    private $smtpEncryption = 'tls';
    private $lastError = '';
    private $smtpDebug = false;

    public function __construct() {
        if (!defined('SMTP_HOST')) {
            if (file_exists(__DIR__ . '/../../config/config.php')) {
                require_once __DIR__ . '/../../config/config.php';
            }
        }

        $this->smtpHost = defined('SMTP_HOST') ? SMTP_HOST : '';
        $this->smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $this->smtpUser = defined('SMTP_USER') ? SMTP_USER : (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
        $this->smtpPass = defined('SMTP_PASS') ? SMTP_PASS : (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
        if (defined('SMTP_ENCRYPTION')) {
            $this->smtpEncryption = SMTP_ENCRYPTION;
        }
        if (defined('SMTP_FROM_EMAIL')) {
            $this->senderEmail = SMTP_FROM_EMAIL;
        }
        if (defined('SMTP_FROM_NAME')) {
            $this->senderName = SMTP_FROM_NAME;
        }
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $this->smtpDebug = true;
        }

        try {
            require_once __DIR__ . '/../../core/Database.php';
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->query("SELECT * FROM furn_email_config WHERE is_active = 1 LIMIT 1");
            $emailConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($emailConfig) {
                $this->smtpHost = $emailConfig['smtp_host'] ?: $this->smtpHost;
                $this->smtpPort = !empty($emailConfig['smtp_port']) ? (int) $emailConfig['smtp_port'] : $this->smtpPort;
                $this->smtpUser = $emailConfig['smtp_username'] ?: $this->smtpUser;
                $this->smtpPass = $emailConfig['smtp_password'] ?: $this->smtpPass;
                $this->smtpEncryption = $emailConfig['smtp_encryption'] ?: $this->smtpEncryption;
                $this->senderEmail = $emailConfig['from_email'] ?: $this->senderEmail;
                $this->senderName = $emailConfig['from_name'] ?: $this->senderName;
            }
        } catch (Exception $e) {
            // Ignore DB email config if unavailable
        }
    }

    public function getLastError() {
        return $this->lastError;
    }

    private function setLastError($message) {
        $this->lastError = $message;
    }

    public function setSenderEmail($email) {
        $this->senderEmail = $email;
    }

    public function setSenderName($name) {
        $this->senderName = $name;
    }

    public function sendOrderConfirmation($customerEmail, $orderData) {
        $subject = "Order Confirmation - Order #" . $orderData['order_number'];
        $body    = $this->orderConfirmationTemplate($orderData);
        return $this->send($customerEmail, $subject, $body);
    }

    public function sendPaymentReminder($customerEmail, $orderData) {
        $subject = "Payment Required - Order #" . $orderData['order_number'];
        $body    = $this->paymentReminderTemplate($orderData);
        return $this->send($customerEmail, $subject, $body);
    }

    public function sendProductionUpdate($customerEmail, $orderData) {
        $subject = "Production Update - Order #" . $orderData['order_number'];
        $body    = $this->productionUpdateTemplate($orderData);
        return $this->send($customerEmail, $subject, $body);
    }

    public function sendOrderCompletion($customerEmail, $orderData) {
        $subject = "Your Order is Ready - Order #" . $orderData['order_number'];
        $body    = $this->orderCompletionTemplate($orderData);
        return $this->send($customerEmail, $subject, $body);
    }

    public function sendPasswordReset($customerEmail, $resetLink) {
        $subject = "Password Reset Request";
        $body    = $this->passwordResetTemplate($resetLink);
        return $this->send($customerEmail, $subject, $body);
    }

    // -------------------------------------------------------
    // Core send method
    // -------------------------------------------------------
    public function send($to, $subject, $body) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->senderName} <{$this->senderEmail}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Date: " . gmdate('D, d M Y H:i:s') . " +0000\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        if (!empty($this->smtpHost)) {
            $result = $this->smtpSend($to, $subject, $body, $headers);
        } else {
            $result = @mail($to, $subject, $body, $headers);
        }

        if (!$result) {
            $error = $this->getLastError() ?: 'Unknown SMTP error';
            error_log("EmailService: Failed to send email to {$to} | Subject: {$subject} | Error: {$error}");
        }
        return $result;
    }

    private function smtpSend($to, $subject, $body, $headers) {
        $port = (int) $this->smtpPort;
        $encryption = strtolower($this->smtpEncryption);
        $protocol = 'tcp://';

        if ($encryption === 'ssl') {
            $protocol = 'ssl://';
            if ($port === 0) {
                $port = 465;
            }
        }

        $remoteAddress = $protocol . $this->smtpHost . ':' . $port;
        $timeout = 10;
        $contextOptions = ['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ]];

        // FIX FOR LOCALHOST TESTING - Remove when on live server
        if (defined('BASE_URL') && strpos(BASE_URL, 'localhost') !== false) {
            $contextOptions['ssl']['verify_peer'] = false;
            $contextOptions['ssl']['verify_peer_name'] = false;
            $contextOptions['ssl']['allow_self_signed'] = true;
        }

        $context = stream_context_create($contextOptions);
        $socket = @stream_socket_client($remoteAddress, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            $this->setLastError("SMTP connection failed: {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($socket, $timeout);

        if (!$this->smtpExpect($socket, 220)) {
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'EHLO ' . gethostname(), [250])) {
            if (!$this->smtpCommand($socket, 'HELO ' . gethostname(), [250])) {
                fclose($socket);
                return false;
            }
        }

        if ($encryption === 'tls') {
            if (!$this->smtpCommand($socket, 'STARTTLS', [220])) {
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->setLastError('EmailService SMTP STARTTLS negotiation failed.');
                fclose($socket);
                return false;
            }
            if (!$this->smtpCommand($socket, 'EHLO ' . gethostname(), [250])) {
                fclose($socket);
                return false;
            }
        }

        if (!empty($this->smtpUser) && !empty($this->smtpPass)) {
            if (!$this->smtpCommand($socket, 'AUTH LOGIN', [334])) {
                fclose($socket);
                return false;
            }
            if (!$this->smtpCommand($socket, base64_encode($this->smtpUser), [334])) {
                fclose($socket);
                return false;
            }
            if (!$this->smtpCommand($socket, base64_encode($this->smtpPass), [235])) {
                fclose($socket);
                return false;
            }
        }

        if (!$this->smtpCommand($socket, 'MAIL FROM: <' . $this->senderEmail . '>', [250])) {
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'RCPT TO: <' . $to . '>', [250, 251])) {
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'DATA', [354])) {
            fclose($socket);
            return false;
        }

        $message = "Subject: {$subject}\r\n" . $headers . "\r\n" . $body;
        $message = str_replace("\r\n.", "\r\n..", $message);
        $message .= "\r\n.\r\n";
        fputs($socket, $message);

        if (!$this->smtpExpect($socket, 250)) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    }

    private function smtpCommand($socket, $command, array $expectedCodes = [250]) {
        fputs($socket, $command . "\r\n");
        $response = $this->smtpGetResponse($socket);
        if ($this->smtpDebug) {
            error_log("EmailService DEBUG COMMAND: {$command} | RESPONSE: {$response}");
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            $this->setLastError("SMTP command failed: {$command} | Response: {$response}");
            return false;
        }
        return true;
    }

    private function smtpExpect($socket, $expectedCode) {
        $response = $this->smtpGetResponse($socket);
        if ($this->smtpDebug) {
            error_log("EmailService DEBUG EXPECT: expected {$expectedCode} | RESPONSE: {$response}");
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            $this->setLastError("SMTP expected {$expectedCode} but got {$response}");
            return false;
        }
        return true;
    }

    private function smtpGetResponse($socket) {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (isset($line[3]) && $line[3] !== '-') {
                break;
            }
        }
        return trim($response);
    }

    // -------------------------------------------------------
    // Email Templates
    // -------------------------------------------------------
    private function baseTemplate($title, $content) {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
        <style>
            body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
            .wrapper{max-width:600px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)}
            .header{background:#3d1f14;color:#fff;padding:24px 30px;text-align:center}
            .header h1{margin:0;font-size:22px}
            .body{padding:30px}
            .body p{color:#444;line-height:1.6;margin:0 0 12px}
            .detail-box{background:#f8f4e9;border-left:4px solid #3d1f14;padding:16px 20px;border-radius:6px;margin:16px 0}
            .detail-box p{margin:4px 0;font-size:14px}
            .btn{display:inline-block;background:#3d1f14;color:#fff;padding:12px 28px;border-radius:50px;text-decoration:none;font-weight:bold;margin-top:16px}
            .footer{background:#f8f4e9;text-align:center;padding:16px;font-size:12px;color:#888}
        </style></head><body>
        <div class='wrapper'>
            <div class='header'><h1>SmartWorkshop</h1><p style='margin:4px 0 0;font-size:14px;opacity:.85'>{$title}</p></div>
            <div class='body'>{$content}</div>
            <div class='footer'>SmartWorkshop &mdash; Custom Furniture Crafted for You<br>This is an automated message, please do not reply.</div>
        </div></body></html>";
    }

    private function orderConfirmationTemplate($d) {
        $name    = htmlspecialchars($d['customer_name'] ?? 'Valued Customer');
        $orderNo = htmlspecialchars($d['order_number'] ?? 'N/A');
        $product = htmlspecialchars($d['product_name'] ?? 'Custom Furniture');
        $total   = number_format($d['estimated_total'] ?? 0, 2);
        $content = "<p>Dear {$name},</p>
            <p>Thank you for your order! We have received it and our team is reviewing the details.</p>
            <div class='detail-box'>
                <p><strong>Order Number:</strong> #{$orderNo}</p>
                <p><strong>Product:</strong> {$product}</p>
                <p><strong>Estimated Total:</strong> \${$total}</p>
            </div>
            <p>We will notify you once the manager approves the cost estimate.</p>";
        return $this->baseTemplate('Order Confirmation', $content);
    }

    private function paymentReminderTemplate($d) {
        $name    = htmlspecialchars($d['customer_name'] ?? 'Valued Customer');
        $orderNo = htmlspecialchars($d['order_number'] ?? 'N/A');
        $deposit = number_format($d['deposit_amount'] ?? 0, 2);
        $due     = htmlspecialchars($d['due_date'] ?? 'As soon as possible');
        $content = "<p>Dear {$name},</p>
            <p>Your order cost has been approved and is ready for the deposit payment.</p>
            <div class='detail-box'>
                <p><strong>Order Number:</strong> #{$orderNo}</p>
                <p><strong>Deposit Required (40%):</strong> \${$deposit}</p>
                <p><strong>Due Date:</strong> {$due}</p>
            </div>
            <p>Please log in to your account to upload your payment receipt.</p>";
        return $this->baseTemplate('Payment Required', $content);
    }

    private function productionUpdateTemplate($d) {
        $name    = htmlspecialchars($d['customer_name'] ?? 'Valued Customer');
        $orderNo = htmlspecialchars($d['order_number'] ?? 'N/A');
        $status  = htmlspecialchars($d['status'] ?? 'In Production');
        $eta     = htmlspecialchars($d['expected_completion'] ?? 'To be confirmed');
        $content = "<p>Dear {$name},</p>
            <p>Great news! Your order has entered the production phase.</p>
            <div class='detail-box'>
                <p><strong>Order Number:</strong> #{$orderNo}</p>
                <p><strong>Status:</strong> {$status}</p>
                <p><strong>Expected Completion:</strong> {$eta}</p>
            </div>
            <p>You can track your order progress by logging into your account.</p>";
        return $this->baseTemplate('Production Update', $content);
    }

    private function orderCompletionTemplate($d) {
        $name   = htmlspecialchars($d['customer_name'] ?? 'Valued Customer');
        $orderNo = htmlspecialchars($d['order_number'] ?? 'N/A');
        $final  = number_format($d['final_amount'] ?? 0, 2);
        $content = "<p>Dear {$name},</p>
            <p>Your furniture is complete and ready for pickup or delivery!</p>
            <div class='detail-box'>
                <p><strong>Order Number:</strong> #{$orderNo}</p>
                <p><strong>Final Balance Due:</strong> \${$final}</p>
            </div>
            <p>Please complete the final payment to arrange delivery or pickup.</p>";
        return $this->baseTemplate('Order Complete', $content);
    }

    private function passwordResetTemplate($resetLink) {
        $link    = htmlspecialchars($resetLink);
        $content = "<p>We received a request to reset your password.</p>
            <p>Click the button below to set a new password. This link expires in 1 hour.</p>
            <a href='{$link}' class='btn'>Reset Password</a>
            <p style='margin-top:20px;font-size:13px;color:#888'>If you did not request this, you can safely ignore this email.</p>";
        return $this->baseTemplate('Password Reset', $content);
    }
}
