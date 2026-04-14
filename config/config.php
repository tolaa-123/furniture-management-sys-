<?php
/**
 * Main Application Configuration
 * Contains global settings for the Custom Furniture ERP System
 */

if (!defined('APP_NAME'))        define('APP_NAME', 'Custom Furniture ERP & E-Commerce Platform');
if (!defined('APP_VERSION'))     define('APP_VERSION', '1.0.0');
if (!defined('APP_AUTHOR'))      define('APP_AUTHOR', 'DEREJE AYELE - Jimma University');
if (!defined('APP_DESCRIPTION')) define('APP_DESCRIPTION', 'Production-ready ERP and E-Commerce platform for custom furniture business');

if (!defined('BASE_URL'))        define('BASE_URL', 'http://localhost/NEWkoder');
if (!defined('APP_PATH'))        define('APP_PATH', dirname(__DIR__) . '/app/');
if (!defined('MODELS_PATH'))     define('MODELS_PATH', APP_PATH . 'models/');
if (!defined('CONTROLLERS_PATH'))define('CONTROLLERS_PATH', APP_PATH . 'controllers/');
if (!defined('VIEWS_PATH'))      define('VIEWS_PATH', APP_PATH . 'views/');
if (!defined('CORE_PATH'))       define('CORE_PATH', dirname(__DIR__) . '/core/');
if (!defined('PUBLIC_PATH'))     define('PUBLIC_PATH', dirname(__DIR__) . '/public/');

if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_LOCKOUT_TIME')) define('LOGIN_LOCKOUT_TIME', 900);

if (!defined('UPLOAD_PATH'))     define('UPLOAD_PATH', PUBLIC_PATH . 'uploads/');
if (!defined('MAX_FILE_SIZE'))   define('MAX_FILE_SIZE', 5 * 1024 * 1024);
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

if (!defined('SMTP_HOST'))       define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT'))       define('SMTP_PORT', 587);
if (!defined('SMTP_USER'))       define('SMTP_USER', 'derejeayele292@gmail.com');
if (!defined('SMTP_PASS'))       define('SMTP_PASS', 'xkcm riem paee nhmx');
if (!defined('SMTP_USERNAME'))   define('SMTP_USERNAME', SMTP_USER);
if (!defined('SMTP_PASSWORD'))   define('SMTP_PASSWORD', SMTP_PASS);
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'tls');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'derejeayele292@gmail.com');
if (!defined('SMTP_FROM_NAME'))  define('SMTP_FROM_NAME', 'SmartWorkshop');

if (!defined('ITEMS_PER_PAGE'))  define('ITEMS_PER_PAGE', 10);
if (!defined('DEBUG_MODE'))      define('DEBUG_MODE', true);
