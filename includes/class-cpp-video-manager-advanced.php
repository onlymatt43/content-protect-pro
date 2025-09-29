<?php
/**
 * Advanced Video Management Functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Video_Manager_Advanced {

    /**
     * Delete a protected video
     *
     * @param int $video_id Video ID
     * @param bool $delete_from_bunny Whether to delete from Bunny CDN
     * @return array Result with success status and message
     * @since 1.0.0
     */
    public function delete_video($video_id, $delete_from_bunny = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        // Get video details before deletion
        $video = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $video_id
        ));
        
        if (!$video) {
            return [
                'success' => false,
                'message' => __('Video not found.', 'content-protect-pro')
            ];
        }
        
        // Delete from Bunny CDN if requested (uses Bunny video GUID in video_id)
        if ($delete_from_bunny && class_exists('CPP_Bunny_Integration')) {
            $bunny = new CPP_Bunny_Integration();
            $deleted = $bunny->delete_video($video->video_id);
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => __('Failed to delete from Bunny CDN.', 'content-protect-pro')
                ];
            }
        }
        
        // Delete from database
        $deleted = $wpdb->delete(
            $table_name,
            ['id' => $video_id],
            ['%d']
        );
        
        if ($deleted === false) {
            return [
                'success' => false,
                'message' => __('Failed to delete video from database.', 'content-protect-pro')
            ];
        }
        
        // Clean up related analytics data
        $analytics_table = $wpdb->prefix . 'cpp_analytics';
        $wpdb->delete(
            $analytics_table,
            [
                'object_type' => 'video',
                'object_id' => $video->video_id
            ],
            ['%s', '%s']
        );
        
        // Log the deletion
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $analytics->log_event(
                'video_deleted',
                'video',
                $video->video_id,
                [
                    'admin_user' => get_current_user_id(),
                    'deleted_from_bunny' => $delete_from_bunny,
                    'video_title' => $video->title
                ]
            );
        }
        
        return [
            'success' => true,
            'message' => __('Video deleted successfully.', 'content-protect-pro')
        ];
    }

    /**
     * Bulk import videos from CSV
     *
     * @param string $csv_file_path Path to CSV file
     * @param array $options Import options
     * @return array Import results
     * @since 1.0.0
     */
    public function bulk_import_csv($csv_file_path, $options = []) {
        if (!file_exists($csv_file_path)) {
            return [
                'success' => false,
                'message' => __('CSV file not found.', 'content-protect-pro'),
                'imported' => 0,
                'errors' => []
            ];
        }
        
        $defaults = [
            'update_existing' => false,
            'skip_duplicates' => true,
            'default_required_minutes' => 60,
            'default_integration' => 'bunny'
        ];
        
        $options = array_merge($defaults, $options);
        
        $handle = fopen($csv_file_path, 'r');
        if (!$handle) {
            return [
                'success' => false,
                'message' => __('Could not open CSV file.', 'content-protect-pro'),
                'imported' => 0,
                'errors' => []
            ];
        }
        
        $headers = fgetcsv($handle);
    $required_headers = ['video_id', 'title'];
        $missing_headers = array_diff($required_headers, $headers);
        
        if (!empty($missing_headers)) {
            fclose($handle);
            return [
                'success' => false,
                'message' => sprintf(
                    __('Missing required CSV headers: %s', 'content-protect-pro'),
                    implode(', ', $missing_headers)
                ),
                'imported' => 0,
                'errors' => []
            ];
        }
        
        $imported_count = 0;
        $errors = [];
        $row_number = 1;
        
        while (($data = fgetcsv($handle)) !== false) {
            $row_number++;
            
            if (count($data) !== count($headers)) {
                $errors[] = "Row {$row_number}: Column count mismatch";
                continue;
            }
            
            $video_data = array_combine($headers, $data);
            
            // Validate required fields
            if (empty($video_data['video_id']) || empty($video_data['title'])) {
                $errors[] = "Row {$row_number}: Missing video_id or title";
                continue;
            }
            
            // Check for existing video
            global $wpdb;
            $table_name = $wpdb->prefix . 'cpp_protected_videos';
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE video_id = %s",
                $video_data['video_id']
            ));
            
            if ($existing) {
                if ($options['skip_duplicates']) {
                    continue;
                }
                
                if ($options['update_existing']) {
                    // Update existing video
                    $result = $this->update_video($existing, $video_data);
                    if ($result['success']) {
                        $imported_count++;
                    } else {
                        $errors[] = "Row {$row_number}: " . $result['message'];
                    }
                    continue;
                }
            }
            
            // Determine integration type from CSV columns
            $integration_type = isset($video_data['integration_type']) ? sanitize_text_field($video_data['integration_type']) : $options['default_integration'];
            $bunny_library_id = isset($video_data['bunny_library_id']) ? sanitize_text_field($video_data['bunny_library_id']) : '';
            $presto_player_id = isset($video_data['presto_player_id']) ? sanitize_text_field($video_data['presto_player_id']) : '';
            $direct_url = isset($video_data['direct_url']) ? esc_url_raw($video_data['direct_url']) : '';

            if (empty($integration_type)) {
                if (!empty($presto_player_id)) $integration_type = 'presto';
                elseif (!empty($bunny_library_id)) $integration_type = 'bunny';
                elseif (!empty($direct_url)) $integration_type = 'direct';
                else $integration_type = 'bunny';
            }

            $import_data = [
                'video_id' => sanitize_text_field($video_data['video_id']),
                'title' => sanitize_text_field($video_data['title']),
                'description' => isset($video_data['description']) ? sanitize_textarea_field($video_data['description']) : '',
                'required_minutes' => isset($video_data['required_minutes']) ? intval($video_data['required_minutes']) : intval($options['default_required_minutes']),
                'integration_type' => $integration_type,
                'bunny_library_id' => $bunny_library_id,
                'presto_player_id' => $presto_player_id,
                'direct_url' => $direct_url,
                'status' => isset($video_data['status']) ? sanitize_text_field($video_data['status']) : 'active',
                'created_at' => current_time('mysql')
            ];

            $inserted = $wpdb->insert($table_name, $import_data);
            
            if ($inserted) {
                $imported_count++;
                
                // Log import
                if (class_exists('CPP_Analytics')) {
                    $analytics = new CPP_Analytics();
                    $analytics->log_event(
                        'video_imported',
                        'video',
                        $import_data['video_id'],
                        ['import_source' => 'csv', 'row_number' => $row_number]
                    );
                }
            } else {
                $errors[] = "Row {$row_number}: Database insertion failed";
            }
        }
        
        fclose($handle);
        
        return [
            'success' => true,
            'message' => sprintf(
                __('%d videos imported successfully.', 'content-protect-pro'),
                $imported_count
            ),
            'imported' => $imported_count,
            'errors' => $errors
        ];
    }

    /**
     * Export videos to CSV
     *
     * @param array $filters Export filters
     * @return string CSV content
     * @since 1.0.0
     */
    public function export_videos_csv($filters = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        $where_conditions = ['1=1'];
        $values = [];
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $values[] = $filters['status'];
        }
        
        if (!empty($filters['protection_level'])) {
            $where_conditions[] = 'protection_level = %s';
            $values[] = $filters['protection_level'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = '(title LIKE %s OR video_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_conditions);
        
    $query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Generate CSV
        $csv_output = '';
        
        if (!empty($results)) {
            // Headers (explicit, stable order)
            $headers = ['id','video_id','title','required_minutes','integration_type','bunny_library_id','presto_player_id','direct_url','description','status','usage_count','max_uses','created_at','updated_at'];
            $csv_output .= implode(',', $headers) . "\n";
            
            // Data rows
            foreach ($results as $row) {
                $ordered = [];
                foreach ($headers as $h) { $ordered[] = isset($row[$h]) ? $row[$h] : ''; }
                $csv_output .= implode(',', array_map(function($value) {
                    return '"' . str_replace('"', '""', (string) $value) . '"';
                }, $ordered)) . "\n";
            }
        }
        
        return $csv_output;
    }

    /**
     * Update video information
     *
     * @param int $video_id Video ID
     * @param array $data Video data
     * @return array Result
     * @since 1.0.0
     */
    private function update_video($video_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        $update_data = [];
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['required_minutes'])) {
            $update_data['required_minutes'] = intval($data['required_minutes']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        if (isset($data['integration_type'])) {
            $update_data['integration_type'] = sanitize_text_field($data['integration_type']);
        }
        if (isset($data['bunny_library_id'])) {
            $update_data['bunny_library_id'] = sanitize_text_field($data['bunny_library_id']);
        }
        if (isset($data['presto_player_id'])) {
            $update_data['presto_player_id'] = sanitize_text_field($data['presto_player_id']);
        }
        if (isset($data['direct_url'])) {
            $update_data['direct_url'] = esc_url_raw($data['direct_url']);
        }
        
        if (empty($update_data)) {
            return [
                'success' => false,
                'message' => __('No data to update.', 'content-protect-pro')
            ];
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $video_id],
            null,
            ['%d']
        );
        
        if ($updated === false) {
            return [
                'success' => false,
                'message' => __('Failed to update video.', 'content-protect-pro')
            ];
        }
        
        return [
            'success' => true,
            'message' => __('Video updated successfully.', 'content-protect-pro')
        ];
    }

    /**
     * Bulk update videos
     *
     * @param array $video_ids Array of video IDs
     * @param array $update_data Data to update
     * @return array Result
     * @since 1.0.0
     */
    public function bulk_update_videos($video_ids, $update_data) {
        if (empty($video_ids) || empty($update_data)) {
            return [
                'success' => false,
                'message' => __('Invalid parameters for bulk update.', 'content-protect-pro'),
                'updated' => 0
            ];
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        $updated_count = 0;
        $errors = [];
        
        foreach ($video_ids as $video_id) {
            $result = $this->update_video($video_id, $update_data);
            if ($result['success']) {
                $updated_count++;
            } else {
                $errors[] = "Video ID {$video_id}: " . $result['message'];
            }
        }
        
        return [
            'success' => true,
            'message' => sprintf(
                __('%d videos updated successfully.', 'content-protect-pro'),
                $updated_count
            ),
            'updated' => $updated_count,
            'errors' => $errors
        ];
    }

    /**
     * Get video statistics
     *
     * @param string $video_id Video ID
     * @return array Statistics
     * @since 1.0.0
     */
    public function get_video_statistics($video_id) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'cpp_analytics';
        
        // Get view counts by event type
        $view_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM {$analytics_table} 
             WHERE object_type = 'video' AND object_id = %s 
             GROUP BY event_type",
            $video_id
        ), ARRAY_A);
        
        // Get daily views for last 30 days
        $daily_views = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as views 
             FROM {$analytics_table} 
             WHERE object_type = 'video' AND object_id = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at) 
             ORDER BY date",
            $video_id
        ), ARRAY_A);
        
        // Get total unique viewers (by IP)
        $unique_viewers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT client_ip) 
             FROM {$analytics_table} 
             WHERE object_type = 'video' AND object_id = %s",
            $video_id
        ));
        
        return [
            'view_stats' => $view_stats,
            'daily_views' => $daily_views,
            'unique_viewers' => $unique_viewers ?: 0,
            'total_events' => array_sum(array_column($view_stats, 'count'))
        ];
    }
}