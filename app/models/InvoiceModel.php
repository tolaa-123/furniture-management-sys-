<?php
/**
 * Invoice Model
 * Handles invoice generation, management, and PDF creation
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class InvoiceModel extends BaseModel {
    protected $table = 'furn_invoices';
    
    /**
     * Generate invoice for order
     */
    public function generateInvoice($orderId, $customerId) {
        try {
            $this->db->beginTransaction();
            
            // Get order details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            // Check if invoice already exists
            if ($order['invoice_generated']) {
                throw new Exception('Invoice already generated for this order');
            }
            
            // Get company configuration
            $config = $this->getInvoiceConfig();
            
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber();
            
            // Calculate invoice details
            $depositPaid = $order['deposit_paid'] ?? 0;
            $remainingBalance = $order['total_amount'] - $depositPaid;
            
            // Calculate dates
            $invoiceDate = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime("+" . $config['due_days'] . " days"));
            
            // Create invoice
            $invoiceData = [
                'invoice_number' => $invoiceNumber,
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'subtotal' => $order['total_amount'],
                'tax_amount' => 0, // No tax for now
                'total_amount' => $order['total_amount'],
                'deposit_paid' => $depositPaid,
                'remaining_balance' => $remainingBalance,
                'status' => 'draft',
                'created_by' => $_SESSION['user_id'] ?? null
            ];
            
            $invoiceId = parent::insert($invoiceData);
            
            // Create invoice items from order customizations
            $this->createInvoiceItems($invoiceId, $orderId);
            
            // Update order
            $this->updateOrderInvoiceStatus($orderId, $invoiceId);
            
            // Update invoice number sequence
            $this->updateInvoiceNumberSequence();
            
            $this->db->commit();
            return $invoiceId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Invoice generation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get invoice by ID with all details
     */
    public function getInvoiceDetails($invoiceId) {
        $stmt = $this->db->prepare("
            SELECT 
                i.*,
                o.order_number,
                o.created_at as order_date,
                o.status as order_status,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                c.address as customer_address,
                conf.company_name,
                conf.company_address,
                conf.company_phone,
                conf.company_email,
                conf.bank_name,
                conf.bank_account_number,
                conf.bank_account_name,
                conf.bank_branch,
                conf.swift_code,
                conf.logo_path,
                conf.notes as company_notes
            FROM {$this->table} i
            JOIN furn_orders o ON i.order_id = o.id
            JOIN furn_users c ON i.customer_id = c.id
            JOIN furn_invoice_config conf ON conf.id = 1
            WHERE i.id = ?
        ");
        $stmt->execute([$invoiceId]);
        return $stmt->fetch();
    }
    
    /**
     * Get invoice items
     */
    public function getInvoiceItems($invoiceId) {
        try {
            $stmt = $this->db->prepare("
                SELECT ii.*, p.name as product_name, p.category
                FROM furn_invoice_items ii
                LEFT JOIN furn_products p ON ii.product_id = p.id
                WHERE ii.invoice_id = ?
                ORDER BY ii.id
            ");
            $stmt->execute([$invoiceId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if p.id column doesn't exist or JOIN fails
            error_log("Invoice items query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT ii.*, 'Product' as product_name, 'N/A' as category
                    FROM furn_invoice_items ii
                    WHERE ii.invoice_id = ?
                    ORDER BY ii.id
                ");
                $stmt->execute([$invoiceId]);
                return $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Invoice items fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get invoice payments
     */
    public function getInvoicePayments($invoiceId) {
        $stmt = $this->db->prepare("
            SELECT ip.*, CONCAT(u.first_name, ' ', u.last_name) as processed_by
            FROM furn_invoice_payments ip
            LEFT JOIN furn_users u ON ip.created_by = u.id
            WHERE ip.invoice_id = ?
            ORDER BY ip.payment_date DESC
        ");
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get invoices with filtering
     */
    public function getInvoices($status = null, $limit = 50) {
        $sql = "SELECT * FROM vw_invoice_overview";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY invoice_date DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Update invoice status
     */
    public function updateInvoiceStatus($invoiceId, $status) {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $invoiceId]);
    }
    
    /**
     * Add payment to invoice
     */
    public function addPayment($invoiceId, $amount, $paymentMethod, $referenceNumber = null, $notes = null) {
        try {
            $this->db->beginTransaction();
            
            // Add payment record
            $stmt = $this->db->prepare("
                INSERT INTO furn_invoice_payments 
                (invoice_id, payment_date, amount, payment_method, reference_number, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceId,
                date('Y-m-d'),
                $amount,
                $paymentMethod,
                $referenceNumber,
                $notes,
                $_SESSION['user_id'] ?? null
            ]);
            
            // Update invoice balance
            $invoice = $this->getById($invoiceId);
            $newBalance = $invoice['remaining_balance'] - $amount;
            $newStatus = $newBalance <= 0 ? 'paid' : 'sent';
            
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET remaining_balance = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$newBalance, $newStatus, $invoiceId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Payment addition error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get invoice statistics
     */
    public function getInvoiceStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(total_amount) as total_revenue,
                SUM(deposit_paid) as total_deposits,
                SUM(remaining_balance) as total_outstanding
            FROM {$this->table}
        ");
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices() {
        $stmt = $this->db->prepare("
            SELECT * FROM vw_invoice_overview 
            WHERE status = 'sent' AND due_date < CURDATE()
            ORDER BY due_date ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get invoice configuration
     */
    public function getInvoiceConfig() {
        $stmt = $this->db->prepare("SELECT * FROM furn_invoice_config WHERE id = 1");
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Update invoice configuration
     */
    public function updateInvoiceConfig($configData) {
        $stmt = $this->db->prepare("
            UPDATE furn_invoice_config 
            SET company_name = ?, company_address = ?, company_phone = ?, company_email = ?,
                bank_name = ?, bank_account_number = ?, bank_account_name = ?, 
                bank_branch = ?, swift_code = ?, logo_path = ?, notes = ?
            WHERE id = 1
        ");
        return $stmt->execute([
            $configData['company_name'],
            $configData['company_address'],
            $configData['company_phone'],
            $configData['company_email'],
            $configData['bank_name'],
            $configData['bank_account_number'],
            $configData['bank_account_name'],
            $configData['bank_branch'] ?? null,
            $configData['swift_code'] ?? null,
            $configData['logo_path'] ?? null,
            $configData['notes'] ?? null
        ]);
    }
    
    /**
     * Private helper methods
     */
    private function getOrderDetails($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT o.*, oc.product_id, p.name as product_name
                FROM furn_orders o
                JOIN furn_order_customizations oc ON o.id = oc.order_id
                JOIN furn_products p ON oc.product_id = p.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist or JOIN fails
            error_log("Order details query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT o.*, oc.product_id, 'Product' as product_name
                    FROM furn_orders o
                    LEFT JOIN furn_order_customizations oc ON o.id = oc.order_id
                    WHERE o.id = ?
                ");
                $stmt->execute([$orderId]);
                return $stmt->fetch();
            } catch (PDOException $e2) {
                error_log("Order details fallback error: " . $e2->getMessage());
                return null;
            }
        }
    }
    
    private function generateInvoiceNumber() {
        $config = $this->getInvoiceConfig();
        $nextNumber = $config['next_invoice_number'];
        return $config['invoice_prefix'] . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    
    private function createInvoiceItems($invoiceId, $orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT oc.*, p.name as product_name, p.category
                FROM furn_order_customizations oc
                JOIN furn_products p ON oc.product_id = p.id
                WHERE oc.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $customizations = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist or JOIN fails
            error_log("Create invoice items query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT oc.*, 'Product' as product_name, 'N/A' as category
                    FROM furn_order_customizations oc
                    WHERE oc.order_id = ?
                ");
                $stmt->execute([$orderId]);
                $customizations = $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Create invoice items fallback error: " . $e2->getMessage());
                $customizations = [];
            }
        }
        
        foreach ($customizations as $customization) {
            $itemData = [
                'invoice_id' => $invoiceId,
                'product_id' => $customization['product_id'],
                'description' => $customization['product_name'] . ' - Customization',
                'quantity' => $customization['quantity'],
                'unit_price' => $customization['price'] / $customization['quantity'],
                'total_price' => $customization['price']
            ];
            
            $itemStmt = $this->db->prepare("
                INSERT INTO furn_invoice_items 
                (invoice_id, product_id, description, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $itemStmt->execute([
                $itemData['invoice_id'],
                $itemData['product_id'],
                $itemData['description'],
                $itemData['quantity'],
                $itemData['unit_price'],
                $itemData['total_price']
            ]);
        }
    }
    
    private function updateOrderInvoiceStatus($orderId, $invoiceId) {
        $stmt = $this->db->prepare("
            UPDATE furn_orders 
            SET invoice_generated = 1, invoice_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$invoiceId, $orderId]);
    }
    
    private function updateInvoiceNumberSequence() {
        $stmt = $this->db->prepare("
            UPDATE furn_invoice_config 
            SET next_invoice_number = next_invoice_number + 1 
            WHERE id = 1
        ");
        $stmt->execute();
    }
}