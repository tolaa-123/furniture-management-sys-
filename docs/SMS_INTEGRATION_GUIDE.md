# Africa's Talking SMS Integration Guide

## 1. Configuration (db_config.php)

Add these constants to your `config/db_config.php` file:

```php
// SMS Configuration (Africa's Talking)
// Set SMS_MODE to 'live' when you have API credentials
if (!defined('SMS_MODE')) {
    define('SMS_MODE', 'live'); // 'test' = log to file, 'live' = actually send SMS
}
if (!defined('AFRICASTALKING_USERNAME')) {
    define('AFRICASTALKING_USERNAME', 'sandbox'); // Your Africa's Talking username
}
if (!defined('AFRICASTALKING_API_KEY')) {
    define('AFRICASTALKING_API_KEY', 'atsk_eece984d339b85aad6267b3a1cd42aeef57ca56e49b8434e0934410ff70b475c46e0f42c'); // Your Africa's Talking API key
}
if (!defined('AFRICASTALKING_SENDER_ID')) {
    define('AFRICASTALKING_SENDER_ID', 'TEST'); // Sender ID (max 11 chars) - not used in sandbox
}
if (!defined('AFRICASTALKING_API_URL')) {
    define('AFRICASTALKING_API_URL', 'https://api.sandbox.africastalking.com'); // API URL (sandbox/live)
}
```

## 2. Reusable SMS Function

Use the `send_sms()` function from `utils/sms_utils.php`:

```php
require_once 'utils/sms_utils.php';

// Basic SMS send
$result = send_sms('+251943778192', 'Hello from SmartWorkshop!');
if ($result['success']) {
    echo 'SMS sent successfully!';
} else {
    echo 'SMS failed: ' . $result['error'];
}
```

**Function Signature:**
```php
function send_sms(string $phone, string $message): array
```

**Returns:**
```php
[
    'success' => bool,     // true if SMS was sent successfully
    'message' => string,   // success/failure message
    'error' => string|null // error details if failed
]
```

**Phone Number Formats Supported:**
- `0912345678` (local Ethiopian format)
- `+251912345678` (international format)
- `912345678` (without country code)

## 3. Notification Type Examples

### New Order Notification
```php
$result = send_order_notification('+251943778192', 12345, 'John Doe');
// Sends: "Hi John Doe, Your order #12345 has been received. We'll review it and send you a cost estimate soon. - SmartWorkshop"
```

### Payment Receipt Notification
```php
$result = send_payment_notification('+251943778192', 12345, 2500.00, 'John Doe');
// Sends: "Hi John Doe, We've received your payment of ETB 2500 for order #12345. Thank you! - SmartWorkshop"
```

### Order Status Update Notification
```php
// Available statuses: 'cost_estimated', 'approved', 'in_production', 'completed', 'delivered'
$result = send_status_notification('+251943778192', 12345, 'approved', 'John Doe');
// Sends: "Hi John Doe, Your order #12345 has been approved! Please submit your deposit payment to start production. - SmartWorkshop"
```

## 4. Error Handling and Logging

### Error Handling
```php
$result = send_sms('+251943778192', 'Test message');

if (!$result['success']) {
    // Log the error
    error_log('SMS failed: ' . $result['error']);

    // Handle the error (show user message, retry, etc.)
    echo 'Failed to send SMS. Please try again later.';
}
```

### Logging
- **Test Mode**: SMS are logged to `logs/sms.log`
- **Live Mode**: Errors are logged to PHP error log
- **API Errors**: Automatically logged with details

### Common Error Messages
- `"Invalid Ethiopian phone number format"` - Phone number validation failed
- `"Africa's Talking credentials not configured"` - Missing API credentials
- `"cURL Error: ..."` - Network/connection issues
- `"API Error: HTTP XXX - ..."` - Africa's Talking API errors

## 5. Testing Steps

### Run the Test Script
```bash
cd /path/to/your/project
php test_sms.php
```

### Expected Test Results
```
Test 1: Basic SMS send - SUCCESS
Test 2: Phone number formatting - All formats work
Test 3: Order notification - SUCCESS
Test 4: Payment notification - SUCCESS
Test 5: Status notification - All statuses SUCCESS
Test 6: Configuration check - All credentials configured
```

### Manual Testing
```php
// Test with your phone number
$result = send_sms('+251943778192', 'Test SMS from SmartWorkshop');
var_dump($result);
```

## 6. Switching from Sandbox to Live Mode

### Step 1: Get Live API Credentials
1. Go to [Africa's Talking Dashboard](https://account.africastalking.com/)
2. Sign up for a live account (if not already done)
3. Get your live API key and username

### Step 2: Request Sender ID
1. In your Africa's Talking dashboard, go to "SMS" > "Sender IDs"
2. Request a custom sender ID (e.g., "SmartWork" or your business name)
3. Wait for approval (usually 1-2 business days)
4. Once approved, you'll receive your sender ID

### Step 3: Update Configuration
```php
// Change these values in db_config.php
define('SMS_MODE', 'live');
define('AFRICASTALKING_USERNAME', 'your_live_username');
define('AFRICASTALKING_API_KEY', 'your_live_api_key');
define('AFRICASTALKING_SENDER_ID', 'YourApprovedSenderID'); // From step 2
define('AFRICASTALKING_API_URL', 'https://api.africastalking.com'); // Remove sandbox
```

### Step 4: Test Live Mode
```php
// Test with a small amount first
$result = send_sms('+251943778192', 'Live SMS test');
```

### Important Notes for Live Mode
- **Costs**: SMS have real costs - check Africa's Talking pricing
- **Phone Numbers**: Only Ethiopian numbers (+251) are supported
- **Sender ID**: Must be approved by Africa's Talking
- **SSL**: Enabled for live mode (more secure)
- **Rate Limits**: Live accounts have higher limits than sandbox

## 7. Integration Examples

### In Order Creation (submit_custom_order.php)
```php
// After successful order creation
if ($smsEnabled && $customerPhone) {
    $result = send_order_notification($customerPhone, $orderId, $customerName);
    if (!$result['success']) {
        error_log('Order SMS failed: ' . $result['error']);
        // Continue with order creation - don't fail the whole process
    }
}
```

### In Payment Processing (PaymentController.php)
```php
// After payment approval
if ($smsEnabled && !empty($orderDetails['phone'])) {
    $result = send_payment_notification(
        $orderDetails['phone'],
        $receipt['order_id'],
        $receipt['amount'],
        $orderDetails['first_name']
    );
}
```

### In Order Status Updates
```php
// When order status changes
if ($smsEnabled && $customerPhone) {
    $result = send_status_notification($customerPhone, $orderId, $newStatus, $customerName);
}
```

## 8. Troubleshooting

### SMS Not Sending
1. Check `logs/error.log` for PHP errors
2. Check `logs/sms.log` for SMS-specific errors
3. Verify API credentials are correct
4. Test with the `test_sms.php` script

### Invalid Phone Numbers
- Ensure phone numbers start with +251
- Check for valid Ethiopian mobile number format
- Remove any spaces or special characters

### API Errors
- **401 Unauthorized**: Check API key
- **400 Bad Request**: Check phone number format
- **429 Too Many Requests**: Rate limit exceeded
- **500 Internal Error**: Africa's Talking server issues

### SSL Issues
- Sandbox: SSL verification disabled
- Live: SSL verification enabled
- If SSL errors occur, check PHP cURL SSL configuration

## 9. Security Considerations

- Store API credentials securely (not in version control)
- Use HTTPS for all API calls
- Validate phone numbers server-side
- Log errors but don't expose sensitive information
- Rate limit SMS sending to prevent abuse

## 10. Cost Optimization

- Only send SMS when necessary
- Use the SMS notifications setting to allow users to opt-out
- Batch SMS if sending multiple messages
- Monitor usage in Africa's Talking dashboard</content>
<parameter name="filePath">c:\xampp\htdocs\NEWkoder\SMS_INTEGRATION_GUIDE.md