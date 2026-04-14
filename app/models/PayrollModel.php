<?php
/**
 * Payroll Model
 * Handles payroll calculations, employee salaries, and deduction management
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class PayrollModel extends BaseModel {
    protected $table = 'furn_payroll_records';
    
    /**
     * Calculate payroll for a period
     */
    public function calculatePayroll($periodId, $startDate, $endDate) {
        try {
            $this->db->beginTransaction();
            
            // Get all active employees
            $stmt = $this->db->prepare("
                SELECT u.id, u.employee_id, u.first_name, u.last_name, u.email, u.department,
                       s.basic_salary, s.hourly_rate, s.overtime_rate, s.salary_type
                FROM furn_users u
                JOIN furn_employee_salaries s ON u.id = s.employee_id
                WHERE u.role IN ('employee', 'manager') AND u.is_active = 1 AND s.is_active = 1
            ");
            $stmt->execute();
            $employees = $stmt->fetchAll();
            
            $totalEmployees = 0;
            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            
            foreach ($employees as $employee) {
                // Calculate working days from attendance
                $workingDays = $this->calculateWorkingDays($employee['id'], $startDate, $endDate);
                
                // Calculate working hours (assuming 8 hours per day)
                $workingHours = $workingDays * 8;
                
                // Calculate overtime hours from production records
                $overtimeHours = $this->calculateOvertimeHours($employee['id'], $startDate, $endDate);
                
                // Calculate salary components
                $basicSalary = $this->calculateBasicSalary($employee, $workingDays);
                $overtimePay = $overtimeHours * ($employee['overtime_rate'] ?? 0);
                $grossSalary = $basicSalary + $overtimePay;
                
                // Calculate deductions
                $deductions = $this->calculateDeductions($employee['id'], $grossSalary);
                $totalDeductionAmount = array_sum(array_column($deductions, 'calculated_amount'));
                
                // Calculate net salary
                $netSalary = $grossSalary - $totalDeductionAmount;
                
                // Insert payroll record
                $payrollData = [
                    'payroll_period_id' => $periodId,
                    'employee_id' => $employee['id'],
                    'employee_code' => $employee['employee_id'],
                    'employee_name' => $employee['first_name'] . ' ' . $employee['last_name'],
                    'employee_email' => $employee['email'],
                    'working_days' => $workingDays,
                    'working_hours' => $workingHours,
                    'basic_salary' => $basicSalary,
                    'overtime_hours' => $overtimeHours,
                    'overtime_pay' => $overtimePay,
                    'gross_salary' => $grossSalary,
                    'total_deductions' => $totalDeductionAmount,
                    'net_salary' => $netSalary
                ];
                
                $payrollRecordId = parent::insert($payrollData);
                
                // Insert deduction details
                if ($payrollRecordId && !empty($deductions)) {
                    foreach ($deductions as $deduction) {
                        $deductionData = [
                            'payroll_record_id' => $payrollRecordId,
                            'deduction_id' => $deduction['deduction_id'],
                            'deduction_name' => $deduction['name'],
                            'deduction_type' => $deduction['type'],
                            'deduction_value' => $deduction['value'],
                            'calculated_amount' => $deduction['calculated_amount']
                        ];
                        
                        $this->insertDeductionDetail($deductionData);
                    }
                }
                
                $totalEmployees++;
                $totalGross += $grossSalary;
                $totalDeductions += $totalDeductionAmount;
                $totalNet += $netSalary;
            }
            
            // Update payroll period totals
            $this->updatePayrollPeriodTotals($periodId, $totalEmployees, $totalGross, $totalDeductions, $totalNet);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Payroll calculation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate working days from attendance
     */
    private function calculateWorkingDays($employeeId, $startDate, $endDate) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as working_days
            FROM furn_attendance
            WHERE employee_id = ? 
            AND DATE(check_in_time) BETWEEN ? AND ?
            AND status IN ('present', 'late')
        ");
        $stmt->execute([$employeeId, $startDate, $endDate]);
        $result = $stmt->fetch();
        return $result['working_days'] ?? 0;
    }
    
    /**
     * Calculate overtime hours from production records
     */
    private function calculateOvertimeHours($employeeId, $startDate, $endDate) {
        $stmt = $this->db->prepare("
            SELECT SUM(actual_hours - estimated_hours) as overtime_hours
            FROM furn_production_assignments
            WHERE employee_id = ? 
            AND DATE(started_at) BETWEEN ? AND ?
            AND actual_hours > estimated_hours
            AND status = 'completed'
        ");
        $stmt->execute([$employeeId, $startDate, $endDate]);
        $result = $stmt->fetch();
        return max(0, $result['overtime_hours'] ?? 0);
    }
    
    /**
     * Calculate basic salary based on working days
     */
    private function calculateBasicSalary($employee, $workingDays) {
        if ($employee['salary_type'] === 'monthly') {
            // Calculate daily rate and multiply by working days
            $dailyRate = $employee['basic_salary'] / 22; // Assuming 22 working days per month
            return $dailyRate * $workingDays;
        } else {
            // Hourly rate calculation
            return $workingDays * 8 * $employee['hourly_rate'];
        }
    }
    
    /**
     * Calculate deductions for an employee
     */
    private function calculateDeductions($employeeId, $grossSalary) {
        $deductions = [];
        
        // Get applicable deductions
        $stmt = $this->db->prepare("
            SELECT d.id, d.name, d.type, d.value,
                   ed.custom_value
            FROM furn_payroll_deductions d
            LEFT JOIN furn_employee_deductions ed ON d.id = ed.deduction_id AND ed.employee_id = ? AND ed.is_active = 1
            WHERE d.is_active = 1 
            AND (d.applies_to = 'all' OR ed.id IS NOT NULL)
        ");
        $stmt->execute([$employeeId]);
        $applicableDeductions = $stmt->fetchAll();
        
        foreach ($applicableDeductions as $deduction) {
            $value = $deduction['custom_value'] ?? $deduction['value'];
            $calculatedAmount = 0;
            
            if ($deduction['type'] === 'percentage') {
                $calculatedAmount = ($grossSalary * $value) / 100;
            } else {
                $calculatedAmount = $value;
            }
            
            $deductions[] = [
                'deduction_id' => $deduction['id'],
                'name' => $deduction['name'],
                'type' => $deduction['type'],
                'value' => $value,
                'calculated_amount' => $calculatedAmount
            ];
        }
        
        return $deductions;
    }
    
    /**
     * Insert deduction detail
     */
    private function insertDeductionDetail($deductionData) {
        $stmt = $this->db->prepare("
            INSERT INTO furn_payroll_deduction_details 
            (payroll_record_id, deduction_id, deduction_name, deduction_type, deduction_value, calculated_amount, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $deductionData['payroll_record_id'],
            $deductionData['deduction_id'],
            $deductionData['deduction_name'],
            $deductionData['deduction_type'],
            $deductionData['deduction_value'],
            $deductionData['calculated_amount']
        ]);
    }
    
    /**
     * Update payroll period totals
     */
    private function updatePayrollPeriodTotals($periodId, $totalEmployees, $totalGross, $totalDeductions, $totalNet) {
        $stmt = $this->db->prepare("
            UPDATE furn_payroll_periods 
            SET total_employees = ?, 
                total_gross_salary = ?,
                total_deductions = ?,
                total_net_salary = ?,
                status = 'calculated'
            WHERE id = ?
        ");
        $stmt->execute([$totalEmployees, $totalGross, $totalDeductions, $totalNet, $periodId]);
    }
    
    /**
     * Get payroll records for a period
     */
    public function getPayrollRecords($periodId) {
        $stmt = $this->db->prepare("
            SELECT pr.*, 
                   GROUP_CONCAT(CONCAT(pd.deduction_name, ': ETB ', FORMAT(pd.calculated_amount, 2)) SEPARATOR '; ') as deduction_details
            FROM {$this->table} pr
            LEFT JOIN furn_payroll_deduction_details pd ON pr.id = pd.payroll_record_id
            WHERE pr.payroll_period_id = ?
            GROUP BY pr.id
            ORDER BY pr.employee_name
        ");
        $stmt->execute([$periodId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get payroll period details
     */
    public function getPayrollPeriod($periodId) {
        $stmt = $this->db->prepare("
            SELECT pp.*, u.first_name, u.last_name
            FROM furn_payroll_periods pp
            LEFT JOIN furn_users u ON pp.generated_by = u.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$periodId]);
        return $stmt->fetch();
    }
    
    /**
     * Get all payroll periods
     */
    public function getPayrollPeriods() {
        $stmt = $this->db->prepare("
            SELECT pp.*, u.first_name, u.last_name
            FROM furn_payroll_periods pp
            LEFT JOIN furn_users u ON pp.generated_by = u.id
            ORDER BY pp.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Create new payroll period
     */
    public function createPayrollPeriod($periodData) {
        $stmt = $this->db->prepare("
            INSERT INTO furn_payroll_periods 
            (period_name, start_date, end_date, generated_by, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $periodData['period_name'],
            $periodData['start_date'],
            $periodData['end_date'],
            $periodData['generated_by']
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Get employee payroll history
     */
    public function getEmployeePayrollHistory($employeeId, $limit = 12) {
        $stmt = $this->db->prepare("
            SELECT pr.*, pp.period_name, pp.start_date, pp.end_date
            FROM furn_payroll_records pr
            JOIN furn_payroll_periods pp ON pr.payroll_period_id = pp.id
            WHERE pr.employee_id = ?
            ORDER BY pp.end_date DESC
            LIMIT ?
        ");
        $stmt->execute([$employeeId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get payroll statistics
     */
    public function getPayrollStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_periods,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_periods,
                SUM(total_net_salary) as total_paid,
                AVG(total_net_salary) as average_payroll
            FROM furn_payroll_periods
            WHERE status = 'paid'
        ");
        $stmt->execute();
        return $stmt->fetch();
    }
}