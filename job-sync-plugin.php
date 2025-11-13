<?php
/**
 * Plugin Name: SmartRecruiters Sync
 * Plugin URI: http://intuitivehealth.com
 * Description: Syncs jobs from SmartRecruiters API and manages them as custom post types with automatic add/remove functionality.
 * Version: 1.0.0
 * Author: Hridoy Ahmed
 * License: GPL v2 or later
 * Text Domain: smartrecruiters-job-sync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JOB_SYNC_PLUGIN_VERSION', '1.0.0');
define('JOB_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JOB_SYNC_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main SmartRecruiters Job Sync Plugin Class
 */
class SmartRecruitersJobSyncPlugin
{

    private $last_sync_error = '';
    private $last_synced_title = '';

    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));

        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Cron hooks
        add_action('smartrecruiters_job_sync_cron', array($this, 'sync_jobs'), 10, 1);


        // AJAX hooks for manual sync
        add_action('wp_ajax_manual_smartrecruiters_sync', array($this, 'manual_sync_ajax'));

        // AJAX hooks for webhook management
        add_action('wp_ajax_create_smartrecruiters_webhook', array($this, 'create_webhook_ajax'));
        add_action('wp_ajax_delete_smartrecruiters_webhook', array($this, 'delete_webhook_ajax'));
        add_action('wp_ajax_refresh_smartrecruiters_webhooks', array($this, 'refresh_webhooks_ajax'));
        add_action('wp_ajax_activate_smartrecruiters_webhook', array($this, 'activate_webhook_ajax'));

        // Webhook endpoint for real-time job updates
        add_filter('query_vars', array($this, 'add_webhook_query_var'));
        add_action('init', array($this, 'add_webhook_endpoint'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));
        add_action('smartrecruiters_retry_job_sync', array($this, 'retry_job_sync'), 10, 1);
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        $this->register_job_post_type();
        $this->add_custom_fields();
    }


    /**
     * Register custom post type for jobs
     */
    public function register_job_post_type()
    {
        $labels = array(
            'name' => 'Jobs',
            'singular_name' => 'Job',
            'menu_name' => 'Jobs',
            'add_new' => 'Add New Job',
            'add_new_item' => 'Add New Job',
            'edit_item' => 'Edit Job',
            'new_item' => 'New Job',
            'view_item' => 'View Job',
            'search_items' => 'Search Jobs',
            'not_found' => 'No jobs found',
            'not_found_in_trash' => 'No jobs found in trash',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'jobs'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-businessman',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true,
        );

        register_post_type('job', $args);
    }

    /**
     * Add custom fields metabox
     */
    public function add_custom_fields()
    {
        add_action('add_meta_boxes', array($this, 'add_job_meta_boxes'));
        add_action('save_post', array($this, 'save_job_meta'));
    }

    /**
     * Add metaboxes for job custom fields
     */
    public function add_job_meta_boxes()
    {
        add_meta_box(
            'job_details',
            'Job Details',
            array($this, 'job_details_callback'),
            'job',
            'normal',
            'high'
        );

        add_meta_box(
            'job_sync_info',
            'SmartRecruiters Sync Information',
            array($this, 'job_sync_info_callback'),
            'job',
            'side',
            'default'
        );
    }

    /**
     * Job details metabox callback
     */
    public function job_details_callback($post)
    {
        wp_nonce_field('job_meta_nonce', 'job_meta_nonce');

        $ref_number = get_post_meta($post->ID, '_job_ref_number', true);
        $status = get_post_meta($post->ID, '_job_status', true);
        $posting_status = get_post_meta($post->ID, '_job_posting_status', true);
        $department = get_post_meta($post->ID, '_job_department', true);
        $location = get_post_meta($post->ID, '_job_location', true);
        $language = get_post_meta($post->ID, '_job_language', true);
        $city = get_post_meta($post->ID, '_job_city', true);
        $country_code = get_post_meta($post->ID, '_job_country_code', true);
        $region_code = get_post_meta($post->ID, '_job_region_code', true);
        $postal_code = get_post_meta($post->ID, '_job_postal_code', true);
        $remote = get_post_meta($post->ID, '_job_remote', true);
        $apply_url = get_post_meta($post->ID, '_job_apply_url', true);
        $external_id = get_post_meta($post->ID, '_job_external_id', true);
        $api_url = get_post_meta($post->ID, '_job_api_url', true);
        $experience_level = get_post_meta($post->ID, '_job_experience_level', true);
        $location_full = get_post_meta($post->ID, '_job_location_full', true);
        $actions_full = get_post_meta($post->ID, '_job_actions_full', true);
        $job_ad_full = get_post_meta($post->ID, '_job_ad_full', true);
        $job_ad_company_description_title = get_post_meta($post->ID, '_job_ad_company_description_title', true);
        $job_ad_company_description_text = get_post_meta($post->ID, '_job_ad_company_description_text', true);
        $job_ad_job_description_title = get_post_meta($post->ID, '_job_ad_job_description_title', true);
        $job_ad_job_description_text = get_post_meta($post->ID, '_job_ad_job_description_text', true);
        $job_ad_qualifications_title = get_post_meta($post->ID, '_job_ad_qualifications_title', true);
        $job_ad_qualifications_text = get_post_meta($post->ID, '_job_ad_qualifications_text', true);
        $job_ad_additional_information_title = get_post_meta($post->ID, '_job_ad_additional_information_title', true);
        $job_ad_additional_information_text = get_post_meta($post->ID, '_job_ad_additional_information_text', true);
        $job_ad_videos_urls = get_post_meta($post->ID, '_job_ad_videos_urls', true);
        $job_partners_name = get_post_meta($post->ID, '_job_partners_name', true);
        $job_properties_full = get_post_meta($post->ID, '_job_properties_full', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="job_ref_number">Reference Number</label></th>
                <td><input type="text" id="job_ref_number" name="job_ref_number" value="<?php echo esc_attr($ref_number); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_status">Status</label></th>
                <td><input type="text" id="job_status" name="job_status" value="<?php echo esc_attr($status); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_posting_status">Posting Status</label></th>
                <td><input type="text" id="job_posting_status" name="job_posting_status"
                        value="<?php echo esc_attr($posting_status); ?>" style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_department">Department</label></th>
                <td><input type="text" id="job_department" name="job_department" value="<?php echo esc_attr($department); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_location">Location</label></th>
                <td><input type="text" id="job_location" name="job_location" value="<?php echo esc_attr($location); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_city">City</label></th>
                <td><input type="text" id="job_city" name="job_city" value="<?php echo esc_attr($city); ?>" style="width: 100%;"
                        readonly /></td>
            </tr>
            <tr>
                <th><label for="job_country_code">Country Code</label></th>
                <td><input type="text" id="job_country_code" name="job_country_code"
                        value="<?php echo esc_attr($country_code); ?>" style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_region_code">Region Code</label></th>
                <td><input type="text" id="job_region_code" name="job_region_code" value="<?php echo esc_attr($region_code); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_postal_code">Postal Code</label></th>
                <td><input type="text" id="job_postal_code" name="job_postal_code" value="<?php echo esc_attr($postal_code); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_language">Language</label></th>
                <td><input type="text" id="job_language" name="job_language" value="<?php echo esc_attr($language); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_remote">Remote Work</label></th>
                <td><input type="text" id="job_remote" name="job_remote" value="<?php echo esc_attr($remote); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_apply_url">Apply URL</label></th>
                <td><input type="url" id="job_apply_url" name="job_apply_url" value="<?php echo esc_attr($apply_url); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_external_id">SmartRecruiters Job ID</label></th>
                <td><input type="text" id="job_external_id" name="job_external_id" value="<?php echo esc_attr($external_id); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_api_url">API URL</label></th>
                <td><input type="url" id="job_api_url" name="job_api_url" value="<?php echo esc_attr($api_url); ?>"
                        style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_experience_level">Experience Level</label></th>
                <td><input type="text" id="job_experience_level" name="job_experience_level"
                        value="<?php echo esc_attr($experience_level); ?>" style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_location_full">Location (Full Object)</label></th>
                <td><textarea id="job_location_full" name="job_location_full" style="width: 100%; height: 100px;"
                        readonly><?php echo esc_textarea($location_full); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="job_actions_full">Actions (Full Object)</label></th>
                <td><textarea id="job_actions_full" name="job_actions_full" style="width: 100%; height: 100px;"
                        readonly><?php echo esc_textarea($actions_full); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="job_ad_full">Job Ad (Full Object)</label></th>
                <td><textarea id="job_ad_full" name="job_ad_full" style="width: 100%; height: 150px;"
                        readonly><?php echo esc_textarea($job_ad_full); ?></textarea></td>
            </tr>
            <tr>
                <th><label>Company Description</label></th>
                <td>
                    <input type="text" value="<?php echo esc_attr($job_ad_company_description_title); ?>" style="width: 100%;"
                        readonly />
                    <textarea style="width: 100%; height: 120px;"
                        readonly><?php echo esc_textarea($job_ad_company_description_text); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label>Job Description</label></th>
                <td>
                    <input type="text" value="<?php echo esc_attr($job_ad_job_description_title); ?>" style="width: 100%;"
                        readonly />
                    <textarea style="width: 100%; height: 120px;"
                        readonly><?php echo esc_textarea($job_ad_job_description_text); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label>Qualifications</label></th>
                <td>
                    <input type="text" value="<?php echo esc_attr($job_ad_qualifications_title); ?>" style="width: 100%;"
                        readonly />
                    <textarea style="width: 100%; height: 120px;"
                        readonly><?php echo esc_textarea($job_ad_qualifications_text); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label>Additional Information</label></th>
                <td>
                    <input type="text" value="<?php echo esc_attr($job_ad_additional_information_title); ?>"
                        style="width: 100%;" readonly />
                    <textarea style="width: 100%; height: 120px;"
                        readonly><?php echo esc_textarea($job_ad_additional_information_text); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label>Videos (URLs)</label></th>
                <td><textarea style="width: 100%; height: 80px;"
                        readonly><?php echo esc_textarea($job_ad_videos_urls); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="job_partners_name">Partners Name</label></th>
                <td><input type="text" id="job_partners_name" name="job_partners_name"
                        value="<?php echo esc_attr($job_partners_name); ?>" style="width: 100%;" readonly /></td>
            </tr>
            <tr>
                <th><label for="job_properties_full">Properties (Full Object)</label></th>
                <td><textarea id="job_properties_full" name="job_properties_full" style="width: 100%; height: 150px;"
                        readonly><?php echo esc_textarea($job_properties_full); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Job sync info metabox callback
     */
    public function job_sync_info_callback($post)
    {
        $last_synced = get_post_meta($post->ID, '_job_last_synced', true);
        $sync_status = get_post_meta($post->ID, '_job_sync_status', true);

        ?>
        <p><strong>Last Synced:</strong><br>
            <?php echo $last_synced ? date('Y-m-d H:i:s', $last_synced) : 'Never'; ?></p>

        <p><strong>Sync Status:</strong><br>
            <?php echo esc_html($sync_status ?: 'Unknown'); ?></p>

        <p><em>This job is managed by the SmartRecruiters Job Sync Plugin. Manual edits may be overwritten during sync.</em></p>
        <?php
    }

    /**
     * Save job meta data
     */
    public function save_job_meta($post_id)
    {
        if (!isset($_POST['job_meta_nonce']) || !wp_verify_nonce($_POST['job_meta_nonce'], 'job_meta_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'job_company' => '_job_company',
            'job_location' => '_job_location',
            'job_department' => '_job_department',
            'job_experience_level' => '_job_experience_level',
            'job_employment_type' => '_job_employment_type',
            'job_remote' => '_job_remote',
            'job_salary' => '_job_salary',
            'job_apply_url' => '_job_apply_url',
            'job_external_id' => '_job_external_id',
            'job_postal_code' => '_job_postal_code'
        );

        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            'SmartRecruiters Job Sync',
            'Job Sync',
            'manage_options',
            'smartrecruiters-job-sync',
            array($this, 'admin_page'),
            'dashicons-update',
            30
        );
    }

    /**
     * Admin page callback
     */
    public function admin_page()
    {
        ?>
        <div class="wrap">
            <h1>SmartRecruiters Job Sync Settings</h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('smartrecruiters_job_sync_settings');
                do_settings_sections('smartrecruiters_job_sync_settings');
                submit_button();
                ?>
            </form>

            <hr>

            <h2>Manual Sync</h2>
            <div style="margin-bottom:8px;">
                <label>
                    <input type="checkbox" id="exclude-cancelled-jobs" checked>
                    Exclude cancelled jobs when syncing manually
                </label>
            </div>
            <button type="button" id="manual-sync-btn" class="button button-primary">Sync Jobs Now</button>
            <div id="sync-status" style="margin-top: 10px;"></div>
            <?php
            // Check if we have any active webhooks for this site
            $webhooks_check = get_option('smartrecruiters_webhooks_list', array());
            $webhook_url_check = home_url('/smartrecruiters-webhook/');
            $has_active_webhook_for_log = false;

            foreach ($webhooks_check as $webhook) {
                $callback_url = $webhook['callbackUrl'] ?? '';
                $status = $webhook['status'] ?? 'inactive';
                if (strpos($callback_url, $webhook_url_check) !== false && $status === 'active') {
                    $has_active_webhook_for_log = true;
                    break;
                }
            }

            // Hide log box when webhook is active
            if (!$has_active_webhook_for_log): ?>
                <textarea id="sync-log" style="margin-top:10px;width:100%;height:180px;white-space:pre;" readonly
                    placeholder="Logs will appear here..."></textarea>
            <?php endif; ?>

            <hr>
            <h2>Webhook Management</h2>
            <p>Manage real-time webhook subscriptions for instant job updates.</p>

            <?php
            $webhook_id = get_option('smartrecruiters_webhook_id');
            $webhook_secret = get_option('smartrecruiters_webhook_secret');
            $webhook_url = home_url('/smartrecruiters-webhook/');
            ?>

            <?php
            // Check if we have any active webhooks for this site
            $webhooks = get_option('smartrecruiters_webhooks_list', array());
            $webhook_url = home_url('/smartrecruiters-webhook/');
            $has_active_webhook = false;

            foreach ($webhooks as $webhook) {
                $callback_url = $webhook['callbackUrl'] ?? '';
                $status = $webhook['status'] ?? 'inactive';
                if (strpos($callback_url, $webhook_url) !== false && $status === 'active') {
                    $has_active_webhook = true;
                    break;
                }
            }
            ?>

            <div style="background: #f0f0f1; padding: 15px; margin: 15px 0; border-left: 4px solid #2271b1;">
                <?php if ($webhook_secret || $has_active_webhook): ?>
                    <p><strong>Webhook Status:</strong> <span style="color:green;">✅ Active</span></p>
                <?php else: ?>
                    <p><strong>Webhook Status:</strong> <span style="color:orange;">⏳ Inactive</span></p>
                <?php endif; ?>
            </div>

            <?php
            // Hide create webhook button when we have active webhook for this site
            if (!$has_active_webhook): ?>
                <button type="button" id="create-webhook-btn" class="button button-secondary">Create Webhook Subscription</button>
            <?php endif; ?>

            <button type="button" id="refresh-webhooks-btn" class="button button-secondary">Refresh Webhook List</button>
            <button type="button" id="delete-webhook-btn" class="button button-secondary">Delete Webhook Subscription</button>
            <div id="webhook-status" style="margin-top: 10px;"></div>

            <div id="webhook-list" style="margin-top: 20px;">
                <?php
                // Auto-load webhooks on page load (silently, don't show error if fails)
                $this->get_all_webhooks();
                $this->display_webhook_list();
                ?>
            </div>


            <hr>
            <h2>Webhook Activity Log</h2>
            <p>Recent webhook events and job updates:</p>
            <div id="webhook-activity-log"
                style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                <?php
                $webhook_logs = get_option('smartrecruiters_webhook_logs', array());
                if (empty($webhook_logs)) {
                    echo '<p style="color: #666;">No webhook activity yet. Activity will appear here when jobs are created, updated, or deleted in SmartRecruiters.</p>';
                } else {
                    $display_logs = array_slice($webhook_logs, 0, 10);
                    echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 0;">';
                    echo '<thead><tr>';
                    echo '<th style="width: 150px;">Time</th>';
                    echo '<th style="width: 180px;">Event</th>';
                    echo '<th style="width: 100px;">Job ID</th>';
                    echo '<th>Job Title</th>';
                    echo '<th style="width: 100px;">Status</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($display_logs as $log) {
                        $time = date('Y-m-d H:i:s', $log['timestamp']);
                        $status_color = 'green';
                        if (strpos($log['status'], 'failed') !== false || $log['status'] === 'unknown_event') {
                            $status_color = 'red';
                        } elseif ($log['status'] === 'received') {
                            $status_color = 'blue';
                        }

                        echo '<tr>';
                        echo '<td>' . esc_html($time) . '</td>';
                        echo '<td><strong>' . esc_html($log['event_label']) . '</strong></td>';
                        echo '<td><code>' . esc_html($log['job_id']) . '</code></td>';
                        echo '<td>' . esc_html($log['job_title']) . '</td>';
                        echo '<td><span style="color: ' . esc_attr($status_color) . ';">' . esc_html($log['status_label']) . '</span>';
                        if (!empty($log['details'])) {
                            echo '<br><small style="color:#666;">' . esc_html($log['details']) . '</small>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';

                    if (count($webhook_logs) > 10) {
                        $remaining = count($webhook_logs) - 10;
                        echo '<p class="description" style="margin-top: 10px; color:#666;">';
                        echo 'Showing latest 10 events. ' . esc_html($remaining) . ' older entries hidden.';
                        echo '</p>';
                    }
                }
                ?>
            </div>


            <hr>
            <h2>Sync Status</h2>
            <div id="sync-status-info">
                <?php
                $opts = get_option('smartrecruiters_job_sync_options');
                $webhooks_active = !empty($opts['webhook_enabled']);

                if ($webhooks_active): ?>
                    <p><strong>Real-time sync active via webhooks</strong></p>
                    <p>Jobs will update instantly when changed in SmartRecruiters.</p>
                <?php else: ?>
                    <p><strong>Manual sync only</strong></p>
                    <p>Use the "Sync Jobs Now" button above to update jobs manually.</p>
                <?php endif; ?>
            </div>

            <hr>
            <h2>Cron Schedule Status</h2>
            <div id="cron-status">
                <?php
                $next_scheduled = wp_next_scheduled('smartrecruiters_job_sync_cron');
                if ($next_scheduled) {
                    $next_run = date('Y-m-d H:i:s', $next_scheduled);
                    $time_until = $next_scheduled - time();
                    $hours_until = round($time_until / 3600, 1);

                    echo '<p><strong>Next Scheduled Run:</strong> ' . esc_html($next_run) . '</p>';
                    if ($time_until > 0) {
                        echo '<p><strong>Time Until Next Run:</strong> ' . esc_html($hours_until) . ' hours</p>';
                    } else {
                        echo '<p><strong style="color:orange;">Warning:</strong> Next run is overdue. Cron will run on next site visit.</p>';
                    }
                } else {
                    echo '<p><strong style="color:red;">Cron is not scheduled!</strong> It will be rescheduled automatically.</p>';
                }
                ?>
                <p class="description"><em>Note: WordPress cron jobs only run when someone visits your website. For guaranteed
                        daily runs, consider setting up a real server cron job to visit your site daily, or use webhooks for
                        real-time updates.</em></p>
            </div>

            <hr>
            <h2>Last Run</h2>
            <div id="last-run">
                <?php
                $last = get_option('smartrecruiters_last_run');
                if ($last) {
                    echo '<p><strong>Time:</strong> ' . date('Y-m-d H:i:s', intval($last['timestamp'])) . '</p>';
                    echo '<p><strong>Status:</strong> ' . (!empty($last['success']) ? '<span style="color:green;">Success</span>' : '<span style="color:red;">Failed</span>') . '</p>';
                    echo '<p><strong>Message:</strong> ' . esc_html($last['message'] ?? '') . '</p>';
                    $lr_logs = isset($last['logs']) ? $last['logs'] : array();
                    echo '<textarea style="width:100%;height:180px;white-space:pre;" readonly>' . esc_textarea(is_array($lr_logs) ? implode("\n", $lr_logs) : $lr_logs) . '</textarea>';
                } else {
                    echo '<p>No runs recorded yet.</p>';
                }
                ?>
            </div>

            <script>
                document.getElementById('manual-sync-btn').addEventListener('click', function () {
                    var btn = this;
                    var status = document.getElementById('sync-status');
                    var excludeCancelledToggle = document.getElementById('exclude-cancelled-jobs');

                    var params = new URLSearchParams();
                    params.append('action', 'manual_smartrecruiters_sync');
                    params.append('nonce', '<?php echo wp_create_nonce('manual_smartrecruiters_sync_nonce'); ?>');
                    if (excludeCancelledToggle && excludeCancelledToggle.checked) {
                        params.append('exclude_cancelled', '1');
                    }

                    btn.disabled = true;
                    btn.textContent = 'Syncing...';
                    status.innerHTML = '<p>Starting sync...</p>';

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params.toString()
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                status.innerHTML = '<p style="color: green;">' + (data.data.message || 'Sync completed') + '</p>';
                            } else {
                                status.innerHTML = '<p style="color: red;">' + (data.data.message || 'Sync failed') + '</p>';
                            }
                            var syncLog = document.getElementById('sync-log');
                            if (syncLog) {
                                var logs = (data.data && data.data.logs) ? data.data.logs : [];
                                syncLog.value = Array.isArray(logs) ? logs.join('\n') : (logs || '');
                            }
                        })
                        .catch(error => {
                            status.innerHTML = '<p style="color: red;">Request error: ' + error.message + '</p>';
                            var syncLog = document.getElementById('sync-log');
                            if (syncLog) {
                                syncLog.value = 'Request error: ' + error.message;
                            }
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.textContent = 'Sync Jobs Now';
                        });



                    // fetch(ajaxurl, {
                    //     method: 'POST',
                    //     headers: {
                    //         'Content-Type': 'application/x-www-form-urlencoded',
                    //     },
                    //     body: 'action=manual_smartrecruiters_sync&nonce=' + '<?php //echo wp_create_nonce('manual_smartrecruiters_sync_nonce'); ?>'
                    // })
                    //     .then(response => response.json())
                    //     .then(data => {
                    //         // wnat to show the message in a console.log
                    //         console.log(data);
                    //         if (data.success) {
                    //             status.innerHTML = '<p style="color: green;">' + data.data.message + '</p>';
                    //         } else {
                    //             status.innerHTML = '<p style="color: red;">Error: ' + data.data + '</p>';
                    //         }
                    //     })
                    //     .catch(error => {
                    //         status.innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
                    //     })
                    //     .finally(() => {
                    //         btn.disabled = false;
                    //         btn.textContent = 'Sync Jobs Now';
                    //     });
                });

                // Webhook management
                var createWebhookBtn = document.getElementById('create-webhook-btn');
                if (createWebhookBtn) {
                    createWebhookBtn.addEventListener('click', function () {
                        var btn = this;
                        var status = document.getElementById('webhook-status');

                        btn.disabled = true;
                        btn.textContent = 'Creating...';
                        status.innerHTML = '<p>Creating webhook subscription...</p>';

                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=create_smartrecruiters_webhook&nonce=<?php echo wp_create_nonce('webhook_management_nonce'); ?>'
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    status.innerHTML = '<p style="color: green;">' + (data.data.message || 'Webhook created successfully') + '</p>';
                                    setTimeout(function () { location.reload(); }, 2000);
                                } else {
                                    status.innerHTML = '<p style="color: red;">' + (data.data.message || 'Failed to create webhook') + '</p>';
                                }
                            })
                            .catch(error => {
                                status.innerHTML = '<p style="color: red;">Request error: ' + error.message + '</p>';
                            })
                            .finally(() => {
                                btn.disabled = false;
                                btn.textContent = 'Create Webhook Subscription';
                            });
                    });
                }

                // Refresh webhooks list
                document.getElementById('refresh-webhooks-btn').addEventListener('click', function () {
                    var btn = this;
                    var status = document.getElementById('webhook-status');

                    btn.disabled = true;
                    btn.textContent = 'Refreshing...';
                    status.innerHTML = '<p>Refreshing webhook list...</p>';

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=refresh_smartrecruiters_webhooks&nonce=<?php echo wp_create_nonce('webhook_management_nonce'); ?>'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                status.innerHTML = '<p style="color: green;">' + (data.data.message || 'Webhooks refreshed successfully') + '</p>';
                                setTimeout(function () { location.reload(); }, 1000);
                            } else {
                                status.innerHTML = '<p style="color: red;">' + (data.data.message || 'Failed to refresh webhooks') + '</p>';
                            }
                        })
                        .catch(error => {
                            status.innerHTML = '<p style="color: red;">Request error: ' + error.message + '</p>';
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.textContent = 'Refresh Webhook List';
                        });
                });

                // Delete webhook button
                document.getElementById('delete-webhook-btn').addEventListener('click', function () {
                    var btn = this;
                    var status = document.getElementById('webhook-status');

                    if (!confirm('Are you sure you want to delete the saved webhook subscription?')) {
                        return;
                    }

                    btn.disabled = true;
                    btn.textContent = 'Deleting...';
                    status.innerHTML = '<p>Deleting webhook subscription...</p>';

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=delete_smartrecruiters_webhook&nonce=<?php echo wp_create_nonce('webhook_management_nonce'); ?>'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                status.innerHTML = '<p style="color: green;">' + (data.data.message || 'Webhook deleted successfully') + '</p>';
                                setTimeout(function () { location.reload(); }, 2000);
                            } else {
                                status.innerHTML = '<p style="color: red;">' + (data.data.message || 'Failed to delete webhook') + '</p>';
                            }
                        })
                        .catch(error => {
                            status.innerHTML = '<p style="color: red;">Request error: ' + error.message + '</p>';
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.textContent = 'Delete Webhook Subscription';
                        });
                });

                // Activate webhook buttons (dynamic)
                document.addEventListener('click', function (e) {
                    if (e.target.classList.contains('activate-webhook-btn')) {
                        var btn = e.target;
                        var webhookId = btn.getAttribute('data-webhook-id');
                        var status = document.getElementById('webhook-status');

                        btn.disabled = true;
                        btn.textContent = 'Activating...';
                        status.innerHTML = '<p>Activating webhook...</p>';

                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=activate_smartrecruiters_webhook&webhook_id=' + encodeURIComponent(webhookId) + '&nonce=<?php echo wp_create_nonce('webhook_management_nonce'); ?>'
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    status.innerHTML = '<p style="color: green;">' + (data.data.message || 'Webhook activated successfully') + '</p>';
                                    setTimeout(function () { location.reload(); }, 2000);
                                } else {
                                    status.innerHTML = '<p style="color: red;">' + (data.data.message || 'Failed to activate webhook') + '</p>';
                                }
                            })
                            .catch(error => {
                                status.innerHTML = '<p style="color: red;">Request error: ' + error.message + '</p>';
                            })
                            .finally(() => {
                                btn.disabled = false;
                                btn.textContent = 'Activate';
                            });
                    }

                    // Delete webhook buttons (dynamic)
                    if (e.target.classList.contains('delete-webhook-btn')) {
                        var btn = e.target;
                        var webhookId = btn.getAttribute('data-webhook-id');
                        var status = document.getElementById('webhook-status');

                        if (!confirm('Are you sure you want to delete this webhook subscription?')) {
                            return;
                        }

                        btn.disabled = true;
                        btn.textContent = 'Deleting...';
                        status.innerHTML = '<p>Deleting webhook subscription...</p>';

                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=delete_smartrecruiters_webhook&webhook_id=' + encodeURIComponent(webhookId) + '&nonce=<?php echo wp_create_nonce('webhook_management_nonce'); ?>'
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    status.innerHTML = '<p style="color: green;">' + (data.data.message || 'Webhook deleted successfully') + '</p>';
                                    setTimeout(function () { location.reload(); }, 2000);
                                } else {
                                    status.innerHTML = '<p style="color: red;">' + (data.data.message || 'Failed to delete webhook') + '</p>';
                                }
                            })
                            .catch(error => {
                                status.innerHTML = '<p style="color: red;">Request error: ' + error.message + '</p>';
                            })
                            .finally(() => {
                                btn.disabled = false;
                                btn.textContent = btn.textContent.includes('Delete') ? 'Delete' : 'Delete';
                            });
                    }
                });



            </script>
        </div>
        <?php
    }

    /**
     * Admin init
     */
    public function admin_init()
    {
        // Ensure cron is scheduled when admin page loads
        $this->ensure_cron_scheduled();

        register_setting('smartrecruiters_job_sync_settings', 'smartrecruiters_job_sync_options');

        add_settings_section(
            'smartrecruiters_api_section',
            'SmartRecruiters API Configuration',
            array($this, 'api_section_callback'),
            'smartrecruiters_job_sync_settings'
        );

        add_settings_field(
            'api_url',
            'SmartRecruiters API URL',
            array($this, 'api_url_callback'),
            'smartrecruiters_job_sync_settings',
            'smartrecruiters_api_section'
        );

        add_settings_field(
            'client_id',
            'Client ID',
            array($this, 'client_id_callback'),
            'smartrecruiters_job_sync_settings',
            'smartrecruiters_api_section'
        );

        add_settings_field(
            'limit',
            'Limit',
            array($this, 'limit_callback'),
            'smartrecruiters_job_sync_settings',
            'smartrecruiters_api_section'
        );

        add_settings_field(
            'client_secret',
            'Client Secret',
            array($this, 'client_secret_callback'),
            'smartrecruiters_job_sync_settings',
            'smartrecruiters_api_section'
        );


        // Webhook settings section
        add_settings_section(
            'smartrecruiters_webhook_section',
            'Webhook Configuration',
            array($this, 'webhook_section_callback'),
            'smartrecruiters_job_sync_settings'
        );

        add_settings_field(
            'webhook_enabled',
            'Enable Webhooks',
            array($this, 'webhook_enabled_callback'),
            'smartrecruiters_job_sync_settings',
            'smartrecruiters_webhook_section'
        );


    }

    /**
     * API section callback
     */
    public function api_section_callback()
    {
        echo '<p>Configure your SmartRecruiters API credentials and settings below.</p>';
    }

    /**
     * API URL callback
     */
    public function api_url_callback()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $value = isset($options['api_url']) ? $options['api_url'] : 'https://api.sandbox.smartrecruiters.com';
        echo '<input type="url" name="smartrecruiters_job_sync_options[api_url]" value="' . esc_attr($value) . '" style="width: 100%;" />';
    }

    /**
     * Client ID callback
     */
    public function client_id_callback()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $value = isset($options['client_id']) ? $options['client_id'] : '';
        echo '<input type="text" name="smartrecruiters_job_sync_options[client_id]" value="' . esc_attr($value) . '" style="width: 100%;" />';
    }

    /**
     * Client Secret callback
     */
    public function client_secret_callback()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $value = isset($options['client_secret']) ? $options['client_secret'] : '';
        echo '<input type="password" name="smartrecruiters_job_sync_options[client_secret]" value="' . esc_attr($value) . '" style="width: 100%;" />';
    }



    /**
     * Webhook section callback
     */
    public function webhook_section_callback()
    {
        $webhook_url = home_url('/smartrecruiters-webhook/');
        echo '<p><strong>Webhook URL:</strong> <code>' . esc_html($webhook_url) . '</code></p>';
    }

    /**
     * Webhook enabled callback
     */
    public function webhook_enabled_callback()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $value = isset($options['webhook_enabled']) ? $options['webhook_enabled'] : '0';
        echo '<input type="checkbox" name="smartrecruiters_job_sync_options[webhook_enabled]" value="1"' . checked($value, '1', false) . ' />';
        echo '<p class="description">Enable real-time webhook updates (requires webhook subscription in SmartRecruiters)</p>';
    }

    /**
     * Webhook secret callback
     */
    public function webhook_secret_callback()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $value = isset($options['webhook_secret']) ? $options['webhook_secret'] : '';
        echo '<input type="text" name="smartrecruiters_job_sync_options[webhook_secret]" value="' . esc_attr($value) . '" style="width: 100%;" />';
        echo '<p class="description">Secret key for webhook verification (optional but recommended for security)</p>';
    }





    /**
     * Add webhook query var
     */
    public function add_webhook_query_var($vars)
    {
        $vars[] = 'smartrecruiters_webhook';
        return $vars;
    }

    /**
     * Add webhook endpoint
     */
    public function add_webhook_endpoint()
    {
        add_rewrite_rule('^smartrecruiters-webhook/?$', 'index.php?smartrecruiters_webhook=1', 'top');
        add_rewrite_tag('%smartrecruiters_webhook%', '([^&]+)');
    }

    /**
     * Handle webhook requests
     */
    public function handle_webhook_request()
    {
        // Log all webhook requests for debugging
        $this->log_webhook_debug('SmartRecruiters Webhook: Request received - URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        $this->log_webhook_debug('SmartRecruiters Webhook: Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
        $this->log_webhook_debug('SmartRecruiters Webhook: Query var: ' . (get_query_var('smartrecruiters_webhook') ? 'YES' : 'NO'));

        // Check query var first
        if (!get_query_var('smartrecruiters_webhook')) {
            // Also check if URL matches webhook endpoint directly
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($request_uri, 'smartrecruiters-webhook') === false) {
                return;
            }
        }

        // Only allow POST requests (but allow GET for testing)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // For testing - return OK
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(array('status' => 'ok', 'message' => 'Webhook endpoint is accessible'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed');
        }

        $options = get_option('smartrecruiters_job_sync_options');

        // Check if webhooks are enabled
        if (empty($options['webhook_enabled'])) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Webhooks are disabled in settings');
            http_response_code(403);
            exit('Webhooks disabled');
        }

        // Handle webhook handshake (for activation)
        // SmartRecruiters sends X-Hook-Secret header during handshake that must be echoed back
        $hook_secret = $_SERVER['HTTP_X_HOOK_SECRET'] ?? '';

        // Also check lowercase header name
        if (empty($hook_secret)) {
            $hook_secret = $_SERVER['HTTP_X_HOOK_SECRET'] ?? '';
        }

        if (!empty($hook_secret)) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Handshake request received with secret: ' . substr($hook_secret, 0, 10) . '...');
            // This is a handshake request - save the secret and return it
            update_option('smartrecruiters_webhook_secret', $hook_secret);

            // Return the X-Hook-Secret header in response
            header('X-Hook-Secret: ' . $hook_secret);
            http_response_code(200);
            exit('OK');
        }

        // Get raw POST data
        $raw_data = file_get_contents('php://input');
        $this->log_webhook_debug('SmartRecruiters Webhook: Raw POST data received: ' . substr($raw_data, 0, 500));

        // Log relevant SmartRecruiters headers for debugging
        $header_map = array(
            'HTTP_EVENT_NAME' => 'event-name',
            'HTTP_EVENT_VERSION' => 'event-version',
            'HTTP_EVENT_ID' => 'event-id',
            'HTTP_LINK' => 'Link',
            'HTTP_SMARTRECRUITERS_SIGNATURE' => 'smartrecruiters-signature',
            'HTTP_SMARTRECRUITERS_TIMESTAMP' => 'smartrecruiters-timestamp'
        );
        $header_context = array();
        foreach ($header_map as $server_key => $label) {
            if (!empty($_SERVER[$server_key])) {
                $value = $_SERVER[$server_key];
                $this->log_webhook_debug('SmartRecruiters Webhook: Header ' . $label . ' => ' . $value);

                switch ($label) {
                    case 'event-name':
                        $header_context['eventType'] = strtolower(trim($value));
                        break;
                    case 'event-version':
                        $header_context['_eventVersion'] = trim($value);
                        break;
                    case 'Link':
                        $link_url = $this->extract_link_from_header($value);
                        if ($link_url) {
                            $header_context['_link'] = $link_url;
                        }
                        break;
                }
            }
        }

        $webhook_data = json_decode($raw_data, true);

        // Log decoded data
        if ($webhook_data) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Decoded data - EventType: ' . ($webhook_data['eventType'] ?? 'N/A'));
            $this->log_webhook_debug('SmartRecruiters Webhook: Full webhook data: ' . print_r($webhook_data, true));
        }

        // If JSON decode fails, it might be an empty handshake request
        if (json_last_error() !== JSON_ERROR_NONE && !empty($raw_data)) {
            $this->log_webhook_debug('SmartRecruiters Webhook: JSON decode error: ' . json_last_error_msg());
            http_response_code(400);
            exit('Invalid JSON');
        }

        if (!is_array($webhook_data)) {
            $webhook_data = array();
        }

        foreach ($header_context as $key => $value) {
            if (!isset($webhook_data[$key]) || $webhook_data[$key] === '' || $webhook_data[$key] === null) {
                $webhook_data[$key] = $value;
            }
        }

        // If no data and no hook secret, might be a ping/test request
        if (empty($webhook_data) && empty($hook_secret)) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Empty request - treating as ping');
            http_response_code(200);
            exit('OK');
        }

        // Verify webhook signature if secret is set
        $saved_secret = get_option('smartrecruiters_webhook_secret');
        if (!empty($saved_secret)) {
            $signature = $_SERVER['HTTP_X_SMARTRECRUITERS_SIGNATURE'] ?? '';
            if (!empty($signature)) {
                if (!$this->verify_webhook_signature($raw_data, $signature, $saved_secret)) {
                    $this->log_webhook_debug('SmartRecruiters Webhook: Signature verification failed');
                    http_response_code(401);
                    exit('Invalid signature');
                }
                $this->log_webhook_debug('SmartRecruiters Webhook: Signature verified successfully');
            }
        }

        // Process webhook data (only if we have actual data)
        if (!empty($webhook_data)) {
            // Check different possible structures for eventType
            $event_type = $webhook_data['eventType'] ?? $webhook_data['event_type'] ?? $webhook_data['type'] ?? '';

            // Also check if job data is directly in webhook_data or in 'data' key
            $job_data = $webhook_data['data'] ?? $webhook_data;

            if (!empty($event_type)) {
                $this->log_webhook_debug('SmartRecruiters Webhook: Processing event type: ' . $event_type);
                // Normalize eventType key and ensure data structure
                $webhook_data['eventType'] = $event_type;
                $webhook_data['data'] = $job_data;
                $this->process_webhook_data($webhook_data);
            } else {
                // If no eventType but has job data, try to process it as update
                if (!empty($job_data['id'])) {
                    $this->log_webhook_debug('SmartRecruiters Webhook: No eventType found, treating as job update');
                    $webhook_data['eventType'] = 'job.updated';
                    $webhook_data['data'] = $job_data;
                    $this->process_webhook_data($webhook_data);
                } else {
                    $this->log_webhook_debug('SmartRecruiters Webhook: No eventType found in webhook data');
                    $this->log_webhook_debug('SmartRecruiters Webhook: Available keys: ' . implode(', ', array_keys($webhook_data)));
                }
            }
        } else {
            $this->log_webhook_debug('SmartRecruiters Webhook: Empty webhook data received');
        }

        http_response_code(202);
        exit('Accepted');
    }

    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($payload, $signature, $secret)
    {
        $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Process webhook data and update database
     */
    private function process_webhook_data($webhook_data)
    {
        $event_type = strtolower($webhook_data['eventType'] ?? '');
        if (empty($event_type)) {
            $event_type = 'job.updated';
        }

        $payload = $this->build_job_payload_from_webhook($webhook_data);
        $job_id = $payload['job_id'];
        $job_title = $payload['job_title'] ?: 'N/A';
        $job_data = $payload['job_data'];

        $this->log_webhook_debug('SmartRecruiters Webhook: Processing event - Type: ' . $event_type . ', Job ID: ' . ($job_id ?: 'N/A') . ', Title: ' . $job_title);
        $this->log_webhook_activity($event_type, $job_id ?: 'N/A', $job_title, 'received');

        if (empty($job_id)) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Missing job ID in webhook data (event: ' . $event_type . ')');
            $this->log_webhook_activity($event_type, 'N/A', $job_title, 'failed', 'No job ID found', true);
            return;
        }

        try {
            switch ($event_type) {
                case 'job.created':
                case 'job.updated':
                case 'job.status.updated':
                case 'position.created':
                case 'position.updated':
                    $this->log_webhook_debug('SmartRecruiters Webhook: Syncing job - ID: ' . $job_id . ' (event: ' . $event_type . ')');
                    $result = $this->sync_single_job($job_data);
                    if ($result) {
                        $this->log_webhook_debug('SmartRecruiters Webhook: Job sync successful - ID: ' . $job_id);
                        $resolved_title = $this->last_synced_title ?: $job_title;
                        $this->log_webhook_activity($event_type, $job_id, $resolved_title, 'success', '', true);
                    } else {
                        $this->log_webhook_debug('SmartRecruiters Webhook: Job sync failed - ID: ' . $job_id);
                        $details = $this->last_sync_error ?: 'Unable to save job';
                        if ($this->should_treat_as_skip($details)) {
                            $this->log_webhook_activity($event_type, $job_id, $job_title, 'skipped', $details, true);
                            $this->schedule_job_sync_retry($job_id);
                        } else {
                            $this->log_webhook_activity($event_type, $job_id, $job_title, 'failed', $details, true);
                        }
                    }
                    break;
                case 'position.deleted':
                case 'job.deleted':
                    $this->log_webhook_debug('SmartRecruiters Webhook: Deleting job - ID: ' . $job_id . ' (event: ' . $event_type . ')');
                    $result = $this->delete_job_by_external_id($job_id);
                    if ($result) {
                        $this->log_webhook_debug('SmartRecruiters Webhook: Job deleted successfully - ID: ' . $job_id);
                        $resolved_title = $this->last_synced_title ?: $job_title;
                        $this->log_webhook_activity($event_type, $job_id, $resolved_title, 'deleted', '', true);
                    } else {
                        $this->log_webhook_debug('SmartRecruiters Webhook: Job delete failed - ID: ' . $job_id);
                        $details = $this->last_sync_error ?: 'Job not found locally';
                        if ($this->should_treat_as_skip($details)) {
                            $this->log_webhook_activity($event_type, $job_id, $job_title, 'skipped', $details, true);
                            $this->schedule_job_sync_retry($job_id);
                        } else {
                            $this->log_webhook_activity($event_type, $job_id, $job_title, 'delete_failed', $details, true);
                        }
                    }
                    break;
                case 'job.retry':
                    $this->log_webhook_debug('SmartRecruiters Webhook: Job retry sync - ID: ' . $job_id . ' (event: ' . $event_type . ')');
                    $result = $this->sync_single_job($job_data);
                    if ($result) {
                        $this->log_webhook_debug('SmartRecruiters Webhook: Job retry sync successful - ID: ' . $job_id);
                        $resolved_title = $this->last_synced_title ?: $job_title;
                        $this->log_webhook_activity($event_type, $job_id, $resolved_title, 'success', '', true);
                    } else {
                        $this->log_webhook_debug('SmartRecruiters Webhook: Job retry sync failed - ID: ' . $job_id);
                        $details = $this->last_sync_error ?: 'Unable to retry sync job';
                        if ($this->should_treat_as_skip($details)) {
                            $this->log_webhook_activity($event_type, $job_id, $job_title, 'skipped', $details, true);
                            $this->schedule_job_sync_retry($job_id);
                        } else {
                            $this->log_webhook_activity($event_type, $job_id, $job_title, 'failed', $details, true);
                        }
                    }
                    break;
                default:
                    $this->log_webhook_debug('SmartRecruiters Webhook: Unhandled event type "' . $event_type . '" - defaulting to job sync');
                    $result = $this->sync_single_job($job_data);
                    if ($result) {
                        $this->log_webhook_activity($event_type, $job_id, $job_title, 'success', '', true);
                        $resolved_title = $this->last_synced_title ?: $job_title;
                        $this->log_webhook_activity($event_type, $job_id, $resolved_title, 'success', '', true);
                    } else {
                        $details = $this->last_sync_error ?: 'Default handler failed';
                        if ($this->should_treat_as_skip($details)) {
                            $this->log_webhook_activity($event_type, $job_id, $job_title, 'skipped', $details, true);
                            $this->schedule_job_sync_retry($job_id);
                        } else {
                            $this->log_webhook_activity($event_type, $job_id, $job_title, 'failed', $details, true);
                        }
                    }
                    break;
            }
        } catch (\Throwable $exception) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Exception while handling event ' . $event_type . ' for job ' . $job_id . ': ' . $exception->getMessage());
            $this->log_webhook_activity($event_type, $job_id, $job_title, 'failed', $exception->getMessage(), true);
        }
    }

    /**
     * Log webhook activity for admin display
     */
    private function log_webhook_activity($event_type, $job_id, $job_title, $status, $details = '', $update_existing = false)
    {
        $logs = get_option('smartrecruiters_webhook_logs', array());

        $event_labels = array(
            'job.created' => 'Job Created',
            'job.updated' => 'Job Updated',
            'job.status.updated' => 'Job Status Updated',
            'position.created' => 'Position Created',
            'position.updated' => 'Position Updated',
            'position.deleted' => 'Position Deleted',
            'job.retry' => 'Job Retry Sync'
        );

        $status_labels = array(
            'received' => 'Received',
            'success' => 'Success',
            'failed' => 'Failed',
            'deleted' => 'Deleted',
            'delete_failed' => 'Delete Failed',
            'unknown_event' => 'Unknown Event',
            'skipped' => 'Skipped'
        );

        if ($update_existing && !empty($logs)) {
            foreach ($logs as &$existing_log) {
                if ($existing_log['event_type'] === $event_type && $existing_log['job_id'] === $job_id && $existing_log['status'] === 'received') {
                    $existing_log['status'] = $status;
                    $existing_log['status_label'] = $status_labels[$status] ?? $status;
                    if (!empty($job_title) && $job_title !== 'N/A') {
                        $existing_log['job_title'] = $job_title;
                    }
                    if (!empty($details)) {
                        $existing_log['details'] = $details;
                    }
                    $existing_log['timestamp'] = time();
                    update_option('smartrecruiters_webhook_logs', $logs, false);
                    return;
                }
            }
        }

        // Keep only last 50 entries
        if (count($logs) >= 50) {
            $logs = array_slice($logs, -49);
        }

        $log_entry = array(
            'timestamp' => time(),
            'event_type' => $event_type,
            'event_label' => $event_labels[$event_type] ?? $event_type,
            'job_id' => $job_id,
            'job_title' => $job_title,
            'status' => $status,
            'status_label' => $status_labels[$status] ?? $status,
            'details' => $details
        );

        array_unshift($logs, $log_entry);
        update_option('smartrecruiters_webhook_logs', $logs, false);

        // Also log to error_log for debugging
        error_log(sprintf(
            'SmartRecruiters Webhook: %s - Job ID: %s, Title: %s, Status: %s',
            $event_labels[$event_type] ?? $event_type,
            $job_id,
            $job_title,
            $status_labels[$status] ?? $status
        ));
    }

    /**
     * Write webhook debug logs to both PHP error log and custom log file
     */
    private function log_webhook_debug($message)
    {
        $timestamp = function_exists('current_time') ? current_time('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        $formatted_message = '[' . $timestamp . '] ' . $message;
        error_log($formatted_message);
    }

    /**
     * Extract the self link URL from Link header
     */
    private function extract_link_from_header($link_header)
    {
        $parts = explode(',', $link_header);
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/<([^>]+)>;\\s*rel="?self"?/i', $part, $matches)) {
                return $matches[1];
            }
        }

        if (preg_match('/<([^>]+)>/', $link_header, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Normalize webhook payload into a job-centric structure
     */
    private function build_job_payload_from_webhook($webhook_data)
    {
        $job_data = array();

        if (!empty($webhook_data['data']) && is_array($webhook_data['data'])) {
            $job_data = $webhook_data['data'];
        }

        $possible_payload_keys = array('job', 'position', 'object', 'payload', 'entity');
        if (empty($job_data) || !is_array($job_data)) {
            foreach ($possible_payload_keys as $key) {
                if (!empty($webhook_data[$key]) && is_array($webhook_data[$key])) {
                    $job_data = $webhook_data[$key];
                    break;
                }
            }
        }

        if (empty($job_data) || !is_array($job_data)) {
            $job_data = $webhook_data;
        }

        $job_id = '';
        $job_id_candidates = array(
            $job_data['id'] ?? null,
            $job_data['jobId'] ?? null,
            $job_data['job_id'] ?? null,
            $job_data['jobUid'] ?? null,
            $job_data['jobUUID'] ?? null,
            $webhook_data['job_id'] ?? null,
            $webhook_data['jobId'] ?? null,
            (isset($job_data['job']) && is_array($job_data['job'])) ? ($job_data['job']['id'] ?? null) : null,
            (isset($job_data['job']) && is_array($job_data['job'])) ? ($job_data['job']['jobId'] ?? null) : null,
            (isset($job_data['position']) && is_array($job_data['position'])) ? ($job_data['position']['job']['id'] ?? null) : null,
            (isset($webhook_data['position']) && is_array($webhook_data['position'])) ? ($webhook_data['position']['job']['id'] ?? null) : null
        );

        foreach ($job_id_candidates as $candidate) {
            if (!empty($candidate)) {
                $job_id = $candidate;
                break;
            }
        }

        if (empty($job_id)) {
            $link_source = $webhook_data['_link'] ?? $job_data['_link'] ?? '';
            if (!empty($link_source) && preg_match('/\/jobs\/([a-f0-9-]+)/i', $link_source, $matches)) {
                $job_id = $matches[1];
            }
        }

        if (!empty($job_id)) {
            $job_data['id'] = $job_id;
        }

        $job_title = '';
        $title_candidates = array(
            $job_data['title'] ?? null,
            $job_data['name'] ?? null,
            (isset($job_data['job']) && is_array($job_data['job'])) ? ($job_data['job']['title'] ?? null) : null,
            (isset($webhook_data['job']) && is_array($webhook_data['job'])) ? ($webhook_data['job']['title'] ?? null) : null
        );

        foreach ($title_candidates as $candidate) {
            if (!empty($candidate)) {
                $job_title = $candidate;
                break;
            }
        }

        $link = $webhook_data['_link'] ?? '';
        if (!empty($link)) {
            $job_data['_link'] = $link;
        }

        return array(
            'job_id' => $job_id,
            'job_title' => $job_title,
            'job_data' => is_array($job_data) ? $job_data : array(),
            'link' => $link
        );
    }

    /**
     * Sync a single job from webhook data
     */
    private function sync_single_job($job_data)
    {
        $this->last_sync_error = '';

        if (empty($job_data['id'])) {
            $this->last_sync_error = 'Missing job ID in payload';
            return false;
        }

        // Check if job already exists
        $existing_post = $this->find_job_by_external_id($job_data['id']);

        if ($existing_post) {
            // Update existing job
            $result = $this->update_job_post($existing_post->ID, $job_data);
            if (is_wp_error($result)) {
                $this->last_sync_error = $result->get_error_message();
                return false;
            }
            if ($result === false || empty($result)) {
                $this->last_sync_error = 'wp_update_post returned empty result';
                return false;
            }
            return true;
        } else {
            // Create new job
            $result = $this->create_job_from_webhook($job_data);
            if (is_wp_error($result)) {
                $this->last_sync_error = $result->get_error_message();
                return false;
            }
            if ($result === false || empty($result)) {
                if (empty($this->last_sync_error)) {
                    $this->last_sync_error = 'wp_insert_post returned empty result';
                }
                return false;
            }
            return true;
        }
    }

    /**
     * Find job post by external ID
     */
    private function find_job_by_external_id($external_id)
    {
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_job_external_id' AND meta_value = %s",
            $external_id
        ));

        return $post_id ? get_post($post_id) : null;
    }

    /**
     * Delete job by external ID
     */
    private function delete_job_by_external_id($external_id)
    {
        if (empty($external_id)) {
            return false;
        }

        $post = $this->find_job_by_external_id($external_id);
        if ($post) {
            $this->last_synced_title = get_the_title($post->ID) ?: $post->post_title;
            $deleted = wp_delete_post($post->ID, true);
            return $deleted !== false;
        }
        return false;
    }

    /**
     * Create job from webhook data
     */
    private function create_job_from_webhook($job_data)
    {
        // If webhook data doesn't have full details, fetch from API
        $job_data = $this->enrich_job_data($job_data);

        if (!empty($job_data['__enrich_error'])) {
            $this->last_sync_error = 'Waiting for SmartRecruiters details: ' . $job_data['__enrich_error'];
            return false;
        }

        if (empty($job_data['title']) && empty($job_data['jobAd'])) {
            $this->last_sync_error = 'Job details still incomplete (missing title/jobAd)';
            return false;
        }

        unset($job_data['__enrich_error']);

        $api_sync = new SmartRecruitersAPISync();
        return $api_sync->create_job($job_data);
    }

    /**
     * Update existing job post
     */
    private function update_job_post($post_id, $job_data)
    {
        // If webhook data doesn't have full details, fetch from API
        $job_data = $this->enrich_job_data($job_data);

        if (!empty($job_data['__enrich_error'])) {
            $this->last_sync_error = 'SmartRecruiters details unavailable: ' . $job_data['__enrich_error'];
            return false;
        }

        unset($job_data['__enrich_error']);

        $api_sync = new SmartRecruitersAPISync();
        return $api_sync->update_job($post_id, $job_data);
    }

    /**
     * Enrich job data by fetching full details from API if needed
     */
    private function enrich_job_data($job_data)
    {
        // If we already have full job data (has jobAd or other detailed fields), return as is
        if (!empty($job_data['jobAd']) || !empty($job_data['department']) || !empty($job_data['location'])) {
            unset($job_data['__enrich_error']);
            return $job_data;
        }

        // Otherwise, fetch full job details from API
        $job_id = $job_data['id'] ?? '';
        if (empty($job_id)) {
            $job_data['__enrich_error'] = 'Job ID missing';
            return $job_data;
        }

        $options = get_option('smartrecruiters_job_sync_options');
        $access_token = $this->get_access_token();

        if (!$access_token) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Failed to get access token for enriching job data');
            $job_data['__enrich_error'] = 'Access token unavailable';
            return $job_data;
        }

        $link = $job_data['_link'] ?? '';
        $job_url = $link ? $link : rtrim($options['api_url'], '/') . '/jobs/' . $job_id;

        if (isset($job_data['_link'])) {
            unset($job_data['_link']);
        }
        $response = wp_remote_get($job_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Failed to fetch job details: ' . $response->get_error_message());
            $job_data['__enrich_error'] = $response->get_error_message();
            return $job_data;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            $full_job_data = json_decode(wp_remote_retrieve_body($response), true);
            if ($full_job_data) {
                unset($full_job_data['__enrich_error']);
                return $full_job_data;
            }
            $job_data['__enrich_error'] = 'Job details JSON decode failed';
        } else {
            $this->log_webhook_debug('SmartRecruiters Webhook: Unexpected response fetching job details - HTTP ' . $response_code);
            $job_data['__enrich_error'] = 'HTTP ' . $response_code;
        }

        return $job_data;
    }

    /**
     * Manual sync AJAX handler
     */
    public function manual_sync_ajax()
    {
        check_ajax_referer('manual_smartrecruiters_sync_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $exclude_cancelled = !empty($_POST['exclude_cancelled']);
        $result = $this->sync_jobs(array('exclude_cancelled' => $exclude_cancelled));

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message'], 'logs' => ($result['logs'] ?? array())));
        } else {
            wp_send_json_error(array('message' => $result['message'], 'logs' => ($result['logs'] ?? array())));
        }
    }

    /**
     * Create webhook subscription AJAX handler
     */
    public function create_webhook_ajax()
    {
        check_ajax_referer('webhook_management_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $result = $this->create_webhook_subscription();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Delete webhook subscription AJAX handler
     */
    public function delete_webhook_ajax()
    {
        check_ajax_referer('webhook_management_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $webhook_id = isset($_POST['webhook_id']) ? sanitize_text_field($_POST['webhook_id']) : '';
        $result = $this->delete_webhook_subscription($webhook_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Refresh webhooks list AJAX handler
     */
    public function refresh_webhooks_ajax()
    {
        check_ajax_referer('webhook_management_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $webhooks = $this->get_all_webhooks();

        if ($webhooks['success']) {
            wp_send_json_success($webhooks);
        } else {
            wp_send_json_error($webhooks);
        }
    }

    /**
     * Activate webhook AJAX handler
     */
    public function activate_webhook_ajax()
    {
        check_ajax_referer('webhook_management_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $webhook_id = isset($_POST['webhook_id']) ? sanitize_text_field($_POST['webhook_id']) : '';

        if (empty($webhook_id)) {
            wp_send_json_error(array('message' => 'Webhook ID is required'));
        }

        $result = $this->activate_webhook_subscription($webhook_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Create webhook subscription in SmartRecruiters
     */
    private function create_webhook_subscription()
    {
        $options = get_option('smartrecruiters_job_sync_options');

        if (empty($options['api_url']) || empty($options['client_id']) || empty($options['client_secret'])) {
            return array(
                'success' => false,
                'message' => 'API configuration incomplete'
            );
        }

        // Get access token
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain access token'
            );
        }

        $webhook_url = home_url('/smartrecruiters-webhook/');

        // SmartRecruiters API event names
        $webhook_data = array(
            'callbackUrl' => $webhook_url,
            'events' => array(
                'job.created',
                'job.updated',
                'job.status.updated',
                'position.created',
                'position.updated',
                'position.deleted'
            )
        );

        // Use correct API endpoint: /webhooks-api/v201907/subscriptions
        $webhook_endpoint = rtrim($options['api_url'], '/') . '/webhooks-api/v201907/subscriptions';
        $response = wp_remote_post($webhook_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($webhook_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 201) {
            $webhook_info = json_decode($response_body, true);
            $webhook_id = $webhook_info['id'] ?? '';
            update_option('smartrecruiters_webhook_id', $webhook_id);

            // Refresh webhooks list
            $this->get_all_webhooks();

            return array(
                'success' => true,
                'message' => 'Webhook subscription created successfully. Please activate it using the "Activate" button below.'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to create webhook: ' . $response_body
            );
        }
    }

    /**
     * Get all webhook subscriptions from SmartRecruiters
     */
    private function get_all_webhooks()
    {
        $options = get_option('smartrecruiters_job_sync_options');

        if (empty($options['api_url']) || empty($options['client_id']) || empty($options['client_secret'])) {
            return array(
                'success' => false,
                'message' => 'API configuration incomplete'
            );
        }

        // Get access token
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain access token'
            );
        }

        $webhook_endpoint = rtrim($options['api_url'], '/') . '/webhooks-api/v201907/subscriptions';
        $response = wp_remote_get($webhook_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $webhooks_data = json_decode($response_body, true);
            $webhooks = isset($webhooks_data['content']) ? $webhooks_data['content'] : (is_array($webhooks_data) ? $webhooks_data : array());

            // Save webhooks list
            update_option('smartrecruiters_webhooks_list', $webhooks);

            return array(
                'success' => true,
                'webhooks' => $webhooks,
                'message' => 'Webhooks retrieved successfully'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to get webhooks: ' . $response_body
            );
        }
    }

    /**
     * Activate webhook subscription
     */
    private function activate_webhook_subscription($webhook_id)
    {
        $options = get_option('smartrecruiters_job_sync_options');

        if (empty($options['api_url']) || empty($options['client_id']) || empty($options['client_secret'])) {
            return array(
                'success' => false,
                'message' => 'API configuration incomplete'
            );
        }

        // Get access token
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain access token'
            );
        }

        $activate_endpoint = rtrim($options['api_url'], '/') . '/webhooks-api/v201907/subscriptions/' . $webhook_id . '/activation';
        $response = wp_remote_request($activate_endpoint, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200 || $response_code === 204) {
            // Refresh webhooks list
            $this->get_all_webhooks();
            return array(
                'success' => true,
                'message' => 'Webhook activated successfully'
            );
        } else {
            $response_body = wp_remote_retrieve_body($response);
            return array(
                'success' => false,
                'message' => 'Failed to activate webhook: ' . $response_body
            );
        }
    }

    /**
     * Display webhook list in admin
     */
    private function display_webhook_list()
    {
        $webhooks = get_option('smartrecruiters_webhooks_list', array());
        $webhook_url = home_url('/smartrecruiters-webhook/');

        // Filter only webhooks from our site
        $our_webhooks = array();
        foreach ($webhooks as $webhook) {
            $callback_url = $webhook['callbackUrl'] ?? '';
            // Check if this webhook belongs to our site
            if (strpos($callback_url, $webhook_url) !== false) {
                $our_webhooks[] = $webhook;
            }
        }

        if (empty($our_webhooks)) {
            echo '<p style="color: #666;">No webhooks found for this site. Click "Refresh Webhook List" to load webhooks.</p>';
            return;
        }

        // Only show table if we have webhooks from our site
        if (count($our_webhooks) > 0) {
            echo '<h3>Webhook Subscriptions for This Site</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th style="width: 150px;">Webhook ID</th>';
            echo '<th>Callback URL</th>';
            echo '<th style="width: 100px;">Status</th>';
            echo '<th style="width: 200px;">Actions</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($our_webhooks as $webhook) {
                $id = $webhook['id'] ?? '';
                $callback_url = $webhook['callbackUrl'] ?? '';
                // Check status field (not active field)
                $status = $webhook['status'] ?? 'inactive';
                $is_active = ($status === 'active');

                echo '<tr>';
                echo '<td><code>' . esc_html(substr($id, 0, 20)) . '...</code></td>';
                echo '<td>' . esc_html($callback_url) . '</td>';

                if ($is_active) {
                    echo '<td><span style="color: green;">✅ Active</span></td>';
                    echo '<td>';
                    echo '<button type="button" class="button button-small delete-webhook-btn" data-webhook-id="' . esc_attr($id) . '">Delete</button>';
                    echo '</td>';
                } else {
                    echo '<td><span style="color: orange;">⏳ Inactive</span></td>';
                    echo '<td>';
                    echo '<button type="button" class="button button-small activate-webhook-btn" data-webhook-id="' . esc_attr($id) . '">Activate</button> ';
                    echo '<button type="button" class="button button-small delete-webhook-btn" data-webhook-id="' . esc_attr($id) . '">Delete</button>';
                    echo '</td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table>';

            if (count($our_webhooks) > 1) {
                echo '<p class="description" style="margin-top: 10px; color: #d63638;">';
                echo '<strong>⚠️ Warning:</strong> Multiple webhook subscriptions found for this site. You may want to delete inactive ones to avoid conflicts.';
                echo '</p>';
            }
        }
    }

    /**
     * Delete webhook subscription from SmartRecruiters
     */
    private function delete_webhook_subscription($webhook_id = '')
    {
        $options = get_option('smartrecruiters_job_sync_options');

        if (empty($webhook_id)) {
            $webhook_id = get_option('smartrecruiters_webhook_id');
        }

        if (empty($webhook_id)) {
            return array(
                'success' => false,
                'message' => 'No webhook subscription found'
            );
        }

        // Get access token
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain access token'
            );
        }

        // Use correct API endpoint: /webhooks-api/v201907/subscriptions/{id}
        $webhook_endpoint = rtrim($options['api_url'], '/') . '/webhooks-api/v201907/subscriptions/' . $webhook_id;
        $response = wp_remote_request($webhook_endpoint, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 204 || $response_code === 200) {
            delete_option('smartrecruiters_webhook_id');
            delete_option('smartrecruiters_webhook_secret');

            return array(
                'success' => true,
                'message' => 'Webhook subscription deleted successfully'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to delete webhook'
            );
        }
    }

    /**
     * Get access token for API calls
     */
    private function get_access_token()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $token_url = $options['api_url'] . '/identity/oauth/token';

        $data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $options['client_id'],
            'client_secret' => $options['client_secret']
        );

        $response = wp_remote_post($token_url, array(
            'body' => $data,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        $token_data = json_decode($response_body, true);

        return $token_data['access_token'] ?? false;
    }

    /**
     * Main sync function
     */
    public function sync_jobs($args = array())
    {
        $options = get_option('smartrecruiters_job_sync_options');

        if (empty($options['api_url']) || empty($options['client_id']) || empty($options['client_secret'])) {
            return array(
                'success' => false,
                'message' => 'SmartRecruiters API configuration is incomplete. Please check your settings.'
            );
        }

        // Validate API URL format
        if (!filter_var($options['api_url'], FILTER_VALIDATE_URL)) {
            return array(
                'success' => false,
                'message' => 'Invalid API URL format. Please use a valid URL (e.g., https://api.sandbox.smartrecruiters.com)'
            );
        }

        // Log configuration for debugging
        error_log('SmartRecruiters: Starting sync with API URL: ' . $options['api_url']);
        error_log('SmartRecruiters: Client ID: ' . $options['client_id']);
        error_log('SmartRecruiters: Client Secret: ' . (empty($options['client_secret']) ? 'EMPTY' : 'SET'));

        $filters = array(
            'exclude_cancelled' => !empty($args['exclude_cancelled'])
        );
        $api_sync = new SmartRecruitersAPISync($filters);
        $result = $api_sync->sync_jobs();
        // Persist last run info (for cron or manual) to display in admin
        $last_run = array(
            'timestamp' => time(),
            'message' => isset($result['message']) ? $result['message'] : '',
            'success' => !empty($result['success']),
            'logs' => isset($result['logs']) ? $result['logs'] : array(),
            'filters' => $filters,
        );
        update_option('smartrecruiters_last_run', $last_run, false);
        return $result;
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        $this->register_job_post_type();

        // Add webhook endpoint rewrite rule
        $this->add_webhook_endpoint();

        // Flush rewrite rules to make endpoint accessible
        flush_rewrite_rules();

        // Schedule initial cron
        $this->ensure_cron_scheduled();
    }

    /**
     * Ensure cron is scheduled (called on activation and admin page load)
     */
    private function ensure_cron_scheduled()
    {
        $next_scheduled = wp_next_scheduled('smartrecruiters_job_sync_cron');

        // If not scheduled or scheduled more than 25 hours ago, reschedule
        if (!$next_scheduled || ($next_scheduled - time()) < -3600) {
            // Clear any existing schedule
            wp_clear_scheduled_hook('smartrecruiters_job_sync_cron');
            // Schedule for next day at the same time (or immediately if way past)
            $schedule_time = time() + (24 * 60 * 60); // 24 hours from now
            wp_schedule_event($schedule_time, 'daily', 'smartrecruiters_job_sync_cron');
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        wp_clear_scheduled_hook('smartrecruiters_job_sync_cron');
    }

    /**
     * Check if a sync error should be treated as skipped
     */
    private function should_treat_as_skip($details)
    {
        if (empty($details)) {
            return false;
        }

        $patterns = array(
            'Job details still incomplete',
            'Waiting for SmartRecruiters details',
            'HTTP 404',
            'Access token unavailable'
        );

        foreach ($patterns as $pattern) {
            if (stripos($details, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Schedule job sync retry
     */
    private function schedule_job_sync_retry($job_id)
    {
        $job_id = trim((string) $job_id);
        if (empty($job_id)) {
            return;
        }

        $retry_key = 'smartrecruiters_retry_count_' . md5($job_id);
        $attempts = intval(get_transient($retry_key));
        $max_attempts = apply_filters('smartrecruiters_retry_max_attempts', 3, $job_id);
        if ($attempts >= $max_attempts) {
            $this->log_webhook_debug('SmartRecruiters Webhook: Retry limit reached for job ' . $job_id);
            return;
        }

        if (wp_next_scheduled('smartrecruiters_retry_job_sync', array($job_id))) {
            return;
        }

        $delay = apply_filters('smartrecruiters_retry_delay_seconds', 60, $job_id, $attempts);
        $delay = max(30, intval($delay));
        wp_schedule_single_event(time() + $delay, 'smartrecruiters_retry_job_sync', array($job_id));
        set_transient($retry_key, $attempts + 1, 30 * MINUTE_IN_SECONDS);

        $this->log_webhook_debug(sprintf('SmartRecruiters Webhook: Scheduled retry #%d for job %s in %d seconds', $attempts + 1, $job_id, $delay));
        $this->log_webhook_activity('job.retry', $job_id, 'N/A', 'skipped', sprintf('Retry scheduled in %d seconds', $delay));
    }

    public function retry_job_sync($job_id)
    {
        $job_id = trim((string) $job_id);
        if (empty($job_id)) {
            return;
        }

        $this->log_webhook_debug('SmartRecruiters Webhook: Retry job sync triggered for job ' . $job_id);
        $job_data = array('id' => $job_id);
        $result = $this->sync_single_job($job_data);

        $retry_key = 'smartrecruiters_retry_count_' . md5($job_id);

        if ($result) {
            delete_transient($retry_key);
            $title = $this->last_synced_title ?: 'N/A';
            $this->log_webhook_activity('job.retry', $job_id, $title, 'success', 'Retry sync succeeded');
        } else {
            $details = $this->last_sync_error ?: 'Retry sync failed';
            if ($this->should_treat_as_skip($details)) {
                $this->log_webhook_activity('job.retry', $job_id, 'N/A', 'skipped', $details);
                $this->schedule_job_sync_retry($job_id);
            } else {
                $this->log_webhook_activity('job.retry', $job_id, 'N/A', 'failed', $details);
            }
        }
    }
}


/**
 * SmartRecruiters API Sync Class
 * Fetches list of jobs, then fetches each job's details and stores them
 */
class SmartRecruitersAPISync
{

    private $options;
    private $logs = array();
    private $exclude_cancelled = false;

    public function __construct($args = array())
    {
        $this->options = get_option('smartrecruiters_job_sync_options');
        $this->exclude_cancelled = !empty($args['exclude_cancelled']);
    }

    public function sync_jobs()
    {
        try {
            $this->logs = array();
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception('Failed to obtain access token from SmartRecruiters API');
            }

            if ($this->exclude_cancelled) {
                $this->logs[] = 'Excluding cancelled jobs from manual sync.';
            }

            // Get the list of jobs first
            $jobs = $this->fetch_jobs_from_api($access_token);
            if (!is_array($jobs)) {
                throw new Exception('Failed to fetch jobs list from SmartRecruiters API');
            }

            // Delete all existing job posts before re-creating
            $this->delete_all_existing_jobs();

            $added = 0;
            foreach ($jobs as $job_summary) {
                $job_id = $job_summary['id'] ?? null;
                if (!$job_id) {
                    continue;
                }

                if ($this->exclude_cancelled) {
                    $status = strtoupper($job_summary['status'] ?? '');
                    $posting_status = strtoupper($job_summary['postingStatus'] ?? '');
                    if ($status === 'CANCELLED' || $status === 'CANCELED' || $posting_status === 'CANCELLED' || $posting_status === 'CANCELED') {
                        $this->logs[] = 'Skipped cancelled job: ' . ($job_summary['title'] ?? $job_id) . ' (' . $job_id . ')';
                        continue;
                    }
                }

                $job_details = $this->fetch_single_job_details($access_token, $job_summary);
                if (!$job_details) {
                    // Fallback to summary if details missing
                    $job_details = $job_summary;
                }

                if ($this->exclude_cancelled) {
                    $detail_status = strtoupper($job_details['status'] ?? '');
                    $detail_posting_status = strtoupper($job_details['postingStatus'] ?? '');
                    if ($detail_status === 'CANCELLED' || $detail_status === 'CANCELED' || $detail_posting_status === 'CANCELLED' || $detail_posting_status === 'CANCELED') {
                        $this->logs[] = 'Skipped cancelled job after details fetch: ' . ($job_details['title'] ?? $job_id) . ' (' . $job_id . ')';
                        continue;
                    }
                }

                // Attach original summary payload so we can store it too
                if (is_array($job_details)) {
                    $job_details['__summary'] = $job_summary;
                }

                $this->create_job($job_details);
                $added++;
                $this->logs[] = 'Synced job: ' . ($job_details['title'] ?? $job_id) . ' (' . $job_id . ')';
            }

            return array(
                'success' => true,
                'message' => sprintf('SmartRecruiters sync completed: %d jobs refreshed with details', $added),
                'logs' => $this->logs
            );

        } catch (Exception $e) {
            error_log('SmartRecruiters Job Sync Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'SmartRecruiters sync failed: ' . $e->getMessage(),
                'logs' => $this->logs
            );
        }
    }

    private function get_access_token()
    {
        $token_url = $this->options['api_url'] . '/identity/oauth/token';

        $data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->options['client_id'],
            'client_secret' => $this->options['client_secret']
        );

        $response = wp_remote_post($token_url, array(
            'body' => $data,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return false;
        }

        $token_data = json_decode($body, true);
        if (isset($token_data['access_token'])) {
            return $token_data['access_token'];
        }
        return false;
    }

    private function fetch_jobs_from_api($access_token)
    {
        $all_jobs = array();
        $offset = 0;
        $limit = 100;

        do {
            $jobs_url = $this->options['api_url'] . '/jobs?limit=' . $limit . '&offset=' . $offset;

            $this->logs[] = 'Fetching jobs list: ' . $jobs_url;
            $response = wp_remote_get($jobs_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                )
            ));

            if (is_wp_error($response)) {
                error_log('SmartRecruiters: Error fetching jobs: ' . $response->get_error_message());
                $this->logs[] = 'Jobs list error: ' . $response->get_error_message();
                break;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                error_log('SmartRecruiters: API Error - HTTP ' . $response_code . ': ' . $body);
                $this->logs[] = 'Jobs list HTTP ' . $response_code . ': ' . substr($body, 0, 300);
                break;
            }

            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('SmartRecruiters: JSON Decode Error: ' . json_last_error_msg());
                $this->logs[] = 'Jobs list JSON decode error: ' . json_last_error_msg();
                break;
            }

            $jobs = isset($data['content']) ? $data['content'] : $data;
            if (empty($jobs)) {
                break;
            }

            $all_jobs = array_merge($all_jobs, $jobs);
            $offset += $limit;

            error_log('SmartRecruiters: Fetched ' . count($jobs) . ' jobs, total so far: ' . count($all_jobs));
            $this->logs[] = 'Fetched ' . count($jobs) . ' jobs (total: ' . count($all_jobs) . ')';

        } while (count($jobs) === $limit);

        error_log('SmartRecruiters: Total jobs fetched: ' . count($all_jobs));
        return $all_jobs;
    }

    private function fetch_single_job_details($access_token, $job_summary)
    {
        $job_id = $job_summary['id'] ?? null;
        if (!$job_id) {
            return false;
        }

        $details_url = rtrim($this->options['api_url'], '/') . '/jobs/' . $job_id;

        $this->logs[] = 'Fetching job details: ' . $details_url;
        $response = wp_remote_get($details_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            error_log('SmartRecruiters: Error fetching job details for ID ' . $job_id . ': ' . $response->get_error_message());
            $this->logs[] = 'Job details error: ' . $response->get_error_message();
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('SmartRecruiters: Job details API Error for ID ' . $job_id . ' - HTTP ' . $response_code . ': ' . $body);
            $this->logs[] = 'Job details HTTP ' . $response_code . ' for ' . $job_id . ': ' . substr($body, 0, 300);
            return false;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SmartRecruiters: Job details JSON Decode Error for ID ' . $job_id . ': ' . json_last_error_msg());
            $this->logs[] = 'Job details JSON decode error for ' . $job_id . ': ' . json_last_error_msg();
            return false;
        }

        return $data;
    }

    public function delete_all_existing_jobs()
    {
        global $wpdb;
        $job_posts = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'job'"
        );
        foreach ($job_posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }

    public function create_job($job_data)
    {
        // Debug: Log the actual API response structure
        error_log('SmartRecruiters: Job data structure: ' . print_r(array_keys($job_data), true));
        error_log('SmartRecruiters: Full job data: ' . json_encode($job_data, JSON_PRETTY_PRINT));

        if (isset($job_data['jobAd'])) {
            error_log('SmartRecruiters: jobAd found: ' . print_r($job_data['jobAd'], true));
        } else {
            error_log('SmartRecruiters: No jobAd field found in API response');
        }

        if (isset($job_data['jobAdSections'])) {
            error_log('SmartRecruiters: jobAdSections found: ' . print_r($job_data['jobAdSections'], true));
        }

        if (isset($job_data['ad'])) {
            error_log('SmartRecruiters: ad field found: ' . print_r($job_data['ad'], true));
        }

        $title = $job_data['title'] ?? 'Untitled Job';
        $slug = sanitize_title($title ?: ($job_data['id'] ?? uniqid('job-')));
        $description = $this->format_job_description($job_data);

        // Extract jobAd sections if present for dedicated storage
        $jobAdSections = $job_data['jobAd']['sections'] ?? array();
        $companyDescription = $jobAdSections['companyDescription'] ?? array();
        $jobDescriptionSection = $jobAdSections['jobDescription'] ?? array();
        $qualifications = $jobAdSections['qualifications'] ?? array();
        $additionalInformation = $jobAdSections['additionalInformation'] ?? array();
        $videosUrls = $jobAdSections['videos']['urls'] ?? array();

        $post_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_type' => 'job',
            'post_name' => $slug,
            'meta_input' => array(
                // Basic job info
                '_job_title' => $job_data['title'] ?? '',
                '_job_ref_number' => $job_data['refNumber'] ?? '',
                '_job_status' => $job_data['status'] ?? '',
                '_job_posting_status' => $job_data['postingStatus'] ?? '',
                '_job_external_id' => $job_data['id'] ?? '',

                // Department and language
                '_job_department' => $job_data['department']['label'] ?? '',
                '_job_language' => $job_data['language']['label'] ?? '',

                // Experience level (label + full object JSON)
                '_job_experience_level' => is_array($job_data['experienceLevel'] ?? null) ? ($job_data['experienceLevel']['label'] ?? '') : ($job_data['experienceLevel'] ?? ''),
                '_job_experience_level_full' => json_encode($job_data['experienceLevel'] ?? array()),

                // Full location object (JSON)
                '_job_location_full' => json_encode($job_data['location'] ?? array()),
                '_job_location' => $this->format_location($job_data['location'] ?? array()),
                '_job_country_code' => $job_data['location']['countryCode'] ?? '',
                '_job_city' => $job_data['location']['city'] ?? '',
                '_job_region_code' => $job_data['location']['regionCode'] ?? '',
                '_job_postal_code' => $job_data['location']['postalCode'] ?? $job_data['location']['zipCode'] ?? '',
                '_job_remote' => !empty($job_data['location']['remote']) ? 'REMOTE' : 'ONSITE',

                // Full actions object (JSON)
                '_job_actions_full' => json_encode($job_data['actions'] ?? array()),
                '_job_api_url' => $job_data['actions']['details']['url'] ?? '',

                // Job Ad sections (full object) - check multiple possible field names
                '_job_ad_full' => json_encode($job_data['jobAd'] ?? $job_data['jobAdSections'] ?? $job_data['ad'] ?? array()),

                // Job Ad sections (dedicated fields for details page)
                '_job_ad_company_description_title' => $companyDescription['title'] ?? '',
                '_job_ad_company_description_text' => $companyDescription['text'] ?? '',
                '_job_ad_job_description_title' => $jobDescriptionSection['title'] ?? '',
                '_job_ad_job_description_text' => $jobDescriptionSection['text'] ?? '',
                '_job_ad_qualifications_title' => $qualifications['title'] ?? '',
                '_job_ad_qualifications_text' => $qualifications['text'] ?? '',
                '_job_ad_additional_information_title' => $additionalInformation['title'] ?? '',
                '_job_ad_additional_information_text' => $additionalInformation['text'] ?? '',
                '_job_ad_videos_urls' => json_encode($videosUrls),
                '_job_type_of_employment' => $job_data['typeOfEmployment']['label'] ?? '',

                // Original jobs list summary object
                '_job_summary_full' => json_encode($job_data['__summary'] ?? array()),

                // Properties (full object + Partners extraction)
                '_job_properties_full' => json_encode($job_data['properties'] ?? array()),
                '_job_partners_name' => $this->extract_partners_name($job_data['properties'] ?? array()),

                // Individual Properties (extracted from properties array)
                ...$this->extract_individual_properties($job_data['properties'] ?? array()),

                // Dates
                '_job_created_on' => $job_data['createdOn'] ?? '',
                '_job_updated_on' => $job_data['updatedOn'] ?? '',
                '_job_last_activity' => $job_data['lastActivityOn'] ?? '',
                '_job_expiration_date' => $job_data['targetHiringDate'] ?? '',

                // Apply URL
                '_job_apply_url' => !empty($job_data['refNumber']) ? ('https://jobs.smartrecruiters.com/' . $job_data['refNumber']) : '',
                // aita asbe __job_actions_full aita applyOnWeb theke 
                '_job_apply_on_web' => $job_data['actions']['applyOnWeb']['url'] ?? '',

                // Sync info
                '_job_last_synced' => time(),
                '_job_sync_status' => 'synced'
            )
        );

        return wp_insert_post($post_data);
    }

    /**
     * Update existing job post
     */
    public function update_job($post_id, $job_data)
    {
        $title = $job_data['title'] ?? 'Untitled Job';
        $slug = sanitize_title($title ?: ($job_data['id'] ?? uniqid('job-')));
        $description = $this->format_job_description($job_data);

        // Extract jobAd sections if present for dedicated storage
        $jobAdSections = $job_data['jobAd']['sections'] ?? array();
        $companyDescription = $jobAdSections['companyDescription'] ?? array();
        $jobDescriptionSection = $jobAdSections['jobDescription'] ?? array();
        $qualifications = $jobAdSections['qualifications'] ?? array();
        $additionalInformation = $jobAdSections['additionalInformation'] ?? array();
        $videosUrls = $jobAdSections['videos']['urls'] ?? array();

        $post_data = array(
            'ID' => $post_id,
            'post_title' => $title,
            'post_content' => $description,
            'post_name' => $slug,
            'meta_input' => array(
                // Basic job info
                '_job_title' => $job_data['title'] ?? '',
                '_job_ref_number' => $job_data['refNumber'] ?? '',
                '_job_status' => $job_data['status'] ?? '',
                '_job_posting_status' => $job_data['postingStatus'] ?? '',
                '_job_external_id' => $job_data['id'] ?? '',

                // Department and language
                '_job_department' => $job_data['department']['label'] ?? '',
                '_job_language' => $job_data['language']['label'] ?? '',

                // Experience level (label + full object JSON)
                '_job_experience_level' => is_array($job_data['experienceLevel'] ?? null) ? ($job_data['experienceLevel']['label'] ?? '') : ($job_data['experienceLevel'] ?? ''),
                '_job_experience_level_full' => json_encode($job_data['experienceLevel'] ?? array()),

                // Full location object (JSON)
                '_job_location_full' => json_encode($job_data['location'] ?? array()),
                '_job_location' => $this->format_location($job_data['location'] ?? array()),
                '_job_country_code' => $job_data['location']['countryCode'] ?? '',
                '_job_city' => $job_data['location']['city'] ?? '',
                '_job_region_code' => $job_data['location']['regionCode'] ?? '',
                '_job_postal_code' => $job_data['location']['postalCode'] ?? $job_data['location']['zipCode'] ?? '',
                '_job_remote' => !empty($job_data['location']['remote']) ? 'REMOTE' : 'ONSITE',

                // Full actions object (JSON)
                '_job_actions_full' => json_encode($job_data['actions'] ?? array()),
                '_job_api_url' => $job_data['actions']['details']['url'] ?? '',

                // Job Ad sections (full object) - check multiple possible field names
                '_job_ad_full' => json_encode($job_data['jobAd'] ?? $job_data['jobAdSections'] ?? $job_data['ad'] ?? array()),

                // Job Ad sections (dedicated fields for details page)
                '_job_ad_company_description_title' => $companyDescription['title'] ?? '',
                '_job_ad_company_description_text' => $companyDescription['text'] ?? '',
                '_job_ad_job_description_title' => $jobDescriptionSection['title'] ?? '',
                '_job_ad_job_description_text' => $jobDescriptionSection['text'] ?? '',
                '_job_ad_qualifications_title' => $qualifications['title'] ?? '',
                '_job_ad_qualifications_text' => $qualifications['text'] ?? '',
                '_job_ad_additional_information_title' => $additionalInformation['title'] ?? '',
                '_job_ad_additional_information_text' => $additionalInformation['text'] ?? '',
                '_job_ad_videos_urls' => json_encode($videosUrls),

                // Original jobs list summary object
                '_job_summary_full' => json_encode($job_data['__summary'] ?? array()),

                // Properties (full object + Partners extraction)
                '_job_properties_full' => json_encode($job_data['properties'] ?? array()),
                '_job_partners_name' => $this->extract_partners_name($job_data['properties'] ?? array()),

                // Individual Properties (extracted from properties array)
                ...$this->extract_individual_properties($job_data['properties'] ?? array()),

                // Dates
                '_job_created_on' => $job_data['createdOn'] ?? '',
                '_job_updated_on' => $job_data['updatedOn'] ?? '',
                '_job_last_activity' => $job_data['lastActivityOn'] ?? '',

                // Apply URL
                '_job_apply_url' => !empty($job_data['refNumber']) ? ('https://jobs.smartrecruiters.com/' . $job_data['refNumber']) : '',

                // Sync info
                '_job_last_synced' => time(),
                '_job_sync_status' => 'synced'
            )
        );

        return wp_update_post($post_data);
    }

    private function format_job_description($job_data)
    {
        $description = '';
        $description .= '<h3>Job Information</h3>';
        $description .= '<p><strong>Title:</strong> ' . ($job_data['title'] ?? 'N/A') . '</p>';
        $description .= '<p><strong>Reference Number:</strong> ' . ($job_data['refNumber'] ?? 'N/A') . '</p>';
        $description .= '<p><strong>Status:</strong> ' . ($job_data['status'] ?? 'N/A') . '</p>';
        $description .= '<p><strong>Posting Status:</strong> ' . ($job_data['postingStatus'] ?? 'N/A') . '</p>';
        if (isset($job_data['department']['label'])) {
            $description .= '<p><strong>Department:</strong> ' . $job_data['department']['label'] . '</p>';
        }
        if (isset($job_data['location'])) {
            $location = $this->format_location($job_data['location']);
            if ($location) {
                $description .= '<p><strong>Location:</strong> ' . $location . '</p>';
            }
        }
        if (isset($job_data['language']['label'])) {
            $description .= '<p><strong>Language:</strong> ' . $job_data['language']['label'] . '</p>';
        }
        if (isset($job_data['createdOn'])) {
            $description .= '<p><strong>Created:</strong> ' . date('Y-m-d H:i:s', strtotime($job_data['createdOn'])) . '</p>';
        }
        if (isset($job_data['updatedOn'])) {
            $description .= '<p><strong>Last Updated:</strong> ' . date('Y-m-d H:i:s', strtotime($job_data['updatedOn'])) . '</p>';
        }
        return $description;
    }

    private function format_location($location_data)
    {
        if (empty($location_data)) {
            return '';
        }
        $location_parts = array();
        if (isset($location_data['city'])) {
            $location_parts[] = $location_data['city'];
        }
        if (isset($location_data['regionCode'])) {
            $location_parts[] = $location_data['regionCode'];
        }
        if (isset($location_data['country'])) {
            $location_parts[] = $location_data['country'];
        }
        return implode(', ', $location_parts);
    }

    private function extract_partners_name($properties)
    {
        if (!is_array($properties)) {
            return '';
        }

        foreach ($properties as $property) {
            if (isset($property['key']) && $property['key'] === 'Partners') {
                if (isset($property['value']['label'])) {
                    return $property['value']['label'];
                }
            }
        }

        return '';
    }

    /**
     * Extract individual properties and return as array of meta fields
     */
    private function extract_individual_properties($properties)
    {
        $property_meta = array();

        if (!is_array($properties)) {
            return $property_meta;
        }

        foreach ($properties as $property) {
            if (isset($property['key']) && isset($property['value'])) {
                $key = sanitize_key($property['key']);
                $label = $property['value']['label'] ?? '';
                $id = $property['value']['id'] ?? '';

                // Store both label and ID
                $property_meta['_job_property_' . $key . '_label'] = $label;
                $property_meta['_job_property_' . $key . '_id'] = $id;
                $property_meta['_job_property_' . $key . '_full'] = json_encode($property);
            }
        }

        return $property_meta;
    }
}

// Initialize the plugin
new SmartRecruitersJobSyncPlugin();
