<?php
/**
 * View form import Excel
 */
?>
<div class="wrap">
    <h1>Import dữ liệu từ Excel</h1>
    
    <div class="card">
        <h2>Hướng dẫn</h2>
        <p>File Excel cần có các cột theo thứ tự sau:</p>
        <ol>
            <li><strong>Title</strong> (bắt buộc): Tiêu đề bài viết</li>
            <li><strong>Content</strong>: Nội dung bài viết</li>
            <li><strong>Status</strong>: Trạng thái (publish/draft)</li>
            <li><strong>Author</strong>: Tên đăng nhập của tác giả</li>
            <li><strong>Categories</strong>: Danh mục (phân cách bằng dấu phẩy)</li>
            <li><strong>Tags</strong>: Thẻ (phân cách bằng dấu phẩy)</li>
        </ol>
    </div>
    
    <div class="card">
        <h2>Upload File</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('excel_import_action', 'excel_import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="excel_file">Chọn file Excel</label></th>
                    <td>
                        <input type="file" 
                               name="excel_file" 
                               id="excel_file" 
                               accept=".xlsx,.xls" 
                               required>
                        <p class="description">
                            Hỗ trợ file .xlsx, .xls.<br>
                            Dòng đầu tiên sẽ được coi là tiêu đề cột và sẽ bị bỏ qua.
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Import'); ?>
        </form>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
    padding: 20px;
}

.card h2 {
    margin-top: 0;
    font-size: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.card ol {
    margin: 0;
    padding-left: 20px;
}

.card ol li {
    margin-bottom: 10px;
}

.card ol li strong {
    color: #23282d;
}
</style>
