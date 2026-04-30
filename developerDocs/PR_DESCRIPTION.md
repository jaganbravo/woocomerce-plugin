# Add Stop Button Control and Separate API Settings Page

## Summary
This PR introduces two key improvements to the Dataviz AI WooCommerce plugin:
1. **Separate API Settings Page**: Moves API configuration to its own dedicated settings page
2. **Enhanced Stop Button**: Improves the stop button functionality for canceling ongoing LLM responses

## Changes

### 1. Separate API Settings Page
- **New Submenu**: Added "API Settings" as a submenu under the main "Dataviz AI" menu
- **Dedicated Settings Page**: Created `render_settings_page()` method that displays only API configuration
- **Improved UX**: Main chat page now focuses solely on AI interactions, while settings are accessible via a dedicated page
- **Status Indicators**: Settings page shows clear status messages indicating whether API key is configured

**Files Modified:**
- `dataviz-ai-woocommerce-plugin/includes/class-dataviz-ai-admin.php`
  - Added `$settings_slug` property
  - Added `add_submenu_page()` call in `register_menu_page()`
  - Created new `render_settings_page()` method
  - Updated `enqueue_assets()` to handle both pages
  - Removed API settings form from main admin page
  - Updated warning message on main page to link to settings page

### 2. Enhanced Stop Button Functionality
- **Consistent Visibility**: Fixed stop button show/hide logic throughout the codebase
- **Proper Stream Cancellation**: Ensures fetch requests and stream readers are properly aborted when stopped
- **Better User Feedback**: Shows "(stopped)" message when user cancels a response
- **Improved CSS**: Enhanced styling with `!important` flag to ensure proper display

**Files Modified:**
- `dataviz-ai-woocommerce-plugin/admin/js/admin.js`
  - Made stop button visibility logic consistent (using both `.addClass('show')` and `.show()`)
  - Updated all stop button hide/show calls to use consistent pattern
  - Ensured proper cleanup of stream controllers and readers

- `dataviz-ai-woocommerce-plugin/admin/css/admin.css`
  - Added `!important` flag to `.show` class for stop button to ensure visibility
  - Maintained existing styling for stop button (red circular button)

## Technical Details

### API Settings Page
- **Route**: `admin.php?page=dataviz-ai-woocommerce-settings`
- **Capability**: `manage_woocommerce`
- **Settings Group**: `dataviz_ai_wc` (unchanged)
- **Fields**: API Base URL and API Key

### Stop Button Behavior
- Appears when a streaming request is active
- Aborts the fetch request using `AbortController`
- Cancels the stream reader
- Shows user feedback message
- Automatically hides when request completes or is stopped

## Testing
- [x] API settings page accessible via submenu
- [x] API settings can be saved and loaded correctly
- [x] Stop button appears when request is in progress
- [x] Stop button successfully cancels ongoing requests
- [x] Stop button hides after cancellation or completion
- [x] Main chat page no longer shows API settings form
- [x] Warning message on main page links to settings page

## Screenshots
(Add screenshots if available)

## Related Issues
(Link to any related issues)

