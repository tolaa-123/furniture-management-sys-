<?php
/**
 * DatabaseErrorHandler - Handles database errors gracefully
 * Wraps database queries to catch and log errors without crashing
 */

class DatabaseErrorHandler {
    
    /**
     * Execute a query safely with error handling
     * 
     * @param callable $callback The query callback
     * @param string $errorContext Context for error logging
     * @param mixed $defaultReturn Default return value on error
     * @return mixed Query result or default return value
     */
    public static function executeQuery($callback, $errorContext = 'Database Query', $defaultReturn = null) {
        try {
            return call_user_func($callback);
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            // Log the error
            error_log("[$errorContext] PDO Error ($errorCode): $errorMessage");
            
            // Handle specific error codes
            if ($errorCode === '42S22') {
                // Column not found - return safe default
                error_log("[$errorContext] Column not found - returning default value");
                return $defaultReturn;
            } elseif ($errorCode === '42S02') {
                // Table not found - return safe default
                error_log("[$errorContext] Table not found - returning default value");
                return $defaultReturn;
            } else {
                // Other database errors
                error_log("[$errorContext] Database error - returning default value");
                return $defaultReturn;
            }
        } catch (Exception $e) {
            error_log("[$errorContext] Exception: " . $e->getMessage());
            return $defaultReturn;
        }
    }
    
    /**
     * Execute a query that returns an array
     * 
     * @param callable $callback The query callback
     * @param string $errorContext Context for error logging
     * @return array Query result or empty array
     */
    public static function executeQueryArray($callback, $errorContext = 'Database Query') {
        return self::executeQuery($callback, $errorContext, []);
    }
    
    /**
     * Execute a query that returns a single row
     * 
     * @param callable $callback The query callback
     * @param string $errorContext Context for error logging
     * @return array|null Query result or null
     */
    public static function executeQueryRow($callback, $errorContext = 'Database Query') {
        return self::executeQuery($callback, $errorContext, null);
    }
    
    /**
     * Execute a query that returns a single value
     * 
     * @param callable $callback The query callback
     * @param string $errorContext Context for error logging
     * @param mixed $defaultValue Default value on error
     * @return mixed Query result or default value
     */
    public static function executeQueryValue($callback, $errorContext = 'Database Query', $defaultValue = 0) {
        return self::executeQuery($callback, $errorContext, $defaultValue);
    }
    
    /**
     * Execute a write query (INSERT, UPDATE, DELETE)
     * 
     * @param callable $callback The query callback
     * @param string $errorContext Context for error logging
     * @return bool Success status
     */
    public static function executeWrite($callback, $errorContext = 'Database Write') {
        try {
            return call_user_func($callback);
        } catch (PDOException $e) {
            error_log("[$errorContext] PDO Error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("[$errorContext] Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a table exists
     * 
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @return bool True if table exists
     */
    public static function tableExists($pdo, $tableName) {
        try {
            $result = $pdo->query("SELECT 1 FROM $tableName LIMIT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if a column exists in a table
     * 
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool True if column exists
     */
    public static function columnExists($pdo, $tableName, $columnName) {
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM $tableName")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
            return in_array($columnName, $columnNames);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get all columns from a table
     * 
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @return array Array of column names
     */
    public static function getTableColumns($pdo, $tableName) {
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM $tableName")->fetchAll(PDO::FETCH_ASSOC);
            return array_column($columns, 'Field');
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
