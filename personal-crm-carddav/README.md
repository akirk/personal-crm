# Personal CRM CardDAV Integration

A WordPress plugin that adds CardDAV server capability to the [Personal CRM plugin](https://github.com/akirk/personal-crm), allowing you to sync your CRM contacts with any CardDAV-compatible application.

## Description

This plugin extends the Personal CRM plugin with CardDAV protocol support, enabling bidirectional synchronization of contacts between your WordPress CRM and CardDAV clients such as:

- macOS Contacts
- iOS/iPadOS Contacts
- Thunderbird (with CardBook add-on)
- Android (with DAVx⁵)
- Any other CardDAV-compatible application

## Features

- **Full CardDAV Protocol Support**: Implements the CardDAV standard (RFC 6352)
- **Bidirectional Sync**: Changes made in your CardDAV client are synced back to Personal CRM
- **Multiple Address Books**: Each Personal CRM group appears as a separate CardDAV address book
- **Rich Contact Data**: Syncs emails, birthdays, anniversaries, websites, social profiles, and more
- **Secure Authentication**: Uses WordPress user authentication with HTTP Basic Auth
- **vCard 4.0 Format**: Modern vCard format with full field support
- **WordPress Hooks Integration**: Seamlessly integrates with Personal CRM using WordPress action and filter hooks

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Personal CRM plugin (must be installed and activated first)
- Pretty permalinks enabled in WordPress
- HTTPS recommended (some clients require it)

## Installation

1. Ensure the Personal CRM plugin is installed and activated
2. Upload the `personal-crm-carddav` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings > Permalinks and click "Save Changes" to flush rewrite rules
5. Navigate to CRM > CardDAV in your WordPress admin to view setup instructions

## Configuration

### Server Information

The CardDAV server will be available at:
```
https://your-site.com/carddav/
```

### Authentication

Use your WordPress credentials:
- **Username**: Your WordPress username
- **Password**: Your WordPress password

### Address Books

Each Personal CRM group is available as a separate address book:
```
https://your-site.com/carddav/{group-slug}/
```

For example, if you have a group with slug "team", the address book URL would be:
```
https://your-site.com/carddav/team/
```

## Client Setup

### macOS Contacts

1. Open the Contacts app
2. Go to Contacts > Preferences > Accounts
3. Click the "+" button to add a new account
4. Select "Add CardDAV Account"
5. Enter:
   - **Server**: `your-site.com/carddav/`
   - **Username**: Your WordPress username
   - **Password**: Your WordPress password
6. Click "Sign In"

### iOS/iPadOS

1. Open Settings > Contacts > Accounts
2. Tap "Add Account"
3. Select "Other" > "Add CardDAV Account"
4. Enter:
   - **Server**: `your-site.com`
   - **User Name**: Your WordPress username
   - **Password**: Your WordPress password
   - **Description**: Personal CRM (or any name)
5. Tap "Next"

### Android with DAVx⁵

1. Install [DAVx⁵](https://www.davx5.com/) from Google Play or F-Droid
2. Open DAVx⁵ and tap the "+" button
3. Select "Login with URL and user name"
4. Enter:
   - **Base URL**: `https://your-site.com/carddav/`
   - **User name**: Your WordPress username
   - **Password**: Your WordPress password
5. Tap "Login"
6. Select which address books to sync

### Thunderbird

1. Install the [CardBook add-on](https://addons.thunderbird.net/addon/cardbook/)
2. Right-click on "CardBook" in the sidebar
3. Select "New Address Book" > "Remote" > "CardDAV"
4. Enter:
   - **URL**: `https://your-site.com/carddav/{group-slug}/`
   - **Username**: Your WordPress username
   - **Password**: Your WordPress password
5. Click "Validate" and then "Next"

## WordPress Hooks Used

This plugin integrates with Personal CRM using the following WordPress hooks:

### Actions Hooks Used

- `personal_crm_loaded` - Initialize CardDAV integration after Personal CRM loads
- `personal_crm_dashboard_sidebar` - Add CardDAV sync info to dashboard sidebar

### Action Hooks Provided

This plugin provides the following hooks for other plugins:

- `personal_crm_carddav_initialized` - Fired after CardDAV integration is initialized
  ```php
  do_action( 'personal_crm_carddav_initialized', $integration );
  ```

- `personal_crm_carddav_contact_saved` - Fired after a contact is saved via CardDAV
  ```php
  do_action( 'personal_crm_carddav_contact_saved', $username, $person_data, $group_slug );
  ```

- `personal_crm_carddav_contact_deleted` - Fired after a contact is deleted via CardDAV
  ```php
  do_action( 'personal_crm_carddav_contact_deleted', $username, $group_slug );
  ```

### Filter Hooks Provided

- `personal_crm_carddav_user_can_access` - Filter to customize CardDAV access permissions
  ```php
  apply_filters( 'personal_crm_carddav_user_can_access', $can_access, $user );
  ```

## Architecture

The plugin is structured with clear separation of concerns:

```
personal-crm-carddav/
├── personal-crm-carddav.php          # Main plugin file
├── includes/
│   ├── class-carddav-integration.php # WordPress hooks integration
│   ├── class-carddav-server.php      # CardDAV protocol implementation
│   ├── class-vcard-converter.php     # Person ↔ vCard conversion
│   └── class-carddav-auth.php        # Authentication handler
└── README.md
```

### Class Overview

**Personal_CRM_CardDAV_Integration**
- Singleton that manages the integration
- Hooks into Personal CRM using `personal_crm_loaded` action
- Registers rewrite rules for CardDAV endpoints
- Adds settings page to WordPress admin
- Adds CardDAV info to Personal CRM dashboard

**Personal_CRM_CardDAV_Server**
- Implements CardDAV protocol (RFC 6352)
- Handles HTTP methods: PROPFIND, REPORT, GET, PUT, DELETE, OPTIONS
- Manages address book and contact resources
- Generates XML responses for WebDAV/CardDAV requests

**Personal_CRM_VCard_Converter**
- Converts Person objects to vCard 4.0 format
- Parses vCard data back to Person data arrays
- Handles vCard escaping and date formatting
- Supports custom fields using X- prefix

**Personal_CRM_CardDAV_Auth**
- Handles HTTP Basic Authentication
- Validates WordPress user credentials
- Checks user permissions for CRM access
- Sends appropriate authentication challenges

## Supported vCard Fields

The plugin maps Personal CRM fields to vCard properties:

| Personal CRM Field | vCard Property | Notes |
|-------------------|----------------|-------|
| name | FN, N | Full name and structured name |
| nickname | NICKNAME | |
| email | EMAIL | Type: work |
| role | TITLE | Job title |
| birthday | BDAY | |
| company_anniversary | ANNIVERSARY | |
| timezone | TZ | |
| location | ADR | Type: work |
| website | URL | Type: home |
| github | URL | Type: github, full URL |
| linkedin | URL | Type: linkedin |
| wordpress | URL | Type: wordpress, full URL |
| links | URL | Custom types |
| category, team | CATEGORIES | |
| partner, kids, notes | NOTE | Combined in notes field |
| Gravatar | PHOTO | If available |
| linear | X-LINEAR | Custom field |
| left_company | X-LEFT-COMPANY | Custom field |
| deceased | X-DECEASED | Custom field |

## Troubleshooting

### 404 Errors

If you get 404 errors when accessing CardDAV endpoints:
1. Go to Settings > Permalinks in WordPress admin
2. Click "Save Changes" without making any changes
3. This flushes the rewrite rules and should fix the issue

### Authentication Failures

If you can't authenticate:
- Verify your WordPress username and password are correct
- Check if your server supports HTTP Basic Authentication
- Some server configurations block authorization headers - contact your hosting provider

### HTTPS Issues

Some CardDAV clients require HTTPS:
- Install an SSL certificate on your WordPress site
- Use a service like Let's Encrypt for free SSL
- Update your WordPress site URL to use HTTPS

### Sync Not Working

If contacts don't sync:
- Check that Personal CRM plugin is active
- Verify you have contacts in the group you're trying to sync
- Check server error logs for PHP errors
- Try re-adding the CardDAV account in your client

## Security

- Uses WordPress authentication (same as your admin panel)
- Respects WordPress user capabilities
- Only users who can access Personal CRM can use CardDAV
- HTTPS is strongly recommended for production use
- Supports HTTP Basic Authentication

## Performance

- Efficient database queries using Personal CRM's storage layer
- Supports sync tokens for incremental syncing
- ETags for change detection
- Lazy loading of group members

## Limitations

- Address book creation via CardDAV is not supported (use Personal CRM admin)
- Groups must be created in Personal CRM admin panel
- Some advanced vCard fields may not map perfectly to Personal CRM schema

## Development

### Extending the Plugin

You can extend the plugin using the provided hooks:

```php
// Example: Log when contacts are saved via CardDAV
add_action( 'personal_crm_carddav_contact_saved', function( $username, $person_data, $group_slug ) {
    error_log( "Contact {$username} saved to {$group_slug} via CardDAV" );
}, 10, 3 );

// Example: Custom access control
add_filter( 'personal_crm_carddav_user_can_access', function( $can_access, $user ) {
    // Only allow users with a specific role
    return user_can( $user, 'edit_posts' );
}, 10, 2 );
```

### Running in Development

For local development:
1. Use a local WordPress installation with pretty permalinks enabled
2. Ensure Personal CRM is installed and activated
3. Install and activate this plugin
4. Test with a CardDAV client or tools like `curl`

### Testing with curl

```bash
# List address books
curl -X PROPFIND -u username:password \
  -H "Depth: 1" \
  https://your-site.com/carddav/

# Get a contact
curl -u username:password \
  https://your-site.com/carddav/team/username.vcf
```

## Credits

- Built for [Personal CRM](https://github.com/akirk/personal-crm)
- CardDAV protocol: [RFC 6352](https://tools.ietf.org/html/rfc6352)
- vCard format: [RFC 6350](https://tools.ietf.org/html/rfc6350)

## License

GPL v2 or later

## Support

For issues and questions:
1. Check the troubleshooting section above
2. Review the [Personal CRM documentation](https://github.com/akirk/personal-crm)
3. Submit an issue on GitHub

## Changelog

### 1.0.0
- Initial release
- Full CardDAV protocol support
- vCard 4.0 conversion
- Multi-address book support
- WordPress hooks integration
- Settings page with setup instructions
