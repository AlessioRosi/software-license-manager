# Software License Manager - Version Update API

## Overview
This document describes the new version tracking functionality added to the Software License Manager plugin.

## Database Changes

### New Field in Domain Table
A new field `version` (VARCHAR 50) has been added to the `wp_lic_reg_domain_tbl` table to store the version of the software installed on each domain.

- **Database Version**: Updated to 1.7
- **Field Name**: `version`
- **Type**: `varchar(50)`
- **Default**: Empty string

## Backend Changes

### Admin Interface
The "Registered Domains" section in the license edit page now displays:
- Domain name
- Product Reference (item_reference)
- Version (if reported by the client)

When no version is reported, the interface displays "Not reported".

## API Endpoints

### 1. Activate License with Version (Enhanced)
The existing `slm_activate` endpoint now supports an optional `version` parameter.

**Endpoint**: `slm_activate`

**Parameters**:
- `slm_action`: `slm_activate`
- `secret_key`: Your verification secret key
- `license_key`: The license key to activate
- `registered_domain`: The domain where the license is being activated
- `item_reference`: (optional) The product reference
- `version`: (optional) The version of the software being activated

**Example Request**:
```php
$api_params = array(
    'slm_action' => 'slm_activate',
    'secret_key' => 'YOUR_SECRET_KEY',
    'license_key' => 'YOUR_LICENSE_KEY',
    'registered_domain' => 'example.com',
    'item_reference' => 'plugin-a',
    'version' => '1.2.3'
);

$response = wp_remote_get(
    add_query_arg($api_params, 'https://your-slm-site.com/')
);
```

### 2. Update Version (New)
A new dedicated endpoint for updating the version information.

**Endpoint**: `slm_update_version`

**Parameters** (all required):
- `slm_action`: `slm_update_version`
- `secret_key`: Your verification secret key
- `license_key`: The license key
- `registered_domain`: The domain to update
- `version`: The version number to set
- `item_reference`: (optional) The product reference - if provided, only updates that specific product

**Example Request**:
```php
$api_params = array(
    'slm_action' => 'slm_update_version',
    'secret_key' => 'YOUR_SECRET_KEY',
    'license_key' => 'YOUR_LICENSE_KEY',
    'registered_domain' => 'example.com',
    'item_reference' => 'plugin-a',
    'version' => '1.2.4'
);

$response = wp_remote_post(
    'https://your-slm-site.com/',
    array('body' => $api_params)
);
```

**Success Response**:
```json
{
    "result": "success",
    "message": "Version updated successfully",
    "version": "1.2.4"
}
```

**Error Responses**:
- License key missing: Error code `70` (LICENSE_INVALID)
- Domain missing: Error code `80` (DOMAIN_MISSING)
- Version parameter missing: Error code `110`
- Invalid license key: Error code `70` (LICENSE_INVALID)
- Domain not registered: Error code `80` (DOMAIN_MISSING)
- Update failed: Error code `111`

## Integration Guide for Licensed Plugins

### Recommended Implementation

#### Step 1: Send Version During Activation
When activating the license, include the version parameter:

```php
function my_plugin_activate_license($license_key, $domain) {
    $plugin_version = '1.2.3'; // Your plugin version

    $api_params = array(
        'slm_action' => 'slm_activate',
        'secret_key' => 'YOUR_SECRET_KEY',
        'license_key' => $license_key,
        'registered_domain' => $domain,
        'item_reference' => 'my-plugin',
        'version' => $plugin_version
    );

    $response = wp_remote_get(
        add_query_arg($api_params, 'https://your-slm-site.com/')
    );

    return json_decode(wp_remote_retrieve_body($response), true);
}
```

#### Step 2: Update Version Periodically
Send version updates when your plugin is updated or periodically (e.g., daily):

```php
function my_plugin_update_version($license_key, $domain) {
    $plugin_version = '1.2.4'; // Your updated plugin version

    $api_params = array(
        'slm_action' => 'slm_update_version',
        'secret_key' => 'YOUR_SECRET_KEY',
        'license_key' => $license_key,
        'registered_domain' => $domain,
        'item_reference' => 'my-plugin',
        'version' => $plugin_version
    );

    $response = wp_remote_post(
        'https://your-slm-site.com/',
        array('body' => $api_params)
    );

    return json_decode(wp_remote_retrieve_body($response), true);
}
```

#### Step 3: Hook into Plugin Updates
Automatically send version updates when your plugin is updated:

```php
function my_plugin_on_update($upgrader_object, $options) {
    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        // Check if this plugin was updated
        foreach ($options['plugins'] as $plugin) {
            if ($plugin == 'my-plugin/my-plugin.php') {
                $license_key = get_option('my_plugin_license_key');
                $domain = parse_url(get_site_url(), PHP_URL_HOST);
                my_plugin_update_version($license_key, $domain);
            }
        }
    }
}
add_action('upgrader_process_complete', 'my_plugin_on_update', 10, 2);
```

## Use Cases

1. **Track Software Versions**: Monitor which versions of your software are installed across all licensed sites
2. **Identify Outdated Installations**: Quickly find customers using old versions
3. **Support & Debugging**: Know exactly which version a customer is running when they report issues
4. **Product Analytics**: Understand adoption rates of new versions
5. **Multiple Products**: Track different products/plugins per domain using `item_reference`

## Notes

- The version field is optional - existing functionality continues to work without it
- The version update is performed independently from license activation/deactivation
- If no `item_reference` is provided, the update applies to the domain registration regardless of product
- The version field accepts any string up to 50 characters (semantic versioning recommended)
- All API calls require the verification `secret_key` for security

## Error Codes Reference

- `70`: LICENSE_INVALID - Invalid or missing license key
- `80`: DOMAIN_MISSING - Domain not provided or not registered
- `110`: Version parameter missing
- `111`: Failed to update version in database
