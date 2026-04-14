<?php
/**
 * QueryOptimizer - Database query optimization utilities
 * Provides guidelines and helpers for optimizing database queries
 */

class QueryOptimizer {
    
    /**
     * Get optimization guidelines
     * 
     * @return array Array of optimization tips
     */
    public static function getGuidelines() {
        return [
            'select_specific_columns' => [
                'bad' => 'SELECT * FROM orders WHERE customer_id = 1',
                'good' => 'SELECT id, order_number, status, total_amount FROM orders WHERE customer_id = 1',
                'reason' => 'Selecting specific columns reduces data transfer and improves performance'
            ],
            'use_indexes' => [
                'bad' => 'SELECT * FROM orders WHERE YEAR(created_at) = 2026',
                'good' => 'SELECT * FROM orders WHERE created_at >= "2026-01-01" AND created_at < "2027-01-01"',
                'reason' => 'Using indexes on date columns is faster than using functions'
            ],
            'join_optimization' => [
                'bad' => 'SELECT * FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.status = "pending"',
                'good' => 'SELECT o.id, o.order_number, c.name FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.status = "pending"',
                'reason' => 'Select only needed columns from joined tables'
            ],
            'limit_results' => [
                'bad' => 'SELECT * FROM orders',
                'good' => 'SELECT * FROM orders LIMIT 100',
                'reason' => 'Always limit results to prevent loading too much data'
            ],
            'use_where_clause' => [
                'bad' => 'SELECT * FROM orders',
                'good' => 'SELECT * FROM orders WHERE status = "completed"',
                'reason' => 'Filter data at database level, not in application'
            ],
            'avoid_subqueries' => [
                'bad' => 'SELECT * FROM orders WHERE customer_id IN (SELECT id FROM customers WHERE status = "active")',
                'good' => 'SELECT o.* FROM orders o JOIN customers c ON o.customer_id = c.id WHERE c.status = "active"',
                'reason' => 'JOINs are usually faster than subqueries'
            ],
            'use_indexes_on_foreign_keys' => [
                'tip' => 'Always create indexes on foreign key columns',
                'reason' => 'Improves JOIN performance significantly'
            ],
            'batch_operations' => [
                'bad' => 'for ($i = 0; $i < 1000; $i++) { INSERT INTO table VALUES (...) }',
                'good' => 'INSERT INTO table VALUES (...), (...), (...)',
                'reason' => 'Batch inserts are much faster than individual inserts'
            ],
        ];
    }
    
    /**
     * Get recommended database indexes
     * 
     * @return array Array of recommended indexes
     */
    public static function getRecommendedIndexes() {
        return [
            'users' => [
                'CREATE INDEX idx_users_email ON users(email)',
                'CREATE INDEX idx_users_role ON users(role)',
                'CREATE INDEX idx_users_created_at ON users(created_at)',
            ],
            'orders' => [
                'CREATE INDEX idx_orders_customer_id ON orders(customer_id)',
                'CREATE INDEX idx_orders_status ON orders(status)',
                'CREATE INDEX idx_orders_created_at ON orders(created_at)',
                'CREATE INDEX idx_orders_manager_id ON orders(manager_id)',
            ],
            'payments' => [
                'CREATE INDEX idx_payments_order_id ON payments(order_id)',
                'CREATE INDEX idx_payments_status ON payments(status)',
                'CREATE INDEX idx_payments_created_at ON payments(created_at)',
            ],
            'attendance' => [
                'CREATE INDEX idx_attendance_user_id ON attendance(user_id)',
                'CREATE INDEX idx_attendance_date ON attendance(attendance_date)',
                'CREATE INDEX idx_attendance_status ON attendance(status)',
            ],
            'materials' => [
                'CREATE INDEX idx_materials_category_id ON materials(category_id)',
                'CREATE INDEX idx_materials_stock ON materials(stock_quantity)',
            ],
            'production_assignments' => [
                'CREATE INDEX idx_production_order_id ON production_assignments(order_id)',
                'CREATE INDEX idx_production_employee_id ON production_assignments(employee_id)',
                'CREATE INDEX idx_production_status ON production_assignments(status)',
            ],
        ];
    }
    
    /**
     * Analyze query performance
     * 
     * @param string $query SQL query to analyze
     * @return array Analysis results
     */
    public static function analyzeQuery($query) {
        $analysis = [
            'issues' => [],
            'suggestions' => [],
            'score' => 100,
        ];
        
        // Check for SELECT *
        if (preg_match('/SELECT\s+\*/i', $query)) {
            $analysis['issues'][] = 'Using SELECT * - specify only needed columns';
            $analysis['score'] -= 20;
        }
        
        // Check for LIMIT
        if (!preg_match('/LIMIT\s+\d+/i', $query)) {
            $analysis['suggestions'][] = 'Consider adding LIMIT clause to prevent loading too much data';
            $analysis['score'] -= 10;
        }
        
        // Check for WHERE clause
        if (!preg_match('/WHERE/i', $query)) {
            $analysis['suggestions'][] = 'Consider adding WHERE clause to filter data';
            $analysis['score'] -= 15;
        }
        
        // Check for function usage on columns
        if (preg_match('/WHERE\s+\w+\s*\(/i', $query)) {
            $analysis['issues'][] = 'Using functions on columns prevents index usage';
            $analysis['score'] -= 25;
        }
        
        // Check for LIKE with leading wildcard
        if (preg_match('/LIKE\s+[\'"]%/i', $query)) {
            $analysis['issues'][] = 'Using LIKE with leading wildcard prevents index usage';
            $analysis['score'] -= 20;
        }
        
        // Check for OR conditions
        if (preg_match('/\s+OR\s+/i', $query)) {
            $analysis['suggestions'][] = 'Consider using IN clause instead of OR for better performance';
            $analysis['score'] -= 10;
        }
        
        // Ensure score doesn't go below 0
        $analysis['score'] = max(0, $analysis['score']);
        
        return $analysis;
    }
    
    /**
     * Get query optimization tips
     * 
     * @return array Array of tips
     */
    public static function getTips() {
        return [
            'Use prepared statements to prevent SQL injection',
            'Create indexes on frequently queried columns',
            'Use EXPLAIN to analyze query performance',
            'Avoid SELECT * - specify only needed columns',
            'Use LIMIT to restrict result set size',
            'Use WHERE clause to filter at database level',
            'Use JOINs instead of subqueries when possible',
            'Batch insert operations for better performance',
            'Use appropriate data types for columns',
            'Normalize database schema to reduce redundancy',
            'Use caching for frequently accessed data',
            'Monitor slow queries and optimize them',
            'Use UNION instead of multiple queries when appropriate',
            'Avoid using functions on indexed columns',
            'Use LIKE with caution - it can be slow',
        ];
    }
}
?>
