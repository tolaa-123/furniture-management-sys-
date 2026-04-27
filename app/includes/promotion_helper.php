<?php
/**
 * Promotion Helper Functions
 * Utility functions for handling promotions throughout the application
 */

require_once dirname(__DIR__) . '/models/PromotionModel.php';

/**
 * Get applicable promotion for a product/category
 * 
 * @param string $category Furniture category
 * @param int|null $customerId Customer ID (optional)
 * @return array|null Promotion data or null
 */
function getApplicablePromotion($category, $customerId = null) {
    try {
        $promotionModel = new PromotionModel();
        return $promotionModel->getPromotionForProduct($category, $customerId);
    } catch (Exception $e) {
        error_log("Get applicable promotion error: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate discounted price
 * 
 * @param float $originalPrice Original price
 * @param array $promotion Promotion data
 * @return array ['original' => float, 'discount' => float, 'final' => float]
 */
function calculateDiscountedPrice($originalPrice, $promotion) {
    if (!$promotion) {
        return [
            'original' => $originalPrice,
            'discount' => 0,
            'final' => $originalPrice
        ];
    }
    
    $discountAmount = 0;
    
    if ($promotion['discount_type'] === 'percentage') {
        $discountAmount = $originalPrice * ($promotion['discount_value'] / 100);
    } else {
        $discountAmount = $promotion['discount_value'];
    }
    
    // Apply max discount cap if set
    if (isset($promotion['max_discount_amount']) && $promotion['max_discount_amount'] > 0) {
        $discountAmount = min($discountAmount, $promotion['max_discount_amount']);
    }
    
    $finalPrice = $originalPrice - $discountAmount;
    
    return [
        'original' => $originalPrice,
        'discount' => $discountAmount,
        'final' => max(0, $finalPrice) // Ensure non-negative
    ];
}

/**
 * Format promotion badge HTML
 * 
 * @param array $promotion Promotion data
 * @return string HTML for promotion badge
 */
function formatPromotionBadge($promotion) {
    if (!$promotion || empty($promotion['badge_text'])) {
        return '';
    }
    
    $badgeText = htmlspecialchars($promotion['badge_text']);
    $badgeColor = '#E74C3C'; // Red for promotions
    
    return sprintf(
        '<span class="promotion-badge" style="background:%s;color:white;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;display:inline-block;margin-bottom:8px;">
            <i class="fas fa-tag"></i> %s
        </span>',
        $badgeColor,
        $badgeText
    );
}

/**
 * Format promotion banner HTML
 * 
 * @param array $promotion Promotion data
 * @return string HTML for promotion banner
 */
function formatPromotionBanner($promotion) {
    if (!$promotion || empty($promotion['banner_text'])) {
        return '';
    }
    
    $bannerText = htmlspecialchars($promotion['banner_text']);
    $daysRemaining = $promotion['days_remaining'] ?? 0;
    
    $urgencyClass = '';
    $urgencyText = '';
    
    if ($daysRemaining <= 3) {
        $urgencyClass = 'urgent';
        $urgencyText = sprintf('<span style="font-weight:700;">Ends in %d days!</span>', $daysRemaining);
    } elseif ($daysRemaining <= 7) {
        $urgencyText = sprintf('Ends in %d days', $daysRemaining);
    }
    
    return sprintf(
        '<div class="promotion-banner %s" style="background:linear-gradient(135deg,#E74C3C 0%%,#C0392B 100%%);color:white;padding:15px 20px;border-radius:10px;margin-bottom:20px;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <div style="flex:1;">
                    <div style="font-size:18px;font-weight:700;margin-bottom:5px;">%s</div>
                    %s
                </div>
                <div style="text-align:right;">
                    <i class="fas fa-clock" style="margin-right:5px;"></i>%s
                </div>
            </div>
        </div>',
        $urgencyClass,
        $bannerText,
        $promotion['description'] ? '<div style="font-size:14px;opacity:0.9;">'.htmlspecialchars($promotion['description']).'</div>' : '',
        $urgencyText
    );
}

/**
 * Get active homepage promotions
 * 
 * @return array Array of promotion data
 */
function getHomepagePromotions() {
    try {
        $promotionModel = new PromotionModel();
        return $promotionModel->getHomepagePromotions();
    } catch (Exception $e) {
        error_log("Get homepage promotions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Format price with discount display
 * 
 * @param float $originalPrice Original price
 * @param array|null $promotion Promotion data
 * @return string HTML for price display
 */
function formatPriceWithDiscount($originalPrice, $promotion = null) {
    if (!$promotion) {
        return sprintf('<div class="product-price">ETB %s</div>', number_format($originalPrice, 2));
    }
    
    $prices = calculateDiscountedPrice($originalPrice, $promotion);
    
    return sprintf(
        '<div class="product-price-container">
            <div class="original-price" style="text-decoration:line-through;color:#999;font-size:16px;margin-bottom:5px;">
                ETB %s
            </div>
            <div class="discounted-price" style="font-size:24px;font-weight:700;color:#E74C3C;">
                ETB %s
                <span style="font-size:14px;color:#27AE60;margin-left:8px;">Save ETB %s</span>
            </div>
        </div>',
        number_format($prices['original'], 2),
        number_format($prices['final'], 2),
        number_format($prices['discount'], 2)
    );
}

/**
 * Check if promotion is applicable to order
 * 
 * @param array $promotion Promotion data
 * @param float $orderValue Order total value
 * @param int|null $customerId Customer ID
 * @return bool True if applicable
 */
function isPromotionApplicable($promotion, $orderValue, $customerId = null) {
    if (!$promotion) {
        return false;
    }
    
    // Check minimum order value
    if (isset($promotion['min_order_value']) && $orderValue < $promotion['min_order_value']) {
        return false;
    }
    
    // Check customer type
    if ($customerId && $promotion['customer_type'] !== 'all') {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            $orderCount = $stmt->fetchColumn();
            $isNewCustomer = ($orderCount == 0);
            
            if ($promotion['customer_type'] === 'new' && !$isNewCustomer) {
                return false;
            }
            if ($promotion['customer_type'] === 'returning' && $isNewCustomer) {
                return false;
            }
        } catch (PDOException $e) {
            error_log("Check customer type error: " . $e->getMessage());
        }
    }
    
    return true;
}

/**
 * Get promotion summary text
 * 
 * @param array $promotion Promotion data
 * @return string Summary text
 */
function getPromotionSummary($promotion) {
    if (!$promotion) {
        return '';
    }
    
    $summary = '';
    
    if ($promotion['discount_type'] === 'percentage') {
        $summary = sprintf('%s%% OFF', $promotion['discount_value']);
    } else {
        $summary = sprintf('ETB %s OFF', number_format($promotion['discount_value'], 2));
    }
    
    if ($promotion['applies_to'] === 'category' && $promotion['target_category']) {
        $summary .= sprintf(' on %s', $promotion['target_category']);
    } elseif ($promotion['applies_to'] === 'first_order') {
        $summary .= ' for First Order';
    }
    
    return $summary;
}
