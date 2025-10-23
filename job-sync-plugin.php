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
        // No custom intervals needed - using WordPress default 'daily'
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
        $experience_level = get_post_meta($post->ID, '_job_experience_level', true);
        $location_full = get_post_meta($post->ID, '_job_location_full', true);
        $actions_full = get_post_meta($post->ID, '_job_actions_full', true);
        $job_ad_full = get_post_meta($post->ID, '_job_ad_full', true);

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
     * Schedule cron job
     */
    public function schedule_cron()
    {
        if (!wp_next_scheduled('smartrecruiters_job_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'smartrecruiters_job_sync_cron');
        }
    }

    /**
     * Reschedule cron when settings change
     */
    public function reschedule_cron_on_settings_change($old_value, $value)
    {
        // Clear previous schedule
        wp_clear_scheduled_hook('smartrecruiters_job_sync_cron');

        // Schedule with daily interval
        if (!wp_next_scheduled('smartrecruiters_job_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'smartrecruiters_job_sync_cron');
        }
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
            wp_send_json_success(array('message' => $result['message']));
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

        $api_sync = new SmartRecruitersAPISyncV2();
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
        if (!wp_next_scheduled('smartrecruiters_job_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'smartrecruiters_job_sync_cron');
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
 * SmartRecruiters API Sync Class - Version 2
 * Fetches list of jobs, then fetches each job's details and stores them
 */
class SmartRecruitersAPISyncV2
{

    private $options;

    public function __construct()
    {
        $this->options = get_option('smartrecruiters_job_sync_options');
    }

    public function sync_jobs()
    {
        try {
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

                $this->create_job($job_details);
                $added++;
            }

            return array(
                'success' => true,
                'message' => sprintf('SmartRecruiters v2 sync completed: %d jobs refreshed with details', $added)
            );

        } catch (Exception $e) {
            error_log('SmartRecruiters Job Sync V2 Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'SmartRecruiters v2 sync failed: ' . $e->getMessage()
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
                break;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                error_log('SmartRecruiters: API Error - HTTP ' . $response_code . ': ' . $body);
                break;
            }

            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('SmartRecruiters: JSON Decode Error: ' . json_last_error_msg());
                break;
            }

            $jobs = isset($data['content']) ? $data['content'] : $data;
            if (empty($jobs)) {
                break;
            }

            $all_jobs = array_merge($all_jobs, $jobs);
            $offset += $limit;

            error_log('SmartRecruiters: Fetched ' . count($jobs) . ' jobs, total so far: ' . count($all_jobs));

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
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('SmartRecruiters: Job details API Error for ID ' . $job_id . ' - HTTP ' . $response_code . ': ' . $body);
            return false;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SmartRecruiters: Job details JSON Decode Error for ID ' . $job_id . ': ' . json_last_error_msg());
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
        $title = $job_data['title'] ?? 'Untitled Job';
        $description = $this->format_job_description($job_data);

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

                // Experience level
                '_job_experience_level' => $job_data['experienceLevel'] ?? '',

                // Full location object (JSON)
                '_job_location_full' => json_encode($job_data['location'] ?? array()),
                '_job_location' => $this->format_location($job_data['location'] ?? array()),
                '_job_country_code' => $job_data['location']['countryCode'] ?? '',
                '_job_city' => $job_data['location']['city'] ?? '',
                '_job_region_code' => $job_data['location']['regionCode'] ?? '',
                '_job_remote' => !empty($job_data['location']['remote']) ? 'REMOTE' : 'ONSITE',

                // Full actions object (JSON)
                '_job_actions_full' => json_encode($job_data['actions'] ?? array()),
                '_job_api_url' => $job_data['actions']['details']['url'] ?? '',

                // Job Ad sections (full object)
                '_job_ad_full' => json_encode($job_data['jobAd'] ?? array()),

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

        return wp_insert_post($post_data);
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
}

// Initialize the plugin
new SmartRecruitersJobSyncPlugin();
