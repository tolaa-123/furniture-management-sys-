# How to Test SMS Functionality

## Quick Test Command
```bash
cd C:\xampp\htdocs\NEWkoder
php sms_test.php
```

## Manual Testing Steps

### 1. Basic SMS Test
```php
require_once 'utils/sms_utils.php';

$result = send_sms('+251943778192', 'Test message from SmartWorkshop');
if ($result['success']) {
    echo 'SMS sent successfully!';
} else {
    echo 'Failed: ' . $result['error'];
}
```

### 2. Test Different Notification Types
```php
// Order notification
$result = send_order_notification('+251943778192', 12345, 'John Doe');

// Payment notification
$result = send_payment_notification('+251943778192', 12345, 2500.00, 'John Doe');

// Status notification
$result = send_status_notification('+251943778192', 12345, 'approved', 'John Doe');
```

### 3. Test Phone Number Formats
```php
$phones = [
    '+251943778192',  // International format
    '0943778192',     // Local format with 0
    '943778192'       // Without country code
];

foreach ($phones as $phone) {
    $result = send_sms($phone, 'Format test');
    echo "$phone: " . ($result['success'] ? 'OK' : 'FAILED') . "\n";
}
```

## What to Check After Testing

### ✅ Success Indicators
- **Test script shows**: `✅ SUCCESS` for all tests
- **Your phone receives**: SMS messages from sender "dereje"
- **Africa's Talking dashboard**: Shows successful delivery (if live mode)
- **No PHP errors**: In error logs

### ❌ Failure Indicators
- **Test script shows**: `❌ FAILED` with error messages
- **No SMS received**: On your phone
- **Error messages**: Check the specific error details
- **PHP error logs**: Check `logs/error.log`

## Common Issues & Solutions

### 1. "Invalid Ethiopian phone number format"
**Problem**: Phone number not in correct format
**Solution**: Use +251XXXXXXXXX format

### 2. "Africa's Talking credentials not configured"
**Problem**: API credentials missing
**Solution**: Check `config/db_config.php` constants

### 3. "cURL Error"
**Problem**: Network or SSL issues
**Solution**: Check internet connection, firewall settings

### 4. "API Error: HTTP 401"
**Problem**: Invalid API credentials
**Solution**: Verify username and API key in Africa's Talking dashboard

### 5. "API Error: HTTP 400"
**Problem**: Invalid request format
**Solution**: Check phone number and message format

## Testing in Your Application

### Test Order Creation SMS
1. Create a test order through your website
2. Check if SMS is sent to customer phone
3. Verify SMS content matches expected format

### Test Payment SMS
1. Process a test payment
2. Check if SMS is sent to customer
3. Verify payment amount and order ID in SMS

### Test Status Updates
1. Change order status in admin panel
2. Check if customer receives status SMS
3. Verify correct status message

## Switching Between Test and Live Mode

### Current Mode: Live (Sandbox API)
- SMS are sent via Africa's Talking sandbox
- No real costs
- Limited functionality

### To Switch to Live Mode:
1. Update `config/db_config.php`:
   ```php
   define('SMS_MODE', 'live');
   define('AFRICASTALKING_API_URL', 'https://api.africastalking.com');
   // Add your live credentials
   ```
2. Request sender ID approval from Africa's Talking
3. Test with small amounts first

## Monitoring & Logs

### Live Mode Monitoring
- Check Africa's Talking dashboard for delivery reports
- Monitor SMS costs and usage
- Set up delivery webhooks for real-time status

### Test Mode Monitoring
- Check `logs/sms.log` for logged messages
- Verify message content and formatting
- Test error scenarios

## Performance Testing

### Rate Limiting Test
```php
for ($i = 0; $i < 10; $i++) {
    $result = send_sms('+251943778192', "Rate test $i");
    echo "Test $i: " . ($result['success'] ? 'OK' : 'FAILED') . "\n";
    sleep(1); // Rate limiting
}
```

### Load Testing
- Test with multiple phone numbers
- Check response times
- Monitor memory usage

## Final Verification Checklist

- [ ] All phone number formats work
- [ ] All notification types send successfully
- [ ] Error handling works correctly
- [ ] SMS received on test phone
- [ ] No PHP errors in logs
- [ ] Africa's Talking dashboard shows success (live mode)
- [ ] Message content is correct
- [ ] Sender ID appears correctly
- [ ] Long messages are truncated properly
- [ ] Invalid inputs are rejected</content>
<parameter name="filePath">c:\xampp\htdocs\NEWkoder\SMS_TESTING_GUIDE.md