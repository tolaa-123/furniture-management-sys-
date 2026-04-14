<?php
/**
 * Advanced Search & Filtering Service
 * Provides unified search across orders, products, materials, customers
 */
class SearchService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Search orders with filters
     * @param array $filters: search, status, date_from, date_to, min_amount, max_amount
     */
    public function searchOrders(array $filters = [], $limit = 100) {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
            $term     = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$term, $term, $term]);
        }
        if (!empty($filters['status'])) {
            $where[]  = "o.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = "o.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = "o.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['min_amount'])) {
            $where[]  = "o.total_amount >= ?";
            $params[] = $filters['min_amount'];
        }
        if (!empty($filters['max_amount'])) {
            $where[]  = "o.total_amount <= ?";
            $params[] = $filters['max_amount'];
        }

        $sql = "SELECT o.*, u.name AS customer_name, u.email AS customer_email
                FROM furn_orders o
                LEFT JOIN furn_users u ON o.customer_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.created_at DESC
                LIMIT " . (int)$limit;

        return $this->query($sql, $params);
    }

    /**
     * Search products with filters
     * @param array $filters: search, category, min_price, max_price
     */
    public function searchProducts(array $filters = [], $limit = 100) {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = "(product_name LIKE ? OR description LIKE ?)";
            $term     = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$term, $term]);
        }
        if (!empty($filters['category'])) {
            $where[]  = "category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['min_price'])) {
            $where[]  = "base_price >= ?";
            $params[] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $where[]  = "base_price <= ?";
            $params[] = $filters['max_price'];
        }

        $sql = "SELECT * FROM furn_products
                WHERE " . implode(' AND ', $where) . "
                ORDER BY name ASC
                LIMIT " . (int)$limit;

        return $this->query($sql, $params);
    }

    /**
     * Search materials with filters
     * @param array $filters: search, category, low_stock (bool)
     */
    public function searchMaterials(array $filters = [], $limit = 100) {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = "(material_name LIKE ? OR unit LIKE ?)";
            $term     = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$term, $term]);
        }
        if (!empty($filters['category'])) {
            $where[]  = "category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['low_stock'])) {
            $where[] = "quantity < reorder_level";
        }

        $sql = "SELECT * FROM furn_materials
                WHERE " . implode(' AND ', $where) . "
                ORDER BY material_name ASC
                LIMIT " . (int)$limit;

        return $this->query($sql, $params);
    }

    /**
     * Search customers
     * @param array $filters: search, date_from, date_to
     */
    public function searchCustomers(array $filters = [], $limit = 100) {
        $where  = ["role = 'customer'"];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $term     = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$term, $term, $term]);
        }
        if (!empty($filters['date_from'])) {
            $where[]  = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = "SELECT id, name, email, phone, created_at FROM furn_users
                WHERE " . implode(' AND ', $where) . "
                ORDER BY name ASC
                LIMIT " . (int)$limit;

        return $this->query($sql, $params);
    }

    /**
     * Global search across orders, products, customers
     */
    public function globalSearch($term, $limit = 10) {
        $t = '%' . $term . '%';
        return [
            'orders'    => $this->searchOrders(['search' => $term], $limit),
            'products'  => $this->searchProducts(['search' => $term], $limit),
            'customers' => $this->searchCustomers(['search' => $term], $limit),
        ];
    }

    // -------------------------------------------------------
    private function query($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SearchService query error: " . $e->getMessage());
            return [];
        }
    }
}
