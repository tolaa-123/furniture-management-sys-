<?php
/**
 * Production Controller
 * Handles order assignment, production tracking, and material management
 */
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/../models/OrderModel.php';
require_once dirname(__DIR__) . '/../models/ProductionModel.php';
require_once dirname(__DIR__) . '/../models/MaterialModel.php';
require_once dirname(__DIR__) . '/../models/MaterialReservationModel.php';
require_once dirname(__DIR__) . '/../models/AuditLogModel.php';

class ProductionController extends BaseController {
    private $orderModel;
    private $productionModel;
    private $materialModel;
    private $reservationModel;
    private $auditLogModel;
    
    public function __construct() {
        parent::__construct();
        $this->orderModel = new OrderModel();
        $this->productionModel = new ProductionModel();
        $this->materialModel = new MaterialModel();
        $this->reservationModel = new MaterialReservationModel();
        $this->auditLogModel = new AuditLogModel();
    }
    
    /**
     * Manager dashboard for production management
     */
    public function managerDashboard() {
        // Check if user is manager or admin
        if (!$this->auth->isLoggedIn() || !in_array($this->auth->getUserRole(), ['manager', 'admin'])) {
            $this->redirect('/login');
            return;
        }
        
        $pendingAssignments = $this->productionModel->getPendingAssignments();
        $productionStats = $this->productionModel->getProductionStats();
        $lowStockMaterials = $this->materialModel->getLowStockMaterials();
        $ordersInProduction = $this->orderModel->getOrdersInProduction();
        
        $this->view('production/manager_dashboard', [
            'pendingAssignments' => $pendingAssignments,
            'productionStats' => $productionStats,
            'lowStockMaterials' => $lowStockMaterials,
            'ordersInProduction' => $ordersInProduction,
            'pageTitle' => 'Production Management Dashboard'
        ]);
    }
    
    /**
     * Assign order to employee
     */
    public function assignOrder($orderId) {
        // Check if user is manager or admin
        if (!$this->auth->isLoggedIn() || !in_array($this->auth->getUserRole(), ['manager', 'admin'])) {
            $this->redirect('/login');
            return;
        }
        
        $order = $this->orderModel->getOrderWithDetails($orderId);
        if (!$order) {
            $this->showError('Order not found');
            return;
        }
        
        // Check if order is ready for production
        if ($order['status'] !== 'deposit_paid') {
            $this->showError('Order must be in "Deposit Paid" status to assign for production');
            return;
        }
        
        // Get available employees
        $stmt = $this->db->prepare("SELECT * FROM furn_users WHERE role = 'employee' AND is_active = 1 ORDER BY first_name, last_name");
        $stmt->execute();
        $employees = $stmt->fetchAll();
        
        // Get required materials for order
        try {
            $stmt = $this->db->prepare("
                SELECT oc.product_id, oc.quantity, p.name as product_name
                FROM furn_order_customizations oc
                JOIN furn_products p ON oc.product_id = p.id
                WHERE oc.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $customizations = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist
            error_log("Production materials query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT oc.product_id, oc.quantity, 'Product' as product_name
                    FROM furn_order_customizations oc
                    WHERE oc.order_id = ?
                ");
                $stmt->execute([$orderId]);
                $customizations = $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Production materials fallback error: " . $e2->getMessage());
                $customizations = [];
            }
        }
        
        $requiredMaterials = [];
        foreach ($customizations as $customization) {
            $materials = $this->materialModel->getMaterialsForProduct($customization['product_id']);
            foreach ($materials as $material) {
                $totalQuantity = $material['quantity_required'] * $customization['quantity'];
                if (isset($requiredMaterials[$material['id']])) {
                    $requiredMaterials[$material['id']]['total_quantity'] += $totalQuantity;
                } else {
                    $requiredMaterials[$material['id']] = [
                        'material' => $material,
                        'total_quantity' => $totalQuantity,
                        'availability' => $this->materialModel->getMaterialAvailability($material['id'])
                    ];
                }
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processOrderAssignment($orderId, $order);
            return;
        }
        
        $this->view('production/assign_order', [
            'order' => $order,
            'employees' => $employees,
            'requiredMaterials' => $requiredMaterials,
            'pageTitle' => 'Assign Order for Production'
        ]);
    }
    
    /**
     * Process order assignment
     */
    private function processOrderAssignment($orderId, $order) {
        // Validate CSRF token
        if (!$this->security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->showError('Invalid security token');
            return;
        }
        
        $employeeId = intval($_POST['employee_id'] ?? 0);
        $estimatedHours = floatval($_POST['estimated_hours'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $confirmMaterials = isset($_POST['confirm_materials']);
        
        // Validate input
        if ($employeeId <= 0) {
            $this->showError('Please select an employee');
            return;
        }
        
        if ($estimatedHours <= 0) {
            $this->showError('Please enter estimated hours');
            return;
        }
        
        if (!$confirmMaterials) {
            $this->showError('Please confirm that required materials are available');
            return;
        }
        
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Assign order to employee
            $assignmentId = $this->productionModel->assignOrder(
                $orderId,
                $employeeId,
                $this->auth->getUserId(),
                $estimatedHours,
                $notes
            );
            
            // Reserve required materials
            $stmt = $this->db->prepare("
                SELECT oc.product_id, oc.quantity
                FROM furn_order_customizations oc
                WHERE oc.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $customizations = $stmt->fetchAll();
            
            foreach ($customizations as $customization) {
                $materials = $this->materialModel->getMaterialsForProduct($customization['product_id']);
                foreach ($materials as $material) {
                    $requiredQuantity = $material['quantity_required'] * $customization['quantity'];
                    
                    // Reserve material
                    if (!$this->materialModel->reserveStock($material['id'], $requiredQuantity)) {
                        throw new Exception('Insufficient stock for material: ' . $material['name']);
                    }
                    
                    // Create reservation record
                    $this->reservationModel->reserveMaterials($orderId, $material['id'], $requiredQuantity);
                }
            }
            
            // Update order status
            $this->orderModel->startProduction($orderId);
            
            // Log the assignment
            $this->auditLogModel->logAction(
                $this->auth->getUserId(),
                'order_assigned',
                'furn_production_assignments',
                $assignmentId,
                null,
                ['order_id' => $orderId, 'employee_id' => $employeeId, 'estimated_hours' => $estimatedHours]
            );
            
            // Log order status change
            $this->auditLogModel->logAction(
                $this->auth->getUserId(),
                'order_status_changed',
                'furn_orders',
                $orderId,
                ['status' => 'deposit_paid'],
                ['status' => 'in_production']
            );
            
            // Commit transaction
            $this->db->commit();
            
            $this->setFlashMessage('success', 'Order assigned to employee successfully. Materials have been reserved.');
            $this->redirect('/production/manager-dashboard');
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->rollback();
            error_log('Order assignment error: ' . $e->getMessage());
            $this->showError('Failed to assign order: ' . $e->getMessage());
        }
    }
    
    /**
     * Employee dashboard for production work
     */
    public function employeeDashboard() {
        // Check if user is employee
        if (!$this->auth->isLoggedIn() || $this->auth->getUserRole() !== 'employee') {
            $this->redirect('/login');
            return;
        }
        
        $currentAssignments = $this->productionModel->getEmployeeAssignments($this->auth->getUserId(), 'assigned');
        $inProgressAssignments = $this->productionModel->getEmployeeAssignments($this->auth->getUserId(), 'in_progress');
        $completedAssignments = $this->productionModel->getEmployeeAssignments($this->auth->getUserId(), 'completed');
        
        $this->view('production/employee_dashboard', [
            'currentAssignments' => $currentAssignments,
            'inProgressAssignments' => $inProgressAssignments,
            'completedAssignments' => $completedAssignments,
            'pageTitle' => 'My Production Assignments'
        ]);
    }
    
    /**
     * Start production work on assignment
     */
    public function startWork($assignmentId) {
        // Check if user is employee
        if (!$this->auth->isLoggedIn() || $this->auth->getUserRole() !== 'employee') {
            $this->redirect('/login');
            return;
        }
        
        // Verify assignment belongs to employee
        $stmt = $this->db->prepare("SELECT * FROM furn_production_assignments WHERE id = ? AND employee_id = ?");
        $stmt->execute([$assignmentId, $this->auth->getUserId()]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            $this->showError('Assignment not found or not assigned to you');
            return;
        }
        
        if ($assignment['status'] !== 'assigned') {
            $this->showError('Assignment is not in assigned status');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate CSRF token
            if (!$this->security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
                $this->showError('Invalid security token');
                return;
            }
            
            try {
                $this->productionModel->startProduction($assignmentId);
                
                // Log the action
                $this->auditLogModel->logAction(
                    $this->auth->getUserId(),
                    'production_started',
                    'furn_production_assignments',
                    $assignmentId
                );
                
                $this->setFlashMessage('success', 'Production work started successfully');
                $this->redirect('/production/employee-dashboard');
                
            } catch (Exception $e) {
                error_log('Production start error: ' . $e->getMessage());
                $this->showError('Failed to start production work');
            }
        }
        
        // Show confirmation page
        $this->view('production/start_work', [
            'assignment' => $assignment,
            'pageTitle' => 'Start Production Work'
        ]);
    }
    
    /**
     * Complete production work
     */
    public function completeWork($assignmentId) {
        // Check if user is employee
        if (!$this->auth->isLoggedIn() || $this->auth->getUserRole() !== 'employee') {
            $this->redirect('/login');
            return;
        }
        
        // Verify assignment belongs to employee
        $stmt = $this->db->prepare("SELECT * FROM furn_production_assignments WHERE id = ? AND employee_id = ?");
        $stmt->execute([$assignmentId, $this->auth->getUserId()]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            $this->showError('Assignment not found or not assigned to you');
            return;
        }
        
        if ($assignment['status'] !== 'in_progress') {
            $this->showError('Assignment is not in progress');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate CSRF token
            if (!$this->security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
                $this->showError('Invalid security token');
                return;
            }
            
            $actualHours = floatval($_POST['actual_hours'] ?? 0);
            $completionNotes = trim($_POST['completion_notes'] ?? '');
            
            if ($actualHours <= 0) {
                $this->showError('Please enter actual hours worked');
                return;
            }
            
            try {
                // Start transaction
                $this->db->beginTransaction();
                
                // Complete assignment
                $this->productionModel->completeProduction($assignmentId, $actualHours, $completionNotes);
                
                // Check if all assignments for this order are completed
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as remaining
                    FROM furn_production_assignments
                    WHERE order_id = ? AND status != 'completed'
                ");
                $stmt->execute([$assignment['order_id']]);
                $remaining = $stmt->fetchColumn();
                
                if ($remaining == 0) {
                    // All assignments completed, complete order production
                    $this->orderModel->completeProduction($assignment['order_id']);
                    
                    // Mark material reservations as used
                    $reservations = $this->reservationModel->getReservationsByOrder($assignment['order_id']);
                    foreach ($reservations as $reservation) {
                        $this->reservationModel->markAsUsed($reservation['id']);
                        // Update material stock (reduce reserved stock)
                        $this->materialModel->releaseStock($reservation['material_id'], $reservation['quantity']);
                        $this->materialModel->updateStock($reservation['material_id'], -$reservation['quantity']);
                    }
                    
                    // Log order completion
                    $this->auditLogModel->logAction(
                        $this->auth->getUserId(),
                        'order_production_completed',
                        'furn_orders',
                        $assignment['order_id']
                    );
                }
                
                // Log assignment completion
                $this->auditLogModel->logAction(
                    $this->auth->getUserId(),
                    'production_completed',
                    'furn_production_assignments',
                    $assignmentId,
                    null,
                    ['actual_hours' => $actualHours]
                );
                
                // Commit transaction
                $this->db->commit();
                
                $this->setFlashMessage('success', 'Production work completed successfully');
                $this->redirect('/production/employee-dashboard');
                
            } catch (Exception $e) {
                // Rollback transaction
                $this->db->rollback();
                error_log('Production completion error: ' . $e->getMessage());
                $this->showError('Failed to complete production work');
            }
        }
        
        // Show completion form
        $this->view('production/complete_work', [
            'assignment' => $assignment,
            'pageTitle' => 'Complete Production Work'
        ]);
    }
    
    /**
     * View production tracking details
     */
    public function viewTracking($orderId) {
        // Check authentication
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/login');
            return;
        }
        
        $order = $this->orderModel->getOrderWithDetails($orderId);
        if (!$order) {
            $this->showError('Order not found');
            return;
        }
        
        // Check permissions
        if ($this->auth->getUserRole() === 'customer' && $order['customer_id'] != $this->auth->getUserId()) {
            $this->showError('Access denied');
            return;
        }
        
        $assignments = $this->productionModel->getAssignmentsByOrder($orderId);
        $reservations = $this->reservationModel->getReservationsByOrder($orderId);
        $timeline = $this->orderModel->getProductionTimeline($orderId);
        
        $this->view('production/view_tracking', [
            'order' => $order,
            'assignments' => $assignments,
            'reservations' => $reservations,
            'timeline' => $timeline,
            'pageTitle' => 'Production Tracking - ' . $order['order_number']
        ]);
    }
}