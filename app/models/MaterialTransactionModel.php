<?php
/**
 * Material Transaction Model
 * Handles material inventory transactions and tracking
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class MaterialTransactionModel extends BaseModel {
    protected $table = 'furn_material_transactions';
    
    /**
     * Record material transaction
     */
    public function recordTransaction($transactionData) {
        // Calculate total cost if not provided
        if (!isset($transactionData['total_cost']) && isset($transactionData['quantity']) && isset($transactionData['unit_cost'])) {
            $transactionData['total_cost'] = $transactionData['quantity'] * $transactionData['unit_cost'];
        }
        
        return parent::insert($transactionData);
    }
    
    /**
     * Get transactions by material
     */
    public function getTransactionsByMaterial($materialId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT mt.*, u.first_name, u.last_name
            FROM {$this->table} mt
            LEFT JOIN furn_users u ON mt.created_by = u.id
            WHERE mt.material_id = ?
            ORDER BY mt.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$materialId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get recent transactions
     */
    public function getRecentTransactions($limit = 20) {
        $stmt = $this->db->prepare("
            SELECT mt.*, m.name as material_name, u.first_name, u.last_name
            FROM {$this->table} mt
            JOIN furn_materials m ON mt.material_id = m.id
            LEFT JOIN furn_users u ON mt.created_by = u.id
            ORDER BY mt.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get transaction summary by type
     */
    public function getTransactionSummary() {
        $stmt = $this->db->prepare("
            SELECT 
                transaction_type,
                COUNT(*) as transaction_count,
                SUM(total_cost) as total_value,
                SUM(quantity) as total_quantity
            FROM {$this->table}
            GROUP BY transaction_type
            ORDER BY transaction_type
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get monthly transaction summary
     */
    public function getMonthlySummary($months = 12) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN transaction_type = 'purchase' THEN total_cost ELSE 0 END) as purchase_value,
                SUM(CASE WHEN transaction_type = 'usage' THEN total_cost ELSE 0 END) as usage_value,
                COUNT(*) as transaction_count
            FROM {$this->table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }
}