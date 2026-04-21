<?php
/**
 * Analytics Model
 * Handles data aggregation, metrics calculation, and dashboard analytics
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class AnalyticsModel extends BaseModel {
                /**
                 * Get employee productivity data (orders completed, avg completion time)
                 */
                public function getEmployeeProductivityData($limit = 10, $months = 6) {
                    $cacheKey = "employee_productivity_{$limit}_{$months}";
                    $cachedData = $this->getCachedData($cacheKey);
                    if ($cachedData) {
                        return json_decode($cachedData, true);
                    }

                    // Use furn_production_tasks (employee_id, completed_at, created_at)
                    try {
                        $stmt = $this->db->prepare("
                            SELECT 
                                u.id as employee_id,
                                CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                COUNT(t.id) as completed_orders,
                                ROUND(AVG(DATEDIFF(t.completed_at, t.created_at)), 1) as avg_completion_days
                            FROM furn_users u
                            JOIN furn_production_tasks t ON u.id = t.employee_id
                            WHERE t.status = 'completed'
                                AND t.completed_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                            GROUP BY u.id, u.first_name, u.last_name
                            ORDER BY completed_orders DESC, avg_completion_days ASC
                            LIMIT ?
                        ");
                        $stmt->execute([$months, $limit]);
                        $data = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        error_log("getEmployeeProductivityData error: " . $e->getMessage());
                        $data = [];
                    }

                    $result = [
                        'labels' => array_column($data, 'employee_name'),
                        'datasets' => [
                            [
                                'label' => 'Orders Completed',
                                'data' => array_column($data, 'completed_orders'),
                                'backgroundColor' => 'rgba(75, 192, 192, 0.6)',
                                'borderColor' => 'rgba(75, 192, 192, 1)',
                                'borderWidth' => 1
                            ],
                            [
                                'label' => 'Avg Completion Days',
                                'data' => array_map(function($v) { return round($v, 2); }, array_column($data, 'avg_completion_days')),
                                'backgroundColor' => 'rgba(255, 206, 86, 0.6)',
                                'borderColor' => 'rgba(255, 206, 86, 1)',
                                'borderWidth' => 1,
                                'yAxisID' => 'y1'
                            ]
                        ],
                        'detailed_data' => $data
                    ];

                    $this->cacheData($cacheKey, json_encode($result), 'chart_data', 900);
                    return $result;
                }
            /**
             * Get material usage trends (monthly consumption per material)
             */
            public function getMaterialUsageTrends($months = 12, $limit = 10) {
                $cacheKey = "material_usage_{$months}_{$limit}";
                $cachedData = $this->getCachedData($cacheKey);
                if ($cachedData) {
                    return json_decode($cachedData, true);
                }

                // Fixed: use furn_material_usage table with created_at column
                try {
                    $stmt = $this->db->prepare("
                        SELECT 
                            m.id as material_id,
                            m.name as material_name,
                            DATE_FORMAT(mu.created_at, '%Y-%m') as month,
                            SUM(mu.quantity_used) as total_used
                        FROM furn_material_usage mu
                        JOIN furn_materials m ON mu.material_id = m.id
                        WHERE mu.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                        GROUP BY m.id, m.name, DATE_FORMAT(mu.created_at, '%Y-%m')
                        ORDER BY m.id, month
                    ");
                    $stmt->execute([$months]);
                    $data = $stmt->fetchAll();
                } catch (PDOException $e) {
                    error_log("getMaterialUsageTrends error: " . $e->getMessage());
                    $data = [];
                }

                // Pivot data for Chart.js (labels: months, datasets: materials)
                $monthsArr = [];
                $materials = [];
                foreach ($data as $row) {
                    if (!in_array($row['month'], $monthsArr)) {
                        $monthsArr[] = $row['month'];
                    }
                    $materials[$row['material_id']]['name'] = $row['material_name'];
                    $materials[$row['material_id']]['data'][$row['month']] = (float)$row['total_used'];
                }

                // Limit to top N materials by total usage
                $totals = [];
                foreach ($materials as $id => $mat) {
                    $totals[$id] = array_sum($mat['data']);
                }
                arsort($totals);
                $topIds = array_slice(array_keys($totals), 0, $limit, true);

                $datasets = [];
                $colors = [
                    'rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)', 'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)', 'rgba(153, 102, 255, 0.6)', 'rgba(255, 159, 64, 0.6)',
                    'rgba(199, 199, 199, 0.6)', 'rgba(83, 102, 255, 0.6)', 'rgba(255, 102, 255, 0.6)',
                    'rgba(102, 255, 204, 0.6)'
                ];
                $colorIdx = 0;
                foreach ($topIds as $id) {
                    $mat = $materials[$id];
                    $datasets[] = [
                        'label' => $mat['name'],
                        'data' => array_map(function($month) use ($mat) {
                            return $mat['data'][$month] ?? 0;
                        }, $monthsArr),
                        'backgroundColor' => $colors[$colorIdx % count($colors)],
                        'borderColor' => $colors[$colorIdx % count($colors)],
                        'borderWidth' => 2,
                        'fill' => false
                    ];
                    $colorIdx++;
                }

                $result = [
                    'labels' => $monthsArr,
                    'datasets' => $datasets,
                    'detailed_data' => $data
                ];

                $this->cacheData($cacheKey, json_encode($result), 'chart_data', 900);
                return $result;
            }
        /**
         * Get top customers by total revenue
         */
        public function getTopCustomers($limit = 10, $months = 12) {
            $cacheKey = "top_customers_{$limit}_{$months}";
            $cachedData = $this->getCachedData($cacheKey);
            if ($cachedData) {
                return json_decode($cachedData, true);
            }

            // Customers are in furn_users with role='customer'
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        u.id as customer_id,
                        CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                        u.email,
                        u.phone,
                        COUNT(o.id) as orders_count,
                        SUM(COALESCE(o.total_amount, o.estimated_cost, 0)) as total_revenue
                    FROM furn_users u
                    JOIN furn_orders o ON u.id = o.customer_id
                    WHERE u.role = 'customer'
                        AND o.status IN ('completed', 'ready_for_delivery')
                        AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                    GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone
                    ORDER BY total_revenue DESC
                    LIMIT ?
                ");
                $stmt->execute([$months, $limit]);
                $data = $stmt->fetchAll();
            } catch (PDOException $e) {
                error_log("getTopCustomers error: " . $e->getMessage());
                $data = [];
            }

            $result = [
                'labels' => array_column($data, 'customer_name'),
                'datasets' => [
                    [
                        'label' => 'Revenue (ETB)',
                        'data' => array_column($data, 'total_revenue'),
                        'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 1
                    ],
                    [
                        'label' => 'Orders Count',
                        'data' => array_column($data, 'orders_count'),
                        'backgroundColor' => 'rgba(255, 206, 86, 0.6)',
                        'borderColor' => 'rgba(255, 206, 86, 1)',
                        'borderWidth' => 1,
                        'yAxisID' => 'y1'
                    ]
                ],
                'detailed_data' => $data
            ];

            $this->cacheData($cacheKey, json_encode($result), 'chart_data', 900);
            return $result;
        }
    protected $table = 'furn_analytics_cache';
    
    /**
     * Get monthly revenue data for chart
     */
    public function getMonthlyRevenueData($months = 12) {
        $cacheKey = "monthly_revenue_{$months}";
        
        // Check cache first
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(total_amount) as revenue,
                COUNT(id) as order_count
            FROM furn_orders 
            WHERE status IN ('completed', 'ready_for_delivery', 'deposit_paid', 'in_production')
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
        ");
        $stmt->execute([$months]);
        $data = $stmt->fetchAll();
        
        // Format for Chart.js
        $result = [
            'labels' => array_column($data, 'month'),
            'datasets' => [
                [
                    'label' => 'Revenue (ETB)',
                    'data' => array_column($data, 'revenue'),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'tension' => 0.1
                ],
                [
                    'label' => 'Orders Count',
                    'data' => array_column($data, 'order_count'),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y1'
                ]
            ]
        ];
        
        // Cache for 5 minutes
        $this->cacheData($cacheKey, json_encode($result), 'chart_data', 300);
        
        return $result;
    }
    
    /**
     * Get orders by status for pie chart
     */
    public function getOrdersByStatusData() {
        $cacheKey = 'orders_by_status';
        
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as total_value
            FROM furn_orders
            GROUP BY status
            ORDER BY count DESC
        ");
        $stmt->execute();
        $data = $stmt->fetchAll();
        
        // Define colors for different statuses
        $colors = [
            'pending' => '#FF6384',
            'approved' => '#36A2EB',
            'in_production' => '#FFCE56',
            'completed' => '#4BC0C0',
            'delivered' => '#9966FF',
            'cancelled' => '#FF9F40',
            'default' => '#C9CBCF'
        ];
        
        $result = [
            'labels' => array_column($data, 'status'),
            'datasets' => [
                [
                    'data' => array_column($data, 'count'),
                    'backgroundColor' => array_map(function($status) use ($colors) {
                        return $colors[$status] ?? $colors['default'];
                    }, array_column($data, 'status')),
                    'borderColor' => '#fff',
                    'borderWidth' => 2
                ]
            ]
        ];
        
        $this->cacheData($cacheKey, json_encode($result), 'chart_data', 300);
        return $result;
    }
    
    /**
     * Get employee working hours summary
     */
    public function getEmployeeHoursData($limit = 10) {
        $cacheKey = "employee_hours_{$limit}";
        
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        // furn_attendance uses employee_id and check_in_time (no hours_worked column)
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                    COUNT(a.id) as days_worked,
                    COUNT(a.id) as total_hours,
                    ROUND(COUNT(a.id) / 30.0 * 8, 1) as avg_daily_hours
                FROM furn_users u
                JOIN furn_attendance a ON u.id = a.employee_id
                WHERE u.role = 'employee' AND a.status = 'present'
                    AND a.check_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY days_worked DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $data = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getEmployeeHoursData error: " . $e->getMessage());
            $data = [];
        }
        
        $result = [
            'labels' => array_column($data, 'employee_name'),
            'datasets' => [
                [
                    'label' => 'Total Hours',
                    'data' => array_column($data, 'total_hours'),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'Average Daily Hours',
                    'data' => array_column($data, 'avg_daily_hours'),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.6)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'borderWidth' => 1
                ]
            ]
        ];
        
        $this->cacheData($cacheKey, json_encode($result), 'chart_data', 600);
        return $result;
    }
    
    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts() {
        $cacheKey = 'low_stock_alerts';
        
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                name,
                current_stock,
                minimum_stock as min_stock_level,
                unit,
                CASE 
                    WHEN current_stock <= minimum_stock THEN 'critical'
                    WHEN current_stock <= (minimum_stock * 1.5) THEN 'warning'
                    ELSE 'normal'
                END as stock_status
            FROM furn_materials
            WHERE current_stock <= (minimum_stock * 2) AND is_active = 1
            ORDER BY current_stock ASC
            LIMIT 15
        ");
        $stmt->execute();
        $data = $stmt->fetchAll();
        
        // Group by status for doughnut chart
        $statusCounts = [];
        $statusColors = [
            'critical' => '#FF6384',
            'warning' => '#FFCE56',
            'normal' => '#4BC0C0'
        ];
        
        foreach ($data as $item) {
            $status = $item['stock_status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        
        $result = [
            'labels' => array_keys($statusCounts),
            'datasets' => [
                [
                    'data' => array_values($statusCounts),
                    'backgroundColor' => array_values($statusColors),
                    'borderColor' => '#fff',
                    'borderWidth' => 2
                ]
            ],
            'detailed_data' => $data
        ];
        
        $this->cacheData($cacheKey, json_encode($result), 'alert_data', 300);
        return $result;
    }
    
    /**
     * Get top selling products
     */
    public function getTopSellingProducts($limit = 10) {
        $cacheKey = "top_products_{$limit}";
        
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.name as product_name,
                    p.category,
                    COUNT(oc.id) as orders_count,
                    SUM(oc.quantity) as total_quantity,
                    SUM(o.total_amount) as total_revenue
                FROM furn_products p
                JOIN furn_order_customizations oc ON p.id = oc.product_id
                JOIN furn_orders o ON oc.order_id = o.id
                WHERE o.status IN ('completed', 'ready_for_delivery')
                    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY p.id, p.name, p.category
                ORDER BY total_revenue DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $data = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist or JOIN fails
            error_log("Top selling products query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        'Product' as product_name,
                        'N/A' as category,
                        COUNT(oc.id) as orders_count,
                        SUM(oc.quantity) as total_quantity,
                        SUM(o.total_amount) as total_revenue
                    FROM furn_order_customizations oc
                    JOIN furn_orders o ON oc.order_id = o.id
                    WHERE o.status IN ('completed', 'ready_for_delivery')
                        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY oc.product_id
                    ORDER BY total_revenue DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $data = $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Top selling products fallback error: " . $e2->getMessage());
                $data = [];
            }
        }
        
        $result = [
            'labels' => array_column($data, 'product_name'),
            'datasets' => [
                [
                    'label' => 'Revenue (ETB)',
                    'data' => array_column($data, 'total_revenue'),
                    'backgroundColor' => 'rgba(153, 102, 255, 0.6)',
                    'borderColor' => 'rgba(153, 102, 255, 1)',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'Quantity Sold',
                    'data' => array_column($data, 'total_quantity'),
                    'backgroundColor' => 'rgba(255, 159, 64, 0.6)',
                    'borderColor' => 'rgba(255, 159, 64, 1)',
                    'borderWidth' => 1,
                    'yAxisID' => 'y1'
                ]
            ],
            'detailed_data' => $data
        ];
        
        $this->cacheData($cacheKey, json_encode($result), 'chart_data', 900);
        return $result;
    }
    
    /**
     * Get monthly profit data
     */
    public function getMonthlyProfitData($months = 12) {
        $cacheKey = "monthly_profit_{$months}";
        
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(calculated_at, '%Y-%m') as month,
                SUM(final_selling_price) as total_revenue,
                SUM(total_cost) as total_cost,
                SUM(profit) as total_profit,
                AVG(profit_margin_percentage) as avg_margin
            FROM furn_profit_calculations
            WHERE calculated_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(calculated_at, '%Y-%m')
            ORDER BY DATE_FORMAT(calculated_at, '%Y-%m') ASC
        ");
        $stmt->execute([$months]);
        $data = $stmt->fetchAll();
        
        $result = [
            'labels' => array_column($data, 'month'),
            'datasets' => [
                [
                    'label' => 'Revenue (ETB)',
                    'data' => array_column($data, 'total_revenue'),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'tension' => 0.1
                ],
                [
                    'label' => 'Profit (ETB)',
                    'data' => array_column($data, 'total_profit'),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'tension' => 0.1
                ],
                [
                    'label' => 'Profit Margin %',
                    'data' => array_column($data, 'avg_margin'),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y1'
                ]
            ]
        ];
        
        $this->cacheData($cacheKey, json_encode($result), 'chart_data', 600);
        return $result;
    }
    
    /**
     * Get dashboard summary statistics
     */
    public function getDashboardStats() {
        $cacheKey = 'dashboard_stats';
        
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        // Get various statistics
        $stats = [];
        
        // Total orders
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM furn_orders");
        $stmt->execute();
        $stats['total_orders'] = $stmt->fetch()['total'];
        
        // Pending orders
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM furn_orders WHERE status = 'pending'");
        $stmt->execute();
        $stats['pending_orders'] = $stmt->fetch()['total'];
        
        // This month revenue
        $stmt = $this->db->prepare("
            SELECT SUM(total_amount) as revenue 
            FROM furn_orders 
            WHERE status IN ('completed', 'ready_for_delivery', 'deposit_paid', 'in_production')
                AND MONTH(created_at) = MONTH(NOW()) 
                AND YEAR(created_at) = YEAR(NOW())
        ");
        $stmt->execute();
        $stats['this_month_revenue'] = $stmt->fetch()['revenue'] ?? 0;
        
        // Active employees
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM furn_users WHERE role = 'employee' AND is_active = 1");
        $stmt->execute();
        $stats['active_employees'] = $stmt->fetch()['total'];
        
        // Low stock items
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM furn_materials WHERE current_stock <= minimum_stock AND is_active = 1");
        $stmt->execute();
        $stats['low_stock_items'] = $stmt->fetch()['total'];
        
        // This month profit
        $stmt = $this->db->prepare("
            SELECT SUM(profit) as total_profit 
            FROM furn_profit_calculations 
            WHERE MONTH(calculated_at) = MONTH(NOW()) 
                AND YEAR(calculated_at) = YEAR(NOW())
        ");
        $stmt->execute();
        $stats['this_month_profit'] = $stmt->fetch()['total_profit'] ?? 0;
        
        $this->cacheData($cacheKey, json_encode($stats), 'stats', 300);
        return $stats;
    }
    
    /**
     * Get cached data
     */
    private function getCachedData($cacheKey) {
        $stmt = $this->db->prepare("
            SELECT cache_data 
            FROM {$this->table} 
            WHERE cache_key = ? AND expires_at > NOW()
        ");
        $stmt->execute([$cacheKey]);
        $result = $stmt->fetch();
        return $result ? $result['cache_data'] : null;
    }
    
    /**
     * Cache data
     */
    private function cacheData($cacheKey, $data, $dataType, $ttl = 300) {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (cache_key, cache_data, data_type, expires_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                cache_data = VALUES(cache_data),
                data_type = VALUES(data_type),
                expires_at = VALUES(expires_at),
                updated_at = NOW()
        ");
        $stmt->execute([$cacheKey, $data, $dataType, $expiresAt]);
    }
    
    /**
     * Get weekly orders data (last N weeks)
     */
    public function getWeeklyOrdersData($weeks = 12) {
        $cacheKey = "weekly_orders_{$weeks}";
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData) {
            return json_decode($cachedData, true);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    YEARWEEK(created_at, 1) as yw,
                    DATE_FORMAT(MIN(created_at), 'W%v %Y') as week_label,
                    COUNT(*) as order_count,
                    SUM(CASE WHEN status IN ('completed','ready_for_delivery','deposit_paid') THEN COALESCE(total_amount,0) ELSE 0 END) as revenue
                FROM furn_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
                GROUP BY YEARWEEK(created_at, 1)
                ORDER BY yw ASC
            ");
            $stmt->execute([$weeks]);
            $data = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getWeeklyOrdersData error: " . $e->getMessage());
            $data = [];
        }

        $result = [
            'labels' => array_column($data, 'week_label'),
            'datasets' => [
                [
                    'label' => 'Orders per Week',
                    'data' => array_column($data, 'order_count'),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 2,
                    'tension' => 0.1
                ],
                [
                    'label' => 'Revenue (ETB)',
                    'data' => array_column($data, 'revenue'),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.3)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'borderWidth' => 2,
                    'tension' => 0.1,
                    'yAxisID' => 'y1'
                ]
            ],
            'detailed_data' => $data
        ];

        $this->cacheData($cacheKey, json_encode($result), 'chart_data', 600);
        return $result;
    }

    /**
     * Clear expired cache
     */
    public function clearExpiredCache() {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE expires_at < NOW()");
        return $stmt->execute();
    }
}