<?php
/**
 * Promotion Model
 * Handles promotion/discount management
 */
require_once dirname(dirname(__DIR__)) . '/core/BaseModel.php';

class PromotionModel extends BaseModel {
    protected $table = 'furn_promotions';
    
    /**
     * Get all active promotions
     * Returns promotions that are currently valid (within date range, active, not maxed out)
     */
    public function getActivePromotions() {
        try {
            $stmt = $this->db->query("SELECT * FROM vw_active_promotions ORDER BY discount_value DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get active promotions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get promotion for specific product/category
     * Finds the best applicable promotion for a given category and customer
     * 
     * @param string $category Furniture category (e.g., 'Sofa', 'Table')
     * @param int $customerId Customer ID to check for first-time customer promotions
     * @return array|null Promotion data or null if no applicable promotion
     */
    public function getPromotionForProduct($category, $customerId = null) {
        try {
            // Get all active promotions
            $activePromotions = $this->getActivePromotions();
            
            if (empty($activePromotions)) {
                return null;
            }
            
            // Check if customer is new (first order)
            $isNewCustomer = false;
            if ($customerId) {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM furn_orders WHERE customer_id = ?");
                $stmt->execute([$customerId]);
                $orderCount = $stmt->fetchColumn();
                $isNewCustomer = ($orderCount == 0);
            }
            
            // Find best matching promotion (priority: first_order > category > all)
            $bestPromotion = null;
            $bestDiscount = 0;
            
            foreach ($activePromotions as $promo) {
                $applicable = false;
                
                // Check applicability
                if ($promo['applies_to'] === 'all') {
                    $applicable = true;
                } elseif ($promo['applies_to'] === 'category' && 
                          strcasecmp($promo['target_category'], $category) === 0) {
                    $applicable = true;
                } elseif ($promo['applies_to'] === 'first_order' && $isNewCustomer) {
                    $applicable = true;
                }
                
                // Check customer type eligibility
                if ($applicable) {
                    if ($promo['customer_type'] === 'new' && !$isNewCustomer) {
                        $applicable = false;
                    } elseif ($promo['customer_type'] === 'returning' && $isNewCustomer) {
                        $applicable = false;
                    }
                }
                
                // Select promotion with highest discount
                if ($applicable) {
                    $discountValue = floatval($promo['discount_value']);
                    if ($discountValue > $bestDiscount) {
                        $bestDiscount = $discountValue;
                        $bestPromotion = $promo;
                    }
                }
            }
            
            return $bestPromotion;
        } catch (PDOException $e) {
            error_log("Get promotion for product error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Apply promotion to order
     * Records promotion usage and updates order with discount
     * 
     * @param int $orderId Order ID
     * @param int $promotionId Promotion ID
     * @param float $originalPrice Original price before discount
     * @return bool Success status
     */
    public function applyPromotion($orderId, $promotionId, $originalPrice) {
        try {
            $this->db->beginTransaction();
            
            // Get promotion details
            $promotion = $this->findById($promotionId);
            if (!$promotion) {
                throw new Exception("Promotion not found");
            }
            
            // Calculate discount
            $discountAmount = 0;
            if ($promotion['discount_type'] === 'percentage') {
                $discountAmount = $originalPrice * ($promotion['discount_value'] / 100);
            } else {
                $discountAmount = $promotion['discount_value'];
            }
            
            // Apply max discount cap if set
            if ($promotion['max_discount_amount'] && $discountAmount > $promotion['max_discount_amount']) {
                $discountAmount = $promotion['max_discount_amount'];
            }
            
            $finalPrice = $originalPrice - $discountAmount;
            
            // Insert into order_promotions tracking table
            $stmt = $this->db->prepare("
                INSERT INTO furn_order_promotions 
                (order_id, promotion_id, original_price, discount_amount, final_price, promotion_name, discount_percentage)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $promotionId,
                $originalPrice,
                $discountAmount,
                $finalPrice,
                $promotion['name'],
                $promotion['discount_type'] === 'percentage' ? $promotion['discount_value'] : null
            ]);
            
            // Update order with promotion info
            $stmt = $this->db->prepare("
                UPDATE furn_orders 
                SET promotion_id = ?, 
                    original_price = ?, 
                    discount_amount = ?
                WHERE id = ?
            ");
            $stmt->execute([$promotionId, $originalPrice, $discountAmount, $orderId]);
            
            // Increment usage count
            $this->incrementUsageCount($promotionId);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Apply promotion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment promotion usage count
     */
    public function incrementUsageCount($promotionId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE furn_promotions 
                SET usage_count = usage_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$promotionId]);
            return true;
        } catch (PDOException $e) {
            error_log("Increment usage count error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get promotions for homepage banner
     */
    public function getHomepagePromotions() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM vw_active_promotions 
                WHERE show_on_homepage = 1 
                ORDER BY discount_value DESC 
                LIMIT 3
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get homepage promotions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get order promotion details
     */
    public function getOrderPromotion($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT op.*, p.name as promotion_name, p.description
                FROM furn_order_promotions op
                LEFT JOIN furn_promotions p ON op.promotion_id = p.id
                WHERE op.order_id = ?
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get order promotion error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new promotion
     */
    public function createPromotion($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO furn_promotions 
                (name, description, discount_type, discount_value, start_date, end_date, 
                 applies_to, target_category, target_product_id, min_order_value, 
                 max_discount_amount, customer_type, max_usage, banner_text, badge_text, 
                 show_on_homepage, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['discount_type'],
                $data['discount_value'],
                $data['start_date'],
                $data['end_date'],
                $data['applies_to'] ?? 'all',
                $data['target_category'] ?? null,
                $data['target_product_id'] ?? null,
                $data['min_order_value'] ?? 0,
                $data['max_discount_amount'] ?? null,
                $data['customer_type'] ?? 'all',
                $data['max_usage'] ?? null,
                $data['banner_text'] ?? null,
                $data['badge_text'] ?? null,
                $data['show_on_homepage'] ?? 1,
                $data['is_active'] ?? 1,
                $data['created_by'] ?? $_SESSION['user_id'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create promotion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update promotion
     */
    public function updatePromotion($promotionId, $data) {
        try {
            $fields = [];
            $values = [];
            
            $allowedFields = [
                'name', 'description', 'discount_type', 'discount_value', 'start_date', 
                'end_date', 'applies_to', 'target_category', 'target_product_id', 
                'min_order_value', 'max_discount_amount', 'customer_type', 'max_usage', 
                'banner_text', 'badge_text', 'show_on_homepage', 'is_active'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $values[] = $promotionId;
            $sql = "UPDATE furn_promotions SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            return true;
        } catch (PDOException $e) {
            error_log("Update promotion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete promotion
     */
    public function deletePromotion($promotionId) {
        try {
            // Check if promotion has been used
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM furn_order_promotions WHERE promotion_id = ?");
            $stmt->execute([$promotionId]);
            $usageCount = $stmt->fetchColumn();
            
            if ($usageCount > 0) {
                // Don't delete, just deactivate
                return $this->updatePromotion($promotionId, ['is_active' => 0]);
            }
            
            // Safe to delete
            $stmt = $this->db->prepare("DELETE FROM furn_promotions WHERE id = ?");
            $stmt->execute([$promotionId]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Delete promotion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all promotions (for admin management)
     */
    public function getAllPromotions() {
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       CASE 
                           WHEN NOW() < p.start_date THEN 'upcoming'
                           WHEN NOW() > p.end_date THEN 'expired'
                           WHEN p.max_usage IS NOT NULL AND p.usage_count >= p.max_usage THEN 'maxed_out'
                           WHEN p.is_active = 0 THEN 'inactive'
                           ELSE 'active'
                       END as status,
                       DATEDIFF(p.end_date, NOW()) as days_remaining
                FROM furn_promotions p
                ORDER BY p.created_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get all promotions error: " . $e->getMessage());
            return [];
        }
    }
}
