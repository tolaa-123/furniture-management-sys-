<?php
/**
 * Profit Model
 * Handles profit calculations, analytics, and reporting
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class ProfitModel extends BaseModel {
    protected $table = 'furn_profit_calculations';
    
    /**
     * Calculate profit for an order
     */
    public function calculateOrderProfit($orderId) {
        try {
            $this->db->beginTransaction();
            
            // Get order details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            // Check if already calculated
            if ($order['profit_calculated'] ?? false) {
                throw new Exception('Profit already calculated for this order');
            }
            
            // Get material costs
            $materialCost = $this->calculateMaterialCost($orderId);
            
            // Get labor costs
            $laborCost = $this->calculateLaborCost($orderId);
            
            // Get production time costs
            $productionTimeCost = $this->calculateProductionTimeCost($orderId);
            
            // Calculate total cost
            $totalCost = $materialCost + $laborCost + $productionTimeCost;
            
            // Calculate profit
            $profit = ($order['total_amount'] ?? 0) - $totalCost;
            
            // Calculate profit margin percentage
            $profitMargin = ($order['total_amount'] ?? 0) > 0 ? ($profit / ($order['total_amount'] ?? 1)) * 100 : 0;
            
            // Insert profit calculation
            $profitData = [
                'order_id' => $orderId,
                'product_id' => $order['product_id'] ?? null,
                'final_selling_price' => $order['total_amount'] ?? 0,
                'material_cost' => $materialCost,
                'labor_cost' => $laborCost,
                'production_time_cost' => $productionTimeCost,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'profit_margin_percentage' => $profitMargin,
                'calculated_by' => $_SESSION['user_id'] ?? null
            ];
            
            $profitId = parent::insert($profitData);
            
            // Update order status
            $this->updateOrderProfitStatus($orderId, true);
            
            // Update summaries
            $this->updateMonthlySummary($order['created_at'] ?? date('Y-m-d'));
            $this->updateProductSummary($order['product_id'] ?? null);
            
            $this->db->commit();
            return $profitId;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('Profit calculation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get profit calculation for order
     */
    public function getOrderProfit($orderId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE order_id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }
    
    /**
     * Get profit for multiple orders
     */
    public function getMultipleOrderProfits($orderIds) {
        if (empty($orderIds)) return [];
        
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE order_id IN ($placeholders)");
        $stmt->execute($orderIds);
        return $stmt->fetchAll();
    }
    
    /**
     * Get profit summary by product
     */
    public function getProductProfitSummary($productId = null, $limit = 20) {
        try {
            $sql = "SELECT pps.*, fp.name as product_name, fp.category
                    FROM furn_product_profit_summary pps
                    JOIN furn_products fp ON pps.product_id = fp.id";
            
            $params = [];
            if ($productId) {
                $sql .= " WHERE pps.product_id = ?";
                $params[] = $productId;
            }
            
            $sql .= " ORDER BY pps.total_profit DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching product profit summary: " . $e->getMessage());
            // Fallback query without product name
            try {
                $sql = "SELECT pps.*
                        FROM furn_product_profit_summary pps";
                
                $params = [];
                if ($productId) {
                    $sql .= " WHERE pps.product_id = ?";
                    $params[] = $productId;
                }
                
                $sql .= " ORDER BY pps.total_profit DESC LIMIT ?";
                $params[] = $limit;
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Fallback query also failed: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get monthly profit summary
     */
    public function getMonthlyProfitSummary($months = 12) {
        $stmt = $this->db->prepare("
            SELECT mps.*, 
                   DATE_FORMAT(STR_TO_DATE(CONCAT(mps.year, '-', mps.month, '-01'), '%Y-%m-%d'), '%M %Y') as month_name
            FROM furn_monthly_profit_summary mps
            ORDER BY mps.year DESC, mps.month DESC
            LIMIT ?
        ");
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get profit statistics
     */
    public function getProfitStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_calculated_orders,
                SUM(final_selling_price) as total_revenue,
                SUM(material_cost) as total_material_cost,
                SUM(labor_cost) as total_labor_cost,
                SUM(production_time_cost) as total_production_cost,
                SUM(total_cost) as total_cost,
                SUM(profit) as total_profit,
                AVG(profit_margin_percentage) as average_profit_margin,
                MAX(profit) as highest_profit,
                MIN(profit) as lowest_profit
            FROM {$this->table}
        ");
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Get top profitable products
     */
    public function getTopProfitableProducts($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.name as product_name,
                    p.category,
                    COUNT(pc.id) as orders_count,
                    SUM(pc.final_selling_price) as total_revenue,
                    SUM(pc.profit) as total_profit,
                    AVG(pc.profit_margin_percentage) as avg_profit_margin
                FROM {$this->table} pc
                JOIN furn_products p ON pc.product_id = p.id
                GROUP BY pc.product_id, p.name, p.category
                ORDER BY total_profit DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching top profitable products: " . $e->getMessage());
            // Fallback query without p.name if column doesn't exist
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        pc.product_id,
                        p.category,
                        COUNT(pc.id) as orders_count,
                        SUM(pc.final_selling_price) as total_revenue,
                        SUM(pc.profit) as total_profit,
                        AVG(pc.profit_margin_percentage) as avg_profit_margin
                    FROM {$this->table} pc
                    LEFT JOIN furn_products p ON pc.product_id = p.id
                    GROUP BY pc.product_id
                    ORDER BY total_profit DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                return $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Fallback query also failed: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get profit trends
     */
    public function getProfitTrends($days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(pc.calculated_at) as date,
                COUNT(pc.id) as orders_count,
                SUM(pc.final_selling_price) as daily_revenue,
                SUM(pc.profit) as daily_profit,
                AVG(pc.profit_margin_percentage) as avg_daily_margin
            FROM {$this->table} pc
            WHERE pc.calculated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(pc.calculated_at)
            ORDER BY DATE(pc.calculated_at) DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get profit settings
     */
    public function getProfitSettings() {
        $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM furn_profit_settings");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }
        return $result;
    }
    
    /**
     * Update profit setting
     */
    public function updateSetting($key, $value) {
        $stmt = $this->db->prepare("
            INSERT INTO furn_profit_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    }
    
    /**
     * Private helper methods
     */
    private function getOrderDetails($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT o.*, oc.product_id
                FROM furn_orders o
                LEFT JOIN furn_order_customizations oc ON o.id = oc.order_id
                WHERE o.id = ?
                LIMIT 1
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting order details: " . $e->getMessage());
            return null;
        }
    }
    
    private function calculateMaterialCost($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(m.quantity_required * m2.average_cost) as total_material_cost
                FROM furn_order_customizations oc
                LEFT JOIN furn_product_materials m ON oc.product_id = m.product_id
                LEFT JOIN furn_materials m2 ON m.material_id = m2.id
                WHERE oc.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $result = $stmt->fetch();
            return $result['total_material_cost'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error calculating material cost: " . $e->getMessage());
            return 0;
        }
    }
    
    private function calculateLaborCost($orderId) {
        try {
            $settings = $this->getProfitSettings();
            $hourlyRate = floatval($settings['labor_hourly_rate'] ?? 50.00);
            
            $stmt = $this->db->prepare("
                SELECT SUM(pa.actual_hours) as total_labor_hours
                FROM furn_production_assignments pa
                LEFT JOIN furn_order_customizations oc ON pa.order_id = oc.order_id
                WHERE oc.order_id = ? AND pa.status = 'completed'
            ");
            $stmt->execute([$orderId]);
            $result = $stmt->fetch();
            
            $totalHours = $result['total_labor_hours'] ?? 0;
            return $totalHours * $hourlyRate;
        } catch (PDOException $e) {
            error_log("Error calculating labor cost: " . $e->getMessage());
            return 0;
        }
    }
    
    private function calculateProductionTimeCost($orderId) {
        try {
            $settings = $this->getProfitSettings();
            $timeCostRate = floatval($settings['production_time_cost_rate'] ?? 30.00);
            
            $stmt = $this->db->prepare("
                SELECT SUM(pa.actual_hours) as total_production_hours
                FROM furn_production_assignments pa
                LEFT JOIN furn_order_customizations oc ON pa.order_id = oc.order_id
                WHERE oc.order_id = ? AND pa.status = 'completed'
            ");
            $stmt->execute([$orderId]);
            $result = $stmt->fetch();
            
            $totalHours = $result['total_production_hours'] ?? 0;
            return $totalHours * $timeCostRate;
        } catch (PDOException $e) {
            error_log("Error calculating production time cost: " . $e->getMessage());
            return 0;
        }
    }
    
    private function updateOrderProfitStatus($orderId, $calculated) {
        try {
            $stmt = $this->db->prepare("
                UPDATE furn_orders 
                SET profit_calculated = ?, profit_calculation_date = ?
                WHERE id = ?
            ");
            return $stmt->execute([$calculated ? 1 : 0, $calculated ? date('Y-m-d H:i:s') : null, $orderId]);
        } catch (PDOException $e) {
            error_log("Error updating order profit status: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateMonthlySummary($orderDate) {
        $year = date('Y', strtotime($orderDate));
        $month = date('m', strtotime($orderDate));
        
        // This would typically be called from a background job or scheduled task
        // For now, we'll just ensure the structure exists
        return true;
    }
    
    private function updateProductSummary($productId) {
        // This would typically be called from a background job or scheduled task
        // For now, we'll just ensure the structure exists
        return true;
    }
}