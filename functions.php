<?php
/**
 * Theme functions and definitions
 *
 * @package Analytics_Excel_Import
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define constants
 */
define('ANALYTICS_THEME_VERSION', '1.0.0');
define('ANALYTICS_THEME_DIR', get_template_directory());
define('ANALYTICS_THEME_URI', get_template_directory_uri());

/**
 * Theme Setup
 */
function analytics_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
    
    // Register nav menus
    register_nav_menus(array(
        'primary' => esc_html__('Primary Menu', 'analytics-excel-import'),
        'footer'  => esc_html__('Footer Menu', 'analytics-excel-import'),
    ));
}
add_action('after_setup_theme', 'analytics_theme_setup');

/**
 * Enqueue scripts and styles
 */
function analytics_enqueue_scripts() {
    // Theme stylesheet
    wp_enqueue_style(
        'analytics-theme-style',
        get_stylesheet_uri(),
        array(),
        ANALYTICS_THEME_VERSION
    );
}
add_action('wp_enqueue_scripts', 'analytics_enqueue_scripts');

/**
 * Load Composer autoloader
 */
if (file_exists(ANALYTICS_THEME_DIR . '/vendor/autoload.php')) {
    require_once ANALYTICS_THEME_DIR . '/vendor/autoload.php';
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Please run composer install to setup theme dependencies.', 'analytics-excel-import');
        echo '</p></div>';
    });
    return;
}

/**
 * Load theme classes
 */
require_once ANALYTICS_THEME_DIR . '/includes/analytics/class-visitor-tracking.php';
require_once ANALYTICS_THEME_DIR . '/includes/analytics/class-analytics-dashboard.php';
require_once ANALYTICS_THEME_DIR . '/includes/excel/class-excel-import.php';

/**
 * Initialize theme features
 */
function analytics_init_features() {
    // Khởi tạo các class
    new WP_Visitor_Tracking();
    new WP_Analytics_Dashboard();
    new WP_Excel_Import();
}
add_action('init', 'analytics_init_features');
        $session_id
    ));
    
    if (!$existing_session) {
        // Tạo session mới
        $wpdb->insert(
            $sessions_table,
            array(
                'session_id' => $session_id,
                'start_time' => $timestamp,
                'last_activity' => $timestamp,
                'ip_address' => $ip,
                'user_agent' => $user_agent,
                'first_page' => $page_url,
                'last_page' => $page_url
            )
        );
    } else {
        // Cập nhật session hiện tại
        $time_diff = strtotime($timestamp) - strtotime($existing_session->last_activity);
        $wpdb->update(
            $sessions_table,
            array(
                'last_activity' => $timestamp,
                'total_time' => $existing_session->total_time + $time_diff,
                'page_views' => $existing_session->page_views + 1,
                'last_page' => $page_url
            ),
            array('session_id' => $session_id)
        );
    }
    
    // Lưu thông tin page view
    $tracking_table = $wpdb->prefix . 'visitor_tracking';
    $wpdb->insert(
        $tracking_table,
        array(
            'session_id' => $session_id,
            'ip_address' => $ip,
            'user_agent' => $user_agent,
            'page_url' => $page_url,
            'referrer' => $referrer,
            'visit_time' => $timestamp
        )
    );
    
    // Track time on page using JavaScript
    add_action('wp_footer', 'add_time_tracking_script');
}
}
add_action('wp_head', 'track_visitor');

// Tạo bảng tracking khi active theme
function create_tracking_tables() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Bảng visitor_tracking
    $table_name = $wpdb->prefix . 'visitor_tracking';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(32) NOT NULL,
        ip_address varchar(45) NOT NULL,
        user_agent text NOT NULL,
        page_url varchar(255) NOT NULL,
        referrer varchar(255),
        visit_time datetime NOT NULL,
        time_on_page int DEFAULT 0,
        PRIMARY KEY  (id),
        KEY session_id (session_id)
    ) $charset_collate;";
    
    dbDelta($sql);
    
    // Bảng sessions
    $sessions_table = $wpdb->prefix . 'visitor_sessions';
    $sql = "CREATE TABLE IF NOT EXISTS $sessions_table (
        session_id varchar(32) NOT NULL,
        start_time datetime NOT NULL,
        last_activity datetime NOT NULL,
        total_time int DEFAULT 0,
        page_views int DEFAULT 1,
        ip_address varchar(45) NOT NULL,
        user_agent text NOT NULL,
        first_page varchar(255) NOT NULL,
        last_page varchar(255) NOT NULL,
        PRIMARY KEY  (session_id)
    ) $charset_collate;";
    
    dbDelta($sql);
}
add_action('after_switch_theme', 'create_tracking_tables');

// Thêm script theo dõi thời gian trên trang
function add_time_tracking_script() {
    $session_id = isset($_COOKIE['wp_visitor_session']) ? $_COOKIE['wp_visitor_session'] : '';
    if (!empty($session_id)) {
        ?>
        <script>
        var startTime = new Date().getTime();
        var pageUrl = '<?php echo esc_js($_SERVER['REQUEST_URI']); ?>';
        var sessionId = '<?php echo esc_js($session_id); ?>';
        
        // Gửi thời gian trên trang khi user rời đi
        window.addEventListener('beforeunload', function() {
            var timeSpent = Math.floor((new Date().getTime() - startTime) / 1000);
            navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', 
                'action=update_time_on_page&session_id=' + sessionId + 
                '&page_url=' + encodeURIComponent(pageUrl) + 
                '&time_spent=' + timeSpent);
        });
        </script>
        <?php
    }
}

// Xử lý AJAX update thời gian trên trang
function update_time_on_page() {
    global $wpdb;
    
    $session_id = $_POST['session_id'];
    $page_url = $_POST['page_url'];
    $time_spent = intval($_POST['time_spent']);
    
    // Cập nhật thời gian cho page view
    $tracking_table = $wpdb->prefix . 'visitor_tracking';
    $wpdb->query($wpdb->prepare(
        "UPDATE $tracking_table 
         SET time_on_page = %d 
         WHERE session_id = %s AND page_url = %s 
         ORDER BY visit_time DESC LIMIT 1",
        $time_spent, $session_id, $page_url
    ));
    
    wp_die();
}
add_action('wp_ajax_nopriv_update_time_on_page', 'update_time_on_page');
add_action('wp_ajax_update_time_on_page', 'update_time_on_page');

// Thêm Google Analytics
function add_google_analytics() {
    ?>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-XXXXXXXXXX');
    </script>
    <?php
}
add_action('wp_head', 'add_google_analytics');

// Thêm menu Analytics trong admin
function add_analytics_menu() {
    add_menu_page(
        'Analytics Dashboard',
        'Analytics',
        'manage_options',
        'analytics-dashboard',
        'render_analytics_page',
        'dashicons-chart-bar',
        31
    );
}
add_action('admin_menu', 'add_analytics_menu');

// Render trang Analytics
function render_analytics_page() {
    global $wpdb;
    $tracking_table = $wpdb->prefix . 'visitor_tracking';
    $sessions_table = $wpdb->prefix . 'visitor_sessions';
    
    // Thống kê chung
    $total_sessions = $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table");
    $total_pageviews = $wpdb->get_var("SELECT COUNT(*) FROM $tracking_table");
    $avg_session_duration = $wpdb->get_var("SELECT AVG(total_time) FROM $sessions_table");
    
    // Thống kê theo ngày
    $daily_stats = $wpdb->get_results(
        "SELECT 
            DATE(visit_time) as date,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(*) as pageviews,
            AVG(time_on_page) as avg_time
         FROM $tracking_table 
         WHERE visit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(visit_time)
         ORDER BY date DESC"
    );
    
    // Lấy 10 session gần nhất với hành trình
    $recent_sessions = $wpdb->get_results(
        "SELECT s.*, 
            COUNT(t.id) as total_pages,
            GROUP_CONCAT(
                CONCAT(t.page_url, ' (', t.time_on_page, 's)')
                ORDER BY t.visit_time ASC
                SEPARATOR ' → '
            ) as journey
         FROM $sessions_table s
         LEFT JOIN $tracking_table t ON s.session_id = t.session_id
         GROUP BY s.session_id
         ORDER BY s.start_time DESC
         LIMIT 10"
    );
    
    // Top trang được xem nhiều nhất
    $top_pages = $wpdb->get_results(
        "SELECT 
            page_url,
            COUNT(*) as views,
            COUNT(DISTINCT session_id) as unique_views,
            AVG(time_on_page) as avg_time
         FROM $tracking_table
         GROUP BY page_url
         ORDER BY views DESC
         LIMIT 10"
    );
    
    ?>
    <div class="wrap">
        <h1>Analytics Dashboard</h1>
        
        <!-- Tổng quan -->
        <div class="analytics-overview">
            <h2>Tổng quan</h2>
            <div class="analytics-cards">
                <div class="card">
                    <h3>Tổng số phiên</h3>
                    <p><?php echo number_format($total_sessions); ?></p>
                </div>
                <div class="card">
                    <h3>Tổng lượt xem trang</h3>
                    <p><?php echo number_format($total_pageviews); ?></p>
                </div>
                <div class="card">
                    <h3>Thời gian trung bình/phiên</h3>
                    <p><?php echo round($avg_session_duration/60, 1); ?> phút</p>
                </div>
            </div>
        </div>
        
        <!-- Thống kê theo ngày -->
        <div class="analytics-daily">
            <h2>Thống kê 7 ngày gần nhất</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Số phiên</th>
                        <th>Lượt xem trang</th>
                        <th>Thời gian TB/trang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_stats as $row): ?>
                    <tr>
                        <td><?php echo $row->date; ?></td>
                        <td><?php echo $row->sessions; ?></td>
                        <td><?php echo $row->pageviews; ?></td>
                        <td><?php echo round($row->avg_time, 1); ?>s</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Top trang -->
        <div class="analytics-pages">
            <h2>Top trang được xem nhiều nhất</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Lượt xem</th>
                        <th>Lượt xem duy nhất</th>
                        <th>Thời gian TB</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_pages as $page): ?>
                    <tr>
                        <td><?php echo esc_html($page->page_url); ?></td>
                        <td><?php echo $page->views; ?></td>
                        <td><?php echo $page->unique_views; ?></td>
                        <td><?php echo round($page->avg_time, 1); ?>s</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Hành trình người dùng -->
        <div class="analytics-journeys">
            <h2>Hành trình người dùng gần đây</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Thời gian bắt đầu</th>
                        <th>Số trang</th>
                        <th>Tổng thời gian</th>
                        <th>Hành trình</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_sessions as $session): ?>
                    <tr>
                        <td><?php echo $session->start_time; ?></td>
                        <td><?php echo $session->total_pages; ?></td>
                        <td><?php echo round($session->total_time/60, 1); ?> phút</td>
                        <td style="max-width: 500px; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo esc_html($session->journey); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Google Analytics -->
        <div class="analytics-google">
            <h2>Google Analytics</h2>
            <p>Xem thống kê chi tiết tại <a href="https://analytics.google.com" target="_blank">Google Analytics Dashboard</a></p>
        </div>
    </div>
    
    <style>
    .analytics-cards {
        display: flex;
        gap: 20px;
        margin-bottom: 30px;
    }
    .analytics-cards .card {
        background: #fff;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        flex: 1;
    }
    .analytics-cards .card h3 {
        margin: 0 0 10px 0;
        color: #23282d;
    }
    .analytics-cards .card p {
        font-size: 24px;
        margin: 0;
        color: #0073aa;
    }
    .analytics-daily, .analytics-pages, .analytics-journeys {
        margin-top: 30px;
    }
    </style>
    <?php
}

use PhpOffice\PhpSpreadsheet\IOFactory;

// Thêm menu vào admin
function add_excel_import_menu() {
    add_menu_page(
        'Import Excel',
        'Import Excel',
        'manage_options',
        'excel-import',
        'render_excel_import_page',
        'dashicons-upload',
        30
    );
}
add_action('admin_menu', 'add_excel_import_menu');

// Render trang import
function render_excel_import_page() {
    ?>
    <div class="wrap">
        <h1>Import dữ liệu từ Excel</h1>
        <form method="post" enctype="multipart/form-data" action="">
            <?php wp_nonce_field('excel_import_action', 'excel_import_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="excel_file">Chọn file Excel</label></th>
                    <td>
                        <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls" required>
                        <p class="description">Hỗ trợ file .xlsx, .xls</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Import'); ?>
        </form>
    </div>
    <?php

    // Xử lý import khi form được submit
    if (isset($_POST['submit']) && check_admin_referer('excel_import_action', 'excel_import_nonce')) {
        handle_excel_import();
    }
}

// Xử lý import Excel
function handle_excel_import() {
    if (!isset($_FILES['excel_file'])) {
        wp_die('Vui lòng chọn file Excel');
    }

    $file = $_FILES['excel_file'];
    $allowed_types = array('xlsx', 'xls');
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_types)) {
        wp_die('File không đúng định dạng');
    }

    try {
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Bỏ qua hàng tiêu đề
        array_shift($rows);

        foreach ($rows as $row) {
            $post_data = array(
                'post_title'    => wp_strip_all_tags($row[0]), // Title
                'post_content'  => $row[1], // Content
                'post_status'   => $row[2] ?: 'draft', // Status
                'post_author'   => get_user_by('login', $row[3])->ID ?: 1, // Author
                'post_type'     => 'post'
            );

            // Tạo post mới
            $post_id = wp_insert_post($post_data);

            if (!is_wp_error($post_id)) {
                // Thêm categories
                if (!empty($row[4])) {
                    $categories = array_map('trim', explode(',', $row[4]));
                    wp_set_post_categories($post_id, array_map('get_cat_ID', $categories));
                }

                // Thêm tags
                if (!empty($row[5])) {
                    $tags = array_map('trim', explode(',', $row[5]));
                    wp_set_post_tags($post_id, $tags);
                }
            }
        }

        echo '<div class="notice notice-success"><p>Import thành công!</p></div>';
    } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>Lỗi: ' . $e->getMessage() . '</p></div>';
    }
}
