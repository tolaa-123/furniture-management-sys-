<?php
/**
 * Breadcrumb Navigation Helper
 * Usage: Breadcrumb::render([['label'=>'Dashboard','url'=>'/manager/dashboard'], ['label'=>'Orders']])
 */
class Breadcrumb {
    public static function render(array $items, $baseUrl = '') {
        if (empty($items)) return '';
        $html = '<nav class="breadcrumb-nav" aria-label="Breadcrumb">';
        $last = count($items) - 1;
        foreach ($items as $i => $item) {
            if ($i === $last) {
                $html .= '<span class="bc-current">' . htmlspecialchars($item['label']) . '</span>';
            } else {
                $url = isset($item['url']) ? $baseUrl . $item['url'] : '#';
                $html .= '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($item['label']) . '</a>';
                $html .= '<i class="fas fa-chevron-right bc-sep"></i>';
            }
        }
        $html .= '</nav>';
        return $html;
    }
}
