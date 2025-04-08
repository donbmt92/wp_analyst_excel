<?php
/**
 * Class WP_Analytics_Dashboard
 * Xử lý và hiển thị analytics dashboard trong admin
 */
class WP_Analytics_Dashboard {
    private $wpdb;
    private $tracking_table;
    private $sessions_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tracking_table = $wpdb->prefix . 'visitor_tracking';
        $this->sessions_table = $wpdb->prefix . 'visitor_sessions';
        
        // Thêm menu Analytics vào admin
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Thêm menu vào admin
     */
    public function add_menu() {
        add_menu_page(
            'Analytics Dashboard',
            'Analytics',
            'manage_options',
            'analytics-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-chart-bar',
            31
        );
    }
    
    /**
     * Enqueue CSS và JS cho dashboard
     */
    public function enqueue_assets($hook) {
        if ($hook != 'toplevel_page_analytics-dashboard') {
            return;
        }
        
        wp_enqueue_style(
            'analytics-dashboard', 
            get_template_directory_uri() . '/assets/css/analytics-dashboard.css',
            array(),
            '1.0.0'
        );
    }
    
    /**
     * Lấy thống kê chung
     */
    private function get_overview_stats() {
        return array(
            'total_sessions' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->sessions_table}"),
            'total_pageviews' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tracking_table}"),
            'avg_session_duration' => $this->wpdb->get_var("SELECT AVG(total_time) FROM {$this->sessions_table}")
        );
    }
    
    /**
     * Lấy thống kê theo ngày
     */
    private function get_daily_stats() {
        return $this->wpdb->get_results(
            "SELECT 
                DATE(visit_time) as date,
                COUNT(DISTINCT session_id) as sessions,
                COUNT(*) as pageviews,
                AVG(time_on_page) as avg_time
             FROM {$this->tracking_table} 
             WHERE visit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(visit_time)
             ORDER BY date DESC"
        );
    }
    
    /**
     * Lấy top trang được xem nhiều
     */
    private function get_top_pages() {
        return $this->wpdb->get_results(
            "SELECT 
                page_url,
                COUNT(*) as views,
                COUNT(DISTINCT session_id) as unique_views,
                AVG(time_on_page) as avg_time
             FROM {$this->tracking_table}
             GROUP BY page_url
             ORDER BY views DESC
             LIMIT 10"
        );
    }
    
    /**
     * Lấy hành trình người dùng gần đây
     */
    private function get_recent_journeys() {
        return $this->wpdb->get_results(
            "SELECT s.*, 
                COUNT(t.id) as total_pages,
                GROUP_CONCAT(
                    CONCAT(t.page_url, ' (', t.time_on_page, 's)')
                    ORDER BY t.visit_time ASC
                    SEPARATOR ' → '
                ) as journey
             FROM {$this->sessions_table} s
             LEFT JOIN {$this->tracking_table} t ON s.session_id = t.session_id
             GROUP BY s.session_id
             ORDER BY s.start_time DESC
             LIMIT 10"
        );
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        $overview = $this->get_overview_stats();
        $daily_stats = $this->get_daily_stats();
        $top_pages = $this->get_top_pages();
        $recent_journeys = $this->get_recent_journeys();
        
        include(get_template_directory() . '/includes/analytics/views/dashboard.php');
    }
}
