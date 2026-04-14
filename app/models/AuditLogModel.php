<?php
/**
 * Audit Log Model
 * Handles logging of critical actions for security and compliance
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class AuditLogModel extends BaseModel {
    protected $table = 'furn_audit_logs';
    
    /**
     * Log an action
     */
    public function logAction($userId, $action, $tableName, $recordId, $oldValues = null, $newValues = null) {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        if ($oldValues) {
            $data['old_values'] = json_encode($oldValues);
        }
        
        if ($newValues) {
            $data['new_values'] = json_encode($newValues);
        }
        
        return parent::insert($data);
    }
    
    /**
     * Get audit logs for a specific record
     */
    public function getLogsForRecord($tableName, $recordId) {
        $stmt = $this->db->prepare("
            SELECT al.*, u.first_name, u.last_name, u.role
            FROM {$this->table} al
            LEFT JOIN furn_users u ON al.user_id = u.id
            WHERE al.table_name = ? AND al.record_id = ?
            ORDER BY al.created_at DESC
        ");
        $stmt->execute([$tableName, $recordId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get recent audit logs
     */
    public function getRecentLogs($limit = 50) {
        $stmt = $this->db->prepare("
            SELECT al.*, u.first_name, u.last_name, u.role
            FROM {$this->table} al
            LEFT JOIN furn_users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get logs by action type
     */
    public function getLogsByAction($action, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT al.*, u.first_name, u.last_name, u.role
            FROM {$this->table} al
            LEFT JOIN furn_users u ON al.user_id = u.id
            WHERE al.action = ?
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$action, $limit]);
        return $stmt->fetchAll();
    }
}