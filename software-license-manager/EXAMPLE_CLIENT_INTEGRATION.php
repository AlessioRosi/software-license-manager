<?php
/**
 * Example Client Integration for SLM Version Tracking
 *
 * This file demonstrates how to integrate version tracking
 * into your licensed plugin (Plugin A, Plugin B, etc.)
 */

// Define your plugin constants
define('MY_PLUGIN_VERSION', '1.2.3');
define('MY_PLUGIN_ITEM_REFERENCE', 'plugin-a'); // Unique identifier for your plugin
define('SLM_SERVER_URL', 'https://your-slm-server.com/');
define('SLM_SECRET_KEY', 'your-secret-key-here');

/**
 * Activate license with version information
 *
 * Call this when the user activates their license
 */
function my_plugin_activate_license($license_key) {
    $domain = parse_url(get_site_url(), PHP_URL_HOST);

    $api_params = array(
        'slm_action' => 'slm_activate',
        'secret_key' => SLM_SECRET_KEY,
        'license_key' => $license_key,
        'registered_domain' => $domain,
        'item_reference' => MY_PLUGIN_ITEM_REFERENCE,
        'version' => MY_PLUGIN_VERSION
    );

    $query_string = http_build_query($api_params);
    $response = wp_remote_get(SLM_SERVER_URL . '?' . $query_string, array('timeout' => 20));

    if (is_wp_error($response)) {
        return array('result' => 'error', 'message' => $response->get_error_message());
    }

    $license_data = json_decode(wp_remote_retrieve_body($response), true);

    if ($license_data['result'] == 'success') {
        // Save license key
        update_option('my_plugin_license_key', $license_key);
        update_option('my_plugin_license_active', true);

        // Schedule daily version update check
        if (!wp_next_scheduled('my_plugin_daily_version_update')) {
            wp_schedule_event(time(), 'daily', 'my_plugin_daily_version_update');
        }
    }

    return $license_data;
}

/**
 * Update version on SLM server
 *
 * Call this after plugin updates or periodically
 */
function my_plugin_update_version_on_server() {
    $license_key = get_option('my_plugin_license_key');
    $is_active = get_option('my_plugin_license_active');

    // Only update if license is active
    if (empty($license_key) || !$is_active) {
        return false;
    }

    $domain = parse_url(get_site_url(), PHP_URL_HOST);

    $api_params = array(
        'slm_action' => 'slm_update_version',
        'secret_key' => SLM_SECRET_KEY,
        'license_key' => $license_key,
        'registered_domain' => $domain,
        'item_reference' => MY_PLUGIN_ITEM_REFERENCE,
        'version' => MY_PLUGIN_VERSION
    );

    $response = wp_remote_post(
        SLM_SERVER_URL,
        array(
            'body' => $api_params,
            'timeout' => 20
        )
    );

    if (is_wp_error($response)) {
        error_log('SLM version update failed: ' . $response->get_error_message());
        return false;
    }

    $response_data = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_data['result']) && $response_data['result'] == 'success') {
        // Update last version report timestamp
        update_option('my_plugin_last_version_report', time());
        return true;
    }

    return false;
}

/**
 * Hook: Send version update when plugin is updated
 */
function my_plugin_after_update($upgrader_object, $options) {
    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        foreach ($options['plugins'] as $plugin) {
            // Check if our plugin was updated
            if ($plugin == 'my-plugin/my-plugin.php') { // Replace with your plugin path
                // Wait a moment for everything to settle
                sleep(2);
                // Send version update to server
                my_plugin_update_version_on_server();
            }
        }
    }
}
add_action('upgrader_process_complete', 'my_plugin_after_update', 10, 2);

/**
 * Hook: Daily version update (scheduled task)
 */
function my_plugin_daily_version_update_task() {
    my_plugin_update_version_on_server();
}
add_action('my_plugin_daily_version_update', 'my_plugin_daily_version_update_task');

/**
 * Hook: Send version update on plugin activation
 */
function my_plugin_on_activation() {
    // If license is already active, update version
    $license_key = get_option('my_plugin_license_key');
    $is_active = get_option('my_plugin_license_active');

    if (!empty($license_key) && $is_active) {
        my_plugin_update_version_on_server();
    }
}
register_activation_hook(__FILE__, 'my_plugin_on_activation');

/**
 * Deactivate license
 */
function my_plugin_deactivate_license() {
    $license_key = get_option('my_plugin_license_key');
    $domain = parse_url(get_site_url(), PHP_URL_HOST);

    $api_params = array(
        'slm_action' => 'slm_deactivate',
        'secret_key' => SLM_SECRET_KEY,
        'license_key' => $license_key,
        'registered_domain' => $domain
    );

    $query_string = http_build_query($api_params);
    $response = wp_remote_get(SLM_SERVER_URL . '?' . $query_string, array('timeout' => 20));

    if (is_wp_error($response)) {
        return array('result' => 'error', 'message' => $response->get_error_message());
    }

    $license_data = json_decode(wp_remote_retrieve_body($response), true);

    if ($license_data['result'] == 'success') {
        delete_option('my_plugin_license_key');
        delete_option('my_plugin_license_active');

        // Clear scheduled task
        wp_clear_scheduled_hook('my_plugin_daily_version_update');
    }

    return $license_data;
}

/**
 * Check license status
 */
function my_plugin_check_license() {
    $license_key = get_option('my_plugin_license_key');

    if (empty($license_key)) {
        return array('result' => 'error', 'message' => 'No license key found');
    }

    $api_params = array(
        'slm_action' => 'slm_check',
        'secret_key' => SLM_SECRET_KEY,
        'license_key' => $license_key
    );

    $query_string = http_build_query($api_params);
    $response = wp_remote_get(SLM_SERVER_URL . '?' . $query_string, array('timeout' => 20));

    if (is_wp_error($response)) {
        return array('result' => 'error', 'message' => $response->get_error_message());
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

/**
 * Example usage in your plugin settings page
 */
function my_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>My Plugin License Settings</h1>

        <?php
        if (isset($_POST['activate_license'])) {
            $license_key = sanitize_text_field($_POST['license_key']);
            $result = my_plugin_activate_license($license_key);

            if ($result['result'] == 'success') {
                echo '<div class="notice notice-success"><p>License activated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($result['message']) . '</p></div>';
            }
        }

        if (isset($_POST['deactivate_license'])) {
            $result = my_plugin_deactivate_license();

            if ($result['result'] == 'success') {
                echo '<div class="notice notice-success"><p>License deactivated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($result['message']) . '</p></div>';
            }
        }
        ?>

        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">License Key</th>
                    <td>
                        <input type="text" name="license_key"
                               value="<?php echo esc_attr(get_option('my_plugin_license_key')); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <?php
                        $is_active = get_option('my_plugin_license_active');
                        if ($is_active) {
                            echo '<span style="color: green;">Active</span>';
                        } else {
                            echo '<span style="color: red;">Inactive</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Current Version</th>
                    <td><?php echo esc_html(MY_PLUGIN_VERSION); ?></td>
                </tr>
            </table>

            <?php
            if (get_option('my_plugin_license_active')) {
                submit_button('Deactivate License', 'secondary', 'deactivate_license');
            } else {
                submit_button('Activate License', 'primary', 'activate_license');
            }
            ?>
        </form>
    </div>
    <?php
}

/**
 * IMPORTANT: Replace the following with your actual values:
 *
 * 1. MY_PLUGIN_VERSION - Set to your plugin version
 * 2. MY_PLUGIN_ITEM_REFERENCE - Unique identifier for your plugin (e.g., 'plugin-a', 'plugin-b')
 * 3. SLM_SERVER_URL - Your SLM server URL
 * 4. SLM_SECRET_KEY - Your SLM verification secret key
 * 5. 'my-plugin/my-plugin.php' - Replace with your plugin's main file path
 * 6. All option names (my_plugin_*) - Replace with your plugin's prefix
 * 7. All function names (my_plugin_*) - Replace with your plugin's prefix
 */
