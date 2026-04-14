<?php
/**
 * Material Reservation Model
 * Handles material reservation for orders
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class MaterialReservationModel extends BaseModel {
    protected $table = 'furn_material_reservations';
    
    /**
     * Reserve materials for order
     */
    public function reserveMaterials($orderId, $materialId, $quantity) {
        $data = [
            'order_id' => $orderId,
            'material_id' => $materialId,
            'quantity' => $quantity,
            'status' => 'reserved'
        ];
        
        return parent::insert($data);
    }
    
    /**
     * Get reservations by order
     */
    public function getReservationsByOrder($orderId) {
        $stmt = $this->db->prepare("
            SELECT mr.*, m.name as material_name, m.unit, m.current_stock, m.reserved_stock
            FROM {$this->table} mr
            JOIN furn_materials m ON mr.material_id = m.id
            WHERE mr.order_id = ?
            ORDER BY m.name
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get reservations by material
     */
    public function getReservationsByMaterial($materialId) {
        $stmt = $this->db->prepare("
            SELECT mr.*, o.order_number, o.status as order_status
            FROM {$this->table} mr
            JOIN furn_orders o ON mr.order_id = o.id
            WHERE mr.material_id = ? AND mr.status = 'reserved'
            ORDER BY mr.reserved_at DESC
        ");
        $stmt->execute([$materialId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Mark reservation as used
     */
    public function markAsUsed($reservationId) {
        $data = [
            'status' => 'used',
            'released_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->update($reservationId, $data);
    }
    
    /**
     * Cancel reservation
     */
    public function cancelReservation($reservationId) {
        $data = [
            'status' => 'cancelled',
            'released_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->update($reservationId, $data);
    }
    
    /**
     * Get total reserved quantity for material
     */
    public function getTotalReserved($materialId) {
        $stmt = $this->db->prepare("
            SELECT SUM(quantity) as total_reserved
            FROM {$this->table}
            WHERE material_id = ? AND status = 'reserved'
        ");
        $stmt->execute([$materialId]);
        $result = $stmt->fetch();
        return $result ? $result['total_reserved'] : 0;
    }
    
    /**
     * Release all reservations for order
     */
    public function releaseOrderReservations($orderId) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET status = 'cancelled', released_at = NOW()
            WHERE order_id = ? AND status = 'reserved'
        ");
        return $stmt->execute([$orderId]);
    }
}