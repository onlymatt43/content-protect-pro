<?php
/**
 * AJAX handlers for bulk code generation
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle bulk gift code creation via AJAX
 */
function cpp_ajax_bulk_create_codes() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cpp_bulk_codes')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Get codes data
    $codes_json = sanitize_textarea_field($_POST['codes']);
    $codes_data = json_decode($codes_json, true);
    
    if (!is_array($codes_data)) {
        wp_send_json_error('Invalid codes data');
        return;
    }
    
    // Load required classes
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-manager.php';
    require_once CPP_PLUGIN_DIR . 'includes/cpp-token-helpers.php';
    
    $giftcode_manager = new CPP_Giftcode_Manager();
    $created_count = 0;
    $errors = array();
    
    // Process each code
    foreach ($codes_data as $code_data) {
        // Validate required fields
        if (empty($code_data['code']) || empty($code_data['secure_token'])) {
            $errors[] = 'Missing required fields for code: ' . ($code_data['code'] ?? 'unknown');
            continue;
        }
        
        // Convert duration to minutes
        $duration_minutes = cpp_convert_to_minutes(
            intval($code_data['duration_value']),
            sanitize_text_field($code_data['duration_unit'])
        );
        
        // Prepare code data for database
        $db_code_data = array(
            'code' => sanitize_text_field($code_data['code']),
            'secure_token' => sanitize_text_field($code_data['secure_token']),
            'duration_minutes' => $duration_minutes,
            'duration_display' => $code_data['duration_value'] . ' ' . $code_data['duration_unit'],
            'status' => 'active',
            'description' => sanitize_textarea_field($code_data['description']),
            'expires_at' => null, // No expiration by default
            'ip_restrictions' => '' // No IP restrictions by default
        );
        
        // Create the code
        $result = $giftcode_manager->create_code($db_code_data);
        
        if ($result) {
            $created_count++;
        } else {
            $errors[] = 'Failed to create code: ' . $code_data['code'];
        }
    }
    
    // Send response
    if ($created_count > 0) {
        $message = sprintf('Successfully created %d codes', $created_count);
        if (!empty($errors)) {
            $message .= '. Errors: ' . implode(', ', $errors);
        }
        
        wp_send_json_success(array(
            'created' => $created_count,
            'errors' => $errors,
            'message' => $message
        ));
    } else {
        wp_send_json_error('No codes were created. Errors: ' . implode(', ', $errors));
    }
}

/**
 * Handle video creation via AJAX
 */
function cpp_ajax_create_video() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cpp_create_video')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Get video data
    $video_json = sanitize_textarea_field($_POST['video_data']);
    $video_data = json_decode($video_json, true);
    
    if (!is_array($video_data)) {
        wp_send_json_error('Invalid video data');
        return;
    }
    
    // Validate required fields
    if (empty($video_data['video_id']) || empty($video_data['title']) || empty($video_data['required_minutes'])) {
        wp_send_json_error('Missing required fields');
        return;
    }
    
    // Prepare database data
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpp_protected_videos';
    
    $db_data = array(
        'video_id' => sanitize_text_field($video_data['video_id']),
        'title' => sanitize_text_field($video_data['title']),
        'required_minutes' => intval($video_data['required_minutes']),
        'integration_type' => sanitize_text_field($video_data['integration_type']),
        'bunny_library_id' => sanitize_text_field($video_data['bunny_library_id']),
        'presto_player_id' => isset($video_data['presto_player_id']) ? sanitize_text_field($video_data['presto_player_id']) : '',
        'direct_url' => esc_url_raw($video_data['direct_url']),
        'description' => sanitize_textarea_field($video_data['description']),
        'status' => sanitize_text_field($video_data['status']),
        'created_at' => current_time('mysql')
    );
    
    // Insert into database
    $result = $wpdb->insert($table_name, $db_data);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Video created successfully',
            'video_id' => $wpdb->insert_id
        ));
    } else {
        wp_send_json_error('Failed to create video in database');
    }
}

/**
 * Handle video deletion via AJAX
 */
function cpp_ajax_delete_video() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cpp_delete_video')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $video_id = intval($_POST['video_id']);
    
    if (!$video_id) {
        wp_send_json_error('Invalid video ID');
        return;
    }
    
    // Delete from database
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpp_protected_videos';
    
    $result = $wpdb->delete(
        $table_name,
        array('id' => $video_id),
        array('%d')
    );
    
    if ($result) {
        wp_send_json_success('Video deleted successfully');
    } else {
        wp_send_json_error('Failed to delete video');
    }
}

/**
 * Handle updating required minutes for Presto Player videos
 */
function cpp_ajax_update_video_minutes() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cpp_update_video_minutes')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $video_id = intval($_POST['video_id']);
    $minutes = intval($_POST['minutes']);
    
    if (!$video_id || $minutes < 0) {
        wp_send_json_error('Invalid video ID or minutes value');
    }
    
    // Verify this is a Presto Player video
    $post = get_post($video_id);
    if (!$post || $post->post_type !== 'pp_video_block') {
        wp_send_json_error('Invalid Presto Player video');
    }
    
    // Update the meta field
    $result = update_post_meta($video_id, '_cpp_required_minutes', $minutes);
    
    if ($result !== false) {
        // Also sync this setting into the plugin's protected_videos table so ajax token requests
        // that look up protected videos by video_id will find an entry.
        if (!class_exists('CPP_Video_Manager')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
        }

        if (class_exists('CPP_Video_Manager')) {
            $vm = new CPP_Video_Manager();
            $existing = $vm->get_protected_video($video_id);

            $requires = $minutes > 0 ? 1 : 0;

            if ($existing) {
                // Update the requires_giftcode flag (and ensure presto_player_id is set)
                $vm->update_protected_video($video_id, array(
                    'requires_giftcode' => $requires,
                    'presto_player_id' => $video_id,
                ));
            } else if ($requires) {
                // Create a minimal protected video entry for compatibility
                $vm->create_protected_video(array(
                    'video_id' => (string) $video_id,
                    'title' => get_the_title($video_id) ?: 'Presto Video ' . $video_id,
                    'protection_type' => 'token',
                    'bunny_library_id' => '',
                    'presto_player_id' => (string) $video_id,
                    'access_level' => 'public',
                    'requires_giftcode' => 1,
                    'allowed_giftcodes' => '',
                ));
            }
        }

        wp_send_json_success('Video minutes updated successfully');
    } else {
        wp_send_json_error('Failed to update video minutes');
    }
}

// Register AJAX handlers
add_action('wp_ajax_cpp_bulk_create_codes', 'cpp_ajax_bulk_create_codes');
add_action('wp_ajax_cpp_create_video', 'cpp_ajax_create_video');
add_action('wp_ajax_cpp_delete_video', 'cpp_ajax_delete_video');
add_action('wp_ajax_cpp_update_video_minutes', 'cpp_ajax_update_video_minutes');

/**
 * Test Bunny connection via AJAX
 */
function cpp_ajax_test_bunny_connection() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    // Verify nonce
    if (empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cpp_test_bunny')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    if (!class_exists('CPP_Bunny_Integration')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-bunny-integration.php';
    }

    $bunny = new CPP_Bunny_Integration();
    $result = $bunny->test_connection();

    // Log the test result to plugin analytics if available for admin diagnostics
    $log_meta = array(
        'result' => is_array($result) ? $result : array('raw' => (string) $result),
        'time' => current_time('mysql')
    );

    if (class_exists('CPP_Analytics')) {
        $analytics = new CPP_Analytics();
        $analytics->log_event('bunny_test', 'integration', 'bunny_test', $log_meta);
    } else {
        error_log('[Content Protect Pro] Bunny test: ' . print_r($log_meta, true));
    }

    if ($result && isset($result['success']) && $result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

add_action('wp_ajax_cpp_test_bunny_connection', 'cpp_ajax_test_bunny_connection');

/**
 * Run full plugin diagnostics via AJAX (admin only)
 */
function cpp_ajax_run_diagnostics() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'cpp_diagnostics')) {
        wp_send_json_error('Invalid nonce');
    }

    if (!class_exists('CPP_Diagnostic')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-diagnostic.php';
    }

    try {
        $diag = new CPP_Diagnostic();
        $results = $diag->run_diagnostics();
        wp_send_json_success($results);
    } catch (Exception $e) {
        wp_send_json_error('Diagnostic error: ' . $e->getMessage());
    }
}

add_action('wp_ajax_cpp_run_diagnostics', 'cpp_ajax_run_diagnostics');

/**
 * Admin-only debug endpoint to inspect protected_videos row and playback token status
 * POST params: video_id (string)
 * Returns: { video_row: {...}|null, cookie_token: string|null, token_row: {...}|null, presto_embed: string|null }
 */
function cpp_ajax_debug_video_row() {
    // Only allow administrators
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $video_id = isset($_POST['video_id']) ? sanitize_text_field($_POST['video_id']) : '';

    if (empty($video_id)) {
        wp_send_json_error('video_id required');
    }

    // Load video manager
    if (!class_exists('CPP_Video_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
    }

    $vm = new CPP_Video_Manager();
    $video_row = $vm->get_protected_video($video_id);

    // Inspect cookie token if present
    $cookie_token = isset($_COOKIE['cpp_playback_token']) ? sanitize_text_field($_COOKIE['cpp_playback_token']) : null;
    $token_row = null;
    if (!empty($cookie_token)) {
        if (!function_exists('cpp_validate_playback_token')) {
            require_once CPP_PLUGIN_DIR . 'includes/cpp-token-helpers.php';
        }
        $token_row = cpp_validate_playback_token($cookie_token) ?: null;
    }

    // Try to render presto embed HTML if possible
    $presto_embed = null;
    if (!empty($video_row) && !empty($video_row->presto_player_id)) {
        // do_shortcode may rely on Presto Player being active
        if (function_exists('do_shortcode')) {
            $presto_embed = do_shortcode('[presto_player id="' . intval($video_row->presto_player_id) . '"]');
        }
    }

    $out = array(
        'video_row' => $video_row ? (array) $video_row : null,
        'cookie_token' => $cookie_token,
        'token_row' => $token_row ? (array) $token_row : null,
        'presto_embed' => $presto_embed ? $presto_embed : null,
    );

    wp_send_json_success($out);
}

add_action('wp_ajax_cpp_debug_video_row', 'cpp_ajax_debug_video_row');

/**
 * Update allowed gift codes for a protected video (admin-only)
 * POST params: video_id (string), codes (comma separated string)
 */
function cpp_ajax_update_video_allowed_codes() {
    // Verify nonce if provided
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'cpp_update_video_allowed_codes')) {
        wp_send_json_error('Invalid nonce');
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $video_id = isset($_POST['video_id']) ? sanitize_text_field($_POST['video_id']) : '';
    $codes = isset($_POST['codes']) ? sanitize_textarea_field($_POST['codes']) : '';
    $minutes = isset($_POST['minutes']) ? intval($_POST['minutes']) : null;

    if (empty($video_id)) {
        wp_send_json_error('video_id required');
    }

    if (!class_exists('CPP_Video_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
    }

    $vm = new CPP_Video_Manager();
    $existing = $vm->get_protected_video($video_id);

    // Normalize codes: split, trim, remove empties and duplicates
    $codes_arr = array_filter(array_map('trim', explode(',', $codes)));
    $codes_arr = array_values(array_unique($codes_arr));
    $codes_clean = implode(',', $codes_arr);

    // If minutes provided, validate that assigned codes have sufficient duration
    $force_save = isset($_POST['force']) && $_POST['force'] === '1';
    if ($minutes !== null && $minutes > 0 && !empty($codes_arr) && !$force_save) {
        global $wpdb;
        $gift_table = $wpdb->prefix . 'cpp_giftcodes';

        // Prepare placeholders and upper-case codes for case-insensitive match
        $upper_codes = array_map('strtoupper', $codes_arr);
        $placeholders = implode(',', array_fill(0, count($upper_codes), '%s'));
        // Use UPPER(code) to compare in DB for case-insensitive
        $query = "SELECT code, duration_minutes, status FROM $gift_table WHERE UPPER(code) IN ($placeholders)";
        $results = $wpdb->get_results($wpdb->prepare($query, $upper_codes));

        $found_map = array();
        foreach ($results as $r) {
            $found_map[strtoupper($r->code)] = intval($r->duration_minutes);
        }

        $missing = array();
        $insufficient = array();

        foreach ($upper_codes as $i => $uc) {
            if (!isset($found_map[$uc])) {
                $missing[] = $codes_arr[$i];
            } else {
                if ($found_map[$uc] < $minutes) {
                    $insufficient[] = array('code' => $codes_arr[$i], 'duration' => $found_map[$uc]);
                }
            }
        }

        if (!empty($missing) || !empty($insufficient)) {
            wp_send_json_error(array(
                'message' => 'Some assigned codes are missing or have insufficient duration.',
                'missing_codes' => $missing,
                'insufficient_codes' => $insufficient
            ));
        }
    }

    if ($existing) {
        // If minutes provided, update post meta as well to keep UI/table in sync
        if ($minutes !== null) {
            update_post_meta(intval($video_id), '_cpp_required_minutes', $minutes);
        }

        $ok = $vm->update_protected_video($video_id, array('allowed_giftcodes' => $codes_clean, 'requires_giftcode' => (empty($codes_clean) ? 0 : 1)));
        if ($ok) {
            wp_send_json_success(array('message' => 'Updated', 'allowed_giftcodes' => $codes_clean));
        }
        wp_send_json_error('Failed to update');
    } else {
        // create minimal protected video
        if ($minutes !== null) {
            // ensure the post meta is set for the minutes
            update_post_meta(intval($video_id), '_cpp_required_minutes', $minutes);
        }

        $created = $vm->create_protected_video(array(
            'video_id' => (string) $video_id,
            'title' => get_the_title($video_id) ?: 'Presto Video ' . $video_id,
            'protection_type' => 'token',
            'presto_player_id' => (string) $video_id,
            'requires_giftcode' => empty($codes_clean) ? 0 : 1,
            'allowed_giftcodes' => $codes_clean,
            'access_level' => 'public'
        ));

        if ($created) {
            wp_send_json_success(array('message' => 'Created', 'allowed_giftcodes' => $codes_clean));
        }
        wp_send_json_error('Failed to create protected video');
    }
}

add_action('wp_ajax_cpp_update_video_allowed_codes', 'cpp_ajax_update_video_allowed_codes');

/**
 * Fetch recent Bunny test events for diagnostics (admin only)
 */
function cpp_ajax_get_bunny_tests() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'cpp_test_bunny')) {
        wp_send_json_error('Invalid nonce');
    }

    if (!class_exists('CPP_Analytics')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics.php';
    }

    $analytics = new CPP_Analytics();
    $events = $analytics->get_analytics(array(
        'per_page' => 20,
        'page' => 1,
        'event_type' => 'bunny_test',
        'object_type' => 'integration'
    ));

    wp_send_json_success($events);
}

add_action('wp_ajax_cpp_get_bunny_tests', 'cpp_ajax_get_bunny_tests');

/**
 * Export analytics as CSV via AJAX (admin only)
 * POST params: date_from, date_to, nonce
 */
function cpp_ajax_export_analytics_csv() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'cpp_analytics_export')) {
        wp_send_json_error('Invalid nonce');
    }

    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : date('Y-m-d', strtotime('-30 days'));
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : date('Y-m-d');

    if (!class_exists('CPP_Analytics_Export')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics-export.php';
    }

    $exporter = new CPP_Analytics_Export();
    $csv = $exporter->export_csv(array('date_from' => $date_from, 'date_to' => $date_to));

    $filename = sprintf('cpp-analytics-%s-to-%s.csv', $date_from, $date_to);
    // Stream as file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csv;
    exit;
}
add_action('wp_ajax_cpp_export_analytics_csv', 'cpp_ajax_export_analytics_csv');

/**
 * Export analytics as JSON via AJAX (admin only)
 * POST params: date_from, date_to, nonce
 */
function cpp_ajax_export_analytics_json() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'cpp_analytics_export')) {
        wp_send_json_error('Invalid nonce');
    }

    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : date('Y-m-d', strtotime('-30 days'));
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : date('Y-m-d');

    if (!class_exists('CPP_Analytics_Export')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics-export.php';
    }

    $exporter = new CPP_Analytics_Export();
    $json = $exporter->export_json(array('date_from' => $date_from, 'date_to' => $date_to));

    $filename = sprintf('cpp-analytics-%s-to-%s.json', $date_from, $date_to);
    // Stream as file download
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $json;
    exit;
}
add_action('wp_ajax_cpp_export_analytics_json', 'cpp_ajax_export_analytics_json');

/**
 * Email analytics report to admin via AJAX (admin only)
 * POST params: date_from, date_to, nonce, format (html/csv)
 */
function cpp_ajax_email_analytics_report() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'cpp_analytics_export')) {
        wp_send_json_error('Invalid nonce');
    }

    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : date('Y-m-d', strtotime('-30 days'));
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : date('Y-m-d');
    $format = isset($_POST['format']) && $_POST['format'] === 'csv' ? 'csv' : 'html';

    if (!class_exists('CPP_Analytics_Export')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics-export.php';
    }

    $exporter = new CPP_Analytics_Export();

    $recipient = get_option('admin_email');
    $options = array('format' => $format, 'period' => 'custom');

    $sent = $exporter->email_report($recipient, $options);

    if ($sent) {
        wp_send_json_success(array('message' => __('Report emailed to admin address.', 'content-protect-pro')));
    }

    wp_send_json_error(array('message' => __('Failed to send report.', 'content-protect-pro')));
}
add_action('wp_ajax_cpp_email_analytics_report', 'cpp_ajax_email_analytics_report');

/*
 * Public AJAX endpoint: return remaining seconds for the current playback/session token.
 * Front-end can poll this endpoint to stop playback when time is up.
 */
function cpp_ajax_get_session_remaining() {
    // Public nonce check
    check_ajax_referer('cpp_public_nonce', 'nonce');

    $cookie_token = isset($_COOKIE['cpp_playback_token']) ? sanitize_text_field($_COOKIE['cpp_playback_token']) : '';

    if (!empty($cookie_token)) {
        if (!function_exists('cpp_validate_playback_token')) {
            require_once CPP_PLUGIN_DIR . 'includes/cpp-token-helpers.php';
        }

        $row = cpp_validate_playback_token($cookie_token);
        if ($row) {
            $expires_at = isset($row->expires_at) ? strtotime($row->expires_at) : 0;
            $remaining = max(0, $expires_at - time());
            wp_send_json_success(array(
                'valid' => true,
                'remaining_seconds' => intval($remaining),
                'expires_at' => $expires_at ? date(DATE_ATOM, $expires_at) : null,
            ));
        }
    }

    wp_send_json_success(array('valid' => false, 'remaining_seconds' => 0, 'message' => 'No valid playback token'));
}

add_action('wp_ajax_cpp_get_session_remaining', 'cpp_ajax_get_session_remaining');
add_action('wp_ajax_nopriv_cpp_get_session_remaining', 'cpp_ajax_get_session_remaining');