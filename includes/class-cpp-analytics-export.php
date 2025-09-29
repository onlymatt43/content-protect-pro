<?php
/**
 * Advanced Analytics and Export Functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Analytics_Export {

    /**
     * Export analytics data as CSV
     *
     * @param array $filters Export filters
     * @return string CSV content
     * @since 1.0.0
     */
    public function export_csv($filters = []) {
        global $wpdb;
        
        $defaults = [
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'event_type' => '',
            'object_type' => ''
        ];
        
        $filters = wp_parse_args($filters, $defaults);
        
        $table_name = $wpdb->prefix . 'cpp_analytics';
        $where_conditions = ['1=1'];
        $values = [];
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(created_at) >= %s';
            $values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(created_at) <= %s';
            $values[] = $filters['date_to'];
        }
        
        if (!empty($filters['event_type'])) {
            $where_conditions[] = 'event_type = %s';
            $values[] = $filters['event_type'];
        }
        
        if (!empty($filters['object_type'])) {
            $where_conditions[] = 'object_type = %s';
            $values[] = $filters['object_type'];
        }
        
        $where_sql = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Generate CSV
        $csv_output = '';
        
        // Headers
        if (!empty($results)) {
            $csv_output .= implode(',', array_keys($results[0])) . "\n";
            
            // Data rows
            foreach ($results as $row) {
                $csv_output .= implode(',', array_map(function($value) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }, $row)) . "\n";
            }
        }
        
        return $csv_output;
    }

    /**
     * Export analytics data as JSON
     *
     * @param array $filters Export filters
     * @return string JSON content
     * @since 1.0.0
     */
    public function export_json($filters = []) {
        global $wpdb;
        
        $defaults = [
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'event_type' => '',
            'object_type' => '',
            'include_summary' => true
        ];
        
        $filters = wp_parse_args($filters, $defaults);
        
        $table_name = $wpdb->prefix . 'cpp_analytics';
        $where_conditions = ['1=1'];
        $values = [];
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(created_at) >= %s';
            $values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(created_at) <= %s';
            $values[] = $filters['date_to'];
        }
        
        if (!empty($filters['event_type'])) {
            $where_conditions[] = 'event_type = %s';
            $values[] = $filters['event_type'];
        }
        
        if (!empty($filters['object_type'])) {
            $where_conditions[] = 'object_type = %s';
            $values[] = $filters['object_type'];
        }
        
        $where_sql = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $export_data = [
            'export_info' => [
                'generated_at' => current_time('mysql'),
                'date_range' => [
                    'from' => $filters['date_from'],
                    'to' => $filters['date_to']
                ],
                'filters' => $filters,
                'total_records' => count($results)
            ],
            'data' => $results
        ];
        
        // Add summary if requested
        if ($filters['include_summary']) {
            $export_data['summary'] = $this->generate_summary($results);
        }
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }

    /**
     * Generate analytics summary
     *
     * @param array $data Analytics data
     * @return array Summary statistics
     * @since 1.0.0
     */
    private function generate_summary($data) {
        $summary = [
            'event_types' => [],
            'object_types' => [],
            'daily_counts' => [],
            'top_events' => []
        ];
        
        foreach ($data as $row) {
            // Event types
            $event_type = $row['event_type'];
            if (!isset($summary['event_types'][$event_type])) {
                $summary['event_types'][$event_type] = 0;
            }
            $summary['event_types'][$event_type]++;
            
            // Object types
            $object_type = $row['object_type'];
            if (!isset($summary['object_types'][$object_type])) {
                $summary['object_types'][$object_type] = 0;
            }
            $summary['object_types'][$object_type]++;
            
            // Daily counts
            $date = date('Y-m-d', strtotime($row['created_at']));
            if (!isset($summary['daily_counts'][$date])) {
                $summary['daily_counts'][$date] = 0;
            }
            $summary['daily_counts'][$date]++;
        }
        
        // Sort and limit top events
        arsort($summary['event_types']);
        $summary['top_events'] = array_slice($summary['event_types'], 0, 10, true);
        
        return $summary;
    }

    /**
     * Send analytics report via email
     *
     * @param string $recipient Email recipient
     * @param array $options Report options
     * @return bool Success status
     * @since 1.0.0
     */
    public function email_report($recipient, $options = []) {
        $defaults = [
            'format' => 'html', // html or csv
            'period' => 'weekly', // daily, weekly, monthly
            'include_attachments' => true
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Generate report data
        $date_ranges = $this->get_date_range_for_period($options['period']);
        $summary = $this->generate_summary_for_period($options['period']);
        
        // Email subject
        $subject = sprintf(
            __('[%s] Content Protect Pro - %s Analytics Report', 'content-protect-pro'),
            get_bloginfo('name'),
            ucfirst($options['period'])
        );
        
        // Email content
        if ($options['format'] === 'html') {
            $message = $this->generate_html_email_report($summary, $date_ranges);
            $headers = ['Content-Type: text/html; charset=UTF-8'];
        } else {
            $message = $this->generate_text_email_report($summary, $date_ranges);
            $headers = ['Content-Type: text/plain; charset=UTF-8'];
        }
        
        // Attachments
        $attachments = [];
        if ($options['include_attachments']) {
            $csv_content = $this->export_csv([
                'date_from' => $date_ranges['from'],
                'date_to' => $date_ranges['to']
            ]);
            
            $temp_file = wp_tempnam();
            file_put_contents($temp_file, $csv_content);
            $attachments[] = $temp_file;
        }
        
        $sent = wp_mail($recipient, $subject, $message, $headers, $attachments);
        
        // Clean up temp files
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        return $sent;
    }

    /**
     * Get date range for reporting period
     *
     * @param string $period Period type
     * @return array Date range
     * @since 1.0.0
     */
    private function get_date_range_for_period($period) {
        $now = current_time('timestamp');
        
        switch ($period) {
            case 'daily':
                return [
                    'from' => date('Y-m-d', $now),
                    'to' => date('Y-m-d', $now)
                ];
                
            case 'weekly':
                return [
                    'from' => date('Y-m-d', strtotime('-7 days', $now)),
                    'to' => date('Y-m-d', $now)
                ];
                
            case 'monthly':
                return [
                    'from' => date('Y-m-d', strtotime('-30 days', $now)),
                    'to' => date('Y-m-d', $now)
                ];
                
            default:
                return [
                    'from' => date('Y-m-d', strtotime('-7 days', $now)),
                    'to' => date('Y-m-d', $now)
                ];
        }
    }

    /**
     * Generate summary for reporting period
     *
     * @param string $period Period type
     * @return array Summary data
     * @since 1.0.0
     */
    private function generate_summary_for_period($period) {
        $date_range = $this->get_date_range_for_period($period);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_analytics';
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE DATE(created_at) BETWEEN %s AND %s 
             ORDER BY created_at DESC",
            $date_range['from'],
            $date_range['to']
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return $this->generate_summary($results);
    }

    /**
     * Generate HTML email report
     *
     * @param array $summary Summary data
     * @param array $date_range Date range
     * @return string HTML content
     * @since 1.0.0
     */
    private function generate_html_email_report($summary, $date_range) {
        ob_start();
        ?>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .summary-box { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 15px 0; }
                .stats-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .stats-table th, .stats-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .stats-table th { background: #f1f1f1; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo get_bloginfo('name'); ?> - Analytics Report</h1>
                <p><?php echo $date_range['from']; ?> to <?php echo $date_range['to']; ?></p>
            </div>
            
            <div class="content">
                <div class="summary-box">
                    <h2>Summary</h2>
                    <p><strong>Total Events:</strong> <?php echo array_sum($summary['event_types']); ?></p>
                    <p><strong>Active Days:</strong> <?php echo count($summary['daily_counts']); ?></p>
                    <p><strong>Most Active Event:</strong> 
                        <?php 
                        if (!empty($summary['top_events'])) {
                            $top_event = array_keys($summary['top_events'])[0];
                            echo $top_event . ' (' . $summary['top_events'][$top_event] . ' times)';
                        }
                        ?>
                    </p>
                </div>
                
                <?php if (!empty($summary['event_types'])): ?>
                <h3>Event Types</h3>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Event Type</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_events = array_sum($summary['event_types']);
                        foreach ($summary['event_types'] as $type => $count): 
                            $percentage = round(($count / $total_events) * 100, 2);
                        ?>
                        <tr>
                            <td><?php echo esc_html($type); ?></td>
                            <td><?php echo number_format($count); ?></td>
                            <td><?php echo $percentage; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <?php if (!empty($summary['daily_counts'])): ?>
                <h3>Daily Activity</h3>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Events</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['daily_counts'] as $date => $count): ?>
                        <tr>
                            <td><?php echo $date; ?></td>
                            <td><?php echo number_format($count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate text email report
     *
     * @param array $summary Summary data
     * @param array $date_range Date range
     * @return string Text content
     * @since 1.0.0
     */
    private function generate_text_email_report($summary, $date_range) {
        $report = '';
        
        $report .= get_bloginfo('name') . " - Analytics Report\n";
        $report .= str_repeat('=', 50) . "\n";
        $report .= "Period: {$date_range['from']} to {$date_range['to']}\n\n";
        
        $report .= "SUMMARY\n";
        $report .= "-------\n";
        $report .= "Total Events: " . array_sum($summary['event_types']) . "\n";
        $report .= "Active Days: " . count($summary['daily_counts']) . "\n";
        
        if (!empty($summary['top_events'])) {
            $top_event = array_keys($summary['top_events'])[0];
            $report .= "Most Active Event: " . $top_event . ' (' . $summary['top_events'][$top_event] . " times)\n";
        }
        
        $report .= "\nEVENT TYPES\n";
        $report .= "-----------\n";
        $total_events = array_sum($summary['event_types']);
        foreach ($summary['event_types'] as $type => $count) {
            $percentage = round(($count / $total_events) * 100, 2);
            $report .= sprintf("%-30s %8s (%s%%)\n", $type, number_format($count), $percentage);
        }
        
        $report .= "\nDAILY ACTIVITY\n";
        $report .= "--------------\n";
        foreach ($summary['daily_counts'] as $date => $count) {
            $report .= sprintf("%-12s %s\n", $date, number_format($count));
        }
        
        return $report;
    }
}