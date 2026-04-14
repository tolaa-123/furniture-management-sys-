<?php
/**
 * QueryCache - Simple query caching system
 * Caches query results to improve performance
 */

class QueryCache {
    private static $cacheDir = __DIR__ . '/../../logs/cache/';
    private static $defaultTTL = 3600; // 1 hour default
    
    /**
     * Initialize cache directory
     */
    public static function init() {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cache file path
     * 
     * @param string $key Cache key
     * @return string Cache file path
     */
    private static function getCachePath($key) {
        return self::$cacheDir . md5($key) . '.cache';
    }
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null if expired/not found
     */
    public static function get($key) {
        self::init();
        
        $file = self::getCachePath($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data === false) {
            return null;
        }
        
        // Check if cache has expired
        if (isset($data['expires']) && $data['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'] ?? null;
    }
    
    /**
     * Set cache value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool True if successful
     */
    public static function set($key, $value, $ttl = null) {
        self::init();
        
        if ($ttl === null) {
            $ttl = self::$defaultTTL;
        }
        
        $file = self::getCachePath($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created_at' => time(),
            'key' => $key,
        ];
        
        $result = file_put_contents($file, serialize($data));
        
        return $result !== false;
    }
    
    /**
     * Check if cache exists and is valid
     * 
     * @param string $key Cache key
     * @return bool True if cache exists and is valid
     */
    public static function has($key) {
        self::init();
        
        $file = self::getCachePath($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data === false) {
            return false;
        }
        
        // Check if cache has expired
        if (isset($data['expires']) && $data['expires'] < time()) {
            unlink($file);
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete cache entry
     * 
     * @param string $key Cache key
     * @return bool True if successful
     */
    public static function delete($key) {
        self::init();
        
        $file = self::getCachePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * Clear all cache
     * 
     * @return int Number of files deleted
     */
    public static function clear() {
        self::init();
        
        $files = glob(self::$cacheDir . '*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Clear expired cache entries
     * 
     * @return int Number of files deleted
     */
    public static function clearExpired() {
        self::init();
        
        $files = glob(self::$cacheDir . '*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            
            if ($data === false) {
                if (unlink($file)) {
                    $count++;
                }
                continue;
            }
            
            // Check if cache has expired
            if (isset($data['expires']) && $data['expires'] < time()) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public static function getStatistics() {
        self::init();
        
        $files = glob(self::$cacheDir . '*.cache');
        $stats = [
            'total_files' => count($files),
            'total_size' => 0,
            'expired_count' => 0,
            'valid_count' => 0,
        ];
        
        foreach ($files as $file) {
            $stats['total_size'] += filesize($file);
            
            $data = unserialize(file_get_contents($file));
            
            if ($data === false) {
                $stats['expired_count']++;
                continue;
            }
            
            // Check if cache has expired
            if (isset($data['expires']) && $data['expires'] < time()) {
                $stats['expired_count']++;
            } else {
                $stats['valid_count']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Remember a value in cache
     * If cache exists, return cached value
     * Otherwise, call callback and cache result
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to execute if cache miss
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or computed value
     */
    public static function remember($key, $callback, $ttl = null) {
        // Check if cache exists
        if (self::has($key)) {
            return self::get($key);
        }
        
        // Execute callback
        $value = call_user_func($callback);
        
        // Cache the result
        self::set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Remember a value forever (until manually cleared)
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to execute if cache miss
     * @return mixed Cached or computed value
     */
    public static function rememberForever($key, $callback) {
        // Use very large TTL (10 years)
        return self::remember($key, $callback, 315360000);
    }
}

// Initialize cache
QueryCache::init();
?>
