<?php
/**
 * Product Model
 * Handles product management and related operations
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class ProductModel extends BaseModel {
    protected $table = 'furn_products';
    
    /**
     * Get all active products with category information
     */
    public function getActiveProductsWithCategories() {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, c.name as category_name, c.description as category_description
                FROM {$this->table} p
                JOIN furn_categories c ON p.category_id = c.id
                WHERE p.is_active = 1
                ORDER BY c.name, p.name
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if p.name doesn't exist
            error_log("Get active products error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT p.*, 'Category' as category_name, 'N/A' as category_description
                    FROM {$this->table} p
                    WHERE p.is_active = 1
                    ORDER BY p.id
                ");
                $stmt->execute();
                return $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Get active products fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Find products by category
     */
    public function findByCategory($categoryId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE category_id = ? AND is_active = 1 ORDER BY name");
            $stmt->execute([$categoryId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if name column doesn't exist
            error_log("Find by category error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE category_id = ? AND is_active = 1 ORDER BY id");
                $stmt->execute([$categoryId]);
                return $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Find by category fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get product with images
     */
    public function getProductWithImages($productId) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, c.name as category_name,
                       (SELECT image_path FROM furn_product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM {$this->table} p
                JOIN furn_categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.is_active = 1
            ");
            $stmt->execute([$productId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            // Fallback if category JOIN fails
            error_log("Get product with images error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT p.*, 'Category' as category_name,
                           (SELECT image_path FROM furn_product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                    FROM {$this->table} p
                    WHERE p.id = ? AND p.is_active = 1
                ");
                $stmt->execute([$productId]);
                return $stmt->fetch();
            } catch (PDOException $e2) {
                error_log("Get product with images fallback error: " . $e2->getMessage());
                return null;
            }
        }
    }
    
    /**
     * Get product images
     */
    public function getProductImages($productId) {
        $stmt = $this->db->prepare("SELECT * FROM furn_product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
}