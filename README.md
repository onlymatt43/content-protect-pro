# Content Protect Pro

A comprehensive WordPress plugin that unifies gift code protection and video library protection systems. This plugin combines the functionality of two separate protection systems into a single, powerful solution for content creators and online businesses.

## Features

### 🎁 Gift Code Management
- **Create and manage gift codes** with customizable parameters
- **Usage limits and expiration dates** for flexible distribution
- **Bulk generation and management** tools
- **Partial usage support** for multi-use codes
- **Case sensitivity options** for code validation
- **Custom prefix/suffix** support for branded codes

### 🎬 Video Protection
- **JWT token-based video protection** for secure streaming
- **Bunny signed URL integration** for secure streaming
- **Presto Player Pro compatibility** with overlay protection and hooks
- **Access level control** (public, private, gift code required)
- **Time-limited video tokens** with configurable expiry and IP restriction
  
- **Shortcode-based video embedding** with protection

### 📊 Analytics & Monitoring
- **Comprehensive event tracking** for both gift codes and videos
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
3. **Configure settings** via the 'Content Protect Pro' menu in your WordPress admin
4. **Start creating** gift codes and protecting your content!

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher (for database tables)
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

### Protecting Videos

1. Go to **Content Protect Pro > Protected Videos**
2. Click **"Add New Video"**
3. Set up your video protection:
   - **Video ID**: Your video identifier (Bunny GUID or Presto Player ID)
   - **Protection Type**: Token-based, DRM, or gift code required
   - **Access Level**: Public, private, or restricted
   - **Bunny Integration**: Library ID and DRM settings
   - **Presto Integration**: Player configuration and overlay protection

### Using Shortcodes

#### Gift Code Form
```php
[cpp_giftcode_form redirect_url="/premium-content/" success_message="Welcome to premium content!"]
```

#### Protected Video
```php
[cpp_protected_video video_id="your-video-id" require_giftcode="true" player_type="bunny"]
```

#### Conditional Content
```php
[cpp_giftcode_check required_codes="PREMIUM,VIP" success_content="Premium content here" failure_content="Please enter a valid code to access this content."]
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
- **Token Expiry**: How long video access tokens remain valid (300-86400 seconds)
- **Bunny Integration**: API key, library ID, and DRM configuration
- **Presto Integration**: Player hooks and overlay protection settings
  
- **IP Restriction**: Bind tokens to client IP addresses
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

## Integration Details

### Bunny Integration
Content Protect Pro integrates with Bunny Stream:

- **Signed URLs**: Automatic generation of time-limited, IP-restricted HLS URLs
- **Upload Management**: Direct video upload to Bunny Stream libraries
- **Analytics Integration**: Real-time statistics from Bunny Stream API
- **Token Authentication**: Custom token authentication for enhanced security

**Setup Requirements:**
1. Bunny Stream account with API access
2. Library ID and Access Key from Bunny Stream

### Presto Player Pro Integration
Seamless integration with Presto Player Pro features:

- **Player Hooks**: Automatic protection overlay injection
- **Access Control**: Pre-playback access validation
- **Gift Code Integration**: Real-time code validation within player
- **Custom Overlays**: Branded protection messages and forms
- **Player Configuration**: Dynamic player setup based on access rights

**Setup Requirements:**
1. Presto Player Pro plugin installed and activated
2. Video configuration in Content Protect Pro admin
3. Player shortcode implementation with protection parameters

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

### Bunny Integration
```php
$bunny = new CPP_Bunny_Integration();
$signed_url = $bunny->generate_signed_url('video-guid', time() + 3600);
$video_stats = $bunny->get_video_statistics('video-guid');
```

### Presto Integration
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
- Check that the video ID exists and is configured
- Verify Bunny API key and library ID are correct
- Ensure Presto Player Pro is installed and activated
- Check DRM license server configuration if using DRM

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
- **CDN Integration**: Use Bunny CDN for optimal video delivery performance
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
- Initial release combining gift code and video protection features
- Full admin interface with analytics dashboard
- Comprehensive shortcode system
- Multi-language support
- Security and performance optimizations

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

Content Protect Pro combines and enhances functionality from:
- Gift Code Protect v2 - Gift code management and validation system
- Video Library Protect - Video protection and access control system

Built with ❤️ for the WordPress community.