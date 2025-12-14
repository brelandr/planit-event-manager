# Quick Start Guide

## Fix 404 Errors on Event Pages

If clicking on events shows a 404 error, you need to flush the permalink structure:

### Option 1: Use Plugin Settings (Easiest)
1. Go to **Events > Settings** in WordPress admin
2. Scroll down to "Fix Permalink Issues" section
3. Click **"Flush Permalinks"** button

### Option 2: WordPress Permalinks
1. Go to **Settings > Permalinks** in WordPress admin
2. Click **"Save Changes"** (you don't need to change anything)
3. This will flush the permalink structure

### Option 3: Deactivate/Reactivate
1. Go to **Plugins** in WordPress admin
2. Deactivate "The WordPress Event Calendar"
3. Reactivate it
4. This will automatically flush rewrite rules

## Display Events

### Calendar View
Use this shortcode anywhere (page, post, widget):
```
[twec_calendar]
```

Or specify a view:
```
[twec_calendar view="month"]
[twec_calendar view="week"]
[twec_calendar view="day"]
[twec_calendar view="year"]
```

### List View (Chronological)
Use this shortcode to show events in a chronological list:
```
[twec_list]
```

Options:
```
[twec_list per_page="10" past_events="hide"]
[twec_list per_page="20" past_events="show"]
[twec_list category="music"]
[twec_list tag="free"]
```

### Events Archive Page
Visit your events archive page (usually at `/events/`) to see:
- List view by default
- Toggle to calendar view
- All events chronologically

## Common Issues

**Event pages show 404:**
→ Flush permalinks (see above)

**Calendar not loading:**
→ Make sure JavaScript is enabled
→ Check browser console for errors

**Events not showing:**
→ Check if "Hide Past Events" is enabled in settings
→ Verify events have start/end dates set

