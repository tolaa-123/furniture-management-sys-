<?php
/**
 * Customization Model
 * Handles order customizations and product modifications
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class CustomizationModel extends BaseModel {
    protected $table = 'furn_order_customizations';
    
    /**
     * Create customization for order
     */
    public function createCustomization($customizationData) {
        return parent::insert($customizationData);
    }
    
    /**
     * Get customizations for order
     */
    public function getCustomizationsByOrder($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT oc.*, p.name as product_name, p.base_price, c.name as category_name
                FROM {$this->table} oc
                JOIN furn_products p ON oc.product_id = p.id
                JOIN furn_categories c ON p.category_id = c.id
                WHERE oc.order_id = ?
                ORDER BY oc.created_at ASC
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist or JOIN fails
            error_log("Customizations by order query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT oc.*, 'Product' as product_name, p.base_price, 'Category' as category_name
                    FROM {$this->table} oc
                    LEFT JOIN furn_products p ON oc.product_id = p.id
                    WHERE oc.order_id = ?
                    ORDER BY oc.created_at ASC
                ");
                $stmt->execute([$orderId]);
                return $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Customizations by order fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Update customization price
     */
    public function updatePrice($customizationId, $adjustedPrice) {
        return $this->update($customizationId, ['adjusted_price' => $adjustedPrice]);
    }
    
    /**
     * Get customization with product details
     */
    public function getCustomizationWithProduct($customizationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT oc.*, p.name as product_name, p.description, p.base_price, p.materials_used,
                       c.name as category_name
                FROM {$this->table} oc
                JOIN furn_products p ON oc.product_id = p.id
                JOIN furn_categories c ON p.category_id = c.id
                WHERE oc.id = ?
            ");
            $stmt->execute([$customizationId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist or JOIN fails
            error_log("Customization with product query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT oc.*, 'Product' as product_name, p.description, p.base_price, p.materials_used,
                           'Category' as category_name
                    FROM {$this->table} oc
                    LEFT JOIN furn_products p ON oc.product_id = p.id
                    WHERE oc.id = ?
                ");
                $stmt->execute([$customizationId]);
                return $stmt->fetch();
            } catch (PDOException $e2) {
                error_log("Customization with product fallback error: " . $e2->getMessage());
                return null;
            }
        }
    }
    
    /**
     * Get pending customizations for approval
     */
    public function getPendingCustomizations($orderId = null) {
        try {
            $sql = "
                SELECT oc.*, p.name as product_name, p.base_price, c.name as category_name,
                       o.order_number, o.status, u.first_name, u.last_name
                FROM {$this->table} oc
                JOIN furn_products p ON oc.product_id = p.id
                JOIN furn_categories c ON p.category_id = c.id
                JOIN furn_orders o ON oc.order_id = o.id
                JOIN furn_users u ON o.customer_id = u.id
                WHERE oc.adjusted_price IS NULL
            ";
            
            $params = [];
            if ($orderId) {
                $sql .= " AND oc.order_id = ?";
                $params[] = $orderId;
            }
            
            $sql .= " ORDER BY o.created_at ASC, oc.created_at ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist or JOIN fails
            error_log("Pending customizations query error: " . $e->getMessage());
            try {
                $sql = "
                    SELECT oc.*, 'Product' as product_name, p.base_price, 'Category' as category_name,
                           o.order_number, o.status, u.first_name, u.last_name
                    FROM {$this->table} oc
                    LEFT JOIN furn_products p ON oc.product_id = p.id
                    LEFT JOIN furn_orders o ON oc.order_id = o.id
                    LEFT JOIN furn_users u ON o.customer_id = u.id
                    WHERE oc.adjusted_price IS NULL
                ";
                
                $params = [];
                if ($orderId) {
                    $sql .= " AND oc.order_id = ?";
                    $params[] = $orderId;
                }
                
                $sql .= " ORDER BY o.created_at ASC, oc.created_at ASC";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Pending customizations fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
}