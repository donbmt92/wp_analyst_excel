<?php
/**
 * Class WP_Excel_Import
 * Xử lý import dữ liệu từ Excel
 */
class WP_Excel_Import {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
    }
    
    /**
     * Thêm menu Import Excel
     */
    public function add_menu() {
        add_menu_page(
            'Import Excel',
            'Import Excel',
            'manage_options',
            'excel-import',
            array($this, 'render_import_page'),
            'dashicons-upload',
            30
        );
    }
    
    /**
     * Render trang import
     */
    public function render_import_page() {
        if (isset($_POST['submit']) && check_admin_referer('excel_import_action', 'excel_import_nonce')) {
            $this->handle_import();
        }
        
        include(get_template_directory() . '/includes/excel/views/import-form.php');
    }
    
    /**
     * Xử lý import Excel
     */
    private function handle_import() {
        try {
            // Kiểm tra file
            if (!isset($_FILES['excel_file'])) {
                throw new Exception('Vui lòng chọn file Excel');
            }
            
            $file = $_FILES['excel_file'];
            $allowed_types = array('xlsx', 'xls');
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_types)) {
                throw new Exception('File không đúng định dạng');
            }
            
            // Đọc file Excel
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Bỏ qua hàng tiêu đề
            array_shift($rows);
            
            $imported = 0;
            $errors = array();
            
            // Import từng dòng
            foreach ($rows as $index => $row) {
                try {
                    $this->import_row($row);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Lỗi dòng " . ($index + 2) . ": " . $e->getMessage();
                }
            }
            
            // Hiển thị kết quả
            $this->show_import_result($imported, $errors);
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Lỗi: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    
    /**
     * Import một dòng dữ liệu
     */
    private function import_row($row) {
        // Validate dữ liệu
        if (empty($row[0])) {
            throw new Exception('Tiêu đề không được để trống');
        }
        
        // Chuẩn bị dữ liệu post
        $post_data = array(
            'post_title' => wp_strip_all_tags($row[0]),
            'post_content' => $row[1],
            'post_status' => $row[2] ?: 'draft',
            'post_type' => 'post'
        );
        
        // Set author nếu có
        if (!empty($row[3])) {
            $author = get_user_by('login', $row[3]);
            if ($author) {
                $post_data['post_author'] = $author->ID;
            }
        }
        
        // Tạo post
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }
        
        // Thêm categories
        if (!empty($row[4])) {
            $categories = array_map('trim', explode(',', $row[4]));
            $cat_ids = array();
            
            foreach ($categories as $cat_name) {
                $cat_id = get_cat_ID($cat_name);
                if (!$cat_id) {
                    // Tạo category mới nếu chưa tồn tại
                    $cat_id = wp_create_category($cat_name);
                }
                $cat_ids[] = $cat_id;
            }
            
            wp_set_post_categories($post_id, $cat_ids);
        }
        
        // Thêm tags
        if (!empty($row[5])) {
            $tags = array_map('trim', explode(',', $row[5]));
            wp_set_post_tags($post_id, $tags);
        }
    }
    
    /**
     * Hiển thị kết quả import
     */
    private function show_import_result($imported, $errors) {
        // Tạo lại sitemap nếu có bài viết được import
        if ($imported > 0) {
            $this->generate_sitemap();
        }

        $message = sprintf(
            'Đã import thành công %d bài viết. Sitemap đã được cập nhật.',
            $imported
        );
        
        echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        
        if (!empty($errors)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Các lỗi phát sinh:</strong></p>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Tạo sitemap
     */
    private function generate_sitemap() {
        $sitemap_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap_content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Lấy tất cả bài viết đã publish
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));

        // Thêm URL của trang chủ
        $sitemap_content .= $this->get_sitemap_url(get_home_url(), '1.0');

        // Thêm URL của từng bài viết
        foreach ($posts as $post) {
            $sitemap_content .= $this->get_sitemap_url(
                get_permalink($post),
                '0.8',
                get_post_modified_time('c', true, $post)
            );
        }

        // Thêm URL của các trang Categories
        $categories = get_categories();
        foreach ($categories as $category) {
            $sitemap_content .= $this->get_sitemap_url(
                get_category_link($category->term_id),
                '0.6'
            );
        }

        $sitemap_content .= '</urlset>';

        // Lưu sitemap
        $sitemap_path = ABSPATH . 'sitemap.xml';
        file_put_contents($sitemap_path, $sitemap_content);

        // Ping Google về sitemap mới
        $ping_url = 'http://www.google.com/webmasters/tools/ping?sitemap=' . urlencode(get_home_url() . '/sitemap.xml');
        wp_remote_get($ping_url);
    }

    /**
     * Tạo XML cho một URL trong sitemap
     */
    private function get_sitemap_url($url, $priority = '0.5', $lastmod = '') {
        $url = esc_url($url);
        $output = "  <url>\n";
        $output .= "    <loc>$url</loc>\n";
        if ($lastmod) {
            $output .= "    <lastmod>$lastmod</lastmod>\n";
        }
        $output .= "    <priority>$priority</priority>\n";
        $output .= "  </url>\n";
        return $output;
    }
}
