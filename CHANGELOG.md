# Changelog

## 1.6.1.2

### Pending Suggestions Widget

- **Removed Built-in Visibility Conditions** - Removed logged-in, admin, and post author checks; use Voxel's Elementor conditions instead for flexible visibility control
- **Added Accepted Status Filter** - Status dropdown now includes All, Pending, Queued, and Accepted options
- **Show Accepted in All View** - Accepted suggestions now display when "All" filter is selected
- **Accepted Status Badge** - Added green status badge for accepted suggestions

### Translations

- **Complete POT File Generation** - New `generate-pot.php` script scans all PHP files and extracts 3,775+ translatable strings (previously only ~60 strings were in the POT file)

### Dynamic Tags

- **Address Part Localization** - The `address_part` method now returns address components (city, country, etc.) in the site's current language by re-geocoding with Google/Mapbox API; supports WPML, Polylang, TranslatePress, and WordPress locale with 24-hour caching

### External Link Warning

- **Modal Border Radius Setting** - New setting to customize modal border radius (was hardcoded to 12px)
- **Overlay Background Setting** - New setting to customize the overlay/backdrop color
- **CSS Variables for All Styling** - All modal styling now uses CSS variables; no more hardcoded values

### AI Bot

- **Localized UI Messages** - "No results", "error", "rate limit", and "login required" messages now respect the AI Response Language setting (supports 15 languages including Russian, Ukrainian, Polish, Arabic, Japanese, Chinese, etc.)
- **Improved Taxonomy Search** - Schema now includes `taxonomy_name` for taxonomy/term fields so AI knows the correct WordPress taxonomy to use with `_taxonomy:` filter
- **Better Taxonomy Hints** - Field hints now explicitly tell the AI to use `_taxonomy:taxonomy_name` format with term slugs

### Schedule Posts

- **Native Date Input** - Replaced Pikaday calendar popup with native HTML date input; eliminates z-index/positioning issues with footers and other elements
- **Pending Status Support** - Posts with "pending" status can now be scheduled (previously only worked with "publish" status)
- **Translated Sites Fix** - Fixed scheduling field incorrectly appearing on confirmation page for translated sites

### Load Search

- **Hide When Empty** - Load Search button now automatically hides when user is logged out or has no saved searches

### Phone Field

- **New Caledonia Missing** - Added New Caledonia to backend country selection list (was only available on frontend)

## 1.6.1.1

### Suggest Edits Improvements

- **Hierarchical Taxonomy Display** - Taxonomy fields in the suggest edit modal now display with proper parent/child hierarchy using indentation
- **Checkbox List UI for Multi-select** - Replaced native `<select multiple>` with a scrollable checkbox list featuring circle/checkmark indicators for clearer selection state
- **Pre-fill with Current Values Toggle** - New Elementor control to pre-populate multi-select fields with current values, allowing users to add or remove items without re-selecting everything
- **Admin Display Fix** - Taxonomy and select field suggestions now show labels instead of IDs in the admin suggestions table
- **Template Editor Fix** - Fixed "Fields to Show" dropdown showing empty when editing Elementor templates

### Schedule Posts Fixes

- **Pikaday Calendar Fix** - Fixed calendar not displaying by loading Pikaday directly from Voxel theme
- **CSS Loading Fix** - Fixed stylesheet not loading due to hook timing issue
- **Inline Layout** - Date and time inputs now display side-by-side as intended
- **Consistent Styling** - Time input now matches date selector height (44px)

### Image Optimization

- **Watermark Opacity Control** - New setting to adjust watermark transparency (0-100%) for both text and image watermarks
- **Translation Support** - Toast notifications and messages are now translatable via Loco Translate and similar plugins
- **Elementor Media Library Support** - Image optimization now works with Elementor image widgets, Gutenberg blocks, and WordPress Media Library uploads

### Social Proof

- **Translation Support** - Time ago strings (minutes, hours, days, ago) are now translatable for boost events

### Saved Search

- **Alert Width Fix** - Fixed alert popup text overlapping when using translated languages (e.g., Polish)

### Admin Columns

- **Verification Status Column** - New column type showing post verification status with configurable labels, custom icon support, and show/hide toggles for icon and text

### Add Category

- **Elementor Styling Fix** - Fixed form input styling controls not applying in Elementor editor
- **Translation Support** - Added all frontend strings to POT file for translation (Add new, Term name, etc.)

### Messenger Widget

- **Input Text Color Default** - Set default input text color to black for better visibility

### Promotion Create Form

- **Package Icons Fix** - Custom SVG icons from promotion package settings now display correctly in the create form
- **Duration Color Control** - New Elementor color control for styling the duration text independently from other card elements

### Bulk Resize

- **WebP Format Conversion** - Bulk resize now converts images to WebP based on the optimization mode setting, even for images that don't need resizing
- **Warning for Larger Files** - Shows warning icon with tooltip for images that increased in size after conversion
- **Backup Original Files** - New option to keep original images in a backup folder (wp-content/uploads/vt-originals/) instead of deleting them during WebP conversion
- **Delete Originals Button** - Backup Management card shows file count and size with a delete button requiring "confirm" input for safety

### Translation

- **Complete POT File** - Regenerated translation template with 3,749 translatable strings for full Loco Translate support
