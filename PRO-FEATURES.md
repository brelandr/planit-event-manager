# Pro Features Documentation

## Import Functionality

### Import from The Events Calendar Plugin

1. Go to **Events > Import** in WordPress admin
2. If The Events Calendar plugin is installed, you'll see an import option
3. Click **"Import Events"** to import all events, venues, and organizers
4. Events that have already been imported will be skipped

**What gets imported:**
- All events with dates, times, and content
- Venues with addresses and coordinates
- Organizers with contact information
- Event categories and tags
- Featured images

### Import from CSV File

1. Go to **Events > Import** in WordPress admin
2. Download the CSV template to see the required format
3. Fill in your events data in the CSV file
4. Upload the CSV file and click **"Import from CSV"**

**CSV Format:**
- **Required columns:** `title`, `start_date`
- **Optional columns:** `description`, `excerpt`, `start_time`, `end_date`, `end_time`, `all_day`, `venue`, `organizer`, `categories`, `tags`, `status`
- **Venue columns:** `venue_address`, `venue_city`, `venue_state`, `venue_zip`, `venue_country`, `venue_phone`, `venue_website`, `venue_latitude`, `venue_longitude`
- **Organizer columns:** `organizer_phone`, `organizer_email`, `organizer_website`

## Recurring Events

Create events that repeat automatically:

1. When editing an event, check **"This is a recurring event"** in the sidebar
2. Choose repeat frequency: Daily, Weekly, Monthly, or Yearly
3. Set the interval (e.g., every 2 weeks)
4. Set end date or number of occurrences

Recurring events will automatically appear in calendar views on their scheduled dates.

## Custom Fields

Add custom fields to events:

1. Go to **Events > Settings > Custom Fields** (coming soon)
2. Define custom fields with types: text, textarea, number, url, email, select, checkbox
3. Custom fields will appear in the event editor
4. Access custom field values in templates using: `TWEC_Custom_Fields::get( $event_id, 'field_name' )`

## Event Series

Group related events into series:

1. Go to **Events > Series** to create event series
2. Assign events to series when editing
3. Use the **Event Series Widget** to display events from a specific series
4. Filter events by series on archive pages

## Featured Events

Highlight important events:

1. When editing an event, check **"Feature this event"** in the sidebar
2. Featured events are highlighted in calendar and list views
3. Use the **Featured Events Widget** to display only featured events
4. Featured events appear with special styling

## Additional Calendar Views

### Photo View
- Displays events in a grid with featured images
- Great for visual browsing
- Shows event thumbnails, titles, dates, and excerpts
- Featured events are highlighted

### Map View
- Shows all events with venues on an interactive map
- Click markers to see event details
- Sidebar lists all events with venue information
- Requires Google Maps API key in settings

## Advanced Widgets

### Featured Events Widget
- Display only featured events
- Customize number of events to show
- Perfect for highlighting important events

### Event Series Widget
- Display events from a specific series
- Select which series to display
- Shows upcoming events from that series

### Event Countdown Widget
- Display a countdown timer to a specific event
- Shows days, hours, minutes, and seconds
- Automatically updates in real-time
- Perfect for promoting upcoming events

## Usage Examples

### Shortcodes

**Calendar with Photo View:**
```
[twec_calendar view="photo"]
```

**Calendar with Map View:**
```
[twec_calendar view="map"]
```

**List with Featured Events Only:**
```
[twec_list featured="yes"]
```

### Widgets

1. Go to **Appearance > Widgets**
2. Add any of the Pro widgets to your sidebar
3. Configure settings for each widget

### Featured Events in Templates

Check if an event is featured:
```php
$is_featured = get_post_meta( $event_id, '_twec_is_featured', true );
```

Get custom field value:
```php
$custom_value = TWEC_Custom_Fields::get( $event_id, 'field_name' );
```

## All Pro Features Summary

✅ **Import from The Events Calendar** - Migrate events from other plugins  
✅ **CSV Import** - Bulk import events from spreadsheet  
✅ **Recurring Events** - Create repeating events automatically  
✅ **Custom Fields** - Add unlimited custom data to events  
✅ **Event Series** - Group related events together  
✅ **Featured Events** - Highlight important events  
✅ **Photo View** - Visual grid display of events  
✅ **Map View** - Interactive map with event locations  
✅ **Featured Events Widget** - Display featured events  
✅ **Event Series Widget** - Display series events  
✅ **Countdown Widget** - Real-time countdown timer  

All features are included and ready to use!

