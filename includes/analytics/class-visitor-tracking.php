<?php
/**
 * Class WP_Visitor_Tracking
 * Xử lý tracking visitor và session
 */
class WP_Visitor_Tracking {
    private $wpdb;
    private $tracking_table;
    private $sessions_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tracking_table = $wpdb->prefix . 'visitor_tracking';
        $this->sessions_table = $wpdb->prefix . 'visitor_sessions';
        
        // Hooks
        add_action('wp_head', array($this, 'track_visitor'));
        add_action('wp_footer', array($this, 'add_time_tracking_script'));
        add_action('wp_ajax_update_time_on_page', array($this, 'update_time_on_page'));
        add_action('wp_ajax_nopriv_update_time_on_page', array($this, 'update_time_on_page'));
        add_action('after_switch_theme', array($this, 'create_tables'));
    }
    
    /**
     * Tạo các bảng database cần thiết
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Bảng visitor_tracking
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tracking_table} (
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
        ) {$this->wpdb->get_charset_collate()};";
        
        dbDelta($sql);
        
        // Bảng sessions
        $sql = "CREATE TABLE IF NOT EXISTS {$this->sessions_table} (
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
        ) {$this->wpdb->get_charset_collate()};";
        
        dbDelta($sql);
    }
    
    /**
     * Track visitor và session
     */
    public function track_visitor() {
        // Lấy hoặc tạo session_id
        $session_id = $this->get_or_create_session_id();
        
        // Lấy thông tin visitor
        $visitor_data = $this->get_visitor_data();
        
        // Cập nhật hoặc tạo session
        $this->update_session($session_id, $visitor_data);
        
        // Lưu page view
        $this->log_page_view($session_id, $visitor_data);
    }
    
    /**
     * Lấy hoặc tạo session ID mới
     */
    private function get_or_create_session_id() {
        if (!isset($_COOKIE['wp_visitor_session'])) {
            $session_id = md5(uniqid('', true));
            setcookie('wp_visitor_session', $session_id, time() + (30 * 60), '/');
            return $session_id;
        }
        return $_COOKIE['wp_visitor_session'];
    }
    
    /**
     * Lấy thông tin visitor
     */
    private function get_visitor_data() {
        return array(
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'page_url' => $_SERVER['REQUEST_URI'],
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Cập nhật thông tin session
     */
    private function update_session($session_id, $data) {
        $existing_session = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->sessions_table} WHERE session_id = %s",
                $session_id
            )
        );
        
        if (!$existing_session) {
            $this->wpdb->insert(
                $this->sessions_table,
                array(
                    'session_id' => $session_id,
                    'start_time' => $data['timestamp'],
                    'last_activity' => $data['timestamp'],
                    'ip_address' => $data['ip'],
                    'user_agent' => $data['user_agent'],
                    'first_page' => $data['page_url'],
                    'last_page' => $data['page_url']
                )
            );
        } else {
            $time_diff = strtotime($data['timestamp']) - strtotime($existing_session->last_activity);
            $this->wpdb->update(
                $this->sessions_table,
                array(
                    'last_activity' => $data['timestamp'],
                    'total_time' => $existing_session->total_time + $time_diff,
                    'page_views' => $existing_session->page_views + 1,
                    'last_page' => $data['page_url']
                ),
                array('session_id' => $session_id)
            );
        }
    }
    
    /**
     * Lưu thông tin page view
     */
    private function log_page_view($session_id, $data) {
        $this->wpdb->insert(
            $this->tracking_table,
            array(
                'session_id' => $session_id,
                'ip_address' => $data['ip'],
                'user_agent' => $data['user_agent'],
                'page_url' => $data['page_url'],
                'referrer' => $data['referrer'],
                'visit_time' => $data['timestamp']
            )
        );
    }
    
    /**
     * Thêm script theo dõi thời gian
     */
    public function add_time_tracking_script() {
        $session_id = isset($_COOKIE['wp_visitor_session']) ? $_COOKIE['wp_visitor_session'] : '';
        if (!empty($session_id)) {
            ?>
            <script>
            var startTime = new Date().getTime();
            var pageUrl = '<?php echo esc_js($_SERVER['REQUEST_URI']); ?>';
            var sessionId = '<?php echo esc_js($session_id); ?>';
            
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
    
    /**
     * Cập nhật thời gian trên trang
     */
    public function update_time_on_page() {
        $session_id = $_POST['session_id'];
        $page_url = $_POST['page_url'];
        $time_spent = intval($_POST['time_spent']);
        
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->tracking_table} 
             SET time_on_page = %d 
             WHERE session_id = %s AND page_url = %s 
             ORDER BY visit_time DESC LIMIT 1",
            $time_spent, $session_id, $page_url
        ));
        
        wp_die();
    }
}
