<?php
/**
 * Payment Model
 * Handles payment receipts and payment processing
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class PaymentModel extends BaseModel {
    protected $table = 'furn_payment_receipts';
    
    /**
     * Create payment receipt
     */
    public function createReceipt($receiptData) {
        return parent::insert($receiptData);
    }
    
    /**
     * Get payment receipts by order
     */
    public function getReceiptsByOrder($orderId) {
        $stmt = $this->db->prepare("
            SELECT pr.*, u1.first_name as approved_by_name, u1.last_name as approved_by_lastname,
                   u2.first_name as rejected_by_name, u2.last_name as rejected_by_lastname
            FROM {$this->table} pr
            LEFT JOIN furn_users u1 ON pr.approved_by = u1.id
            LEFT JOIN furn_users u2 ON pr.rejected_by = u2.id
            WHERE pr.order_id = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get pending payment receipts for managers
     */
    public function getPendingReceipts() {
        $stmt = $this->db->prepare("
            SELECT pr.*, o.order_number, u.first_name, u.last_name, u.email
            FROM {$this->table} pr
            JOIN furn_orders o ON pr.order_id = o.id
            JOIN furn_users u ON o.customer_id = u.id
            WHERE pr.status = 'pending'
            ORDER BY pr.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Approve payment receipt
     */
    public function approveReceipt($receiptId, $managerId, $managerNotes = null) {
        $data = [
            'status' => 'approved',
            'approved_by' => $managerId,
            'approved_at' => date('Y-m-d H:i:s'),
            'manager_notes' => $managerNotes
        ];
        
        return $this->update($receiptId, $data);
    }
    
    /**
     * Reject payment receipt
     */
    public function rejectReceipt($receiptId, $managerId, $managerNotes = null) {
        $data = [
            'status' => 'rejected',
            'rejected_by' => $managerId,
            'rejected_at' => date('Y-m-d H:i:s'),
            'manager_notes' => $managerNotes
        ];
        
        return $this->update($receiptId, $data);
    }
    
    /**
     * Get receipt by ID with order details
     */
    public function getReceiptWithOrder($receiptId) {
        $stmt = $this->db->prepare("
            SELECT pr.*, o.order_number, o.status as order_status, o.total_amount,
                   o.deposit_amount, o.deposit_paid, o.remaining_balance,
                   u.first_name, u.last_name, u.email, u.phone
            FROM {$this->table} pr
            JOIN furn_orders o ON pr.order_id = o.id
            JOIN furn_users u ON o.customer_id = u.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$receiptId]);
        return $stmt->fetch();
    }
    
    /**
     * Get customer's payment history
     */
    public function getCustomerPaymentHistory($customerId) {
        $stmt = $this->db->prepare("
            SELECT pr.*, o.order_number, o.status as order_status
            FROM {$this->table} pr
            JOIN furn_orders o ON pr.order_id = o.id
            WHERE o.customer_id = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }
}