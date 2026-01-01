=== PlanIt Event Manager – Responsive Event Calendar & Management Plugin ===
Contributors: brelandr
Tags: event calendar, event manager, events, calendar, booking
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Free WordPress event calendar with calendar views, event management, venues, and organizers. The perfect event calendar solution for any website.

== Description ==

PlanIt Event Manager is the perfect free event calendar plugin for WordPress. This comprehensive event calendar solution makes it easy to create, manage, and display events on your website with beautiful calendar views and intuitive event management tools. Whether you're organizing conferences, workshops, concerts, or any type of event, this event calendar provides everything you need to keep your visitors informed.

With powerful calendar views, list displays, venue management, and seamless WordPress integration, PlanIt Event Manager is the ideal event calendar for businesses, organizations, and bloggers. The responsive design ensures your event calendar looks perfect on all devices, while the flexible customization options let you create the perfect event calendar experience for your site.

**Upgrade to Premium** for advanced features like Week View, Year View, Photo View, Map View, Recurring Events, Custom Fields, Event Series, Featured Events, CSV Import, and much more!

Visit [https://landtechwebdesigns.com/planit-event-manager-premium](https://landtechwebdesigns.com/planit-event-manager-premium) to upgrade.

= Free Version Features =

* **Event Calendar Views:** Stunning Day and Month calendar views to display your events beautifully
* **List View:** Display events in a clean, chronological list format for easy browsing
* **Event Management:** Complete event calendar management - create, edit, and delete events with full details
* **Venues & Organizers:** Manage venues and organizers separately for better event calendar organization
* **Event Categories & Tags:** Organize your event calendar with categories and tags for better navigation
* **Hide Past Events:** Automatically hide past events from your event calendar to keep it current
* **Google Maps Integration:** Display venue locations on interactive maps in your event calendar (requires API key)
* **iCal & Google Calendar Export:** Export events from your event calendar to external calendar applications
* **Responsive Event Calendar Design:** Your event calendar looks perfect on all devices - desktop, tablet, and mobile
* **Event Calendar Widget:** Upcoming events widget for sidebars to showcase your event calendar
* **Event Calendar Shortcodes:** Easy calendar and list embedding anywhere on your site
* **Event Calendar Search & Filter:** Powerful search and filtering to help visitors find events quickly
* **Featured Images:** Add beautiful images to events in your event calendar

= Premium Features (Available in Premium Version) =

* Week View - See your week at a glance
* Year View - Annual overview
* Photo View - Visual grid with event images
* Map View - Interactive map with event locations
* Recurring Events - Create repeating events automatically
* Custom Fields - Add unlimited custom data to events
* Event Series - Group related events together
* Featured Events - Highlight important events
* CSV Import - Bulk import events from spreadsheet
* The Events Calendar Import - Migrate from other plugins
* Event Cost/Price - Display event pricing
* Event Website - Link to external event pages
* Event Timezone - Per-event timezone support
* RSS Feed - Events RSS feed
* Advanced Widgets - Featured Events, Event Series, Countdown widgets

== External services ==

This plugin makes use of the following third-party services:

=== Google Maps JavaScript API ===

What it is: Google Maps JavaScript API for displaying event locations.

Data sent: The user's browser sends the site's API key and the visitor's IP address to Google when viewing a map.

* [Terms of Service](https://developers.google.com/maps/terms)
* [Privacy Policy](https://policies.google.com/privacy)

== Try It Live ==

You can preview PlanIt Event Manager instantly with WordPress Playground - no installation required!

**Preview with WordPress Playground:**
1. Visit [WordPress Playground](https://playground.wordpress.net/)
2. Click "Import Blueprint" or use this direct link:
   `https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/brelandr/planit-event-manager/main/blueprint.json`
   
   Or use the short link: [Try PlanIt Event Manager Now →](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/brelandr/planit-event-manager/main/blueprint.json)
3. The playground will automatically install WordPress, activate the plugin, and create sample events, venues, and organizers
4. Explore the calendar views, event management, and all features in your browser!

The blueprint includes:
* 6 sample events (conferences, workshops, concerts, webinars)
* 3 venues with full address details
* 3 organizers with contact information
* Event categories and tags
* A demo page showcasing calendar and list views

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Events > Settings to configure the plugin
4. Start creating events!

== Frequently Asked Questions ==

= Does PlanIt work as a full-featured event calendar? =

Yes! PlanIt Event Manager is a complete event calendar solution for WordPress. It includes calendar views (Day and Month), list views, event management, venues, organizers, and all the essential features you need for a fully functional event calendar.

= What makes this event calendar different from other plugins? =

PlanIt Event Manager is designed specifically to be the most user-friendly event calendar for WordPress. With intuitive event calendar management, beautiful calendar views, and seamless integration, it's the perfect event calendar solution for any website. Plus, it's completely free with an optional premium upgrade for advanced features.

= How do I display my event calendar? =

You can display your event calendar using the shortcode `[twec_calendar]` or visit the events archive page. Customize the view: `[twec_calendar view="month"]` for month view or `[twec_calendar view="day"]` for day view. The event calendar automatically integrates with WordPress themes.

= Can this event calendar handle recurring events? =

Recurring events are available in the Premium version. The free event calendar includes comprehensive event management for single events. Upgrade to Premium for advanced recurring event calendar features.

= Can I hide past events from my event calendar? =

Yes! Go to Events > Settings and enable "Hide Past Events" to automatically hide past events from your event calendar. This keeps your event calendar current and focused on upcoming events.

= Does the event calendar work on mobile devices? =

Absolutely! The event calendar is fully responsive and works perfectly on all devices - desktop computers, tablets, and mobile phones. Your event calendar will look great on any screen size.

= What are the requirements for this event calendar? =

WordPress 5.0 or higher and PHP 7.2 or higher. No external dependencies required for basic event calendar functionality. Google Maps integration requires a free Google Maps API key.

== Changelog ==

= 1.0.0 =
* Initial release of the PlanIt Event Manager event calendar
* Event calendar views: Day and Month calendar views (Week, Year, Photo, Map views available in Premium)
* Event calendar list view with pagination
* Venue and organizer management for your event calendar
* Event categories and tags for event calendar organization
* Google Maps integration for event calendar (requires API key)
* iCal and Google Calendar export from your event calendar
* Event calendar widget support: Upcoming Events widget
* Event calendar shortcodes: [twec_calendar] and [twec_list]
* Hide past events option for your event calendar
* Responsive event calendar design for all devices
* Security: Proper nonce verification and capability checks
* Security: All output properly escaped to prevent XSS
* Performance: Optimized database queries for better event calendar performance
* Performance: Efficient meta_query usage with DATE type comparisons
