# Personal CRM

A WordPress-based personal CRM for managing your personal and professional relationships. Track contacts, organize them into groups, remember important dates, and never miss a birthday or anniversary.

## Features

### Contact Management
- Store detailed profiles: name, email, location, timezone, and social links (GitHub, LinkedIn, WordPress.org)
- Track personal details: birthdays, partner info, children
- Add custom notes and links for each person
- Gravatar integration for profile photos

### Groups & Organization
- Create hierarchical groups (teams, friend circles, family, etc.)
- Track membership history with join/leave dates
- Assign people to multiple groups
- Set a default group for quick access

### Events & Reminders
- Automatic birthday and anniversary tracking
- Custom events with multi-day support
- Calendar views (list and month)
- Upcoming events sidebar

### Search & Navigation
- Cmd+K quick finder for instant access
- Full-text search across all contacts
- Pin favorite people and groups to My Apps

### Data Management
- Export/import via JSONL
- Audit reports for data completeness
- Privacy mode for sensitive displays

### Extensibility
- Hooks and filters for plugin integration
- Works with [Keeping Contact](https://github.com/akirk/keeping-contact) for contact frequency tracking
- Works with [Contact Sync](https://github.com/akirk/contact-sync-personal-crm) for CardDAV sync

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Upload the plugin to `/wp-content/plugins/personal-crm`
2. Run `composer install` in the plugin directory
3. Activate through the Plugins menu
4. Visit `/crm` to get started

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.
