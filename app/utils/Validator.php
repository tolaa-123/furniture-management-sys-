<?php
/**
 * Validator - Input validation system
 * Provides both server-side and client-side validation rules
 */

class Validator {
    private $errors = [];
    private $data = [];
    
    /**
     * Constructor
     * 
     * @param array $data Data to validate
     */
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool True if validation passes
     */
    public function validate($data, $rules) {
        $this->data = $data;
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            // Handle array of rules or single rule
            $ruleArray = is_array($fieldRules) ? $fieldRules : [$fieldRules];
            
            foreach ($ruleArray as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply a single validation rule
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule to apply
     */
    private function applyRule($field, $value, $rule) {
        // Skip if field already has error
        if (isset($this->errors[$field])) {
            return;
        }
        
        // Parse rule and parameters
        if (strpos($rule, ':') !== false) {
            list($ruleName, $param) = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $param = null;
        }
        
        $ruleName = trim($ruleName);
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->errors[$field] = ucfirst($field) . " is required";
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = ucfirst($field) . " must be a valid email address";
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < (int)$param) {
                    $this->errors[$field] = ucfirst($field) . " must be at least " . $param . " characters";
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > (int)$param) {
                    $this->errors[$field] = ucfirst($field) . " must not exceed " . $param . " characters";
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->errors[$field] = ucfirst($field) . " must be numeric";
                }
                break;
                
            case 'integer':
                if (!empty($value) && !is_int($value) && !ctype_digit((string)$value)) {
                    $this->errors[$field] = ucfirst($field) . " must be an integer";
                }
                break;
                
            case 'phone':
                if (!empty($value) && !preg_match('/^[0-9\-\+\(\)\s]+$/', $value)) {
                    $this->errors[$field] = ucfirst($field) . " must be a valid phone number";
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->errors[$field] = ucfirst($field) . " must be a valid URL";
                }
                break;
                
            case 'date':
                if (!empty($value) && !$this->isValidDate($value)) {
                    $this->errors[$field] = ucfirst($field) . " must be a valid date (YYYY-MM-DD)";
                }
                break;
                
            case 'confirmed':
                $confirmField = $param;
                $confirmValue = $this->data[$confirmField] ?? null;
                if ($value !== $confirmValue) {
                    $this->errors[$field] = ucfirst($field) . " confirmation does not match";
                }
                break;
                
            case 'unique':
                // This would require database connection
                // Format: unique:table,column
                if (!empty($value)) {
                    $this->validateUnique($field, $value, $param);
                }
                break;
                
            case 'in':
                // Format: in:value1,value2,value3
                $allowedValues = explode(',', $param);
                $allowedValues = array_map('trim', $allowedValues);
                if (!empty($value) && !in_array($value, $allowedValues)) {
                    $this->errors[$field] = ucfirst($field) . " must be one of: " . $param;
                }
                break;
                
            case 'regex':
                if (!empty($value) && !preg_match($param, $value)) {
                    $this->errors[$field] = ucfirst($field) . " format is invalid";
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->errors[$field] = ucfirst($field) . " must contain only letters";
                }
                break;
                
            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->errors[$field] = ucfirst($field) . " must contain only letters and numbers";
                }
                break;
                
            case 'ip':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
                    $this->errors[$field] = ucfirst($field) . " must be a valid IP address";
                }
                break;
        }
    }
    
    /**
     * Check if date is valid
     * 
     * @param string $date Date string
     * @return bool True if valid date
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate unique value in database
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $param Database parameters (table,column)
     */
    private function validateUnique($field, $value, $param) {
        // This would require database connection
        // For now, we'll skip this validation
        // In production, you would query the database here
    }
    
    /**
     * Get all validation errors
     * 
     * @return array Array of errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get error for specific field
     * 
     * @param string $field Field name
     * @return string|null Error message or null
     */
    public function getError($field) {
        return $this->errors[$field] ?? null;
    }
    
    /**
     * Check if validation passed
     * 
     * @return bool True if no errors
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     * 
     * @return bool True if has errors
     */
    public function fails() {
        return !empty($this->errors);
    }
    
    /**
     * Get validation errors as JSON
     * 
     * @return string JSON string of errors
     */
    public function toJson() {
        return json_encode($this->errors);
    }
    
    /**
     * Get validation errors as HTML
     * 
     * @return string HTML string of errors
     */
    public function toHtml() {
        if (empty($this->errors)) {
            return '';
        }
        
        $html = '<div class="alert alert-danger">';
        $html .= '<ul>';
        
        foreach ($this->errors as $field => $error) {
            $html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get common validation rules for forms
     * 
     * @return array Common rules
     */
    public static function getCommonRules() {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'min:8', 'max:255'],
            'name' => ['required', 'min:2', 'max:255'],
            'phone' => ['required', 'phone'],
            'url' => ['required', 'url'],
            'date' => ['required', 'date'],
        ];
    }
}
?>
