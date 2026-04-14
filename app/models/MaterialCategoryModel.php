<?php
/**
 * Material Category Model
 * Handles material categories and organization
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class MaterialCategoryModel extends BaseModel {
    protected $table = 'furn_material_categories';
    
    /**
     * Get all active categories
     */
    public function getActiveCategories() {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get category by ID
     */
    public function getCategory($categoryId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND is_active = 1");
        $stmt->execute([$categoryId]);
        return $stmt->fetch();
    }
    
    /**
     * Get materials count by category
     */
    public function getMaterialCountByCategory() {
        $stmt = $this->db->prepare("
            SELECT mc.name, mc.description, COUNT(m.id) as material_count
            FROM {$this->table} mc
            LEFT JOIN furn_materials m ON mc.id = m.category_id AND m.is_active = 1
            WHERE mc.is_active = 1
            GROUP BY mc.id, mc.name, mc.description
            ORDER BY mc.name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}