# Changelog

## 1.6.1.4

### Enhanced TinyMCE Editor

- **Upload Capability for All Users** - Dynamically grants `upload_files` capability to all logged-in users when Enhanced Editor is active; fixes media uploads silently failing for roles like Voxel's "visitor" (mapped to subscriber) that lack this capability by default

### Saved Search Widget

- **Action Button Hover & Active States** - Added Normal/Hover/Active tab controls for action button background, icon color, and border color styling

### AI Settings

- **Updated AI Model Lists** - Replaced outdated OpenAI models with GPT-5 Mini, GPT-5.2, GPT-5 Nano, and o4-mini; replaced outdated Anthropic models with Claude Haiku 4.5, Sonnet 4.5, Sonnet 4, Opus 4, Opus 4.5, and Opus 4.6

### Weather Widget

- **Kelvin Units Fix** - Fixed "Error loading weather data" when Kelvin units selected by mapping to the correct OpenWeatherMap API parameter

### Image Optimization

- **WebP Upload Inflation Fix** - Fixed client-side optimization inflating WebP files by re-encoding through canvas; same-format files that don't need resize or watermark now skip the canvas pipeline entirely, preserving original file size
- **Size Comparison Fallback** - Added safety net that keeps the original file when canvas re-encoding produces a larger result (e.g., resize/watermark applied but output is still bigger)
- **Keep Original Name Fix** - Fixed "Keep original name" filename format still appending counter and session suffix (e.g., `-01-djsv`); now preserves the original filename with only the extension changed when format conversion occurs

## 1.6.1.3

### Table of Contents Widget

- **Repeater Field Completion** - Fixed repeater fields not showing green completion indicator when items are added
- **Conditional Field Visibility** - Fixed conditional fields still appearing in TOC when their conditions aren't met; TOC now syncs with form visibility

### AI Post Summary

- **Increased Max Token Limit** - Raised the maximum token limit from 1,000 to 5,000 for longer AI-generated summaries

### Show Field Description

- **Repeater Child Description Fix** - Fixed child field descriptions (e.g., image field) incorrectly appearing on parent repeater fields that have no description

### Dynamic Tags

- **Address Part City Fix** - Fixed `address_part(city)` returning state/county instead of city for Polish and other addresses by adding fallbacks for `sublocality`, `sublocality_level_1`, and `administrative_area_level_3` (municipality/gmina) before falling back to `administrative_area_level_2` (county)

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
- **Liechtenstein Missing** - Added Liechtenstein to backend country selection list (was only available on frontend)

### Admin Notifications

- **Individual Users Fix** - Fixed individual users not receiving email notifications due to type mismatch

### Online Status

- **Display Location Settings Fix** - Fixed "Display Locations" checkboxes not saving when unchecked; messenger/inbox/dashboard toggles now work correctly
- **Notification Avatar Fix** - Fixed avatars becoming square in notification popup when Online Status is enabled

### App Events

- **Admin Page Crash Fix** - Fixed PHP Fatal error on Voxel App Events admin page caused by `dynamic_tags()` methods returning arrays instead of proper data group instances

### Active Filters Widget

- **AJAX Label Fix** - Fixed filter labels showing field key (e.g., "terms") instead of field label (e.g., "Category") during AJAX search; labels now read from Voxel's Vue search form at runtime

### Saved Search

- **Improved Notification Reliability** - Fixed timing issue where notifications could fail when admins publish posts from wp-admin; now uses deferred processing at request shutdown to ensure all post data is saved before matching
- **Debug Logging** - Added comprehensive debug logging (enable with `define('VT_SAVED_SEARCH_DEBUG', true)` in wp-config.php) to trace notification failures

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
