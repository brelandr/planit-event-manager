# Troubleshooting Guide

## Event Date/Time Fields Not Showing

If you don't see the "Event Details" meta box with date/time fields when editing an event:

### Solution 1: Check Screen Options
1. When editing an event, look at the top right of the screen
2. Click **"Screen Options"** button
3. Make sure **"Event Details"** checkbox is checked
4. Click away from Screen Options to close it

### Solution 2: Refresh the Page
1. Save your event (even without dates)
2. Refresh the browser page
3. The meta box should appear

### Solution 3: Check Plugin Activation
1. Go to **Plugins** in WordPress admin
2. Make sure "The WordPress Event Calendar" is **activated**
3. If not activated, click **Activate**

### Solution 4: Clear Cache
If you're using a caching plugin:
1. Clear all caches
2. Refresh the event edit page

### Solution 5: Check for Conflicts
1. Temporarily deactivate other plugins
2. Check if the meta box appears
3. If it does, reactivate plugins one by one to find the conflict

## Where to Find Event Fields

When editing an event, you should see:

1. **Event Details** meta box (below the content editor) with:
   - All Day Event checkbox
   - Start Date (date picker)
   - Start Time (time picker)
   - End Date (date picker)
   - End Time (time picker)
   - Venue dropdown
   - Organizer dropdown
   - Event Cost
   - Event Website
   - Event Timezone

2. **Recurring Event** meta box (in sidebar) with:
   - Recurring event options

3. **Featured Event** meta box (in sidebar) with:
   - Feature this event checkbox

4. **Custom Fields** meta box (if configured) with:
   - Your custom fields

## Still Not Working?

If the meta boxes still don't appear:

1. Check browser console for JavaScript errors (F12)
2. Check WordPress debug log for PHP errors
3. Verify the plugin files are uploaded correctly
4. Try deactivating and reactivating the plugin

