<?php
/**
 * Payroll Controller
 * Handles payroll automation, calculation, and management
 */
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/models/PayrollModel.php';

class PayrollController extends BaseController {
    private $payrollModel;
    
    public function __construct() {
        parent::__construct();
        $this->payrollModel = new PayrollModel();
    }
    
    /**
     * Payroll dashboard
     */
    public function dashboard() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        // Get payroll statistics
        $stats = $this->payrollModel->getPayrollStats();
        
        // Get recent payroll periods
        $periods = $this->payrollModel->getPayrollPeriods();
        
        $data = [
            'stats' => $stats,
            'periods' => array_slice($periods, 0, 10) // Last 10 periods
        ];
        
        $this->render('payroll/dashboard', $data);
    }
    
    /**
     * List all payroll periods
     */
    public function periods() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $periods = $this->payrollModel->getPayrollPeriods();
        
        $data = ['periods' => $periods];
        $this->render('payroll/periods', $data);
    }
    
    /**
     * Create new payroll period
     */
    public function createPeriod() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $periodData = [
                'period_name' => $_POST['period_name'] ?? '',
                'start_date' => $_POST['start_date'] ?? '',
                'end_date' => $_POST['end_date'] ?? '',
                'generated_by' => $_SESSION['user_id']
            ];
            
            $periodId = $this->payrollModel->createPayrollPeriod($periodData);
            
            if ($periodId) {
                $this->logAudit('payroll_period_created', 'furn_payroll_periods', $periodId, $periodData);
                $this->setFlashMessage('success', 'Payroll period created successfully');
                $this->redirect('/payroll/periods');
            } else {
                $this->setFlashMessage('error', 'Failed to create payroll period');
            }
        }
        
        $this->render('payroll/create_period');
    }
    
    /**
     * Calculate payroll for a period
     */
    public function calculate($periodId) {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $period = $this->payrollModel->getPayrollPeriod($periodId);
        if (!$period) {
            $this->setFlashMessage('error', 'Payroll period not found');
            $this->redirect('/payroll/periods');
            return;
        }
        
        if ($period['status'] !== 'draft') {
            $this->setFlashMessage('error', 'Payroll period already calculated');
            $this->redirect('/payroll/periods');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $success = $this->payrollModel->calculatePayroll($periodId, $period['start_date'], $period['end_date']);
            
            if ($success) {
                $this->logAudit('payroll_calculated', 'furn_payroll_periods', $periodId, [
                    'period_name' => $period['period_name'],
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date']
                ]);
                $this->setFlashMessage('success', 'Payroll calculated successfully');
            } else {
                $this->setFlashMessage('error', 'Failed to calculate payroll');
            }
            
            $this->redirect('/payroll/view/' . $periodId);
        }
        
        $data = ['period' => $period];
        $this->render('payroll/calculate', $data);
    }
    
    /**
     * View payroll period details
     */
    public function view($periodId) {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $period = $this->payrollModel->getPayrollPeriod($periodId);
        if (!$period) {
            $this->setFlashMessage('error', 'Payroll period not found');
            $this->redirect('/payroll/periods');
            return;
        }
        
        $records = $this->payrollModel->getPayrollRecords($periodId);
        
        $data = [
            'period' => $period,
            'records' => $records
        ];
        
        $this->render('payroll/view_period', $data);
    }
    
    /**
     * Employee payroll details
     */
    public function employeeDetails($employeeId = null) {
        if (!$employeeId) {
            $employeeId = $_SESSION['user_id'];
        }
        
        // Check if user can view this employee's payroll
        if (!$this->isManager() && $employeeId != $_SESSION['user_id']) {
            $this->setFlashMessage('error', 'Access denied.');
            $this->redirect('/login');
            return;
        }
        
        $employee = $this->getUserById($employeeId);
        if (!$employee) {
            $this->setFlashMessage('error', 'Employee not found');
            $this->redirect('/payroll/dashboard');
            return;
        }
        
        $payrollHistory = $this->payrollModel->getEmployeePayrollHistory($employeeId);
        
        $data = [
            'employee' => $employee,
            'payrollHistory' => $payrollHistory
        ];
        
        $this->render('payroll/employee_details', $data);
    }
    
    /**
     * Payroll reports
     */
    public function reports() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $periodId = $_GET['period'] ?? null;
        $reportType = $_GET['type'] ?? 'summary';
        
        $periods = $this->payrollModel->getPayrollPeriods();
        $selectedPeriod = null;
        $reportData = [];
        
        if ($periodId) {
            $selectedPeriod = $this->payrollModel->getPayrollPeriod($periodId);
            if ($selectedPeriod) {
                $reportData = $this->payrollModel->getPayrollRecords($periodId);
            }
        }
        
        $data = [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'reportData' => $reportData,
            'reportType' => $reportType
        ];
        
        $this->render('payroll/reports', $data);
    }
    
    /**
     * Export payroll data
     */
    public function export($periodId) {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $period = $this->payrollModel->getPayrollPeriod($periodId);
        if (!$period) {
            $this->setFlashMessage('error', 'Payroll period not found');
            $this->redirect('/payroll/periods');
            return;
        }
        
        $records = $this->payrollModel->getPayrollRecords($periodId);
        
        // Generate CSV export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payroll_' . $period['period_name'] . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Employee ID', 'Name', 'Email', 'Working Days', 'Working Hours', 
            'Basic Salary', 'Overtime Hours', 'Overtime Pay', 'Gross Salary',
            'Total Deductions', 'Net Salary'
        ]);
        
        // CSV data
        foreach ($records as $record) {
            fputcsv($output, [
                $record['employee_code'],
                $record['employee_name'],
                $record['employee_email'],
                $record['working_days'],
                $record['working_hours'],
                $record['basic_salary'],
                $record['overtime_hours'],
                $record['overtime_pay'],
                $record['gross_salary'],
                $record['total_deductions'],
                $record['net_salary']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get user by ID
     */
    private function getUserById($userId) {
        $stmt = $this->db->prepare("SELECT * FROM furn_users WHERE id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}