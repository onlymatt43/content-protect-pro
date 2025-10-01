# Content Protect Pro

A streamlined WordPress plugin that protects videos with gift codes using Presto Player for secure video playback. This plugin provides a simple yet powerful solution for content creators who want to protect premium video content behind gift code validation.

## Features

### 🎁 Gift Code Management
- **Create and manage gift codes** with customizable parameters
- **Usage limits and expiration dates** for flexible distribution
- **Bulk generation and management** tools
- **Partial usage support** for multi-use codes
- **Case sensitivity options** for code validation
- **Custom prefix/suffix** support for branded codes

### 🎬 Video Protection (Presto Player Only)
- **Simple integration** with Presto Player for video protection
- **Gift code validation** for video access
- **Session-based access control** for validated users
- **Shortcode-based video embedding** with protection

### 📊 Analytics & Monitoring
- **Comprehensive event tracking** for gift codes and videos
- **Real-time analytics dashboard** with visual charts
- **Usage statistics and trends** analysis
- **IP anonymization** for privacy compliance
- **Configurable data retention** periods

### 🔒 Security Features
- **Rate limiting** to prevent abuse
- **IP-based access control** and monitoring
- **Secure session management** for validated codes
- **Activity logging** with detailed audit trails
- **AJAX nonce protection** for all requests

### 🎨 User Experience
- **Responsive admin interface** with modern design
- **Clean public forms** with customizable styling
- **Real-time form validation** and feedback
- **Mobile-optimized** layouts and interactions
- **Accessibility compliant** with WCAG guidelines

## Installation

1. **Upload the plugin files** to the `/wp-content/plugins/content-protect-pro/` directory
2. **Activate the plugin** through the 'Plugins' screen in WordPress
3. **Install and activate Presto Player** plugin from WordPress.org
4. **Configure settings** via the 'Content Protect Pro' menu in your WordPress admin
5. **Start creating** gift codes and protecting your videos!

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Presto Player**: Latest version installed and activated
- **JavaScript**: Enabled for full functionality

## Quick Start Guide

### Setting Up Gift Codes

1. Navigate to **Content Protect Pro > Gift Codes** in your admin panel
2. Click **"Add New Gift Code"** 
3. Configure your code settings:
   - **Code**: Auto-generated or custom
   - **Value**: Monetary or point value (optional)
   - **Usage Limit**: How many times the code can be used
   - **Expiration Date**: When the code expires (optional)
4. Click **"Create Gift Code"**

### Protecting Videos with Presto Player

1. **Install and activate Presto Player** from WordPress.org
2. Create videos in **Presto Player > Videos** with password protection
3. Go to **Content Protect Pro > Protected Videos**
4. Click **"Add New Video"**
5. Set up your video protection:
   - **Video ID**: Your Presto Player video ID
   - **Title**: Descriptive name for the video
   - **Integration**: Select "Presto Player"
   - **Gift Code Required**: Enable if access needs validation

### Using Shortcodes

#### Gift Code Form
```php
[cpp_giftcode_form redirect_url="/premium-content/" success_message="Welcome to premium content!"]
```

#### Protected Video
```php
[cpp_protected_video id="your-presto-player-id" code="GIFT_CODE"]
```

#### Conditional Content
```php
[cpp_giftcode_check required_codes="PREMIUM,VIP" success_content="Premium content here" failure_content="Please enter a valid code to access this content."]
```

#### Video Library
```php
[cpp_video_library show_filters="true" per_page="12"]
```

## Configuration Options

### Gift Code Settings
- **Enable Gift Codes**: Turn gift code functionality on/off
- **Allow Partial Use**: Enable codes to be used multiple times until value is depleted
- **Case Sensitive**: Make code validation case-sensitive
- **Auto Generate**: Automatically generate codes for new entries
- **Code Length**: Default length for generated codes (4-32 characters)
- **Prefix/Suffix**: Add branded prefixes or suffixes to codes

### Video Protection Settings
- **Enable Video Protection**: Turn video protection on/off
- **Presto Player Integration**: Enable/disable Presto Player integration
- **Default Access Level**: Default protection level for new videos

### Security Settings
- **Enable Logging**: Log all protection-related events
- **Log Retention**: How long to keep log entries (1-365 days)
- **Rate Limiting**: Enable request rate limiting
- **Rate Limit**: Number of requests per time window (1-1000)
- **Time Window**: Rate limiting time window in seconds (60-3600)

### Analytics Settings
- **Enable Analytics**: Turn analytics tracking on/off
- **Track Gift Code Usage**: Record gift code validation events
- **Track Video Views**: Record video access and playback events
- **Anonymize IP**: Remove last octet of IP addresses for privacy

## Database Tables

The plugin creates three main database tables:

### cpp_giftcodes
Stores gift code information including usage tracking and expiration.

### cpp_protected_videos
Manages video protection settings and access configurations.

### cpp_analytics
Records all events for analytics and monitoring purposes.

## Presto Player Integration

Content Protect Pro integrates seamlessly with Presto Player for video protection:

### Setup Requirements
1. **Presto Player Pro** plugin installed and activated
2. **Video Configuration** in Presto Player with password protection
3. **Video IDs** from Presto Player for use in shortcodes

### How It Works
1. Create videos in Presto Player with password protection enabled
2. Add videos to Content Protect Pro with their Presto Player IDs
3. Use the `[cpp_protected_video id="VIDEO_ID" code="GIFT_CODE"]` shortcode
4. The plugin validates the gift code and displays the Presto Player video

### Benefits
- **Simple Setup**: No complex API configurations
- **Reliable**: Uses proven Presto Player technology
- **Lightweight**: Minimal code for better performance
- **Maintainable**: Easier to debug and update

## API Reference

### Gift Code Validation
```php
$giftcode_manager = new CPP_Giftcode_Manager();
$result = $giftcode_manager->validate_code('EXAMPLE123');
```

### Video Token Generation
```php
$video_manager = new CPP_Video_Manager();
$token = $video_manager->generate_access_token('video-123');
```

### Presto Player Integration
```php
$presto = new CPP_Presto_Integration();
$has_access = $presto->check_user_access($video_id, $protection_settings);
```

### Analytics Logging
```php
$analytics = new CPP_Analytics();
$analytics->log_event('custom_event', 'object_type', 'object_id', $metadata);
```

## Hooks and Filters

### Actions
- `cpp_giftcode_validated` - Fired when a gift code is successfully validated
- `cpp_giftcode_created` - Fired when a new gift code is created
- `cpp_video_accessed` - Fired when a video is accessed
- `cpp_analytics_event` - Fired when any analytics event is logged

### Filters
- `cpp_giftcode_validation_result` - Modify gift code validation results
- `cpp_video_access_token` - Modify video access tokens before generation
- `cpp_analytics_metadata` - Modify analytics event metadata
- `cpp_shortcode_attributes` - Modify shortcode attributes

## Troubleshooting

### Common Issues

**Gift codes not working:**
- Check that gift codes are enabled in settings
- Verify the code hasn't expired or exceeded usage limits
- Ensure JavaScript is enabled in the browser

**Videos not loading:**
- Confirm video protection is enabled
- Check that the video ID exists and is configured in Presto Player
- Verify Presto Player Pro is installed and activated
- Ensure the video is set up with password protection in Presto Player

**Analytics not recording:**
- Ensure analytics is enabled in plugin settings
- Check database permissions for table creation/writing
- Verify that the specific tracking options are enabled

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Security Considerations

- **Regular Updates**: Keep the plugin updated to the latest version
- **Strong Codes**: Use sufficiently long and random gift codes
- **Token Expiry**: Set appropriate video token expiration times
- **Rate Limiting**: Enable rate limiting to prevent brute force attacks
- **HTTPS**: Always use SSL/TLS for sites handling sensitive content
- **Database Security**: Ensure your WordPress database is properly secured

## Performance Optimization

- **Caching**: The plugin is compatible with most WordPress caching plugins
- **Database Cleanup**: Configure appropriate log retention periods
- **Presto Player CDN**: Leverage Presto Player's built-in CDN for optimal video delivery
- **Analytics Pruning**: Regularly clean old analytics data to maintain performance

## Support and Development

### Getting Help
- Check the [WordPress.org plugin page](https://wordpress.org/plugins/content-protect-pro/) for FAQs
- Review the plugin documentation in your WordPress admin
- Contact support through the plugin's support channels

### Contributing
This plugin is open source and welcomes contributions:
- Report bugs and request features via GitHub issues
- Submit pull requests for bug fixes and improvements
- Help translate the plugin into other languages

## Changelog

### Version 1.0.0
- Initial release with gift code management and video protection features
- Full admin interface with analytics dashboard
- Comprehensive shortcode system
- Multi-language support
- Security and performance optimizations
- **Simplified to Presto Player only** - removed complex Bunny CDN integration

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

Content Protect Pro is a streamlined solution that focuses on essential video protection features using Presto Player. Built with ❤️ for the WordPress community.