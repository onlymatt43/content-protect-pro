# Content Protect Pro - AI Agent Instructions

## Project Overview
WordPress plugin providing token-based video protection with gift code redemption system. **Simplified architecture** focusing on Presto Player integration only (Bunny CDN support legacy/optional).

**Core Concept**: Users redeem gift codes → receive session tokens → access protected videos for limited duration.

## Architecture & Data Flow

### Token-Based Authentication System
1. **Gift Code Redemption** (`cpp-rest-api.php`):
   - User submits code → validates via `CPP_Giftcode_Manager::validate_code()`
   - Creates session in `cpp_sessions` table with `secure_token`, `expires_at`, IP binding
   - Returns HttpOnly cookie with session token

2. **Video Access Flow**:
   - User requests video → validates session token from cookie
   - Checks `required_minutes` on video against `duration_minutes` from gift code
   - Generates signed playback URL (Bunny CDN) OR returns Presto Player embed HTML

3. **Database Schema** (see `class-cpp-activator.php::create_tables()`):
   ```
   cpp_giftcodes: code, secure_token, duration_minutes, status, expires_at
   cpp_protected_videos: video_id, required_minutes, integration_type, presto_player_id
   cpp_sessions: session_id, code, secure_token, client_ip, expires_at, status
   cpp_analytics: event_type, object_type, object_id, metadata, created_at
   ```

### Hook Registration Pattern
All functionality routes through `CPP_Loader` hook system:
- **Admin hooks**: `class-cpp-admin.php` → `define_admin_hooks()` in `class-content-protect-pro.php`
- **Public hooks**: `class-cpp-public.php` → `define_public_hooks()` includes AJAX endpoints
- **AJAX handlers**: Registered in `cpp-ajax-handlers.php`, called via `wp_ajax_*` actions

## Critical Developer Patterns

### 1. Security-First Validation
**ALWAYS** include these checks in public-facing endpoints:
```php
// CSRF protection
if (!wp_verify_nonce($_POST['nonce'], 'cpp_action_name')) {
    wp_send_json_error('Security check failed');
}

// Rate limiting
$client_ip = cpp_get_client_ip();
if (!$this->check_rate_limit($client_ip)) {
    return ['valid' => false, 'message' => 'Too many attempts'];
}

// Timing-safe comparisons (see class-cpp-giftcode-security.php)
if (!hash_equals($expected_token, $provided_token)) {
    return false;
}
```

### 2. Overlay Image Management
**POST-MIGRATION RULE**: Overlay images MUST be Media Library attachment IDs, NOT external URLs.
- Admin UI uses `wp.media` picker, stores `attachment_id` in `cpp_integration_settings['overlay_image']`
- Frontend resolves via `wp_get_attachment_url($attachment_id)`
- Migration: `class-cpp-migrations.php::migrate_overlay_urls_to_attachments()` auto-converts legacy URLs

### 3. Video Integration Handling
Check integration type before processing:
```php
$integration_type = $video_data->integration_type; // 'presto' or 'bunny'
if ($integration_type === 'presto') {
    // Use CPP_Presto_Integration::generate_access_token()
    // Returns HTML embed code from Presto Player
} else {
    // Use CPP_Bunny_Integration for signed CDN URLs (legacy)
}
```

### 4. REST API Endpoints
- `POST /wp-json/smartvideo/v1/redeem` - Validates code, creates session, sets cookie
- `POST /wp-json/smartvideo/v1/request-playback` - Returns `playback_url` or `embed` HTML
- Both require session cookie for `request-playback`

## Shortcode System (`cpp-shortcodes.php`)
- `[cpp_giftcode_form]` - Gift code redemption form
- `[cpp_protected_video id="presto-id"]` - Single video player
- `[cpp_video_library]` - Gallery with filters (uses `sv_library` internally)
- `[cpp_giftcode_check required_codes="X,Y"]` - Conditional content display

**Frontend JS**: `public/js/cpp-public.js` handles AJAX calls to validate codes and load videos.

## Testing & Diagnostics

### E2E Testing
Use `tools/e2e_playback_test.sh` for full redemption → playback flow:
```bash
SITE=https://example.com VIDEO_ID=123 CODE=VIP2024 ./tools/e2e_playback_test.sh
```
Tests: redeem endpoint → cookie capture → request-playback → HEAD on playback URL.

### Diagnostic Pages
- `test-rapide.php` - Quick sanity checks (shortcode registration, class loading)
- `diagnostic-complet.php` - Full system diagnostics
- Both in plugin root, access via `yoursite.com/test-rapide.php`

## Database Migrations
`CPP_Migrations::maybe_migrate()` runs on plugin load (throttled to 12h intervals):
- Adds missing columns to tables without dropping legacy data
- Migrates overlay URLs to attachment IDs
- Safe to run multiple times (checks existing schema first)

## WordPress Standards Compliance
- **Nonces**: Use `wp_create_nonce('cpp_action')` and `wp_verify_nonce()`
- **Database**: Always use `$wpdb->prepare()` for queries
- **I18n**: Wrap strings in `__('text', 'content-protect-pro')`
- **Sanitization**: `sanitize_text_field()`, `absint()`, `esc_url()` before output
- **Hooks**: Prefix all hooks with `cpp_` (e.g., `cpp_giftcode_validated`)

## Common Tasks

### Adding a New Protected Video
1. Create video in Presto Player with password protection
2. Admin → Content Protect Pro → Protected Videos → Add New
3. Set `presto_player_id`, `required_minutes`, `integration_type='presto'`
4. Use shortcode `[cpp_protected_video id="presto-id"]`

### Adding Analytics Event
```php
if (class_exists('CPP_Analytics')) {
    $analytics = new CPP_Analytics();
    $analytics->log_event('event_type', 'object_type', 'object_id', [
        'custom_key' => 'value'
    ]);
}
```

### Debugging Sessions
Check `cpp_sessions` table for active sessions:
```sql
SELECT * FROM wp_cpp_sessions WHERE status='active' AND expires_at > NOW();
```

## Files to Reference
- **Security patterns**: `includes/class-cpp-giftcode-security.php`, `includes/class-cpp-encryption.php`
- **Token generation**: `includes/cpp-token-helpers.php`
- **Admin pages**: `admin/partials/*.php` for UI templates
- **Settings structure**: `includes/class-cpp-settings-advanced.php` for export/import

## DO NOT
- ❌ Store unencrypted tokens in database (use `CPP_Encryption::encrypt()`)
- ❌ Accept external image URLs for overlays post-migration (attachment IDs only)
- ❌ Skip nonce validation on AJAX endpoints
- ❌ Use `==` for token comparison (use `hash_equals()`)
- ❌ Directly edit `content-protect-pro.php` for new features (extend classes in `includes/`)

## Quick Reference: Key Classes
- `CPP_Giftcode_Manager` - Code validation, creation
- `CPP_Protection_Manager` - Video access control
- `CPP_Presto_Integration` - Presto Player embed generation
- `CPP_Analytics` - Event tracking and reporting
- `CPP_Migrations` - Database schema updates