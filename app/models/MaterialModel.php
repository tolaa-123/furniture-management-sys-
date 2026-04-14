<?php
/**
 * Material Model
 * Handles material inventory and reservation management
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class MaterialModel extends BaseModel {
    protected $table = 'furn_materials';
    
    /**
     * Get all active materials
     */
    public function getActiveMaterials() {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get material by ID
     */
    public function getMaterial($materialId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND is_active = 1");
        $stmt->execute([$materialId]);
        return $stmt->fetch();
    }
    
    /**
     * Get materials for a product
     */
    public function getMaterialsForProduct($productId) {
        $stmt = $this->db->prepare("
            SELECT m.*, pm.quantity_required
            FROM furn_product_materials pm
            JOIN {$this->table} m ON pm.material_id = m.id
            WHERE pm.product_id = ? AND m.is_active = 1
            ORDER BY m.name
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update material stock
     */
    public function updateStock($materialId, $quantity) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET current_stock = current_stock + ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$quantity, $materialId]);
    }
    
    /**
     * Reserve material stock
     */
    public function reserveStock($materialId, $quantity) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET reserved_stock = reserved_stock + ?, updated_at = NOW()
            WHERE id = ? AND (current_stock - reserved_stock) >= ?
        ");
        $result = $stmt->execute([$quantity, $materialId, $quantity]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Release reserved stock
     */
    public function releaseStock($materialId, $quantity) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET reserved_stock = reserved_stock - ?, updated_at = NOW()
            WHERE id = ? AND reserved_stock >= ?
        ");
        $result = $stmt->execute([$quantity, $materialId, $quantity]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get low stock materials
     */
    public function getLowStockMaterials() {
        $stmt = $this->db->prepare("
            SELECT *, (current_stock - reserved_stock) as available_stock
            FROM {$this->table} 
            WHERE is_active = 1 AND (current_stock - reserved_stock) <= minimum_stock
            ORDER BY (current_stock - reserved_stock) ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get material availability
     */
    public function getMaterialAvailability($materialId) {
        $stmt = $this->db->prepare("
            SELECT id, name, current_stock, reserved_stock, 
                   (current_stock - reserved_stock) as available_stock
            FROM {$this->table} 
            WHERE id = ?
        ");
        $stmt->execute([$materialId]);
        return $stmt->fetch();
    }
}