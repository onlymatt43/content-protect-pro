// ...existing code...

<div class="wrap">
    <h1><?php echo esc_html__('Content Protect Pro Settings', 'content-protect-pro'); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=content-protect-pro-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('General', 'content-protect-pro'); ?>
        </a>
        <a href="?page=content-protect-pro-settings&tab=integrations" class="nav-tab <?php echo $active_tab === 'integrations' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Integrations', 'content-protect-pro'); ?>
        </a>
        <!-- ADD THIS TAB -->
        <a href="?page=content-protect-pro-settings&tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('AI Assistant', 'content-protect-pro'); ?>
        </a>
    </h2>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('cpp_settings_group');
        
        switch ($active_tab) {
            case 'ai':
                include plugin_dir_path(__FILE__) . 'cpp-settings-ai-integration.php';
                break;
            // ...existing cases...
        }
        
        submit_button();
        ?>
    </form>
</div>