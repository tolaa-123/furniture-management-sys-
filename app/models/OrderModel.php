<?php
/**
 * Order Model
 * Handles order management and workflow
 */
require_once dirname(dirname(__DIR__)) . '/core/BaseModel.php';
require_once dirname(__DIR__) . '/utils/QueryCache.php';

class OrderModel extends BaseModel {
    protected $table = 'furn_orders';
    
    /**
     * Create new order
     */
    public function createOrder($orderData) {
        // Generate unique order number
        $orderData['order_number'] = $this->generateOrderNumber();
        $orderData['status'] = 'pending_cost_approval';
        $orderData['total_amount'] = 0.00; // Will be calculated from customizations
        
        // Clear cache when creating new order
        QueryCache::delete('orders_by_customer_' . $orderData['customer_id']);
        
        return parent::insert($orderData);
    }
    
    /**
     * Generate unique order number
     */
    private function generateOrderNumber() {
        $prefix = 'ORD';
        $date = date('Ymd');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $date . $random;
    }
    
    /**
     * Get orders by status
     */
    public function getOrdersByStatus($status) {
        $cacheKey = 'orders_by_status_' . $status;
        
        return QueryCache::remember($cacheKey, function() use ($status) {
            $stmt = $this->db->prepare("
                SELECT o.*, u.first_name, u.last_name, u.email
                FROM {$this->table} o
                JOIN furn_users u ON o.customer_id = u.id
                WHERE o.status = ?
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        }, 1800); // Cache for 30 minutes
    }
    
    /**
     * Get orders by customer
     */
    public function getOrdersByCustomer($customerId) {
        $cacheKey = 'orders_by_customer_' . $customerId;
        
        return QueryCache::remember($cacheKey, function() use ($customerId) {
            $stmt = $this->db->prepare("
                SELECT o.*, 
                       (SELECT COUNT(*) FROM furn_order_customizations WHERE order_id = o.id) as item_count
                FROM {$this->table} o
                WHERE o.customer_id = ?
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$customerId]);
            return $stmt->fetchAll();
        }, 1800); // Cache for 30 minutes
    }
    
    /**
     * Get order with details
     */
    public function getOrderWithDetails($orderId) {
        $cacheKey = 'order_details_' . $orderId;
        
        return QueryCache::remember($cacheKey, function() use ($orderId) {
            $stmt = $this->db->prepare("
                SELECT o.*, u.first_name, u.last_name, u.email, u.phone
                FROM {$this->table} o
                JOIN furn_users u ON o.customer_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetch();
        }, 1800); // Cache for 30 minutes
    }
    
    /**
     * Update order status
     */
    public function updateStatus($orderId, $status, $additionalData = []) {
        $data = array_merge(['status' => $status], $additionalData);
        
        // Clear cache when updating order
        QueryCache::delete('order_details_' . $orderId);
        QueryCache::delete('orders_by_status_' . $status);
        
        return $this->update($orderId, $data);
    }
    
    /**
     * Get pending approval orders for managers
     */
    public function getPendingApprovalOrders() {
        return $this->getOrdersByStatus('pending_cost_approval');
    }
    
    /**
     * Get orders needing deposit
     */
    public function getOrdersWaitingForDeposit() {
        return $this->getOrdersByStatus('cost_estimated');
    }
    
    /**
     * Calculate order total from customizations
     */
    public function calculateOrderTotal($orderId) {
        $stmt = $this->db->prepare("
            SELECT SUM(adjusted_price * quantity) as total
            FROM furn_order_customizations
            WHERE order_id = ? AND adjusted_price IS NOT NULL
        ");
        $stmt->execute([$orderId]);
        $result = $stmt->fetch();
        return $result ? $result['total'] : 0;
    }
    
    /**
     * Update deposit payment
     */
    public function updateDepositPayment($orderId, $amount) {
        $order = $this->findById($orderId);
        $newDepositPaid = ($order['deposit_paid'] ?? 0) + $amount;
        
        $data = [
            'deposit_paid' => $newDepositPaid,
            'deposit_paid_at' => date('Y-m-d H:i:s')
        ];
        
        // If deposit is fully paid, calculate remaining balance
        if ($newDepositPaid >= $order['deposit_amount']) {
            $remainingBalance = $order['total_amount'] - $newDepositPaid;
            $data['remaining_balance'] = $remainingBalance;
            $data['final_payment_required'] = $remainingBalance;
        }
        
        // Clear cache when updating
        QueryCache::delete('order_details_' . $orderId);
        
        return $this->update($orderId, $data);
    }
    
    /**
     * Update final payment
     */
    public function updateFinalPayment($orderId, $amount) {
        $order = $this->findById($orderId);
        $newFinalPaid = ($order['final_payment_paid'] ?? 0) + $amount;
        
        $data = [
            'final_payment_paid' => $newFinalPaid,
            'final_payment_paid_at' => date('Y-m-d H:i:s')
        ];
        
        // If final payment covers remaining balance, mark as completed
        $remaining = $order['remaining_balance'] ?? 0;
        if ($newFinalPaid >= $remaining && $remaining > 0) {
            $data['status'] = 'completed';
            
            // Automatically calculate profit when order is completed
            try {
                require_once dirname(__DIR__) . '/models/ProfitModel.php';
                $profitModel = new ProfitModel();
                
                // Check if profit already calculated
                $existingProfit = $profitModel->getOrderProfit($orderId);
                
                if (!$existingProfit) {
                    // Calculate profit automatically
                    $profitModel->calculateOrderProfit($orderId);
                    error_log("Auto-calculated profit for order ID: $orderId");
                }
            } catch (Exception $e) {
                // Log error but don't fail the payment update
                error_log("Auto profit calculation error for order $orderId: " . $e->getMessage());
            }
        }
        
        // Clear cache when updating
        QueryCache::delete('order_details_' . $orderId);
        
        return $this->update($orderId, $data);
    }
    
    /**
     * Get orders ready for production (deposit approved)
     */
    public function getOrdersReadyForProduction() {
        return $this->getOrdersByStatus('deposit_paid');
    }
    
    /**
     * Get completed orders
     */
    public function getCompletedOrders() {
        return $this->getOrdersByStatus('completed');
    }
    
    /**
     * Start production for order
     */
    public function startProduction($orderId, $startDate = null) {
        try {
            // Begin transaction for atomic operation
            $this->db->beginTransaction();
            
            // Get all approved material requests for this order
            $stmt = $this->db->prepare("
                SELECT mr.material_id, COALESCE(mr.approved_quantity, mr.quantity_requested) as quantity_requested
                FROM furn_material_requests mr
                WHERE mr.order_id = ? AND mr.status = 'approved'
            ");
            $stmt->execute([$orderId]);
            $approvedRequests = $stmt->fetchAll();
            
                // Do not deduct current stock here. Stock is deducted when usage is reported.
                // At production start we only validate reservation integrity.
            foreach ($approvedRequests as $request) {
                $materialId = $request['material_id'];
                    $quantity = floatval($request['quantity_requested']);
                
                    $checkStmt = $this->db->prepare("
                        SELECT current_stock, COALESCE(reserved_stock,0) as reserved_stock
                        FROM furn_materials
                        WHERE id = ?
                    ");
                    $checkStmt->execute([$materialId]);
                    $mat = $checkStmt->fetch();
                    if (!$mat) {
                        throw new Exception("Material not found for ID: $materialId");
                    }
                    if (floatval($mat['reserved_stock']) + 0.0001 < $quantity) {
                        throw new Exception("Reserved stock mismatch for material ID: $materialId");
                }
            }
            
            // Update order status to in_production
            $data = [
                'production_started_at' => $startDate ?: date('Y-m-d H:i:s'),
                'status' => 'in_production'
            ];
            
            $this->update($orderId, $data);
            
            // Clear cache when updating
            QueryCache::delete('order_details_' . $orderId);
            
            // Commit transaction
            $this->db->commit();
            
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Start production error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Complete production for order
     */
    public function completeProduction($orderId, $completionDate = null) {
        $data = [
            'production_completed_at' => date('Y-m-d H:i:s'),
            'actual_completion_date' => $completionDate ?: date('Y-m-d'),
            'status' => 'ready_for_delivery'
        ];
        
        // Clear cache when updating
        QueryCache::delete('order_details_' . $orderId);
        
        return $this->update($orderId, $data);
    }
    
    /**
     * Get orders in production
     */
    public function getOrdersInProduction() {
        return $this->getOrdersByStatus('in_production');
    }
    
    /**
     * Get orders ready for delivery
     */
    public function getOrdersReadyForDelivery() {
        return $this->getOrdersByStatus('ready_for_delivery');
    }
    
    /**
     * Set estimated completion date
     */
    public function setEstimatedCompletion($orderId, $estimatedDate) {
        // Clear cache when updating
        QueryCache::delete('order_details_' . $orderId);
        
        return $this->update($orderId, ['estimated_completion_date' => $estimatedDate]);
    }
    
    /**
     * Get production timeline for order
     */
    public function getProductionTimeline($orderId) {
        $cacheKey = 'order_timeline_' . $orderId;
        
        return QueryCache::remember($cacheKey, function() use ($orderId) {
            $stmt = $this->db->prepare("
                SELECT production_started_at, production_completed_at, 
                       estimated_completion_date, actual_completion_date
                FROM {$this->table}
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetch();
        }, 1800); // Cache for 30 minutes
    }
}