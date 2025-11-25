# Changelog

All notable changes to Voxel Toolkit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.4] - 2025-11-23

### Added
- **Dynamic Tag Modifier - .sold()**: Track total quantity sold for products
  - **Usage**: `@post(id).sold()` returns total quantity sold from orders
  - Queries `vx_order_items` table for product field data
  - Parses order details JSON to extract quantity from each order
  - Matches products by post title against product label
  - Returns total quantity as a number (e.g., "42", "156")
  - Always enabled, no configuration required
  - Documented in Voxel Toolkit > Dynamic Tags admin page
- **Dynamic Tag Modifier - .summary()**: Generate email-friendly order summary tables
  - **Usage**: `@order(id).summary()` returns HTML table of all order items
  - Queries order items from `wp_vx_order_items` table by order ID
  - Parses JSON details field for product information
  - **Regular Products**: Displays product name, quantity, unit price, and total
  - **Booking Products**: Includes date ranges, nights/days calculation, and addons
  - **Addon Support**: Formats numeric addons and custom-multiselect options with quantities
  - **Currency Formatting**: Multi-currency symbol support (USD, EUR, GBP, JPY, AUD, CAD)
  - **Email-Optimized**: Inline CSS styling for email client compatibility
  - **Grand Total**: Calculates and displays total across all order items
  - Use in order confirmation emails, receipts, and notifications
  - Always enabled, no configuration required
  - Documented in Voxel Toolkit > Dynamic Tags admin page under "Order Modifiers"
- **Dynamic Tag Modifier - .generate_qr_code()**: Generate QR codes from URLs with optional logo overlay
  - **Important**: Must be used in an Elementor HTML widget
  - **Usage**: `@post(permalink).generate_qr_code(logo_url,color,button_text,quality,button_color,filename)`
  - **Example**: `@post(permalink).generate_qr_code(@post(logo.url),#ff0000,Download the QR Code,2000,#ff0000,@post(title)-qr-code)`
  - **Parameters** (all optional):
    - Logo URL: Centered circular logo overlay on QR code
    - QR Color: Hex color for QR code (default: #000000)
    - Button Text: Download button label (default: "Download high quality PNG")
    - Quality: 1500, 2000, or 3000 pixels (default: 2000)
    - Button Color: Hex color for download button (default: #222222)
    - Filename: Downloaded file name (default: "qr-code")
  - **Features**:
    - Uses qrserver.com API for QR generation
    - Logo appears centered with white background circle
    - Download button generates high-res PNG with transparent background
    - Canvas-based client-side image processing for logo overlay
    - Sanitized filename with special character removal
  - Always enabled, no configuration required
  - Documented in Voxel Toolkit > Dynamic Tags admin page under "Modifiers"
- **Review Collection Widget**: Text alignment controls for enhanced design flexibility
  - **Post Title Alignment**: Responsive left/center/right alignment control
  - **Rating Alignment**: Responsive left/center/right alignment for star ratings
  - **Author Section Alignment**: Combined avatar and username section with responsive left/center/right alignment
  - **Review Content Alignment**: Responsive left/center/right/justify alignment options
  - **Date Alignment**: Responsive left/center/right alignment control
  - All alignment controls support desktop, tablet, and mobile breakpoints
- **Breadcrumbs Widget**: Hierarchical navigation breadcrumbs with full customization and SEO optimization
  - **Content Type Support**: Posts, pages, custom post types, taxonomies, archives, search, and 404 pages
  - **Automatic Hierarchy**: Detects and displays parent post/page chains
  - **Post Type Archives**: Optional archive link before single posts
  - **Separator Customization**: Choose from >, /, →, |, ·, or custom text
  - **Visibility Controls**: Toggle home link, current page, max depth (1-10 levels)
  - **Prefix/Suffix Text**: Optional text before/after breadcrumb trail
  - **SEO Features**:
    - JSON-LD schema markup (BreadcrumbList structured data)
    - Optional nofollow attribute for all links
    - Semantic HTML with `<nav>` and `<ol>` elements
    - ARIA attributes for accessibility
  - **Styling Controls**:
    - Link styling: color, hover color, typography, text decoration
    - Current page: independent color and typography
    - Separator: color, size, spacing, opacity
    - Alignment: horizontal and vertical responsive controls
    - Item gap spacing
    - Container: background, padding, margin, border, shadow
  - **Responsive Design**: All major controls support desktop, tablet, and mobile breakpoints
- **Visitor Location**: Display visitor's location using IP geolocation or browser GPS with dynamic tags
  - **Detection Modes**:
    - IP Geolocation: Automatic detection using IP address (no user interaction)
    - Browser Geolocation: GPS-level accuracy using browser API (requires permission)
  - **Multi-Service IP Detection**: Queries 3 free services (geojs.io, ipapi.co, ip-api.com) in parallel
  - **Consensus Algorithm**: Picks best result based on agreement and data completeness
  - **Browser Mode Features**:
    - Uses device GPS, WiFi, and cell towers for meter-level accuracy
    - Reverse geocodes coordinates using Nominatim (OpenStreetMap) API
    - Cookie-based storage (1 day expiration)
    - Real-time tag updates without page reload
    - Automatic fallback to IP geolocation if permission denied
  - **Dynamic Tags**:
    - `@site(visitor.location)` - Full location (City, State for US / City, Country for international)
    - `@site(visitor.city)` - City name only
    - `@site(visitor.state)` - State/region name only
    - `@site(visitor.country)` - Country name only
  - **Smart Formatting**: Automatically formats as "City, State" for US, "City, Country" for international
  - **Configurable Caching**: Cache duration setting (default: 1 hour) to reduce API calls
  - **Settings**: Detection mode selector and cache duration control in function settings
  - **Privacy-Focused**: Uses ephemeral cookies only, no permanent storage
- **Article Helpful Dynamic Tags**: Display article voting statistics using dynamic tags
  - **Dynamic Tags**: 4 new tags for use anywhere in Elementor
    - `@post(article_helpful_yes_count)` - Number of "Yes" votes
    - `@post(article_helpful_no_count)` - Number of "No" votes
    - `@post(article_helpful_total_votes)` - Total votes (yes + no)
    - `@post(article_helpful_percentage)` - Percentage of yes votes (0-100)
  - **Data Source**: Reads from post meta `_article_helpful_yes` and `_article_helpful_no`
  - **Default Behavior**: Returns 0 if no votes recorded
  - **Conditional Display**: Documentation appears in admin Dynamic Tags page when Article Helpful widget is enabled
- **Tag Usage Page**: New admin page to detect and display all dynamic tag usage across the site
  - **Location**: Voxel Toolkit > Tag Usage in admin menu
  - **Detection**: Scans Elementor data in all pages, posts, and templates
  - **Pattern Matching**: Detects @post(), @user(), @site(), @author(), @current_user() tags
  - **Modifier Support**: Captures tags with modifiers (e.g., @post(field).modifier())
  - **Card Layout**: Modern card-based grid display matching Functions/Widgets page styling
  - **Live Search**: Real-time filtering of tags as you type
  - **Location Display**: Shows first 3 pages/posts per tag with "Show more" button
  - **Copy to Clipboard**: One-click copy of any tag with visual confirmation
  - **Usage Count**: Badge showing how many locations each tag appears in
  - **Direct Links**: Click any location to edit that page/post in new tab
  - **Post Type Icons**: Visual indicators for posts, pages, and templates
  - **Sorted Results**: Tags displayed by usage count (most used first)
  - **No Shadow/Animation**: Clean, flat card design with subtle blue top bar on hover
- **SMS Notifications**: Send SMS notifications when Voxel app events occur
  - **SMS Providers**: Support for Twilio, Vonage, and MessageBird
  - **Event Integration**: Toggle SMS on/off per event directly in Voxel's App Events page
  - **Dynamic Messages**: Use Voxel's dynamic tags in SMS message templates
  - **Destinations**: Send to user, admin, or custom destinations per event
  - **Test SMS**: Send test messages to verify configuration
  - **Phone Field Selection**: Choose which profile field contains phone numbers
  - **Country Code Support**: Default country code setting for phone normalization
  - **Admin Notifications Integration**: Automatically sends SMS to users configured in Admin Notifications
- **International Phone Input**: Enhanced phone fields with country code selection
  - **Flag Dropdown**: Country selector with flag icons on all phone fields
  - **Separate Storage**: Country code stored separately from phone number
  - **Clean Display**: Dynamic tags show phone numbers without country code prefix
  - **E.164 Format**: SMS sends with proper international format (+1234567890)
  - **Auto-Migration**: Existing phone numbers automatically assigned default country code on activation
  - **Bundled with SMS**: Enabled automatically when SMS Notifications is enabled
- **Active Filters Widget**: Display active search filters as clickable tags
  - **URL Parameter Parsing**: Reads filters from URL (range, terms, keywords, sort, etc.)
  - **Human-Readable Labels**: Formats values nicely (e.g., "Price: $0 - $300", "Category: Apartments, Houses")
  - **Remove Filters**: Click any tag to remove that filter and refresh results
  - **Clear All Button**: Remove all filters at once with configurable position (before/after)
  - **Dynamic Updates**: Monitors URL changes for Voxel AJAX filtering without page reload
  - **Layout Options**: Horizontal (wrap) or vertical (stacked) display
  - **Heading**: Optional heading text with alignment controls
  - **Preview Mode**: Show placeholder filters in editor for styling
  - **Hide Options**: Configure which filter types to hide (type, sort, custom params)
  - **Styling Controls**:
    - Filter tags: background, text color, typography, padding, border radius, border, shadow
    - Remove icon: color, size, spacing, multiple icon styles
    - Clear All button: color, background, typography, padding, border radius
    - Heading: color, typography, spacing, alignment
    - Layout: gap between tags, widget alignment (left/center/right)
    - Container: background, padding, margin, border, border radius

### Fixed
- **Poll Display Widget**: Updated widget icon from non-existent `eicon-poll` to standard Elementor `eicon-checkbox` icon for proper display in widget panel
- **Admin Notifications**: Fixed fatal error during WP-CLI operations (cPanel staging/live pushes, database operations)
  - Added class existence check for `\Voxel\Controllers\Base_Controller` before class definition
  - Prevents "Class 'VoxelControllersBase_Controller' not found" error during cPanel operations
  - File now returns early if Voxel classes aren't loaded yet (WP-CLI context)
  - Maintains full functionality when Voxel is properly loaded
- **Article Helpful Widget**: Fixed message customization bugs
  - Added customizable "Vote Updated Message" control for when users change their vote
  - Added customizable "Already Voted Message" control for duplicate votes
  - Fixed custom success message not displaying (was showing hardcoded "Thank you for your feedback!")
  - All messages now use widget settings instead of hardcoded AJAX responses
  - All messages are fully translatable and customizable per widget instance

## [Unreleased]

### Added
- **Campaign Progress Widget Enhancements**: Granular display controls and dynamic tags
  - **Display Toggle Controls**: 5 new switcher controls for fine-grained visibility control
    - Display All Data: Master toggle that shows all elements
    - Display Donated vs Goal: Controls progress summary section
    - Display Progress Bar: Controls visual progress bar
    - Display Number of Donations: Controls donation count display
    - Display Donor List: Controls recent donor list (renamed from "Show Donor List")
  - **Conditional UI**: Individual toggles automatically hide when "Display All Data" is enabled
  - **Dynamic Tags**: 3 new campaign data tags for use anywhere in Elementor
    - `@post(campaign_amount_donated)` - Total amount raised for the campaign
    - `@post(campaign_number_of_donors)` - Count of unique donors/donations
    - `@post(campaign_percentage_donated)` - Percentage of goal reached (whole number, 0-100)
  - **Auto Goal Tracking**: Widget saves goal amount to post meta for dynamic tag access
  - **Backward Compatible**: All toggles default to 'yes' to maintain existing behavior
- **Poll Field (VT)**: Custom post field type for creating interactive polls with voting functionality
  - **Field Creation**: Add polls to any Voxel post type via custom field type
  - **Admin-Defined Options**: Create predefined poll options in field configuration
  - **User-Submitted Options**: Optionally allow users to add their own poll choices
  - **Voting System**: Single or multiple choice voting with user tracking
  - **Auto-Enable Widget**: Poll Display widget automatically enables when field is enabled
  - **Hidden Widget**: Poll widget is functional but hidden from admin widgets page
  - **Poll Display Widget**: Elementor widget for displaying polls with comprehensive styling
    - Facebook-inspired clean design with progress bars as backgrounds
    - Login-required voting and option submission
    - Username badges for user-submitted options
    - Real-time vote percentages and counts
    - **Styling Controls**:
      - Text customization (vote singular/plural, button text, placeholders)
      - Radio/Checkbox appearance (size, colors, borders, checkmark color)
      - Option name typography (separate controls for voted/not voted states)
      - Option box styling (borders, padding, backgrounds for voted/not voted)
      - Progress bar colors and border radius
      - Vote count and percentage typography
      - Add option input and button styling
      - All border radius controls use 4-input dimensions
    - **Hide if Empty**: Optional setting to hide widget when no poll options exist
    - SVG checkmark icon integration
  - **JSON Data Storage**: Voxel-compatible JSON encoding for poll data
  - **AJAX Handlers**: Real-time voting and option addition without page refresh
  - **Vue.js Integration**: Create-post form component for poll configuration

### Improved
- **Show Field Description**: Enhanced support for all field types
  - Fixed description display for switcher fields (now appears below toggle and label)
  - Fixed description display for location fields
  - Fixed description display for taxonomy fields
  - Added MutationObserver to handle repeater field descriptions dynamically
  - Descriptions now work for subfields inside repeater rows
  - Automatically processes descriptions when new repeater rows are added
  - Removed developer credit from settings page

### Added
- **Widget CSS Class & ID**: Inject custom CSS classes and IDs to individual items in Voxel widgets
  - **Supported Widgets**: Navbar (VX), User Bar (VX), Advanced List (VX)
  - **Repeater Item Controls**: Each item in these widgets gets CSS Class and ID fields
  - **JavaScript Injection**: Automatically applies classes/IDs to rendered items via DOM manipulation
  - **Multiple Classes Support**: Add multiple space-separated classes to any item
  - **Unique IDs**: Set unique identifiers for advanced CSS targeting or JavaScript hooks
  - **Always Enabled**: Feature is always active, no need to enable from settings
  - **Use Cases**:
    - Custom styling for specific nav items
    - Individual user bar component targeting
    - Action-specific styling in advanced lists
    - Third-party integrations via ID hooks
- **Site Options**: Create global site options accessible via dynamic tags
  - Configure custom fields from Settings page (text, textarea, number, url, image)
  - Maximum 30 fields for optimal performance
  - Values stored in individual autoloaded WordPress options for efficiency
  - Access via `@site(options.field_name)` dynamic tags
  - Image fields return attachment ID (use `.url` modifier for image source)
  - New "Site Options" submenu under Voxel Toolkit menu (appears when function is enabled)
  - Field configuration UI with add/delete functionality
  - Perfect for site-wide settings like contact info, social links, branding elements
  - Fully integrated with Voxel's dynamic tags system
  - Media library integration for image fields
- **Dynamic Tags**: New `.tally()` modifier for counting published posts in a post type
  - Usage: `@site(post_types.member.singular).tally()` - Returns count of published posts
  - Works with any post type property (singular, plural, icon, etc.)
  - Perfect for dynamic labels like "500 Members", "1,234 Events"
  - Uses efficient WordPress `wp_count_posts()` function
  - Automatically counts only published posts

### Improved
- **Duplicate Title Checker**: Revamped validation display for better efficiency and consistency
  - Now uses Voxel's native error slot mechanism for validation messages
  - Removed validation icons for cleaner UI
  - Error messages display in red, success messages in green
  - Matches Voxel's native field validation styling
  - More efficient DOM manipulation and reduced visual clutter

### Fixed
- **Light Mode**: Fixed white backgrounds appearing on Voxel admin pages when Light Mode is disabled
  - Field headers now display correct dark background (`#40464a`) when Light Mode is disabled
  - White backgrounds (`#e9e9e9`) only appear when Light Mode is enabled
  - Removed inline styles that were unconditionally applying light backgrounds
  - Improved conditional rendering based on Light Mode function status

## [1.5.2]

### Fixed
- **Onboarding Widget**: Fixed tour reset button issues
  - Fixed version display updating wrong widget when multiple widgets on page
  - Fixed version numbers skipping by using panel-scoped selectors
  - Added auto-save functionality after reset button click
  - Tour version now persists immediately without requiring manual Update click

## [1.5.1]

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
  - Added 6-hour transient cache for license status in admin notices
  - Cache automatically clears when license is activated/deactivated
  - Fixed license validation to properly use transient caching (12-hour cache)
  - Reduced unnecessary HTTP requests to license server on every admin page load
  - Cache now only bypasses on update-core.php page or when viewing plugin info
  - Eliminates 770ms+ blocking HTTP request on most admin page loads
- **Removed Debug Logging**: Cleaned up codebase for production
  - Removed all console.log statements from JavaScript files (40+ instances)
  - Removed all debug error_log statements from PHP files (50+ instances)
  - Kept only critical error logging (console.error, console.warn)
  - Reduced browser console noise and server log file sizes

### Removed
- **Password Visibility Toggle**: Removed custom password visibility toggle feature as Voxel now includes native password visibility functionality