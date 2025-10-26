# CardDAV Authentication Options

This document explains the authentication methods available for the Personal CRM CardDAV plugin and their security implications.

## Current Implementation: HTTP Basic Auth

**Status**: ⚠️ Works but has security concerns

### How It Works

The plugin currently uses HTTP Basic Authentication with your WordPress username and password:

```
Authorization: Basic <base64(username:password)>
```

**Location**: `includes/class-carddav-auth.php`

### Security Issues

1. **Main Password Exposure**: Uses your actual WordPress admin password
   - If compromised → entire WordPress site at risk
   - Can't revoke CardDAV access without changing WordPress password
   - Same password used across all devices/applications

2. **No Individual Device Control**: Can't revoke access for a specific device/app

3. **Requires HTTPS**: Without HTTPS, credentials are easily intercepted

4. **Password Sent With Every Request**: More exposure opportunities

## Recommended: Application Passwords ⭐

**Status**: Best practice for WordPress 5.6+

### What Are Application Passwords?

WordPress 5.6+ includes built-in support for application-specific passwords:

- Unique passwords for each app/device
- Can be revoked individually
- Doesn't expose your main WordPress password
- Automatically hashed and stored securely

### How to Enable

#### Step 1: Update Authentication Class

Replace the authentication in `personal-crm-carddav.php`:

```php
// Replace this line:
require_once PERSONAL_CRM_CARDDAV_PLUGIN_DIR . 'includes/class-carddav-auth.php';

// With this:
require_once PERSONAL_CRM_CARDDAV_PLUGIN_DIR . 'includes/class-carddav-auth-app-passwords.php';
```

#### Step 2: Create Application Password

1. Go to **WordPress Admin → Users → Profile**
2. Scroll to **Application Passwords** section
3. Enter name: "CardDAV - iPhone" (or device name)
4. Click **Add New Application Password**
5. Copy the generated password (shown only once!)
6. Use this password in your CardDAV client

### Benefits

✅ Secure: Separate from WordPress password
✅ Revokable: Remove access per device
✅ Auditable: See when each password was last used
✅ Standard: Built into WordPress core

### Example Setup

**macOS Contacts:**
```
Server: your-site.com/carddav/
Username: john_doe
Password: AbCd EfGh IjKl MnOp QrSt UvWx (Application Password)
```

**Settings Page Update:**

The settings page should be updated to show Application Password instructions:

```php
<h3>Using Application Passwords (Recommended)</h3>
<ol>
	<li>Go to your <a href="<?php echo admin_url( 'profile.php#application-passwords-section' ); ?>">WordPress Profile</a></li>
	<li>Scroll to "Application Passwords"</li>
	<li>Enter a name like "CardDAV - iPhone"</li>
	<li>Click "Add New Application Password"</li>
	<li>Copy the generated password</li>
	<li>Use this password (not your WordPress password!) in your CardDAV client</li>
</ol>

<p><strong>Benefits:</strong></p>
<ul>
	<li>Your WordPress password stays secure</li>
	<li>Revoke access per device if lost/stolen</li>
	<li>See when each device last synced</li>
</ul>
```

## Alternative: OAuth 2.0 Bearer Tokens

**Status**: More complex but more flexible

### When to Use

- Need fine-grained permissions
- Want time-limited tokens
- Building a SaaS or multi-tenant system

### Implementation Sketch

```php
class Personal_CRM_CardDAV_Auth_OAuth extends Personal_CRM_CardDAV_Auth {

	public static function authenticate() {
		// Check for Bearer token
		$token = self::get_bearer_token();

		if ( ! $token ) {
			return parent::authenticate(); // Fallback to Basic Auth
		}

		// Validate token
		$user_id = self::validate_token( $token );
		if ( ! $user_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! self::user_can_access_crm( $user ) ) {
			return false;
		}

		self::$authenticated_user = $user;
		return true;
	}

	private static function get_bearer_token() {
		$headers = getallheaders();
		if ( isset( $headers['Authorization'] ) ) {
			if ( preg_match( '/Bearer\s+(.+)/i', $headers['Authorization'], $matches ) ) {
				return $matches[1];
			}
		}
		return null;
	}

	private static function validate_token( $token ) {
		// Validate against custom token table or WordPress transients
		$user_id = get_transient( 'carddav_token_' . hash( 'sha256', $token ) );
		return $user_id ?: false;
	}
}
```

### Token Generation Example

```php
// Generate token for user
function personal_crm_carddav_generate_token( $user_id, $expires_in = DAY_IN_SECONDS ) {
	$token = wp_generate_password( 64, false );
	$hashed = hash( 'sha256', $token );

	set_transient( 'carddav_token_' . $hashed, $user_id, $expires_in );

	return $token;
}
```

## Alternative: API Keys

**Status**: Simple but less standardized

### Custom API Key Implementation

```php
class Personal_CRM_CardDAV_Auth_API_Key extends Personal_CRM_CardDAV_Auth {

	public static function authenticate() {
		// Check for API key in header
		$api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;

		if ( ! $api_key ) {
			return parent::authenticate(); // Fallback
		}

		// Validate API key
		$user_id = self::validate_api_key( $api_key );
		if ( ! $user_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! self::user_can_access_crm( $user ) ) {
			return false;
		}

		self::$authenticated_user = $user;
		return true;
	}

	private static function validate_api_key( $api_key ) {
		// Check against user meta
		$users = get_users( array(
			'meta_key'   => 'carddav_api_key',
			'meta_value' => hash( 'sha256', $api_key ),
		) );

		return ! empty( $users ) ? $users[0]->ID : false;
	}
}
```

**Store API key in user meta:**

```php
// Generate and store API key for user
$api_key = wp_generate_password( 32, false );
$hashed = hash( 'sha256', $api_key );
update_user_meta( $user_id, 'carddav_api_key', $hashed );

// Show to user once (store securely)
echo "Your API Key: " . $api_key;
```

## Security Best Practices

### 1. Always Use HTTPS

```php
// Add to plugin initialization
if ( ! is_ssl() && ! defined( 'PERSONAL_CRM_CARDDAV_ALLOW_HTTP' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>CardDAV Security Warning:</strong> ';
		echo 'CardDAV should only be used over HTTPS. Please install an SSL certificate.';
		echo '</p></div>';
	} );
}
```

### 2. Rate Limiting

```php
// Add rate limiting to prevent brute force
class Personal_CRM_CardDAV_Rate_Limiter {

	public static function check_rate_limit( $identifier ) {
		$transient_key = 'carddav_attempts_' . md5( $identifier );
		$attempts = get_transient( $transient_key ) ?: 0;

		if ( $attempts >= 5 ) {
			header( 'HTTP/1.1 429 Too Many Requests' );
			header( 'Retry-After: 300' );
			exit( 'Too many authentication attempts. Try again in 5 minutes.' );
		}

		set_transient( $transient_key, $attempts + 1, 300 ); // 5 minute window
	}

	public static function reset_rate_limit( $identifier ) {
		delete_transient( 'carddav_attempts_' . md5( $identifier ) );
	}
}

// Use in authentication:
Personal_CRM_CardDAV_Rate_Limiter::check_rate_limit( $username );
```

### 3. Audit Logging

```php
// Log authentication attempts
add_action( 'personal_crm_carddav_auth_success', function( $user ) {
	error_log( sprintf(
		'CardDAV auth success: user=%s, ip=%s, time=%s',
		$user->user_login,
		$_SERVER['REMOTE_ADDR'],
		current_time( 'mysql' )
	) );
} );

add_action( 'personal_crm_carddav_auth_failed', function( $username ) {
	error_log( sprintf(
		'CardDAV auth failed: user=%s, ip=%s, time=%s',
		$username,
		$_SERVER['REMOTE_ADDR'],
		current_time( 'mysql' )
	) );
} );
```

### 4. IP Whitelisting (Optional)

```php
// Restrict CardDAV access to specific IPs
add_filter( 'personal_crm_carddav_user_can_access', function( $can_access, $user ) {
	$allowed_ips = get_option( 'personal_crm_carddav_allowed_ips', array() );

	if ( ! empty( $allowed_ips ) ) {
		$client_ip = $_SERVER['REMOTE_ADDR'];
		if ( ! in_array( $client_ip, $allowed_ips, true ) ) {
			return false;
		}
	}

	return $can_access;
}, 10, 2 );
```

## Comparison Table

| Method | Security | Ease of Use | WordPress Integration | Revocable Per Device |
|--------|----------|-------------|----------------------|---------------------|
| **HTTP Basic Auth** | ⚠️ Low | ✅ Easy | ✅ Built-in | ❌ No |
| **Application Passwords** | ✅ High | ✅ Easy | ✅ WordPress 5.6+ | ✅ Yes |
| **OAuth 2.0** | ✅ High | ⚠️ Complex | ⚠️ Custom | ✅ Yes |
| **API Keys** | ✅ Medium | ✅ Medium | ⚠️ Custom | ✅ Yes |

## Recommendations

### For Most Users
**Use Application Passwords** (WordPress 5.6+)
- Best balance of security and ease of use
- Built into WordPress
- No additional code needed (see `class-carddav-auth-app-passwords.php`)

### For Advanced Users
**Combine Multiple Methods:**
```php
// Try Application Password first, fallback to basic auth
public static function authenticate() {
	// Try app password
	if ( $user = self::try_application_password() ) {
		return $user;
	}

	// Try OAuth token
	if ( $user = self::try_oauth_token() ) {
		return $user;
	}

	// Fallback to basic auth
	return self::try_basic_auth();
}
```

### For SaaS/Multi-tenant
**Use OAuth 2.0** with proper token management and refresh tokens

## Migration Path

### From Basic Auth to Application Passwords

1. Deploy enhanced authentication class
2. Add admin notice encouraging Application Password creation
3. Continue supporting basic auth for transition period
4. After 30-60 days, deprecate basic auth
5. Force Application Passwords for all users

```php
// Add deprecation notice
add_action( 'personal_crm_dashboard_sidebar', function() {
	if ( ! defined( 'PERSONAL_CRM_CARDDAV_REQUIRE_APP_PASSWORDS' ) ) {
		echo '<div class="notice notice-warning">';
		echo '<p><strong>Security Notice:</strong> Please switch to Application Passwords for CardDAV. ';
		echo '<a href="' . admin_url( 'profile.php#application-passwords-section' ) . '">Create one now</a></p>';
		echo '</div>';
	}
} );
```

## Troubleshooting

### Application Passwords Not Available?

Check if blocked by server:
```php
// Add to wp-config.php to force enable
define( 'WP_APPLICATION_PASSWORDS_AVAILABLE', true );
```

### Still Getting 401 Errors?

1. Check if HTTP Authorization headers are being passed:
   ```php
   var_dump( $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET' );
   ```

2. Add to `.htaccess` if needed:
   ```apache
   SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
   ```

3. Enable debug logging:
   ```php
   define( 'PERSONAL_CRM_CARDDAV_DEBUG', true );
   ```

## Further Reading

- [WordPress Application Passwords Documentation](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
- [OAuth 2.0 Authorization Framework](https://datatracker.ietf.org/doc/html/rfc6749)
- [HTTP Basic Authentication](https://datatracker.ietf.org/doc/html/rfc7617)
- [CardDAV RFC 6352](https://datatracker.ietf.org/doc/html/rfc6352)
