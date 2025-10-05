# Code Citations

## License: unknown
https://github.com/r23/MyOOS/blob/6aa924df141732a226b6122bb3641afb60af865c/wordpress/wp-content/plugins/decalog/includes/system/class-loader.php

```
.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            )
```


## License: unknown
https://github.com/siddheshlendhe/flybig_beta/blob/0daa474df21e4089fcbabc19e761defe05288e6f/wp-content/plugins/travelpayouts/src/includes/HooksLoader.php

```
.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            )
```


## License: unknown
https://github.com/r23/MyOOS/blob/6aa924df141732a226b6122bb3641afb60af865c/wordpress/wp-content/plugins/decalog/includes/system/class-loader.php

```
.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            )
```


## License: unknown
https://github.com/siddheshlendhe/flybig_beta/blob/0daa474df21e4089fcbabc19e761defe05288e6f/wp-content/plugins/travelpayouts/src/includes/HooksLoader.php

```
.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            )
```


## License: unknown
https://github.com/r23/MyOOS/blob/6aa924df141732a226b6122bb3641afb60af865c/wordpress/wp-content/plugins/decalog/includes/system/class-loader.php

```
.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            )
```


## License: unknown
https://github.com/siddheshlendhe/flybig_beta/blob/0daa474df21e4089fcbabc19e761defe05288e6f/wp-content/plugins/travelpayouts/src/includes/HooksLoader.php

```
.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            )
```


<?php
/**
 * Presto Player Integration
 * 
 * Primary video integration per copilot-instructions.md.
 * Generates embed HTML with access control.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Presto_Integration {
    
    /**
     * Check if Presto Player is active
     *
     * @return bool Presto Player is available
     */
    public function is_available() {
        return function_exists('presto_player') || class_exists('PrestoPlayer\Plugin');
    }
    
    /**
     * Generate Presto Player embed HTML
     * Per copilot-instructions: PRIMARY integration method
     *
     * @param int|string $presto_player_id Presto Player post ID
     * @return string|false Embed HTML or false on failure
     */
    public function generate_access_token($presto_player_id) {
        if (!$this->is_available()) {
            return false;
        }
        
        $presto_id = absint($presto_player_id);
        
        if (empty($presto_id)) {
            return false;
        }
        
        // Check if Presto Player post exists
        $presto_post = get_post($presto_id);
        
        if (!$presto_post || $presto_post->post_type !== 'pp_video_block') {
            return false;
        }
        
        // Generate embed using Presto Player shortcode
        $embed_html = do_shortcode('[presto_player id="' . $presto_id . '"]');
        
        if (empty($embed_html) || $embed_html === '[presto_player id="' . $presto_id . '"]') {
            return false;
        }
        
        // Log access generation
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $analytics->log_event('presto_embed_generated', 'video', $presto_id, array(
                'integration_type' => 'presto',
            ));
        }
        
        return $embed_html;
    }
    
    /**
     * Get Presto Player metadata
     *
     * @param int $presto_player_id Presto Player post ID
     * @return array|false Video metadata or false
     */
    public function get_video_metadata($presto_player_id) {
        $presto_id = absint($presto_player_id);
        
        if (empty($presto_id)) {
            return false;
        }
        
        $post = get_post($presto_id);
        
        if (!$post || $post->post_type !== 'pp_video_block') {
            return false;
        }
        
        $metadata = array(
            'id' => $presto_id,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'created_at' => $post->post_date,
        );
        
        // Get Presto Player meta
        $presto_meta = get_post_meta($presto_id);
        
        if (!empty($presto_meta)) {
            // Extract source URL if available
            if (isset($presto_meta['presto_player_source'])) {
                $source = maybe_unserialize($presto_meta['presto_player_source'][0]);
                $metadata['source'] = $source;
            }
            
            // Extract thumbnail
            $thumbnail_id = get_post_thumbnail_id($presto_id);
            if ($thumbnail_id) {
                $metadata['thumbnail_url'] = wp_get_attachment_url($thumbnail_id);
            }
            
            // Extract duration if available
            if (isset($presto_meta['presto_player_duration'])) {
                $metadata['duration'] = absint($presto_meta['presto_player_duration'][0]);
            }
        }
        
        return $metadata;
    }
    
    /**
     * Get all Presto Player videos
     *
     * @param array $args Query arguments
     * @return array Array of video objects
     */
    public function get_all_videos($args = array()) {
        if (!$this->is_available()) {
            return array();
        }
        
        $defaults = array(
            'post_type' => 'pp_video_block',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        $query_args = wp_parse_args($args, $defaults);
        
        $query = new WP_Query($query_args);
        
        $videos = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                $video_id = get_the_ID();
                $thumbnail_id = get_post_thumbnail_id($video_id);
                
                $videos[] = array(
                    'id' => $video_id,
                    'title' => get_the_title(),
                    'thumbnail_url' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '',
                    'created_at' => get_the_date('Y-m-d H:i:s'),
                );
            }
            
            wp_reset_postdata();
        }
        
        return $videos;
    }
    
    /**
     * Check if video has password protection
     *
     * @param int $presto_player_id Presto Player post ID
     * @return bool Video is password protected
     */
    public function is_password_protected($presto_player_id) {
        $presto_id = absint($presto_player_id);
        
        if (empty($presto_id)) {
            return false;
        }
        
        $password_enabled = get_post_meta($presto_id, 'presto_player_password_enabled', true);
        
        return !empty($password_enabled);
    }
    
    /**
     * Validate Presto Player ID exists
     *
     * @param int $presto_player_id Presto Player post ID
     * @return bool Video exists
     */
    public function validate_video_id($presto_player_id) {
        $presto_id = absint($presto_player_id);
        
        if (empty($presto_id)) {
            return false;
        }
        
        $post = get_post($presto_id);
        
        return $post && $post->post_type === 'pp_video_block';
    }
    
    /**
     * Get video thumbnail URL
     *
     * @param int $presto_player_id Presto Player post ID
     * @param string $size Thumbnail size (default: 'medium')
     * @return string|false Thumbnail URL or false
     */
    public function get_thumbnail_url($presto_player_id, $size = 'medium') {
        $presto_id = absint($presto_player_id);
        
        if (empty($presto_id)) {
            return false;
        }
        
        $thumbnail_url = get_the_post_thumbnail_url($presto_id, $size);
        
        return $thumbnail_url ? $thumbnail_url : false;
    }
    
    /**
     * Get video title
     *
     * @param int $presto_player_id Presto Player post ID
     * @return string|false Video title or false
     */
    public function get_video_title($presto_player_id) {
        $presto_id = absint($presto_player_id);
        
        if (empty($presto_id)) {
            return false;
        }
        
        $post = get_post($presto_id);
        
        return $post ? $post->post_title : false;
    }
    
    /**
     * Create protected video entry linked to Presto Player
     *
     * @param int $presto_player_id Presto Player post ID
     * @param int $required_minutes Required access minutes
     * @return int|false Protected video ID or false
     */
    public function create_protected_video($presto_player_id, $required_minutes = 10) {
        if (!$this->validate_video_id($presto_player_id)) {
            return false;
        }
        
        if (!class_exists('CPP_Video_Manager')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'class-cpp-video-manager.php';
        }
        
        $video_manager = new CPP_Video_Manager();
        
        $video_data = array(
            'video_id' => 'presto-' . absint($presto_player_id),
            'title' => $this->get_video_title($presto_player_id),
            'integration_type' => 'presto',
            'presto_player_id' => absint($presto_player_id),
            'required_minutes' => absint($required_minutes),
            'status' => 'active',
        );
        
        // Add thumbnail if available
        $thumbnail_url = $this->get_thumbnail_url($presto_player_id);
        if ($thumbnail_url) {
            $video_data['thumbnail_url'] = $thumbnail_url;
        }
        
        return $video_manager->create_protected_video($video_data);
    }
    
    /**
     * Sync Presto Player videos to protected videos table
     * Useful for batch import
     *
     * @param int $default_required_minutes Default required access minutes
     * @return array Array with 'created' and 'skipped' counts
     */
    public function sync_videos($default_required_minutes = 10) {
        $presto_videos = $this->get_all_videos();
        
        $created = 0;
        $skipped = 0;
        
        foreach ($presto_videos as $presto_video) {
            $result = $this->create_protected_video(
                $presto_video['id'],
                $default_required_minutes
            );
            
            if ($result !== false) {
                $created++;
            } else {
                $skipped++;
            }
        }
        
        return array(
            'created' => $created,
            'skipped' => $skipped,
            'total' => count($presto_videos),
        );
    }
}

