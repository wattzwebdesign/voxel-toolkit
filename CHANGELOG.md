# Changelog

All notable changes to Voxel Toolkit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Onboarding Widget**: Interactive step-by-step tours for first-time users using intro.js
  - Auto-start with configurable delay and session-based tracking
  - Manual start button with customizable text and styling
  - CSS selector targeting for tour steps with tooltip positioning
  - Tour version system for one-click reset for all users
  - Preview mode to prevent auto-start while editing in Elementor
  - Comprehensive styling controls for tooltips, navigation buttons, skip button, progress bar, and bullets
  - Elementor editor detection to prevent interruptions while editing
  - Skip confirmation password fields automatically
- **Show Field Description**: Elementor editor preview support and styling controls for Create Post and Login/Register widgets
  - **Elementor Editor Preview**: Field descriptions now display in Elementor editor preview iframe
    - Automatically converts tooltip descriptions to visible subtitles in editor
    - Seamless preview experience matching frontend behavior
    - Works in both Create Post forms and Login/Register forms
  - **Elementor Widget Styling**: Per-page styling controls integrated into multiple Voxel widgets
    - **Create Post Widget**: New "Field Description Style (VT)" section in Style tab → Fields General section
    - **Login/Register Widget**: New "Field Description Style (VT)" section in Field style tab → Form: Input & Textarea section
    - Color control with live preview
    - Full typography group control (font family, size, weight, line height, letter spacing, etc.)
    - Responsive margin top control (supports px, em units)
    - Responsive margin bottom control (supports px, em units)
    - Styles apply instantly in both Elementor editor and frontend
- **Custom Search Filters**: Two new filter types for Voxel search forms
  - **Membership Plan Filter**: Filter posts by author's membership plan
    - Retrieves active plans from Voxel Paid Memberships module
    - Includes "Guest" option for users without membership
    - Supports multiple plan selection
    - Display modes: Popup or Buttons
    - Search functionality when 5+ plans available
    - Configurable default values in Elementor
  - **User Role Filter**: Filter posts by author's WordPress role
    - Dynamically retrieves all WordPress roles
    - Supports multiple role selection (Administrator, Editor, Subscriber, Customer, etc.)
    - Display modes: Popup or Buttons
    - Search functionality when 5+ roles available
    - Configurable default values in Elementor
- **Timeline Photos Widget**: Added "Photo Limit" and "Photo Offset" controls
  - Photo Limit: Limit the maximum number of photos displayed
  - Photo Offset: Skip photos from the beginning of the gallery
- **Dynamic Tags**: New properties and methods for posts and users
  - `@post(reading_time)`: Estimated reading time (e.g., "5 min" or "1 hr 30 min")
  - `@post(word_count)`: Total word count in post content
  - `@user(membership_expiration)` and `@author(membership_expiration)`: Membership expiration date
  - `@user().profile_completion()` and `@author().profile_completion()`: Calculate profile completion percentage based on specified fields
    - Accepts comma-separated field keys or dynamic tags as parameters
    - Example: `@user().profile_completion(@user(profile.content)\,@user(profile.title))`
  - Admin documentation page with usage examples and syntax guide
- **Modifiers**: New data manipulation modifiers
  - `.file_size()`: Get formatted file size from file ID (e.g., `@post(upload-media.id).file_size()`)
  - `.file_extension()`: Get file extension from file ID (e.g., `@post(upload-media.id).file_extension()`)
  - `.address_part()`: Extract specific components from address fields (street number, street name, city, state, postal code, country)
    - Uses Google Geocoding API
    - Works with international addresses
    - Usage: `@post(location.address).address_part(city)`
- **Campaign Progress Widget**: GoFundMe-style donation/crowdfunding progress tracker
  - Display campaign goal with visual progress bar
  - Show total raised, remaining amount, and percentage complete
  - Recent donor list with avatars, names, dates, and amounts
  - Integrates with Voxel orders system
  - Customizable text labels for all elements
  - Full styling controls: progress bar colors, donor list styling, typography
- **Sort by Views**: View Count sorting option
  - Sort posts by view counts in ascending or descending order
  - Support for multiple time periods: all time, 30 days, 7 days, 24 hours
  - Queries view count data from post meta using JSON extraction
  - "Most viewed" preset button for one-click access
  - Also available through Custom order option for advanced configuration

### Changed
- **Admin Notifications**: Removed debug logging (32 error_log statements) that was filling up the debug log
- **Widgets page**: Widgets now display in alphabetical order by name
- **Configure button**: Styling on Functions page now matches Usage badge styling
- **Required PHP version**: Adjusted to 8.1 for broader compatibility

### Performance Improvements
- **License Validation Caching**: Significantly improved admin page load times
  - Fixed license validation to properly use transient caching (12-hour cache)
  - Reduced unnecessary HTTP requests to license server on every admin page load
  - Cache now only bypasses on update-core.php page or when viewing plugin info
  - Eliminates 770ms blocking HTTP request on most admin page loads
- **Removed Debug Logging**: Cleaned up codebase for production
  - Removed all console.log statements from JavaScript files (40+ instances)
  - Removed all debug error_log statements from PHP files (50+ instances)
  - Kept only critical error logging (console.error, console.warn)
  - Reduced browser console noise and server log file sizes

### Removed
- **Password Visibility Toggle**: Removed custom password visibility toggle feature as Voxel now includes native password visibility functionality