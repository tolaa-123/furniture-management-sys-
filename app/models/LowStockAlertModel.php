<?php
/**
 * Low Stock Alert Model
 * Handles low stock notifications and alerts
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class LowStockAlertModel extends BaseModel {
    protected $table = 'furn_low_stock_alerts';
    
    /**
     * Create low stock alert
     */
    public function createAlert($materialId, $currentStock, $minimumStock) {
        $alertLevel = ($currentStock <= $minimumStock * 0.5) ? 'critical' : 'low';
        
        $data = [
            'material_id' => $materialId,
            'current_stock' => $currentStock,
            'minimum_stock' => $minimumStock,
            'alert_level' => $alertLevel
        ];
        
        return parent::insert($data);
    }
    
    /**
     * Get active alerts
     */
    public function getActiveAlerts() {
        $stmt = $this->db->prepare("
            SELECT lsa.*, m.name as material_name, m.unit, mc.name as category_name
            FROM {$this->table} lsa
            JOIN furn_materials m ON lsa.material_id = m.id
            LEFT JOIN furn_material_categories mc ON m.category_id = mc.id
            WHERE lsa.is_resolved = 0
            ORDER BY 
                CASE WHEN lsa.alert_level = 'critical' THEN 1 ELSE 2 END,
                lsa.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get alerts by level
     */
    public function getAlertsByLevel($alertLevel) {
        $stmt = $this->db->prepare("
            SELECT lsa.*, m.name as material_name, m.unit
            FROM {$this->table} lsa
            JOIN furn_materials m ON lsa.material_id = m.id
            WHERE lsa.alert_level = ? AND lsa.is_resolved = 0
            ORDER BY lsa.created_at DESC
        ");
        $stmt->execute([$alertLevel]);
        return $stmt->fetchAll();
    }
    
    /**
     * Resolve alert
     */
    public function resolveAlert($alertId, $userId) {
        $data = [
            'is_resolved' => 1,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $userId
        ];
        
        return $this->update($alertId, $data);
    }
    
    /**
     * Get alert statistics
     */
    public function getAlertStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_alerts,
                COUNT(CASE WHEN is_resolved = 0 THEN 1 END) as active_alerts,
                COUNT(CASE WHEN alert_level = 'critical' AND is_resolved = 0 THEN 1 END) as critical_alerts,
                COUNT(CASE WHEN alert_level = 'low' AND is_resolved = 0 THEN 1 END) as low_alerts
            FROM {$this->table}
        ");
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Check and create alerts for low stock materials
     */
    public function checkAndCreateAlerts() {
        // Get materials with low stock
        $stmt = $this->db->prepare("
            SELECT id, name, current_stock, reserved_stock, minimum_stock
            FROM furn_materials 
            WHERE is_active = 1 AND (current_stock - reserved_stock) <= minimum_stock
        ");
        $stmt->execute();
        $lowStockMaterials = $stmt->fetchAll();
        
        $newAlerts = 0;
        foreach ($lowStockMaterials as $material) {
            $availableStock = $material['current_stock'] - $material['reserved_stock'];
            
            // Check if alert already exists for this material
            $stmt = $this->db->prepare("
                SELECT id FROM {$this->table} 
                WHERE material_id = ? AND is_resolved = 0 
                LIMIT 1
            ");
            $stmt->execute([$material['id']]);
            
            if (!$stmt->fetch()) {
                // Create new alert
                $this->createAlert($material['id'], $availableStock, $material['minimum_stock']);
                $newAlerts++;
            }
        }
        
        return $newAlerts;
    }
}