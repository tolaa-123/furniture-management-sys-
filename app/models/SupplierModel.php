<?php
/**
 * Supplier Model
 * Handles supplier management and relationships
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class SupplierModel extends BaseModel {
    protected $table = 'furn_suppliers';
    
    /**
     * Get all active suppliers
     */
    public function getActiveSuppliers() {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get supplier by ID
     */
    public function getSupplier($supplierId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND is_active = 1");
        $stmt->execute([$supplierId]);
        return $stmt->fetch();
    }
    
    /**
     * Create new supplier
     */
    public function createSupplier($supplierData) {
        return parent::insert($supplierData);
    }
    
    /**
     * Update supplier
     */
    public function updateSupplier($supplierId, $supplierData) {
        return $this->update($supplierId, $supplierData);
    }
    
    /**
     * Get supplier statistics
     */
    public function getSupplierStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_suppliers,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_suppliers
            FROM {$this->table}
        ");
        $stmt->execute();
        return $stmt->fetch();
    }
}