<?php
/**
 * Analytics Model — fully corrected
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class AnalyticsModel extends BaseModel {

    protected $table = 'furn_analytics_cache';

    // ── ensure cache table exists ──────────────────────────────────────────
    private function ensureCacheTable() {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS furn_analytics_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(100) NOT NULL UNIQUE,
                cache_data LONGTEXT NOT NULL,
                data_type VARCHAR(50) DEFAULT 'chart_data',
                expires_at DATETIME NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX(cache_key), INDEX(expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) { /* already exists */ }
    }

    // ── cache helpers ──────────────────────────────────────────────────────
    private function getCachedData($key) {
        try {
            $this->ensureCacheTable();
            $s = $this->db->prepare("SELECT cache_data FROM {$this->table} WHERE cache_key=? AND expires_at>NOW()");
            $s->execute([$key]);
            $r = $s->fetch();
            return $r ? $r['cache_data'] : null;
        } catch (PDOException $e) { return null; }
    }

    private function cacheData($key, $data, $type = 'chart_data', $ttl = 300) {
        try {
            $this->ensureCacheTable();
            $exp = date('Y-m-d H:i:s', time() + $ttl);
            $s = $this->db->prepare("INSERT INTO {$this->table} (cache_key,cache_data,data_type,expires_at)
                VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE cache_data=VALUES(cache_data),
                data_type=VALUES(data_type),expires_at=VALUES(expires_at),updated_at=NOW()");
            $s->execute([$key, $data, $type, $exp]);
        } catch (PDOException $e) { /* cache write failed — non-fatal */ }
    }

    // ── Dashboard Stats ────────────────────────────────────────────────────
    public function getDashboardStats() {
        $cached = $this->getCachedData('dashboard_stats');
        if ($cached) return json_decode($cached, true);

        $stats = [];

        // Total orders
        $s = $this->db->query("SELECT COUNT(*) FROM furn_orders");
        $stats['total_orders'] = (int)$s->fetchColumn();

        // Pending orders
        $s = $this->db->query("SELECT COUNT(*) FROM furn_orders
            WHERE status IN ('pending','pending_review','pending_cost_approval')");
        $stats['pending_orders'] = (int)$s->fetchColumn();

        // This month revenue
        $s = $this->db->query("SELECT COALESCE(SUM(COALESCE(NULLIF(total_amount,0),estimated_cost,0)),0)
            FROM furn_orders
            WHERE status NOT IN ('cancelled','pending_review','pending_cost_approval','pending')
              AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
        $stats['this_month_revenue'] = (float)$s->fetchColumn();

        // Active employees — handle missing is_active column
        try {
            $s = $this->db->query("SELECT COUNT(*) FROM furn_users WHERE role='employee' AND (is_active IS NULL OR is_active=1)");
        } catch (PDOException $e) {
            $s = $this->db->query("SELECT COUNT(*) FROM furn_users WHERE role='employee'");
        }
        $stats['active_employees'] = (int)$s->fetchColumn();

        // Low stock items
        try {
            $s = $this->db->query("SELECT COUNT(*) FROM furn_materials
                WHERE current_stock <= minimum_stock AND (is_active IS NULL OR is_active=1)");
            $stats['low_stock_items'] = (int)$s->fetchColumn();
        } catch (PDOException $e) {
            $stats['low_stock_items'] = 0;
        }

        // This month profit
        try {
            $s = $this->db->query("SELECT COALESCE(SUM(profit),0) FROM furn_profit_calculations
                WHERE MONTH(calculated_at)=MONTH(NOW()) AND YEAR(calculated_at)=YEAR(NOW())");
            $profit = (float)$s->fetchColumn();
            if ($profit == 0) {
                $s = $this->db->query("SELECT COALESCE(SUM(COALESCE(NULLIF(total_amount,0),estimated_cost,0)),0)*0.35
                    FROM furn_orders
                    WHERE status NOT IN ('cancelled','pending_review','pending_cost_approval','pending')
                      AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
                $profit = (float)$s->fetchColumn();
            }
            $stats['this_month_profit'] = $profit;
        } catch (PDOException $e) {
            $stats['this_month_profit'] = 0;
        }

        $this->cacheData('dashboard_stats', json_encode($stats), 'stats', 300);
        return $stats;
    }

    // ── Weekly Orders & Revenue ────────────────────────────────────────────
    public function getWeeklyOrdersData($weeks = 12) {
        $key = "weekly_orders_{$weeks}";
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        try {
            $s = $this->db->prepare("
                SELECT
                    YEARWEEK(created_at,1) as yw,
                    CONCAT('W', LPAD(WEEK(MIN(created_at),1),2,'0'), ' ', YEAR(MIN(created_at))) as week_label,
                    COUNT(*) as order_count,
                    COALESCE(SUM(CASE WHEN status IN ('completed','ready_for_delivery','deposit_paid','payment_verified','fully_paid')
                        THEN COALESCE(NULLIF(total_amount,0),estimated_cost,0) ELSE 0 END),0) as revenue
                FROM furn_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
                GROUP BY YEARWEEK(created_at,1)
                ORDER BY yw ASC
            ");
            $s->execute([$weeks]);
            $data = $s->fetchAll();
        } catch (PDOException $e) {
            error_log("getWeeklyOrdersData: " . $e->getMessage());
            $data = [];
        }

        $result = [
            'labels'   => array_column($data, 'week_label'),
            'datasets' => [
                ['label'=>'Orders','data'=>array_column($data,'order_count'),
                 'backgroundColor'=>'rgba(54,162,235,0.6)','borderColor'=>'rgba(54,162,235,1)','borderWidth'=>2,'tension'=>0.1],
                ['label'=>'Revenue (ETB)','data'=>array_column($data,'revenue'),
                 'backgroundColor'=>'rgba(75,192,192,0.3)','borderColor'=>'rgba(75,192,192,1)','borderWidth'=>2,'tension'=>0.1,'yAxisID'=>'y1']
            ]
        ];
        $this->cacheData($key, json_encode($result), 'chart_data', 600);
        return $result;
    }

    // ── Orders by Status ──────────────────────────────────────────────────
    public function getOrdersByStatusData() {
        $key = 'orders_by_status';
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        $s = $this->db->query("SELECT status, COUNT(*) as cnt FROM furn_orders GROUP BY status ORDER BY cnt DESC");
        $data = $s->fetchAll();

        // Dynamic color map covering all real statuses
        $colorMap = [
            'pending'                  => '#F39C12',
            'pending_review'           => '#E67E22',
            'pending_cost_approval'    => '#D35400',
            'cost_estimated'           => '#3498DB',
            'awaiting_deposit'         => '#2980B9',
            'deposit_paid'             => '#1ABC9C',
            'payment_verified'         => '#16A085',
            'in_production'            => '#9B59B6',
            'production_started'       => '#8E44AD',
            'awaiting_final_payment'   => '#F1C40F',
            'fully_paid'               => '#27AE60',
            'ready_for_delivery'       => '#2ECC71',
            'completed'                => '#1E8449',
            'cancelled'                => '#E74C3C',
        ];
        $defaultColors = ['#3498DB','#E74C3C','#F39C12','#27AE60','#9B59B6','#1ABC9C','#E67E22','#2C3E50'];
        $di = 0;

        $bgColors = [];
        foreach ($data as $row) {
            $bgColors[] = $colorMap[$row['status']] ?? $defaultColors[$di++ % count($defaultColors)];
        }

        $result = [
            'labels'   => array_map(fn($r) => ucwords(str_replace('_',' ',$r['status'])), $data),
            'datasets' => [['data'=>array_column($data,'cnt'),'backgroundColor'=>$bgColors,'borderColor'=>'#fff','borderWidth'=>2]]
        ];
        $this->cacheData($key, json_encode($result), 'chart_data', 300);
        return $result;
    }

    // ── Low Stock Alerts ──────────────────────────────────────────────────
    public function getLowStockAlerts() {
        $key = 'low_stock_alerts';
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        try {
            $s = $this->db->query("
                SELECT name, current_stock, minimum_stock as min_stock_level, unit,
                    CASE
                        WHEN current_stock = 0                          THEN 'out_of_stock'
                        WHEN current_stock <= minimum_stock             THEN 'critical'
                        WHEN current_stock <= (minimum_stock * 1.5)    THEN 'warning'
                        ELSE 'normal'
                    END as stock_status
                FROM furn_materials
                WHERE current_stock <= (minimum_stock * 2) AND (is_active IS NULL OR is_active=1)
                ORDER BY current_stock ASC
                LIMIT 20
            ");
            $data = $s->fetchAll();
        } catch (PDOException $e) {
            $data = [];
        }

        $statusCounts = ['out_of_stock'=>0,'critical'=>0,'warning'=>0,'normal'=>0];
        foreach ($data as $item) $statusCounts[$item['stock_status']]++;
        // Remove zero-count statuses
        $statusCounts = array_filter($statusCounts);

        $colorMap = ['out_of_stock'=>'#C0392B','critical'=>'#E74C3C','warning'=>'#F39C12','normal'=>'#27AE60'];

        $result = [
            'labels'       => array_map(fn($k) => ucwords(str_replace('_',' ',$k)), array_keys($statusCounts)),
            'datasets'     => [['data'=>array_values($statusCounts),
                                'backgroundColor'=>array_values(array_intersect_key($colorMap, $statusCounts)),
                                'borderColor'=>'#fff','borderWidth'=>2]],
            'detailed_data'=> $data
        ];
        $this->cacheData($key, json_encode($result), 'alert_data', 300);
        return $result;
    }

    // ── Monthly Revenue ───────────────────────────────────────────────────
    public function getMonthlyRevenueData($months = 12) {
        $key = "monthly_revenue_{$months}";
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        $s = $this->db->prepare("
            SELECT DATE_FORMAT(created_at,'%Y-%m') as month,
                   COALESCE(SUM(COALESCE(NULLIF(total_amount,0),estimated_cost,0)),0) as revenue,
                   COUNT(id) as order_count
            FROM furn_orders
            WHERE status NOT IN ('cancelled','pending_review','pending_cost_approval','pending')
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at,'%Y-%m')
            ORDER BY month ASC
        ");
        $s->execute([$months]);
        $data = $s->fetchAll();

        $result = [
            'labels'   => array_column($data,'month'),
            'datasets' => [
                ['label'=>'Revenue (ETB)','data'=>array_column($data,'revenue'),
                 'borderColor'=>'rgb(75,192,192)','backgroundColor'=>'rgba(75,192,192,0.2)','tension'=>0.1],
                ['label'=>'Orders Count','data'=>array_column($data,'order_count'),
                 'borderColor'=>'rgb(255,99,132)','backgroundColor'=>'rgba(255,99,132,0.2)','yAxisID'=>'y1']
            ]
        ];
        $this->cacheData($key, json_encode($result), 'chart_data', 300);
        return $result;
    }

    // ── Monthly Profit ────────────────────────────────────────────────────
    public function getMonthlyProfitData($months = 12) {
        $key = "monthly_profit_{$months}";
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        $hasProfit = false;
        try {
            $s = $this->db->query("SELECT COUNT(*) FROM furn_profit_calculations");
            $hasProfit = (int)$s->fetchColumn() > 0;
        } catch (PDOException $e) {}

        if ($hasProfit) {
            $s = $this->db->prepare("
                SELECT DATE_FORMAT(calculated_at,'%Y-%m') as month,
                       COALESCE(SUM(final_selling_price),0) as total_revenue,
                       COALESCE(SUM(profit),0) as total_profit,
                       COALESCE(AVG(profit_margin_percentage),0) as avg_margin
                FROM furn_profit_calculations
                WHERE calculated_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(calculated_at,'%Y-%m')
                ORDER BY month ASC
            ");
            $s->execute([$months]);
        } else {
            $s = $this->db->prepare("
                SELECT DATE_FORMAT(created_at,'%Y-%m') as month,
                       COALESCE(SUM(COALESCE(NULLIF(total_amount,0),estimated_cost,0)),0) as total_revenue,
                       COALESCE(SUM(COALESCE(NULLIF(total_amount,0),estimated_cost,0)),0)*0.35 as total_profit,
                       35 as avg_margin
                FROM furn_orders
                WHERE status NOT IN ('cancelled','pending_review','pending_cost_approval','pending')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at,'%Y-%m')
                ORDER BY month ASC
            ");
            $s->execute([$months]);
        }
        $data = $s->fetchAll();

        $result = [
            'labels'   => array_column($data,'month'),
            'datasets' => [
                ['label'=>'Revenue (ETB)','data'=>array_column($data,'total_revenue'),
                 'borderColor'=>'rgb(75,192,192)','backgroundColor'=>'rgba(75,192,192,0.2)','tension'=>0.1],
                ['label'=>'Profit (ETB)','data'=>array_column($data,'total_profit'),
                 'borderColor'=>'rgb(54,162,235)','backgroundColor'=>'rgba(54,162,235,0.2)','tension'=>0.1],
                ['label'=>'Profit Margin %','data'=>array_column($data,'avg_margin'),
                 'borderColor'=>'rgb(255,99,132)','backgroundColor'=>'rgba(255,99,132,0.2)','yAxisID'=>'y1']
            ]
        ];
        $this->cacheData($key, json_encode($result), 'chart_data', 600);
        return $result;
    }

    // ── Top Selling Products ──────────────────────────────────────────────
    public function getTopSellingProducts($limit = 10) {
        $key = "top_products_{$limit}";
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        try {
            $s = $this->db->prepare("
                SELECT
                    COALESCE(NULLIF(TRIM(furniture_type),''), 'Custom') as product_name,
                    COUNT(id) as orders_count,
                    COALESCE(SUM(COALESCE(NULLIF(total_amount,0),estimated_cost,0)),0) as total_revenue
                FROM furn_orders
                WHERE status NOT IN ('cancelled','pending_review','pending_cost_approval','pending')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  AND furniture_type IS NOT NULL AND TRIM(furniture_type) != ''
                GROUP BY COALESCE(NULLIF(TRIM(furniture_type),''),'Custom')
                ORDER BY total_revenue DESC
                LIMIT ?
            ");
            $s->execute([$limit]);
            $data = $s->fetchAll();
        } catch (PDOException $e) {
            error_log("getTopSellingProducts: " . $e->getMessage());
            $data = [];
        }

        $result = [
            'labels'   => array_column($data,'product_name'),
            'datasets' => [
                ['label'=>'Revenue (ETB)','data'=>array_column($data,'total_revenue'),
                 'backgroundColor'=>'rgba(153,102,255,0.6)','borderColor'=>'rgba(153,102,255,1)','borderWidth'=>1],
                ['label'=>'Orders Count','data'=>array_column($data,'orders_count'),
                 'backgroundColor'=>'rgba(255,159,64,0.6)','borderColor'=>'rgba(255,159,64,1)','borderWidth'=>1,'yAxisID'=>'y1']
            ],
            'detailed_data' => $data
        ];
        $this->cacheData($key, json_encode($result), 'chart_data', 900);
        return $result;
    }

    // ── Top Customers ─────────────────────────────────────────────────────
    public function getTopCustomers($limit = 10, $months = 12) {
        $key = "top_customers_{$limit}_{$months}";
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        try {
            $s = $this->db->prepare("
                SELECT
                    CONCAT(u.first_name,' ',u.last_name) as customer_name,
                    COUNT(o.id) as orders_count,
                    COALESCE(SUM(COALESCE(NULLIF(o.total_amount,0),o.estimated_cost,0)),0) as total_revenue
                FROM furn_users u
                JOIN furn_orders o ON u.id = o.customer_id
                WHERE u.role = 'customer'
                  AND o.status NOT IN ('cancelled','pending_review','pending_cost_approval','pending')
                  AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY total_revenue DESC
                LIMIT ?
            ");
            $s->execute([$months, $limit]);
            $data = $s->fetchAll();
        } catch (PDOException $e) {
            error_log("getTopCustomers: " . $e->getMessage());
            $data = [];
        }

        $result = [
            'labels'   => array_column($data,'customer_name'),
            'datasets' => [
                ['label'=>'Revenue (ETB)','data'=>array_column($data,'total_revenue'),
                 'backgroundColor'=>'rgba(54,162,235,0.6)','borderColor'=>'rgba(54,162,235,1)','borderWidth'=>1],
                ['label'=>'Orders Count','data'=>array_column($data,'orders_count'),
                 'backgroundColor'=>'rgba(255,206,86,0.6)','borderColor'=>'rgba(255,206,86,1)','borderWidth'=>1,'yAxisID'=>'y1']
            ],
            'detailed_data' => $data
        ];
        $this->cacheData($key, json_encode($result), 'chart_data', 900);
        return $result;
    }

    // ── Employee Hours ────────────────────────────────────────────────────
    public function getEmployeeHoursData($limit = 10) {
        $key = "employee_hours_{$limit}";
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        try {
            // Try to use check_out_time for real hours, fall back to days_worked * 8
            $cols = $this->db->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
            $hasCheckOut = in_array('check_out_time', $cols);

            if ($hasCheckOut) {
                $s = $this->db->prepare("
                    SELECT CONCAT(u.first_name,' ',u.last_name) as employee_name,
                           COUNT(a.id) as days_worked,
                           ROUND(SUM(TIMESTAMPDIFF(HOUR, a.check_in_time, a.check_out_time)),1) as total_hours,
                           ROUND(AVG(TIMESTAMPDIFF(HOUR, a.check_in_time, a.check_out_time)),1) as avg_daily_hours
                    FROM furn_users u
                    JOIN furn_attendance a ON u.id = a.employee_id
                    WHERE u.role='employee' AND a.status IN ('present','late')
                      AND a.check_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      AND a.check_out_time IS NOT NULL
                    GROUP BY u.id, u.first_name, u.last_name
                    ORDER BY total_hours DESC
                    LIMIT ?
                ");
            } else {
                $s = $this->db->prepare("
                    SELECT CONCAT(u.first_name,' ',u.last_name) as employee_name,
                           COUNT(a.id) as days_worked,
                           COUNT(a.id)*8 as total_hours,
                           8 as avg_daily_hours
                    FROM furn_users u
                    JOIN furn_attendance a ON u.id = a.employee_id
                    WHERE u.role='employee' AND a.status IN ('present','late')
                      AND a.check_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY u.id, u.first_name, u.last_name
                    ORDER BY days_worked DESC
                    LIMIT ?
                ");
            }
            $s->execute([$limit]);
            $data = $s->fetchAll();
        } catch (PDOException $e) {
            error_log("getEmployeeHoursData: " . $e->getMessage());
            $data = [];
        }

        $result = [
            'labels'   => array_column($data,'employee_name'),
            'datasets' => [
                ['label'=>'Total Hours','data'=>array_column($data,'total_hours'),
                 'backgroundColor'=>'rgba(54,162,235,0.6)','borderColor'=>'rgba(54,162,235,1)','borderWidth'=>1],
                ['label'=>'Avg Daily Hours','data'=>array_column($data,'avg_daily_hours'),
                 'backgroundColor'=>'rgba(255,99,132,0.6)','borderColor'=>'rgba(255,99,132,1)','borderWidth'=>1]
            ]
        ];
        $this->cacheData($key, json_encode($result), 'chart_data', 600);
        return $result;
    }

    // ── Employee Productivity ─────────────────────────────────────────────
    public function getEmployeeProductivityData($limit = 10, $months = 6) {
        $key = "employee_productivity_{$limit}_{$months}";
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        try {
            $s = $this->db->prepare("
                SELECT
                    CONCAT(u.first_name,' ',u.last_name) as employee_name,
                    COUNT(t.id) as completed_orders,
                    ROUND(AVG(DATEDIFF(t.completed_at, t.created_at)),1) as avg_completion_days
                FROM furn_users u
                JOIN furn_production_tasks t ON u.id = t.employee_id
                WHERE t.status = 'completed'
                  AND t.completed_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY completed_orders DESC, avg_completion_days ASC
                LIMIT ?
            ");
            $s->execute([$months, $limit]);
            $data = $s->fetchAll();
        } catch (PDOException $e) {
            error_log("getEmployeeProductivityData: " . $e->getMessage());
            $data = [];
        }

        $result = [
            'labels'   => array_column($data,'employee_name'),
            'datasets' => [
                ['label'=>'Tasks Completed','data'=>array_column($data,'completed_orders'),
                 'backgroundColor'=>'rgba(75,192,192,0.6)','borderColor'=>'rgba(75,192,192,1)','borderWidth'=>1],
                ['label'=>'Avg Completion Days','data'=>array_column($data,'avg_completion_days'),
                 'backgroundColor'=>'rgba(255,206,86,0.6)','borderColor'=>'rgba(255,206,86,1)','borderWidth'=>1,'yAxisID'=>'y1']
            ],
            'detailed_data' => $data
        ];
        $this->cacheData($key, json_encode($result), 'chart_data', 900);
        return $result;
    }

    // ── Material Usage Trends ─────────────────────────────────────────────
    public function getMaterialUsageTrends($months = 12, $limit = 10) {
        $key = "material_usage_{$months}_{$limit}";
        $cached = $this->getCachedData($key);
        if ($cached) return json_decode($cached, true);

        try {
            $sAll = $this->db->query("SELECT id, name FROM furn_materials WHERE (is_active IS NULL OR is_active=1) ORDER BY name ASC");
            $allMaterials = $sAll->fetchAll();

            $s = $this->db->prepare("
                SELECT m.id as material_id, m.name as material_name,
                       DATE_FORMAT(mu.created_at,'%Y-%m') as month,
                       SUM(mu.quantity_used) as total_used
                FROM furn_material_usage mu
                JOIN furn_materials m ON mu.material_id = m.id
                WHERE mu.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY m.id, m.name, DATE_FORMAT(mu.created_at,'%Y-%m')
                ORDER BY m.id, month
            ");
            $s->execute([$months]);
            $data = $s->fetchAll();
        } catch (PDOException $e) {
            error_log("getMaterialUsageTrends: " . $e->getMessage());
            $data = [];
            $allMaterials = [];
        }

        // Only use months that actually have usage data — no empty months
        $monthsArr = [];
        foreach ($data as $row) {
            if (!in_array($row['month'], $monthsArr)) {
                $monthsArr[] = $row['month'];
            }
        }
        sort($monthsArr);

        // Pivot usage data per material
        $matData = [];
        foreach ($data as $row) {
            $matData[$row['material_id']]['name'] = $row['material_name'];
            $matData[$row['material_id']]['data'][$row['month']] = (float)$row['total_used'];
        }

        // Pick top N by total usage
        $totals = [];
        foreach ($matData as $id => $m) $totals[$id] = array_sum($m['data']);
        arsort($totals);
        $topIds = array_slice(array_keys($totals), 0, $limit, true);

        // If no usage data at all, show message-friendly empty state
        if (empty($monthsArr)) {
            $result = ['labels' => [], 'datasets' => [], 'detailed_data' => []];
            $this->cacheData($key, json_encode($result), 'chart_data', 300);
            return $result;
        }

        $colors = [
            'rgba(54,162,235,0.8)','rgba(255,99,132,0.8)','rgba(255,206,86,0.8)',
            'rgba(75,192,192,0.8)','rgba(153,102,255,0.8)','rgba(255,159,64,0.8)',
            'rgba(199,199,199,0.8)','rgba(83,102,255,0.8)','rgba(255,102,255,0.8)',
            'rgba(102,255,204,0.8)'
        ];

        $datasets = [];
        foreach (array_values($topIds) as $ci => $id) {
            $m = $matData[$id];
            $datasets[] = [
                'label'           => $m['name'],
                'data'            => array_map(fn($mo) => $m['data'][$mo] ?? 0, $monthsArr),
                'backgroundColor' => $colors[$ci % count($colors)],
                'borderColor'     => $colors[$ci % count($colors)],
                'borderWidth'     => 2,
                'fill'            => false,
                'tension'         => 0.3,
                'pointRadius'     => 5,
                'pointHoverRadius'=> 7
            ];
        }

        $result = ['labels' => $monthsArr, 'datasets' => $datasets, 'detailed_data' => $data];
        $this->cacheData($key, json_encode($result), 'chart_data', 900);
        return $result;
    }

    // ── Clear expired cache ───────────────────────────────────────────────
    public function clearExpiredCache() {
        try {
            $this->ensureCacheTable();
            $this->db->exec("DELETE FROM {$this->table} WHERE expires_at < NOW()");
        } catch (PDOException $e) {}
    }

    // ── Clear all cache (admin refresh) ──────────────────────────────────
    public function clearAllCache() {
        try {
            $this->ensureCacheTable();
            $this->db->exec("DELETE FROM {$this->table}");
        } catch (PDOException $e) {}
    }
}
