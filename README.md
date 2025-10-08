# SmartRecruiters Job Sync Plugin for WordPress

A comprehensive WordPress plugin that automatically syncs job listings from SmartRecruiters API and manages them as custom post types with custom fields. The plugin includes OAuth2 authentication support for secure API access.

## Features

- **SmartRecruiters Integration**: Direct integration with SmartRecruiters API
- **OAuth2 Authentication**: Secure API access using client ID and client secret
- **Custom Post Type**: Creates a "Jobs" post type with full WordPress integration
- **Custom Fields**: Includes fields for company, location, department, experience level, employment type, remote work options, salary, and apply URL
- **Automatic Synchronization**: Scheduled sync using WordPress cron jobs
- **Manual Sync**: Admin interface for manual synchronization
- **Smart Sync Logic**: Adds new jobs, updates existing ones, and removes jobs no longer available in the API
- **Admin Dashboard**: Complete settings page for API configuration
- **Error Handling**: Comprehensive error logging and user feedback

## Installation

1. Upload the `job-sync-plugin.php` file to your WordPress `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the "Job Sync" menu in your WordPress admin dashboard
4. Configure your SmartRecruiters API settings

## Configuration

### SmartRecruiters API Settings

Navigate to **Job Sync > Job Sync Settings** in your WordPress admin to configure:

- **SmartRecruiters API URL**: 
  - Sandbox: `https://api.sandbox.smartrecruiters.com`
  - Production: `https://api.smartrecruiters.com`
- **Client ID**: Your SmartRecruiters OAuth2 client ID
- **Client Secret**: Your SmartRecruiters OAuth2 client secret
- **Sync Interval**: How often to sync jobs (1-168 hours, default: 6 hours)

### SmartRecruiters API Requirements

The plugin expects your SmartRecruiters API to have:

1. **OAuth2 Token Endpoint**: `{API_URL}/identity/oauth/token`
   - Accepts `client_credentials` grant type
   - Returns `access_token` in response

2. **Jobs Endpoint**: `{API_URL}/core/v1/jobs`
   - Requires Bearer token authentication
   - Returns SmartRecruiters job data

### SmartRecruiters API Response Format

The plugin handles SmartRecruiters' standard job object format:

```json
{
  "content": [
    {
      "id": "unique-job-id",
      "name": "Job Title",
      "company": {
        "name": "Company Name"
      },
      "location": {
        "city": "New York",
        "region": "NY",
        "country": "US"
      },
      "department": {
        "label": "Engineering"
      },
      "experienceLevel": "MID_LEVEL",
      "typeOfEmployment": "FULL_TIME",
      "workplaceModel": "REMOTE",
      "salary": {
        "min": 50000,
        "max": 70000,
        "currency": "USD"
      },
      "refNumber": "job-reference-number",
      "jobAd": {
        "sections": {
          "jobDescription": {
            "title": "Job Description",
            "text": "Job description content"
          },
          "qualifications": {
            "title": "Qualifications",
            "text": "Required qualifications"
          }
        }
      }
    }
  ]
}
```

## Usage

### Automatic Sync

Once configured, the plugin automatically syncs jobs based on your specified interval. The sync process:

1. Obtains an OAuth2 access token from SmartRecruiters
2. Fetches current jobs from the SmartRecruiters API
3. Compares with existing WordPress posts
4. Adds new jobs, updates existing ones, and removes outdated jobs

### Manual Sync

You can manually trigger a sync from the admin dashboard:

1. Go to **Job Sync > Job Sync Settings**
2. Click "Sync Jobs Now" button
3. Monitor the sync status in real-time

### Managing Jobs

- **View Jobs**: Navigate to **Jobs** in your WordPress admin to see all synced jobs
- **Edit Jobs**: Click on any job to edit its details (manual edits may be overwritten during sync)
- **Sync Information**: Each job shows its last sync time and status

## Custom Fields

The plugin creates the following custom fields for each job:

- `_job_company`: Company name from SmartRecruiters
- `_job_location`: Formatted location (City, Region, Country)
- `_job_department`: Department from SmartRecruiters
- `_job_experience_level`: ENTRY_LEVEL, MID_LEVEL, SENIOR_LEVEL, EXECUTIVE
- `_job_employment_type`: FULL_TIME, PART_TIME, CONTRACT, TEMPORARY, INTERNSHIP
- `_job_remote`: REMOTE, HYBRID, ONSITE
- `_job_salary`: Formatted salary range with currency
- `_job_apply_url`: SmartRecruiters job application URL
- `_job_external_id`: SmartRecruiters job ID (read-only)
- `_job_last_synced`: Timestamp of last sync
- `_job_sync_status`: Current sync status

## Frontend Display

Jobs are available as a custom post type and can be displayed using:

- Standard WordPress post queries
- Custom post type archive pages
- Shortcodes (if implemented)
- REST API endpoints

### Example Query

```php
$jobs = get_posts(array(
    'post_type' => 'job',
    'posts_per_page' => 10,
    'meta_query' => array(
        array(
            'key' => '_job_employment_type',
            'value' => 'FULL_TIME',
            'compare' => '='
        )
    )
));
```

## SmartRecruiters Setup

### Getting API Credentials

1. Log in to your SmartRecruiters account
2. Go to Settings > Integrations > API
3. Create a new API application
4. Note down your Client ID and Client Secret
5. Ensure your application has the necessary permissions for job data

### API Endpoints Used

- **Token**: `POST /identity/oauth/token`
- **Jobs**: `GET /core/v1/jobs`

## Troubleshooting

### Common Issues

1. **Sync Fails**: Check your SmartRecruiters API credentials and URL
2. **No Jobs Appearing**: Verify your SmartRecruiters account has jobs published
3. **Authentication Errors**: Ensure your Client ID and Client Secret are correct
4. **Cron Not Running**: Ensure WordPress cron is working (consider using a real cron job)

### Debug Mode

Enable WordPress debug mode to see detailed error logs:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check your WordPress error logs for messages starting with "SmartRecruiters:"

### Manual Cron Trigger

If automatic sync isn't working, you can manually trigger the cron:

```php
// Run this in WordPress admin or via WP-CLI
do_action('smartrecruiters_job_sync_cron');
```

### Testing API Connection

You can test your SmartRecruiters API connection using curl:

```bash
curl --location 'https://api.sandbox.smartrecruiters.com/identity/oauth/token' \
--header 'Content-Type: application/x-www-form-urlencoded' \
--data-urlencode 'client_id=YOUR_CLIENT_ID' \
--data-urlencode 'client_secret=YOUR_CLIENT_SECRET' \
--data-urlencode 'grant_type=client_credentials'
```

## Security Considerations

- Client secret is stored securely in WordPress options
- All API communications use HTTPS
- Input sanitization and output escaping implemented
- Nonce verification for admin actions
- Capability checks for admin functions

## Customization

### Hooks and Filters

The plugin provides several hooks for customization:

```php
// Modify job data before saving
add_filter('smartrecruiters_job_data', function($job_data, $external_job) {
    // Custom modifications
    return $job_data;
}, 10, 2);

// Custom sync interval
add_filter('smartrecruiters_sync_interval', function($interval) {
    return 12; // 12 hours
});
```

### Styling

Jobs inherit your theme's styling. You can add custom CSS:

```css
.job-listing {
    border: 1px solid #ddd;
    padding: 20px;
    margin-bottom: 20px;
}
```

## Support

For support and customization requests, please contact the plugin developer.

## Changelog

### Version 1.0.0
- Initial release
- SmartRecruiters API integration
- OAuth2 authentication
- Custom post type and fields
- Automatic and manual sync
- Admin dashboard
- Error handling and logging

## License

GPL v2 or later
