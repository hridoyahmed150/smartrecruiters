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

        // Webhook endpoint for real-time job updates
        add_filter('query_vars', array($this, 'add_webhook_query_var'));
        add_action('init', array($this, 'add_webhook_endpoint'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));
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
            <p>Click the button below to manually sync jobs from SmartRecruiters API.</p>
            <button type="button" id="manual-sync-btn" class="button button-primary">Sync Jobs Now</button>
            <div id="sync-status" style="margin-top: 10px;"></div>
            <textarea id="sync-log" style="margin-top:10px;width:100%;height:180px;white-space:pre;" readonly
                placeholder="Logs will appear here..."></textarea>

            <hr>
            <h2>Webhook Management</h2>
            <p>Manage real-time webhook subscriptions for instant job updates.</p>

            <?php
            $webhook_id = get_option('smartrecruiters_webhook_id');
            $webhook_secret = get_option('smartrecruiters_webhook_secret');
            $webhook_url = home_url('/smartrecruiters-webhook/');
            ?>

            <div style="background: #f0f0f1; padding: 15px; margin: 15px 0; border-left: 4px solid #2271b1;">
                <p><strong>Webhook URL:</strong> <code><?php echo esc_html($webhook_url); ?></code></p>
                <?php if ($webhook_id): ?>
                    <p><strong>Webhook ID:</strong> <code><?php echo esc_html($webhook_id); ?></code></p>
                <?php endif; ?>
                <?php if ($webhook_secret): ?>
                    <p><strong>Handshake Status:</strong> <span style="color:green;">✅ Completed</span></p>
                    <p><strong>Webhook Secret:</strong> <code><?php echo esc_html(substr($webhook_secret, 0, 20)) . '...'; ?></code>
                    </p>
                <?php else: ?>
                    <p><strong>Handshake Status:</strong> <span style="color:orange;">⏳ Pending</span></p>
                    <p class="description">Webhook create করার পর activate করলে handshake automatically হবে।</p>
                <?php endif; ?>
            </div>

            <button type="button" id="create-webhook-btn" class="button button-secondary">Create Webhook Subscription</button>
            <button type="button" id="delete-webhook-btn" class="button button-secondary">Delete Webhook Subscription</button>
            <div id="webhook-status" style="margin-top: 10px;"></div>

            <p class="description" style="margin-top: 15px;">
                <strong>Note:</strong> Webhook subscription create করার পর, SmartRecruiters automatically আপনার endpoint এ একটি
                handshake request পাঠাবে।
                Code automatically সেই request handle করবে এবং webhook activate হবে।
            </p>

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
                    echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 0;">';
                    echo '<thead><tr>';
                    echo '<th style="width: 150px;">Time</th>';
                    echo '<th style="width: 180px;">Event</th>';
                    echo '<th style="width: 100px;">Job ID</th>';
                    echo '<th>Job Title</th>';
                    echo '<th style="width: 100px;">Status</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($webhook_logs as $log) {
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
                        echo '<td><span style="color: ' . esc_attr($status_color) . ';">' . esc_html($log['status_label']) . '</span></td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                }
                ?>
            </div>
            <p class="description" style="margin-top: 10px;">
                <strong>How it works:</strong> যখন SmartRecruiters এ job create/update/delete হবে, webhook automatically trigger
                হবে এবং
                আপনার site এ instant update হবে। এখানে সব activity দেখতে পাবেন।
            </p>

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

                    btn.disabled = true;
                    btn.textContent = 'Syncing...';
                    status.innerHTML = '<p>Starting sync...</p>';

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=manual_smartrecruiters_sync&nonce=<?php echo wp_create_nonce('manual_smartrecruiters_sync_nonce'); ?>'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                status.innerHTML = '<p style="color: green;">' + (data.data.message || 'Sync completed') + '</p>';
                            } else {
                                status.innerHTML = '<p style="color: red;">' + (data.data || 'Sync failed') + '</p>';
                            }
                            var logs = (data.data && data.data.logs) ? data.data.logs : [];
                            document.getElementById('sync-log').value = Array.isArray(logs) ? logs.join('\n') : (logs || '');
                            console.log(data);
                        })
                        .catch(error => {
                            status.innerHTML = '<p style="color: red;">Request error: ' + error.message + '</p>';
                            document.getElementById('sync-log').value = 'Request error: ' + error.message;
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.textContent = 'Sync Jobs Now';
                        });
                });

                // Webhook management
                document.getElementById('create-webhook-btn').addEventListener('click', function () {
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
                            } else {
                                status.innerHTML = '<p style="color: red;">' + (data.data || 'Failed to create webhook') + '</p>';
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

                document.getElementById('delete-webhook-btn').addEventListener('click', function () {
                    var btn = this;
                    var status = document.getElementById('webhook-status');

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
                            } else {
                                status.innerHTML = '<p style="color: red;">' + (data.data || 'Failed to delete webhook') + '</p>';
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
        echo '<p><strong>Default API URL:</strong> https://api.sandbox.smartrecruiters.com</p>';
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
        echo '<p>Configure webhook settings for real-time job updates from SmartRecruiters.</p>';
        $webhook_url = home_url('/smartrecruiters-webhook/');
        echo '<p><strong>Webhook URL:</strong> <code>' . esc_html($webhook_url) . '</code></p>';
        echo '<p><em>Copy this URL to your SmartRecruiters webhook subscription.</em></p>';
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
        error_log('SmartRecruiters Webhook: Request received - URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        error_log('SmartRecruiters Webhook: Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
        error_log('SmartRecruiters Webhook: Query var: ' . (get_query_var('smartrecruiters_webhook') ? 'YES' : 'NO'));

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
            error_log('SmartRecruiters Webhook: Webhooks are disabled in settings');
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
            error_log('SmartRecruiters Webhook: Handshake request received with secret: ' . substr($hook_secret, 0, 10) . '...');
            // This is a handshake request - save the secret and return it
            update_option('smartrecruiters_webhook_secret', $hook_secret);

            // Return the X-Hook-Secret header in response
            header('X-Hook-Secret: ' . $hook_secret);
            http_response_code(200);
            exit('OK');
        }

        // Get raw POST data
        $raw_data = file_get_contents('php://input');
        error_log('SmartRecruiters Webhook: Raw POST data received: ' . substr($raw_data, 0, 500));

        $webhook_data = json_decode($raw_data, true);

        // Log decoded data
        if ($webhook_data) {
            error_log('SmartRecruiters Webhook: Decoded data - EventType: ' . ($webhook_data['eventType'] ?? 'N/A'));
            error_log('SmartRecruiters Webhook: Full webhook data: ' . print_r($webhook_data, true));
        }

        // If JSON decode fails, it might be an empty handshake request
        if (json_last_error() !== JSON_ERROR_NONE && !empty($raw_data)) {
            error_log('SmartRecruiters Webhook: JSON decode error: ' . json_last_error_msg());
            http_response_code(400);
            exit('Invalid JSON');
        }

        // If no data and no hook secret, might be a ping/test request
        if (empty($webhook_data) && empty($hook_secret)) {
            error_log('SmartRecruiters Webhook: Empty request - treating as ping');
            http_response_code(200);
            exit('OK');
        }

        // Verify webhook signature if secret is set
        $saved_secret = get_option('smartrecruiters_webhook_secret');
        if (!empty($saved_secret)) {
            $signature = $_SERVER['HTTP_X_SMARTRECRUITERS_SIGNATURE'] ?? '';
            if (!empty($signature)) {
                if (!$this->verify_webhook_signature($raw_data, $signature, $saved_secret)) {
                    error_log('SmartRecruiters Webhook: Signature verification failed');
                    http_response_code(401);
                    exit('Invalid signature');
                }
                error_log('SmartRecruiters Webhook: Signature verified successfully');
            }
        }

        // Process webhook data (only if we have actual data)
        if (!empty($webhook_data)) {
            // Check different possible structures for eventType
            $event_type = $webhook_data['eventType'] ?? $webhook_data['event_type'] ?? $webhook_data['type'] ?? '';

            // Also check if job data is directly in webhook_data or in 'data' key
            $job_data = $webhook_data['data'] ?? $webhook_data;

            if (!empty($event_type)) {
                error_log('SmartRecruiters Webhook: Processing event type: ' . $event_type);
                // Normalize eventType key and ensure data structure
                $webhook_data['eventType'] = $event_type;
                $webhook_data['data'] = $job_data;
                $this->process_webhook_data($webhook_data);
            } else {
                // If no eventType but has job data, try to process it as update
                if (!empty($job_data['id'])) {
                    error_log('SmartRecruiters Webhook: No eventType found, treating as job update');
                    $webhook_data['eventType'] = 'job.updated';
                    $webhook_data['data'] = $job_data;
                    $this->process_webhook_data($webhook_data);
                } else {
                    error_log('SmartRecruiters Webhook: No eventType found in webhook data');
                    error_log('SmartRecruiters Webhook: Available keys: ' . implode(', ', array_keys($webhook_data)));
                }
            }
        } else {
            error_log('SmartRecruiters Webhook: Empty webhook data received');
        }

        http_response_code(200);
        exit('OK');
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
        $event_type = $webhook_data['eventType'] ?? '';
        $job_data = $webhook_data['data'] ?? array();

        // If job_data is empty, maybe data is directly in webhook_data
        if (empty($job_data) && isset($webhook_data['id'])) {
            $job_data = $webhook_data;
        }

        $job_id = $job_data['id'] ?? 'N/A';
        $job_title = $job_data['title'] ?? 'N/A';

        // Log webhook event - ALWAYS log when received
        error_log('SmartRecruiters Webhook: Processing event - Type: ' . $event_type . ', Job ID: ' . $job_id . ', Title: ' . $job_title);
        $this->log_webhook_activity($event_type, $job_id, $job_title, 'received');

        // Validate job data
        if (empty($job_id) || $job_id === 'N/A') {
            error_log('SmartRecruiters Webhook: Missing job ID in webhook data');
            $this->log_webhook_activity($event_type, 'N/A', $job_title, 'failed');
            return;
        }

        // Handle different event types from SmartRecruiters webhooks
        // Event types: job.created, job.updated, job.status.updated, position.created, position.updated, position.deleted
        switch ($event_type) {
            case 'job.created':
            case 'job.updated':
            case 'job.status.updated':
            case 'position.created':
            case 'position.updated':
                error_log('SmartRecruiters Webhook: Syncing job - ID: ' . $job_id);
                $result = $this->sync_single_job($job_data);
                if ($result) {
                    error_log('SmartRecruiters Webhook: Job sync successful - ID: ' . $job_id);
                    $this->log_webhook_activity($event_type, $job_id, $job_title, 'success');
                } else {
                    error_log('SmartRecruiters Webhook: Job sync failed - ID: ' . $job_id);
                    $this->log_webhook_activity($event_type, $job_id, $job_title, 'failed');
                }
                break;
            case 'position.deleted':
                error_log('SmartRecruiters Webhook: Deleting job - ID: ' . $job_id);
                $result = $this->delete_job_by_external_id($job_id);
                if ($result) {
                    error_log('SmartRecruiters Webhook: Job deleted successfully - ID: ' . $job_id);
                    $this->log_webhook_activity($event_type, $job_id, $job_title, 'deleted');
                } else {
                    error_log('SmartRecruiters Webhook: Job delete failed - ID: ' . $job_id);
                    $this->log_webhook_activity($event_type, $job_id, $job_title, 'delete_failed');
                }
                break;
            default:
                error_log('SmartRecruiters Webhook: Unknown event type - ' . $event_type);
                $this->log_webhook_activity($event_type, $job_id, $job_title, 'unknown_event');
        }
    }

    /**
     * Log webhook activity for admin display
     */
    private function log_webhook_activity($event_type, $job_id, $job_title, $status)
    {
        $logs = get_option('smartrecruiters_webhook_logs', array());

        // Keep only last 50 entries
        if (count($logs) >= 50) {
            $logs = array_slice($logs, -49);
        }

        $event_labels = array(
            'job.created' => 'Job Created',
            'job.updated' => 'Job Updated',
            'job.status.updated' => 'Job Status Updated',
            'position.created' => 'Position Created',
            'position.updated' => 'Position Updated',
            'position.deleted' => 'Position Deleted'
        );

        $status_labels = array(
            'received' => 'Received',
            'success' => 'Success',
            'failed' => 'Failed',
            'deleted' => 'Deleted',
            'delete_failed' => 'Delete Failed',
            'unknown_event' => 'Unknown Event'
        );

        $log_entry = array(
            'timestamp' => time(),
            'event_type' => $event_type,
            'event_label' => $event_labels[$event_type] ?? $event_type,
            'job_id' => $job_id,
            'job_title' => $job_title,
            'status' => $status,
            'status_label' => $status_labels[$status] ?? $status
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
     * Sync a single job from webhook data
     */
    private function sync_single_job($job_data)
    {
        if (empty($job_data['id'])) {
            return false;
        }

        // Check if job already exists
        $existing_post = $this->find_job_by_external_id($job_data['id']);

        if ($existing_post) {
            // Update existing job
            $result = $this->update_job_post($existing_post->ID, $job_data);
            return $result !== false;
        } else {
            // Create new job
            $result = $this->create_job_from_webhook($job_data);
            return $result !== false;
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
            return $job_data;
        }

        // Otherwise, fetch full job details from API
        $job_id = $job_data['id'] ?? '';
        if (empty($job_id)) {
            return $job_data;
        }

        $options = get_option('smartrecruiters_job_sync_options');
        $access_token = $this->get_access_token();

        if (!$access_token) {
            error_log('SmartRecruiters Webhook: Failed to get access token for enriching job data');
            return $job_data;
        }

        $job_url = rtrim($options['api_url'], '/') . '/jobs/' . $job_id;
        $response = wp_remote_get($job_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            error_log('SmartRecruiters Webhook: Failed to fetch job details: ' . $response->get_error_message());
            return $job_data;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            $full_job_data = json_decode(wp_remote_retrieve_body($response), true);
            if ($full_job_data) {
                return $full_job_data;
            }
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

        $result = $this->sync_jobs();

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

        $result = $this->delete_webhook_subscription();

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

            // Activate the webhook subscription (required by SmartRecruiters API)
            // According to docs: PUT /webhooks-api/v201907/subscriptions/{id}/activation
            if (!empty($webhook_id)) {
                $activate_endpoint = rtrim($options['api_url'], '/') . '/webhooks-api/v201907/subscriptions/' . $webhook_id . '/activation';
                $activate_response = wp_remote_request($activate_endpoint, array(
                    'method' => 'PUT',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $access_token
                    ),
                    'timeout' => 30
                ));

                if (!is_wp_error($activate_response)) {
                    $activate_code = wp_remote_retrieve_response_code($activate_response);
                    if ($activate_code === 200 || $activate_code === 204) {
                        return array(
                            'success' => true,
                            'message' => 'Webhook subscription created and activated successfully'
                        );
                    }
                }
            }

            return array(
                'success' => true,
                'message' => 'Webhook subscription created successfully (activation may be required)'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to create webhook: ' . $response_body
            );
        }
    }

    /**
     * Delete webhook subscription from SmartRecruiters
     */
    private function delete_webhook_subscription()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $webhook_id = get_option('smartrecruiters_webhook_id');

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

        $api_sync = new SmartRecruitersAPISync();
        $result = $api_sync->sync_jobs();
        // Persist last run info (for cron or manual) to display in admin
        $last_run = array(
            'timestamp' => time(),
            'message' => isset($result['message']) ? $result['message'] : '',
            'success' => !empty($result['success']),
            'logs' => isset($result['logs']) ? $result['logs'] : array(),
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
}


/**
 * SmartRecruiters API Sync Class
 * Fetches list of jobs, then fetches each job's details and stores them
 */
class SmartRecruitersAPISync
{

    private $options;
    private $logs = array();

    public function __construct()
    {
        $this->options = get_option('smartrecruiters_job_sync_options');
    }

    public function sync_jobs()
    {
        try {
            $this->logs = array();
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception('Failed to obtain access token from SmartRecruiters API');
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

                $job_details = $this->fetch_single_job_details($access_token, $job_summary);
                if (!$job_details) {
                    // Fallback to summary if details missing
                    $job_details = $job_summary;
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

    private function delete_all_existing_jobs()
    {
        global $wpdb;
        $job_posts = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'job'"
        );
        foreach ($job_posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }

    private function create_job($job_data)
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
