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
        add_action('smartrecruiters_job_sync_cron', array($this, 'sync_jobs'));

        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_custom_cron_interval'));
        add_action('update_option_smartrecruiters_job_sync_options', array($this, 'reschedule_cron_on_settings_change'), 10, 2);

        // AJAX hooks for manual sync
        add_action('wp_ajax_manual_smartrecruiters_sync', array($this, 'manual_sync_ajax'));
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        $this->register_job_post_type();
        $this->add_custom_fields();
        $this->schedule_cron();
    }

    /**
     * Add custom cron interval based on settings (minutes)
     */
    public function add_custom_cron_interval($schedules)
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $minutes = isset($options['sync_interval_minutes']) ? intval($options['sync_interval_minutes']) : 10;
        if ($minutes < 1) {
            $minutes = 1;
        }
        // Cap to a reasonable upper bound to avoid huge schedules
        if ($minutes > 1440) {
            $minutes = 1440;
        }

        $key = 'smartrecruiters_every_' . $minutes . '_minutes';
        $schedules[$key] = array(
            'interval' => $minutes * 60,
            'display' => sprintf(__('Every %d Minutes'), $minutes)
        );
        return $schedules;
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
        $remote = get_post_meta($post->ID, '_job_remote', true);
        $apply_url = get_post_meta($post->ID, '_job_apply_url', true);
        $external_id = get_post_meta($post->ID, '_job_external_id', true);
        $api_url = get_post_meta($post->ID, '_job_api_url', true);

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
            'job_external_id' => '_job_external_id'
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

            <script>
                document.getElementById('manual-sync-btn').addEventListener('click', function () {
                    var btn = this;
                    var status = document.getElementById('sync-status');

                    btn.disabled = true;
                    btn.textContent = 'Syncing...';
                    status.innerHTML = '<p>Starting sync...</p>';

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: 'action=manual_smartrecruiters_sync&nonce=<?php echo wp_create_nonce('manual_smartrecruiters_sync_nonce'); ?>'
                    })
                        .then(response => response.json())
                        .then(data => {
                            console.log(data);
                        })
                        .catch(error => {
                            console.error('Error:', error);
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
            </script>
        </div>
        <?php
    }

    /**
     * Admin init
     */
    public function admin_init()
    {
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

        add_settings_field(
            'sync_interval_minutes',
            'Sync Interval (minutes)',
            array($this, 'sync_interval_minutes_callback'),
            'smartrecruiters_job_sync_settings',
            'smartrecruiters_api_section'
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
     * Limit callback
     */
    public function limit_callback()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $value = isset($options['limit']) ? $options['limit'] : '100';
        echo '<input type="number" name="smartrecruiters_job_sync_options[limit]" value="' . esc_attr($value) . '" min="1" max="1000" />';
    }

    /**
     * Sync interval callback
     */
    public function sync_interval_minutes_callback()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $value = isset($options['sync_interval_minutes']) ? $options['sync_interval_minutes'] : '10';
        echo '<input type="number" name="smartrecruiters_job_sync_options[sync_interval_minutes]" value="' . esc_attr($value) . '" min="1" max="1440" />';
        echo '<p class="description">How often to sync jobs (in minutes, 1-1440)</p>';
    }

    /**
     * Schedule cron job
     */
    public function schedule_cron()
    {
        $options = get_option('smartrecruiters_job_sync_options');
        $minutes = isset($options['sync_interval_minutes']) ? intval($options['sync_interval_minutes']) : 10;
        if ($minutes < 1) {
            $minutes = 1;
        }
        if ($minutes > 1440) {
            $minutes = 1440;
        }
        $schedule_key = 'smartrecruiters_every_' . $minutes . '_minutes';

        if (!wp_next_scheduled('smartrecruiters_job_sync_cron')) {
            wp_schedule_event(time(), $schedule_key, 'smartrecruiters_job_sync_cron');
        }
    }

    /**
     * Reschedule cron when settings change
     */
    public function reschedule_cron_on_settings_change($old_value, $value)
    {
        // Clear previous schedule
        wp_clear_scheduled_hook('smartrecruiters_job_sync_cron');

        // Schedule with new interval
        $minutes = isset($value['sync_interval_minutes']) ? intval($value['sync_interval_minutes']) : 10;
        if ($minutes < 1) {
            $minutes = 1;
        }
        if ($minutes > 1440) {
            $minutes = 1440;
        }
        $schedule_key = 'smartrecruiters_every_' . $minutes . '_minutes';
        if (!wp_next_scheduled('smartrecruiters_job_sync_cron')) {
            wp_schedule_event(time(), $schedule_key, 'smartrecruiters_job_sync_cron');
        }
    }

    /**
     * Manual sync AJAX handler
     */
    public function manual_sync_ajax()
    {
        check_ajax_referer('manual_smartrecruiters_sync_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $result = $this->sync_jobs();

        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . $result['message'] . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . $result['message'] . '</p></div>';
        }
    }

    /**
     * Main sync function
     */
    public function sync_jobs()
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
        return $api_sync->sync_jobs();
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        $this->register_job_post_type();
        flush_rewrite_rules();

        // Schedule initial cron
        $options = get_option('smartrecruiters_job_sync_options');
        $minutes = isset($options['sync_interval_minutes']) ? intval($options['sync_interval_minutes']) : 10;
        if ($minutes < 1) {
            $minutes = 1;
        }
        if ($minutes > 1440) {
            $minutes = 1440;
        }
        $schedule_key = 'smartrecruiters_every_' . $minutes . '_minutes';
        if (!wp_next_scheduled('smartrecruiters_job_sync_cron')) {
            wp_schedule_event(time(), $schedule_key, 'smartrecruiters_job_sync_cron');
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
 */
class SmartRecruitersAPISync
{

    private $options;

    public function __construct()
    {
        $this->options = get_option('smartrecruiters_job_sync_options');
    }

    /**
     * Main sync function
     */
    public function sync_jobs()
    {
        try {
            // Get access token
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception('Failed to obtain access token from SmartRecruiters API');
            }

            // Fetch jobs from SmartRecruiters API
            $jobs = $this->fetch_jobs_from_api($access_token);
            if (!$jobs) {
                throw new Exception('Failed to fetch jobs from SmartRecruiters API');
            }

            // Delete all existing job posts first
            $this->delete_all_existing_jobs();

            // Create new jobs from API data
            $added = 0;
            foreach ($jobs as $job_data) {
                $external_id = $job_data['id'] ?? null;
                if (!$external_id)
                    continue;

                $this->create_job($job_data);
                $added++;
            }

            return array(
                'success' => true,
                'message' => sprintf('SmartRecruiters sync completed: %d jobs refreshed from API', $added)
            );

        } catch (Exception $e) {
            error_log('SmartRecruiters Job Sync Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'SmartRecruiters sync failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get OAuth2 access token from SmartRecruiters API
     */
    private function get_access_token()
    {
        $token_url = $this->options['api_url'] . '/identity/oauth/token';

        error_log('SmartRecruiters: Requesting access token from: ' . $token_url);
        error_log('SmartRecruiters: Client ID: ' . $this->options['client_id']);
        error_log('SmartRecruiters: Client Secret: ' . (empty($this->options['client_secret']) ? 'EMPTY' : 'SET'));

        // SmartRecruiters API expects form-urlencoded data
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
            error_log('SmartRecruiters Token Error: ' . $response->get_error_message());
            error_log('SmartRecruiters Token Error Code: ' . $response->get_error_code());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('SmartRecruiters: Token Response Code: ' . $response_code);
        error_log('SmartRecruiters: Token Response Body: ' . $body);

        if ($response_code !== 200) {
            error_log('SmartRecruiters Token Error - HTTP ' . $response_code . ': ' . $body);
            return false;
        }

        $token_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SmartRecruiters: Token JSON Decode Error: ' . json_last_error_msg());
            return false;
        }

        if (isset($token_data['access_token'])) {
            error_log('SmartRecruiters: Successfully obtained access token');
            return $token_data['access_token'];
        } else {
            error_log('SmartRecruiters Token Error: No access_token in response - ' . $body);
            if (isset($token_data['error'])) {
                error_log('SmartRecruiters Token Error: ' . $token_data['error']);
            }
            if (isset($token_data['error_description'])) {
                error_log('SmartRecruiters Token Error Description: ' . $token_data['error_description']);
            }
            return false;
        }
    }

    /**
     * Fetch jobs from SmartRecruiters API
     */
    private function fetch_jobs_from_api($access_token)
    {
        $limit = $this->options['limit'] ?? 100;
        // SmartRecruiters jobs endpoint
        $jobs_url = $this->options['api_url'] . '/jobs?limit=' . $limit;

        error_log('SmartRecruiters: Fetching jobs from URL: ' . $jobs_url);
        error_log('SmartRecruiters: Using access token: ' . substr($access_token, 0, 20) . '...');

        $response = wp_remote_get($jobs_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            error_log('SmartRecruiters Jobs Fetch Error: ' . $response->get_error_message());
            error_log('SmartRecruiters Jobs Fetch Error Code: ' . $response->get_error_code());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('SmartRecruiters: API Response Code: ' . $response_code);
        error_log('SmartRecruiters: API Response Body: ' . $body);

        if ($response_code !== 200) {
            error_log('SmartRecruiters Jobs Fetch Error - HTTP ' . $response_code . ': ' . $body);
            return false;
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SmartRecruiters: JSON Decode Error: ' . json_last_error_msg());
            return false;
        }

        // SmartRecruiters returns jobs directly in the response
        $jobs = isset($data['content']) ? $data['content'] : $data;
        error_log('SmartRecruiters: Found ' . count($jobs) . ' jobs in API response');

        return $jobs;
    }

    /**
     * Delete all existing job posts
     */
    private function delete_all_existing_jobs()
    {
        global $wpdb;

        // Get all job post IDs
        $job_posts = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'job'"
        );

        $deleted_count = 0;
        foreach ($job_posts as $post) {
            if (wp_delete_post($post->ID, true)) {
                $deleted_count++;
            }
        }

        error_log('SmartRecruiters: Deleted ' . $deleted_count . ' existing job posts');
        return $deleted_count;
    }

    /**
     * Create new job post from SmartRecruiters data
     */
    private function create_job($job_data)
    {
        // Map SmartRecruiters fields to our custom fields based on actual API response
        $title = $job_data['title'] ?? 'Untitled Job';
        $description = $this->format_job_description($job_data);

        $post_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_type' => 'job',
            'meta_input' => array(
                '_job_title' => $job_data['title'] ?? '',
                '_job_ref_number' => $job_data['refNumber'] ?? '',
                '_job_status' => $job_data['status'] ?? '',
                '_job_posting_status' => $job_data['postingStatus'] ?? '',
                '_job_department' => $job_data['department']['label'] ?? '',
                '_job_location' => $this->format_location($job_data['location'] ?? array()),
                '_job_language' => $job_data['language']['label'] ?? '',
                '_job_country_code' => $job_data['location']['countryCode'] ?? '',
                '_job_city' => $job_data['location']['city'] ?? '',
                '_job_region_code' => $job_data['location']['regionCode'] ?? '',
                '_job_remote' => $job_data['location']['remote'] ? 'REMOTE' : 'ONSITE',
                '_job_created_on' => $job_data['createdOn'] ?? '',
                '_job_updated_on' => $job_data['updatedOn'] ?? '',
                '_job_last_activity' => $job_data['lastActivityOn'] ?? '',
                '_job_apply_url' => $job_data['refNumber'] ? 'https://jobs.smartrecruiters.com/' . $job_data['refNumber'] : '',
                '_job_external_id' => $job_data['id'] ?? '',
                '_job_api_url' => $job_data['actions']['details']['url'] ?? '',
                '_job_last_synced' => time(),
                '_job_sync_status' => 'synced'
            )
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id && !is_wp_error($post_id)) {
            error_log('SmartRecruiters: Created job post ID ' . $post_id . ' for job ' . $title);
        }

        return $post_id;
    }


    /**
     * Format job description from SmartRecruiters data
     */
    private function format_job_description($job_data)
    {
        $description = '';

        // Add basic job information
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

    /**
     * Format location from SmartRecruiters data
     */
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

    /**
     * Format salary from SmartRecruiters data
     */
    private function format_salary($salary_data)
    {
        if (empty($salary_data)) {
            return '';
        }

        $salary_parts = array();

        if (isset($salary_data['min'])) {
            $salary_parts[] = '$' . number_format($salary_data['min']);
        }

        if (isset($salary_data['max'])) {
            $salary_parts[] = '$' . number_format($salary_data['max']);
        }

        if (isset($salary_data['currency'])) {
            $currency = $salary_data['currency'];
            if ($currency !== 'USD') {
                $salary_parts = array_map(function ($amount) use ($currency) {
                    return str_replace('$', $currency . ' ', $amount);
                }, $salary_parts);
            }
        }

        return implode(' - ', $salary_parts);
    }
}

// Initialize the plugin
new SmartRecruitersJobSyncPlugin();
