<?php
/*
Plugin Name: WP Site Audit
Description: A plugin to audit site performance, plugins, security, and optimization issues.
Version: 1.2
Author: Melven
*/

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Add admin menu
function sap_add_admin_menu() {
    add_menu_page('WP Site Audit', 'WP Site Audit', 'manage_options', 'wp-site-audit', 'sap_admin_page');
}
add_action('admin_menu', 'sap_add_admin_menu');

// Admin page content
function sap_admin_page() {
    ?>
    <div class="wrap">
        <h1>Site Audit Pro</h1>
        <button id="sap-run-audit" class="button button-primary">Run Audit</button>
        <div id="sap-report"></div>
        <button id="sap-download-report" class="button button-secondary" style="display:none;">Download Report</button>

        <style>
            table.sap-report-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                font-family: Arial, sans-serif;
            }
            table.sap-report-table th, table.sap-report-table td {
                border: 1px solid #ccc;
                padding: 10px;
                text-align: left;
            }
            table.sap-report-table th {
                background-color: #f4f4f4;
                font-weight: bold;
            }
            table.sap-report-table tr:nth-child(even) {
                background-color: #fafafa;
            }
            .sap-priority-high { background-color: #ffdddd; }
            .sap-priority-medium { background-color: #fff8e1; }
            .sap-priority-low { background-color: #e8f5e9; }
        </style>
    </div>

    <script>
        document.getElementById('sap-run-audit').addEventListener('click', function () {
            fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=sap_run_audit")
                .then(response => response.text())
                .then(data => {
                    document.getElementById('sap-report').innerHTML = data;
                    document.getElementById('sap-download-report').style.display = 'inline-block';
                });
        });

        document.getElementById('sap-download-report').addEventListener('click', function () {
            window.location.href = "<?php echo admin_url('admin-ajax.php'); ?>?action=sap_download_report";
        });
    </script>
    <?php
}

// Run the audit
function sap_run_audit() {
    ob_start();
    ?>
    <table class="sap-report-table">
        <tr><th colspan="2">PHP Version</th></tr>
        <tr><td colspan="2"><?php echo phpversion(); ?></td></tr>

        <tr><th colspan="2" class="sap-priority-high">High Priority (Immediate Action Required)</th></tr>
        <?php
        $active_plugins = get_option('active_plugins');
        $plugins = get_plugins();
        $conflicts = [];
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (stripos($plugin_data['Name'], 'SEO') !== false || stripos($plugin_data['Name'], 'Cache') !== false) {
                $conflicts[] = $plugin_data['Name'];
            }
        }
        if (!empty($conflicts)) {
            echo '<tr><td>Conflicting Plugins</td><td>' . implode(', ', $conflicts) . '</td></tr>';
        }

        $inactive_plugins = array_diff(array_keys($plugins), $active_plugins);
        if (!empty($inactive_plugins)) {
            echo '<tr><td>Inactive Plugins</td><td>' . implode(', ', array_map(fn($plugin) => $plugins[$plugin]['Name'], $inactive_plugins)) . '</td></tr>';
        }

        $security_plugins = ['wordfence/wordfence.php', 'sucuri-scanner/sucuri.php'];
        $security_found = false;
        foreach ($active_plugins as $plugin) {
            if (in_array($plugin, $security_plugins)) {
                $security_found = true;
            }
        }
        if (!$security_found) {
            echo '<tr><td>No Security Plugin Found</td><td>Recommending Wordfence or Sucuri</td></tr>';
        }

        $spam_count = get_comments(['status' => 'spam', 'count' => true]);
        if ($spam_count > 0) {
            echo "<tr><td>Spam Comments Detected</td><td>$spam_count spam comments. Recommending the use of WP-Optimize or similar plugins.</td></tr>";
        }

        $trash_count = wp_count_posts()->trash;
        $draft_count = wp_count_posts()->draft;
        if ($trash_count > 0 || $draft_count > 0) {
            echo "<tr><td>Trash/Drafts</td><td>$trash_count in Trash, $draft_count drafts. Recommending cleanup.</td></tr>";
        }
        ?>

        <tr><th colspan="2" class="sap-priority-medium">Medium Priority (Performance & Storage Optimization)</th></tr>
        <?php
        if (!class_exists('Autoptimize')) {
            echo '<tr><td>Minification</td><td>Not enabled. Recommending Autoptimize or similar plugin.</td></tr>';
        }

        if (!defined('CDN_ENABLED')) {
            echo '<tr><td>CDN</td><td>No CDN detected. Recommending Cloudflare or BunnyCDN.</td></tr>';
        }

        if (!get_theme_mod('lazy_load')) {
            echo '<tr><td>Lazy Loading</td><td>Not enabled. Recommendation: Use a plugin that enables lazy load.</td></tr>';
        }
        ?>

        <tr><th colspan="2" class="sap-priority-low">Low Priority (Advanced Optimization)</th></tr>
        <?php
        global $wpdb;
        $orphaned_posts = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'auto-draft'");
        if ($orphaned_posts > 0) {
            echo "<tr><td>Orphaned Database Entries</td><td>$orphaned_posts entries found. Recommending cleanup.</td></tr>";
        }
        ?>
    </table>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_sap_run_audit', 'sap_run_audit');

// Download report
function sap_download_report() {
    header('Content-type: text/html');
    header('Content-Disposition: attachment; filename="site-audit-report.html"');

    sap_run_audit();
    die();
}
add_action('wp_ajax_sap_download_report', 'sap_download_report');
