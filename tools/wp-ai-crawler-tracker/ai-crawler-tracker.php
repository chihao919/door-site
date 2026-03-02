<?php
/**
 * Plugin Name: AI Crawler Tracker
 * Description: Tracks AI bot visits (GPTBot, ClaudeBot, PerplexityBot, etc.) and provides a dashboard in WP Admin.
 * Version: 1.0.0
 * Author: Waterson Corporation
 */

if (!defined('ABSPATH')) exit;

class AI_Crawler_Tracker {

    private static $crawlers = [
        'GPTBot'              => 'OpenAI',
        'ChatGPT-User'        => 'OpenAI',
        'OAI-SearchBot'       => 'OpenAI',
        'ClaudeBot'           => 'Anthropic',
        'Anthropic-AI'        => 'Anthropic',
        'Claude-Web'          => 'Anthropic',
        'PerplexityBot'       => 'Perplexity',
        'Google-Extended'     => 'Google',
        'Googlebot'           => 'Google',
        'Bingbot'             => 'Microsoft',
        'Applebot'            => 'Apple',
        'Applebot-Extended'   => 'Apple',
        'Bytespider'          => 'ByteDance',
        'Meta-ExternalAgent'  => 'Meta',
        'Meta-ExternalFetcher'=> 'Meta',
        'FacebookExternalHit' => 'Meta',
        'Amazonbot'           => 'Amazon',
        'CCBot'               => 'Common Crawl',
        'Cohere-ai'           => 'Cohere',
        'YouBot'              => 'You.com',
        'AI2Bot'              => 'Allen AI',
        'Ai2Bot-Dolma'        => 'Allen AI',
        'PetalBot'            => 'Huawei',
        'Diffbot'             => 'Diffbot',
        'Timpibot'            => 'Timpi',
        'Scrapy'              => 'Scrapy',
        'Webzio-Extended'     => 'Webz.io',
    ];

    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'create_table']);
        add_action('template_redirect', [__CLASS__, 'track_visit']);
        add_action('admin_menu', [__CLASS__, 'add_admin_page']);
        add_action('wp_ajax_aict_get_data', [__CLASS__, 'ajax_get_data']);
    }

    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_crawler_visits';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bot varchar(50) NOT NULL,
            org varchar(50) NOT NULL,
            path varchar(500) NOT NULL,
            user_agent varchar(500) DEFAULT '',
            visited_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY bot_idx (bot),
            KEY visited_at_idx (visited_at)
        ) $charset;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function detect_crawler($ua) {
        if (empty($ua)) return null;
        foreach (self::$crawlers as $pattern => $org) {
            if (stripos($ua, $pattern) !== false) {
                return ['bot' => $pattern, 'org' => $org];
            }
        }
        return null;
    }

    public static function track_visit() {
        if (is_admin()) return;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $crawler = self::detect_crawler($ua);
        if (!$crawler) return;

        global $wpdb;
        $table = $wpdb->prefix . 'ai_crawler_visits';
        $wpdb->insert($table, [
            'bot'        => $crawler['bot'],
            'org'        => $crawler['org'],
            'path'       => isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], 0, 500) : '/',
            'user_agent' => substr($ua, 0, 500),
            'visited_at' => current_time('mysql'),
        ]);
    }

    public static function add_admin_page() {
        add_menu_page(
            'AI Crawler Tracker',
            'AI Crawlers',
            'manage_options',
            'ai-crawler-tracker',
            [__CLASS__, 'render_admin_page'],
            'dashicons-visibility',
            30
        );
    }

    public static function ajax_get_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ai_crawler_visits';

        // Total per bot
        $totals = $wpdb->get_results(
            "SELECT bot, org, COUNT(*) as total,
                    MAX(visited_at) as last_seen
             FROM $table GROUP BY bot, org ORDER BY total DESC"
        );

        // Today count per bot
        $today = $wpdb->get_results($wpdb->prepare(
            "SELECT bot, COUNT(*) as cnt FROM $table
             WHERE visited_at >= %s GROUP BY bot",
            date('Y-m-d 00:00:00')
        ));
        $today_map = [];
        foreach ($today as $row) {
            $today_map[$row->bot] = (int)$row->cnt;
        }

        // Daily totals (last 7 days)
        $daily = $wpdb->get_results(
            "SELECT DATE(visited_at) as day, COUNT(*) as cnt
             FROM $table
             WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(visited_at) ORDER BY day"
        );

        // Top pages
        $pages = $wpdb->get_results(
            "SELECT path, bot, COUNT(*) as cnt FROM $table
             GROUP BY path, bot ORDER BY cnt DESC LIMIT 50"
        );

        // Recent visits
        $recent = $wpdb->get_results(
            "SELECT bot, org, path, visited_at FROM $table
             ORDER BY visited_at DESC LIMIT 100"
        );

        // Summary
        $total_visits = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $unique_bots = $wpdb->get_var("SELECT COUNT(DISTINCT bot) FROM $table");
        $today_total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE visited_at >= %s",
            date('Y-m-d 00:00:00')
        ));
        $week_total = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        wp_send_json_success([
            'summary' => [
                'total_visits' => (int)$total_visits,
                'unique_bots'  => (int)$unique_bots,
                'today'        => (int)$today_total,
                'week'         => (int)$week_total,
            ],
            'totals'   => $totals,
            'today_map'=> $today_map,
            'daily'    => $daily,
            'pages'    => $pages,
            'recent'   => $recent,
        ]);
    }

    public static function render_admin_page() {
        ?>
        <style>
            #aict-app { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1100px; }
            .aict-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
            .aict-stat { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; text-align: center; }
            .aict-stat .num { font-size: 2rem; font-weight: 800; color: #1e293b; }
            .aict-stat .lbl { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
            .aict-stat:nth-child(1) { border-top: 3px solid #f59e0b; }
            .aict-stat:nth-child(2) { border-top: 3px solid #3b82f6; }
            .aict-stat:nth-child(3) { border-top: 3px solid #10b981; }
            .aict-stat:nth-child(4) { border-top: 3px solid #8b5cf6; }
            .aict-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; margin-bottom: 24px; }
            .aict-card { background: #fff; border: 1px solid #e8ecf1; border-radius: 10px; padding: 14px; border-left: 4px solid #e0e0e0; }
            .aict-card.active { border-left-color: #10b981; }
            .aict-card .name { font-weight: 700; font-size: 0.95rem; }
            .aict-card .org { font-size: 0.75rem; color: #64748b; }
            .aict-card .count { font-size: 1.6rem; font-weight: 800; margin: 4px 0; }
            .aict-card .meta { font-size: 0.75rem; color: #10b981; }
            .aict-card .meta.zero { color: #94a3b8; }
            .aict-card .seen { font-size: 0.65rem; color: #94a3b8; float: right; margin-top: -28px; }
            .aict-section { background: #fff; border: 1px solid #e8ecf1; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
            .aict-section h3 { margin: 0 0 12px; font-size: 1rem; }
            .aict-bar { display: flex; align-items: center; margin: 6px 0; }
            .aict-bar .day { width: 80px; font-size: 0.8rem; color: #64748b; }
            .aict-bar .track { flex: 1; height: 24px; background: #f1f5f9; border-radius: 4px; overflow: hidden; margin: 0 8px; }
            .aict-bar .fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #fbbf24); border-radius: 4px; }
            .aict-bar .val { width: 40px; text-align: right; font-weight: 700; font-size: 0.85rem; }
            table.aict-tbl { width: 100%; border-collapse: collapse; }
            table.aict-tbl th, table.aict-tbl td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; }
            table.aict-tbl th { color: #64748b; font-size: 0.75rem; text-transform: uppercase; }
            .aict-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 600; background: rgba(245,158,11,0.12); color: #d97706; }
            .aict-log { max-height: 350px; overflow-y: auto; }
        </style>
        <div id="aict-app">
            <h1>AI Crawler Tracker</h1>
            <p style="color: #64748b; margin-bottom: 20px;">Tracking AI bot visits to <?php echo esc_html(home_url()); ?></p>
            <div id="aict-content"><p>Loading...</p></div>
        </div>
        <script>
        jQuery(function($){
            $.post(ajaxurl, { action: 'aict_get_data' }, function(resp){
                if (!resp.success) { $('#aict-content').html('<p>Error loading data</p>'); return; }
                var d = resp.data;
                var s = d.summary;
                var html = '';

                // Stats
                html += '<div class="aict-stats">';
                html += '<div class="aict-stat"><div class="num">'+s.unique_bots+'</div><div class="lbl">AI Crawlers</div></div>';
                html += '<div class="aict-stat"><div class="num">'+s.total_visits.toLocaleString()+'</div><div class="lbl">Total Visits</div></div>';
                html += '<div class="aict-stat"><div class="num">'+s.today.toLocaleString()+'</div><div class="lbl">Today</div></div>';
                html += '<div class="aict-stat"><div class="num">'+s.week.toLocaleString()+'</div><div class="lbl">Last 7 Days</div></div>';
                html += '</div>';

                // Bot cards
                html += '<h2>Live AI Crawler Activity</h2>';
                html += '<div class="aict-grid">';
                var tm = d.today_map || {};
                (d.totals || []).forEach(function(b){
                    var td = tm[b.bot] || 0;
                    var ago = b.last_seen ? timeAgo(b.last_seen) : '';
                    html += '<div class="aict-card active">';
                    html += '<div class="name">'+b.bot+'</div>';
                    html += '<div class="org">'+b.org+'</div>';
                    html += '<div class="count">'+Number(b.total).toLocaleString()+'</div>';
                    html += '<div class="meta '+(td?'':'zero')+'">'+(td?'+'+td+' today':'No visits today')+'</div>';
                    html += '<div class="seen">'+ago+'</div>';
                    html += '</div>';
                });
                html += '</div>';

                // Daily chart
                html += '<div class="aict-section"><h3>Daily Activity (Last 7 Days)</h3>';
                var maxD = Math.max.apply(null, (d.daily||[]).map(function(x){return x.cnt;})) || 1;
                (d.daily||[]).forEach(function(x){
                    var pct = (x.cnt / maxD * 100).toFixed(1);
                    html += '<div class="aict-bar"><div class="day">'+x.day.slice(5)+'</div><div class="track"><div class="fill" style="width:'+pct+'%"></div></div><div class="val">'+x.cnt+'</div></div>';
                });
                html += '</div>';

                // Top pages
                html += '<div class="aict-section"><h3>Most Crawled Pages</h3><table class="aict-tbl"><thead><tr><th>Page</th><th>Crawler</th><th>Hits</th></tr></thead><tbody>';
                (d.pages||[]).slice(0,30).forEach(function(p){
                    html += '<tr><td><code>'+p.path+'</code></td><td><span class="aict-badge">'+p.bot+'</span></td><td><strong>'+p.cnt+'</strong></td></tr>';
                });
                html += '</tbody></table></div>';

                // Recent visits
                html += '<div class="aict-section"><h3>Recent AI Visits</h3><div class="aict-log"><table class="aict-tbl"><thead><tr><th>Time</th><th>Bot</th><th>Page</th></tr></thead><tbody>';
                (d.recent||[]).forEach(function(v){
                    html += '<tr><td style="white-space:nowrap;color:#94a3b8;">'+v.visited_at+'</td><td><span class="aict-badge">'+v.bot+'</span></td><td><code style="font-size:0.78rem;">'+v.path+'</code></td></tr>';
                });
                html += '</tbody></table></div></div>';

                $('#aict-content').html(html);
            });

            function timeAgo(ts) {
                var diff = Date.now() - new Date(ts.replace(' ','T')+'Z').getTime();
                var m = Math.floor(diff/60000);
                if (m < 1) return 'just now';
                if (m < 60) return m+'m ago';
                var h = Math.floor(m/60);
                if (h < 24) return h+'h ago';
                return Math.floor(h/24)+'d ago';
            }
        });
        </script>
        <?php
    }
}

AI_Crawler_Tracker::init();
