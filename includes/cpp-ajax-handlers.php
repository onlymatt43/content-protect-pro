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