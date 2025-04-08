<?php
/**
 * View cho Analytics Dashboard
 */
?>
<div class="wrap">
    <h1>Analytics Dashboard</h1>
    
    <!-- Tổng quan -->
    <div class="analytics-overview">
        <h2>Tổng quan</h2>
        <div class="analytics-cards">
            <div class="card">
                <h3>Tổng số phiên</h3>
                <p><?php echo number_format($overview['total_sessions']); ?></p>
            </div>
            <div class="card">
                <h3>Tổng lượt xem trang</h3>
                <p><?php echo number_format($overview['total_pageviews']); ?></p>
            </div>
            <div class="card">
                <h3>Thời gian trung bình/phiên</h3>
                <p><?php echo round($overview['avg_session_duration']/60, 1); ?> phút</p>
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
                <?php foreach ($recent_journeys as $session): ?>
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
</div>
