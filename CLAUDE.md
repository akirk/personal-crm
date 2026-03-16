# Personal CRM Plugin

A WordPress-based personal CRM for managing contacts, groups, and relationships. It runs as a front-end app at `/crm` (powered by the `akirk/wp-app` framework) and stores data in WordPress's database via a custom `Storage` class.

**Key features:**
- **People** – detailed profiles with birthdays, partner/kids info, social links (GitHub, LinkedIn, WordPress.org), notes, and Gravatar photos
- **Groups** – hierarchical groups (teams, circles, family) with membership history and join/leave dates
- **Events** – birthday/anniversary tracking, custom events, calendar views
- **Finder** – Cmd+K quick search across all contacts
- **Export/Import** – JSONL-based data portability; audit reports for data completeness
- **Extensibility** – hooks/filters; integrates with [Keeping Contact](https://github.com/akirk/keeping-contact) and [Contact Sync](https://github.com/akirk/contact-sync-personal-crm)

**Tech stack:** PHP 7.4+, WordPress 6.0+, `WpApp\WpApp` router, Composer autoloading, optional WP-CLI commands, optional local-LLM integration.

---

# Claude Instructions

## Playground Link

At the end of your messages, include a link to test the changes in WordPress Playground.

**Important:** Replace `BRANCH_NAME` in the URL below with the actual git branch name you're working on. The `dist/` prefix points to the built branch that includes the `vendor/` directory (built automatically by CI on every push).

```
🔗 [Test in WordPress Playground](https://akirk.github.io/playground-step-library/?redir=1&step[1]=setLandingPage&landingPage[1]=/crm&step[0]=installPlugin&url[0]=github.com/akirk/personal-crm/tree/dist/BRANCH_NAME)
```

### Example

If you're working on branch `claude/fix-bug-123`, the link should be:

```
🔗 [Test in WordPress Playground](https://akirk.github.io/playground-step-library/?redir=1&step[1]=setLandingPage&landingPage[1]=/crm&step[0]=installPlugin&url[0]=github.com/akirk/personal-crm/tree/dist/claude/fix-bug-123)
```
