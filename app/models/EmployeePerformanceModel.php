<?php
/**
 * Employee Performance Model
 * Calculates comprehensive performance scores for employees
 */
require_once dirname(dirname(__DIR__)) . '/core/BaseModel.php';

class EmployeePerformanceModel extends BaseModel {
    
    /**
     * Calculate performance score for a single employee (0-100)
     * 
     * Scoring breakdown:
     * - Task Completion Rate: 30%
     * - Attendance Rate: 25%
     * - Customer Rating: 25%
     * - On-Time Delivery: 10%
     * - Low Waste: 10%
     */
    public function calculateEmployeeScore($employeeId, $months = 3) {
        try {
            $score = 0;
            $details = [];
            
            // 1. Task Completion Rate (30 points)
            $taskScore = $this->calculateTaskCompletionScore($employeeId, $months);
            $score += $taskScore['score'];
            $details['task_completion'] = $taskScore;
            
            // 2. Attendance Rate (25 points)
            $attendanceScore = $this->calculateAttendanceScore($employeeId, $months);
            $score += $attendanceScore['score'];
            $details['attendance'] = $attendanceScore;
            
            // 3. Customer Rating (25 points)
            $ratingScore = $this->calculateRatingScore($employeeId, $months);
            $score += $ratingScore['score'];
            $details['customer_rating'] = $ratingScore;
            
            // 4. On-Time Delivery (10 points)
            $timelinessScore = $this->calculateTimelinessScore($employeeId, $months);
            $score += $timelinessScore['score'];
            $details['on_time_delivery'] = $timelinessScore;
            
            // 5. Low Waste (10 points)
            $wasteScore = $this->calculateWasteScore($employeeId, $months);
            $score += $wasteScore['score'];
            $details['waste_management'] = $wasteScore;
            
            return [
                'total_score' => round($score, 1),
                'details' => $details,
                'grade' => $this->getGrade($score),
                'color' => $this->getScoreColor($score),
                'status' => $this->getPerformanceStatus($score)
            ];
            
        } catch (Exception $e) {
            error_log('Error calculating employee score: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all employees with performance scores
     */
    public function getAllEmployeeScores($months = 3) {
        try {
            // Get all active employees
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email, role
                FROM furn_users
                WHERE role = 'employee'
                ORDER BY first_name, last_name
            ");
            $stmt->execute();
            $employees = $stmt->fetchAll();
            
            $results = [];
            foreach ($employees as $emp) {
                $scoreData = $this->calculateEmployeeScore($emp['id'], $months);
                if ($scoreData) {
                    $results[] = array_merge($emp, $scoreData);
                }
            }
            
            // Sort by score descending
            usort($results, function($a, $b) {
                return $b['total_score'] - $a['total_score'];
            });
            
            return $results;
            
        } catch (Exception $e) {
            error_log('Error getting all employee scores: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Task Completion Score (30 points max)
     */
    private function calculateTaskCompletionScore($employeeId, $months) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM furn_production_tasks
            WHERE employee_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        ");
        $stmt->execute([$employeeId, $months]);
        $data = $stmt->fetch();
        
        $total = intval($data['total_tasks']);
        $completed = intval($data['completed']);
        
        if ($total == 0) {
            return ['score' => 0, 'rate' => 0, 'completed' => 0, 'total' => 0];
        }
        
        $completionRate = ($completed / $total) * 100;
        $score = ($completionRate / 100) * 30;
        
        return [
            'score' => $score,
            'rate' => round($completionRate, 1),
            'completed' => $completed,
            'total' => $total,
            'max_points' => 30
        ];
    }
    
    /**
     * Attendance Score (25 points max)
     */
    private function calculateAttendanceScore($employeeId, $months) {
        try {
            // Check if date column exists
            $cols = $this->db->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
            $dateExpr = in_array('date', $cols) ? 'date' : 'DATE(check_in_time)';
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
                FROM furn_attendance
                WHERE employee_id = ?
                AND $dateExpr >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            ");
            $stmt->execute([$employeeId, $months]);
            $data = $stmt->fetch();
            
            $total = intval($data['total_days']);
            $present = intval($data['present_days']);
            
            if ($total == 0) {
                return ['score' => 0, 'rate' => 0, 'present' => 0, 'total' => 0];
            }
            
            $attendanceRate = ($present / $total) * 100;
            $score = ($attendanceRate / 100) * 25;
            
            return [
                'score' => $score,
                'rate' => round($attendanceRate, 1),
                'present' => $present,
                'total' => $total,
                'absent' => intval($data['absent_days']),
                'late' => intval($data['late_days']),
                'max_points' => 25
            ];
        } catch (Exception $e) {
            return ['score' => 0, 'rate' => 0, 'present' => 0, 'total' => 0, 'max_points' => 25];
        }
    }
    
    /**
     * Customer Rating Score (25 points max)
     */
    private function calculateRatingScore($employeeId, $months) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_ratings,
                AVG(rating) as avg_rating
            FROM furn_ratings
            WHERE employee_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        ");
        $stmt->execute([$employeeId, $months]);
        $data = $stmt->fetch();
        
        $totalRatings = intval($data['total_ratings']);
        $avgRating = floatval($data['avg_rating']);
        
        if ($totalRatings == 0 || $avgRating == 0) {
            return ['score' => 0, 'avg_rating' => 0, 'total_ratings' => 0];
        }
        
        // Convert 5-star rating to percentage, then to score
        $ratingPercentage = ($avgRating / 5) * 100;
        $score = ($ratingPercentage / 100) * 25;
        
        return [
            'score' => $score,
            'avg_rating' => round($avgRating, 1),
            'total_ratings' => $totalRatings,
            'max_points' => 25
        ];
    }
    
    /**
     * On-Time Delivery Score (10 points max)
     */
    private function calculateTimelinessScore($employeeId, $months) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_completed,
                SUM(CASE WHEN completed_at <= deadline THEN 1 ELSE 0 END) as on_time
            FROM furn_production_tasks
            WHERE employee_id = ?
            AND status = 'completed'
            AND completed_at IS NOT NULL
            AND deadline IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        ");
        $stmt->execute([$employeeId, $months]);
        $data = $stmt->fetch();
        
        $total = intval($data['total_completed']);
        $onTime = intval($data['on_time']);
        
        if ($total == 0) {
            return ['score' => 0, 'rate' => 0, 'on_time' => 0, 'total' => 0];
        }
        
        $onTimeRate = ($onTime / $total) * 100;
        $score = ($onTimeRate / 100) * 10;
        
        return [
            'score' => $score,
            'rate' => round($onTimeRate, 1),
            'on_time' => $onTime,
            'total' => $total,
            'max_points' => 10
        ];
    }
    
    /**
     * Waste Management Score (10 points max)
     */
    private function calculateWasteScore($employeeId, $months) {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(mu.quantity_used), 0) as total_used,
                COALESCE(SUM(mu.waste_amount), 0) as total_waste
            FROM furn_material_usage mu
            WHERE mu.employee_id = ?
            AND mu.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        ");
        $stmt->execute([$employeeId, $months]);
        $data = $stmt->fetch();
        
        $totalUsed = floatval($data['total_used']);
        $totalWaste = floatval($data['total_waste']);
        
        if ($totalUsed == 0) {
            return ['score' => 10, 'waste_rate' => 0, 'waste' => 0, 'used' => 0];
        }
        
        $wasteRate = ($totalWaste / $totalUsed) * 100;
        
        // Lower waste = higher score
        // 0% waste = 10 points, 20%+ waste = 0 points
        $score = max(0, 10 - ($wasteRate / 2));
        
        return [
            'score' => $score,
            'rate' => round($wasteRate, 1),
            'waste' => round($totalWaste, 2),
            'used' => round($totalUsed, 2),
            'max_points' => 10
        ];
    }
    
    /**
     * Get grade based on score
     */
    private function getGrade($score) {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'A-';
        if ($score >= 75) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 65) return 'B-';
        if ($score >= 60) return 'C+';
        if ($score >= 55) return 'C';
        if ($score >= 50) return 'C-';
        if ($score >= 40) return 'D';
        return 'F';
    }
    
    /**
     * Get color based on score
     */
    private function getScoreColor($score) {
        if ($score >= 80) return '#27ae60'; // Green
        if ($score >= 60) return '#f39c12'; // Yellow
        return '#e74c3c'; // Red
    }
    
    /**
     * Get performance status
     */
    private function getPerformanceStatus($score) {
        if ($score >= 80) return 'Excellent';
        if ($score >= 60) return 'Good';
        if ($score >= 40) return 'Needs Improvement';
        return 'Poor';
    }
}
