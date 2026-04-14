<?php
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class ProductionModel extends BaseModel {
    
    protected $table = 'furn_production_assignments';
    
    public function __construct()
    {
        parent::__construct();
        $this->ensureTables();
    }
    
    /**
     * Get all active production orders
     */
    public function getActiveProductionOrders() {
        try {
            $sql = "
                SELECT 
                    pa.id as production_id,
                    pa.order_id,
                    o.order_number,
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    u.email as customer_email,
                    GROUP_CONCAT(CONCAT(e.first_name, ' ', e.last_name) SEPARATOR ', ') as assigned_employees,
                    pa.assigned_at as start_date,
                    pa.deadline,
                    pa.progress,
                    pa.materials_reserved,
                    pa.status,
                    pa.notes
                FROM furn_production_assignments pa
                LEFT JOIN furn_orders o ON pa.order_id = o.id
                LEFT JOIN furn_users u ON o.customer_id = u.id
                LEFT JOIN furn_users e ON FIND_IN_SET(e.id, pa.assigned_employee_ids)
                WHERE pa.status IN ('in_progress', 'assigned')
                GROUP BY pa.id
                ORDER BY pa.deadline ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting active production orders: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Assign order to production
     */
    public function assignOrderToProduction($orderId, $employeeIds, $deadline, $requiredMaterials, $notes = '') {
        try {
            $this->db->beginTransaction();
            
            // Insert production assignment
            $data = [
                'order_id' => $orderId,
                'assigned_employee_ids' => implode(',', $employeeIds),
                'deadline' => $deadline,
                'required_materials' => $requiredMaterials,
                'notes' => $notes,
                'assigned_at' => date('Y-m-d H:i:s'),
                'status' => 'assigned'
            ];
            
            $productionId = parent::insert($data);
            
            // Reserve materials
            $this->reserveMaterials($orderId, $requiredMaterials);
            
            // Log activity
            $this->logProductionActivity($productionId, 'assigned', [
                'employees' => $employeeIds,
                'deadline' => $deadline
            ]);
            
            $this->db->commit();
            return $productionId;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Error assigning order to production: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update production progress
     */
    public function updateProductionProgress($productionId, $progress, $notes = '') {
        try {
            $data = [
                'progress' => $progress,
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($progress >= 100) {
                $data['status'] = 'completed';
                $data['completed_at'] = date('Y-m-d H:i:s');
            }
            
            $this->logProductionActivity($productionId, 'progress_updated', ['progress' => $progress]);
            return $this->update($productionId, $data);
        } catch (Exception $e) {
            error_log("Error updating production progress: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Complete production
     */
    public function completeProduction($productionId, $finalNotes = '') {
        try {
            $data = [
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => date('Y-m-d H:i:s'),
                'notes' => $finalNotes
            ];
            
            $this->logProductionActivity($productionId, 'completed', ['notes' => $finalNotes]);
            return $this->update($productionId, $data);
        } catch (Exception $e) {
            error_log("Error completing production: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get production statistics
     */
    public function getProductionStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_assignments,
                    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    AVG(progress) as avg_progress
                FROM {$this->table}
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error getting production statistics: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get employee workload
     */
    public function getEmployeeWorkload() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                    COUNT(pa.id) as assigned_tasks,
                    AVG(pa.progress) as avg_progress
                FROM furn_users u
                LEFT JOIN furn_production_assignments pa ON FIND_IN_SET(u.id, pa.assigned_employee_ids) 
                    AND pa.status IN ('assigned', 'in_progress')
                WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY assigned_tasks DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting employee workload: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get production timeline
     */
    public function getProductionTimeline($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(assigned_at) as date,
                    COUNT(*) as assignments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM {$this->table}
                WHERE assigned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(assigned_at)
                ORDER BY date DESC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting production timeline: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Reserve materials for production
     */
    private function reserveMaterials($orderId, $requiredMaterials) {
        try {
            // This would typically interact with MaterialModel
            // For now, just log the reservation
            error_log("Materials reserved for order $orderId: $requiredMaterials");
            return true;
        } catch (Exception $e) {
            error_log("Error reserving materials: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log production activity
     */
    private function logProductionActivity($productionId, $action, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO furn_production_logs (production_id, action, details, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            return $stmt->execute([$productionId, $action, json_encode($details)]);
        } catch (Exception $e) {
            error_log("Error logging production activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get production logs
     */
    public function getProductionLogs($productionId = null, $limit = 50) {
        try {
            $sql = "SELECT * FROM furn_production_logs";
            $params = [];
            
            if ($productionId) {
                $sql .= " WHERE production_id = ?";
                $params[] = $productionId;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting production logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get overdue productions
     */
    public function getOverdueProductions() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    pa.*,
                    o.order_number,
                    DATEDIFF(NOW(), pa.deadline) as days_overdue
                FROM {$this->table} pa
                LEFT JOIN furn_orders o ON pa.order_id = o.id
                WHERE pa.status IN ('assigned', 'in_progress') 
                AND pa.deadline < NOW()
                ORDER BY pa.deadline ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting overdue productions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get production efficiency
     */
    public function getProductionEfficiency() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                    COUNT(pa.id) as total_assignments,
                    SUM(CASE WHEN pa.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    AVG(DATEDIFF(pa.completed_at, pa.assigned_at)) as avg_completion_days
                FROM furn_users u
                LEFT JOIN furn_production_assignments pa ON FIND_IN_SET(u.id, pa.assigned_employee_ids)
                WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY completed DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting production efficiency: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get assigned orders by employee
     */
    public function getAssignedOrdersByEmployee($employeeId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    pa.*,
                    o.order_number,
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    pa.notes
                FROM {$this->table} pa
                LEFT JOIN furn_orders o ON pa.order_id = o.id
                LEFT JOIN furn_users u ON o.customer_id = u.id
                WHERE FIND_IN_SET(?, pa.assigned_employee_ids)
                AND pa.status IN ('assigned', 'in_progress')
                ORDER BY pa.deadline ASC
            ");
            $stmt->execute([$employeeId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting assigned orders by employee: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get employee recent activities
     */
    public function getEmployeeRecentActivities($employeeId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    pl.*,
                    pa.order_id,
                    o.order_number
                FROM furn_production_logs pl
                LEFT JOIN furn_production_assignments pa ON pl.production_id = pa.id
                LEFT JOIN furn_orders o ON pa.order_id = o.id
                WHERE FIND_IN_SET(?, pa.assigned_employee_ids)
                ORDER BY pl.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$employeeId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting employee recent activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Ensure required tables exist
     */
    private function ensureTables() {
        try {
            // Check if production_logs table exists
            $this->db->query("SELECT 1 FROM furn_production_logs LIMIT 1");
        } catch (Exception $e) {
            try {
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS furn_production_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        production_id INT NOT NULL,
                        action VARCHAR(50) NOT NULL,
                        details JSON,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_production (production_id),
                        KEY idx_created (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (Exception $e2) {
                error_log("Error creating production_logs table: " . $e2->getMessage());
            }
        }
    }
}
