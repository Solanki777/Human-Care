<?php
/**
 * Input Validation Class
 */
class Validator {
    
    private $errors = [];
    private $data = [];
    
    /**
     * Validate data
     */
    public function validate($data, $rules) {
        $this->errors = [];
        $this->data = $data;
        
        foreach ($rules as $field => $ruleSet) {
            $rules = explode('|', $ruleSet);
            $value = $data[$field] ?? '';
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply validation rule
     */
    private function applyRule($field, $value, $rule) {
        $params = [];
        
        // Extract parameters from rule (e.g., max:100)
        if (strpos($rule, ':') !== false) {
            list($rule, $paramStr) = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }
        
        $method = 'validate' . ucfirst($rule);
        
        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);
        }
    }
    
    /**
     * Required validation
     */
    private function validateRequired($field, $value) {
        if (empty($value) && $value !== '0') {
            $this->addError($field, ucfirst($field) . ' is required');
        }
    }
    
    /**
     * Email validation
     */
    private function validateEmail($field, $value) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Invalid email format');
        }
    }
    
    /**
     * Minimum length validation
     */
    private function validateMin($field, $value, $params) {
        $min = $params[0] ?? 0;
        if (!empty($value) && strlen($value) < $min) {
            $this->addError($field, ucfirst($field) . " must be at least $min characters");
        }
    }
    
    /**
     * Maximum length validation
     */
    private function validateMax($field, $value, $params) {
        $max = $params[0] ?? 0;
        if (!empty($value) && strlen($value) > $max) {
            $this->addError($field, ucfirst($field) . " must not exceed $max characters");
        }
    }
    
    /**
     * Numeric validation
     */
    private function validateNumeric($field, $value) {
        if (!empty($value) && !is_numeric($value)) {
            $this->addError($field, ucfirst($field) . ' must be a number');
        }
    }
    
    /**
     * Phone validation
     */
    private function validatePhone($field, $value) {
        if (!empty($value) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $value)) {
            $this->addError($field, 'Invalid phone number format');
        }
    }
    
    /**
     * Date validation
     */
    private function validateDate($field, $value) {
        if (!empty($value)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->addError($field, 'Invalid date format (YYYY-MM-DD required)');
            }
        }
    }
    
    /**
     * Future date validation
     */
    private function validateFutureDate($field, $value) {
        if (!empty($value)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            if ($date < $today) {
                $this->addError($field, 'Date must be in the future');
            }
        }
    }
    
    /**
     * Add error
     */
    private function addError($field, $message) {
        $this->errors[$field] = $message;
    }
    
    /**
     * Get errors
     */
    public function errors() {
        return $this->errors;
    }
    
    /**
     * Get first error
     */
    public function firstError() {
        return !empty($this->errors) ? reset($this->errors) : '';
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}