<?php
require_once 'vendor/autoload.php';

// Tracking Visitors
function track_visitor() {
    if (!isset($_COOKIE['visitor_tracked'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'visitor_tracking';
        
        // Lấy thông tin visitor
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $page_url = $_SERVER['REQUEST_URI'];
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $timestamp = current_time('mysql');
        
        // Lưu vào database
        $wpdb->insert(
            $table_name,
            array(
                'ip_address' => $ip,
                'user_agent' => $user_agent,
                'page_url' => $page_url,
                'referrer' => $referrer,
                'visit_time' => $timestamp
            )
        );
        
        // Set cookie để tránh đếm trùng
        setcookie('visitor_tracked', '1', time() + (86400 * 30), '/'); // Cookie hết hạn sau 30 ngày
    }
}
add_action('wp_head', 'track_visitor');

// Tạo bảng tracking khi active theme
function create_tracking_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_tracking';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_address varchar(45) NOT NULL,
        user_agent text NOT NULL,
        page_url varchar(255) NOT NULL,
        referrer varchar(255),
        visit_time datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_switch_theme', 'create_tracking_table');

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
    $table_name = $wpdb->prefix . 'visitor_tracking';
    
    // Lấy thống kê trong 7 ngày gần nhất
    $stats = $wpdb->get_results(
        "SELECT DATE(visit_time) as date, COUNT(*) as count 
         FROM $table_name 
         WHERE visit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
         GROUP BY DATE(visit_time) 
         ORDER BY date DESC"
    );
    
    ?>
    <div class="wrap">
        <h1>Analytics Dashboard</h1>
        
        <div class="analytics-container">
            <h2>Lượt truy cập 7 ngày gần nhất</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Số lượt truy cập</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $row): ?>
                    <tr>
                        <td><?php echo $row->date; ?></td>
                        <td><?php echo $row->count; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="analytics-google">
                <h2>Google Analytics</h2>
                <p>Xem thống kê chi tiết tại <a href="https://analytics.google.com" target="_blank">Google Analytics Dashboard</a></p>
            </div>
        </div>
    </div>
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
