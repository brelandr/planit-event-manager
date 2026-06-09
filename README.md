# PlanIt Event Manager

A free WordPress event calendar plugin with calendar views, event management, venues, organizers, and more. Upgrade to Premium for advanced features!

**Upgrade to Premium:** [https://landtechwebdesigns.com/planit-event-manager-premium](https://landtechwebdesigns.com/planit-event-manager-premium)

**Author:** Land Tech Web Designs, Corp  
**Author URI:** https://landtechwebdesigns.com  
**Version:** 1.0.0

## Free Version Features

- Calendar Views: Day and Month views
- List View: Display events in a clean list format
- Event Management: Create, edit, and delete events
- Venues & Organizers: Manage separately
- Event Categories & Tags
- Hide Past Events option
- Google Maps Integration (requires API key)
- iCal & Google Calendar Export
- Responsive Design
- Widget Support: Upcoming Events widget
- Shortcodes

## Premium Features (Upgrade Required)

- Week View, Year View, Photo View, Map View
- Recurring Events
- Custom Fields
- Event Series
- Featured Events
- CSV Import
- The Events Calendar Import
- Event Cost/Price
- Event Website
- Event Timezone
- RSS Feed
- Advanced Widgets (Featured Events, Event Series, Countdown)

**Upgrade Now:** [https://landtechwebdesigns.com/planit-event-manager-premium](https://landtechwebdesigns.com/planit-event-manager-premium)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Events > Settings to configure
4. Start creating events!

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- No external dependencies required

## Usage

Use shortcode `[twec_calendar]` to display the calendar or visit the events archive page.

## Development

- **PHPUnit / PHPCS:** From the plugin directory, run `composer install` then `./vendor/bin/phpunit` or `composer test`. Focused PHPCS: `composer lint` ([`phpcs.xml.dist`](phpcs.xml.dist)). GitHub Actions runs both on push/PR to `main`, `master`, or `develop`.
- **Shared RRULE:** Premium is the canonical `includes/twec-rrule.php`; after upstream changes, run `planit-event-manager-premium/scripts/sync-twec-rrule.sh` ([`docs/RRULE-Matrix.md`](docs/RRULE-Matrix.md)).

## License

GPL-2.0+
