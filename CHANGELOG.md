# Changelog

All notable changes to Voxel Toolkit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.1] - 2026-01-01

### Added
- **Admin Columns - Bulk Edit**: Bulk edit taxonomy/category fields directly from the post list table
  - Select multiple posts and click "Bulk Edit" button below taxonomy columns
  - Dropdown-style selector with search functionality for finding terms
  - Three actions: Add to existing, Replace all, or Remove terms
  - Confirmation dialog before applying changes
  - Progress bar with batch processing (10 posts at a time)
  - Cancel support during processing

## [1.6.0] - 2025-12-16

### Fixed
- **Duplicate Post**: Fixed blank page issue when viewing duplicated Voxel listings on frontend after publishing
- **Admin Columns - Title Display**: Fixed double-escaping causing titles (especially Cyrillic) to display as "..."
- **Admin Columns - Title HTML**: Strip HTML tags from title values before display
- **Admin Columns - Post Relations**: Fixed belongs_to_one/belongs_to_many relations not showing related posts
- **Admin Columns - Author Filter**: Filter now shows all users who have posts, not just those with author role or higher

### Added
- **Suggest Edits - Show Empty Fields**: Option to display fields without values in the suggest edit modal
  - New "Show Fields Without Values" toggle in widget settings
  - Customizable "No Value Label" translation (default: "No value set")
  - Allows users to suggest content for fields that have no current value
- **Temporary Login**: Generate secure one-click login URLs for temporary site access
  - Perfect for giving developers or support staff temporary access without sharing passwords
  - Secure selector/validator token pattern with hashed storage
  - Multi-use tokens with configurable expiration (hours or days)
  - Create tokens for existing users or generate new temporary user accounts
  - Configurable redirect URL per token (dashboard, specific page, etc.)
  - Enable/disable tokens without deleting them
  - Full audit log of all login attempts with IP address, user agent, and timestamp
  - Admin page accessible via Users > Temp Logins (VT)
  - Automatic daily cleanup of expired tokens (30+ days old)
- **Enable SVG Uploads**: Allow SVG file uploads to WordPress media library
  - Supports both .svg and .svgz (compressed) formats
  - Automatic security sanitization on upload
  - Removes scripts, event handlers, and dangerous elements
  - Fixes SVG display in media library with proper thumbnails
- **Recurring Events - Multiple Instances**: Show recurring events multiple times in archives
  - Events with multiple upcoming dates appear as separate cards (one per occurrence)
  - Each card displays only its specific occurrence date
  - Cards sorted by occurrence date (soonest first)
  - Respects "Posts per page" setting in Post Feed widget
  - Results count updates to reflect total occurrences
  - Works with Post Feed widgets using "Search Form" or "Filters" data source
  - Use `@post(recurring-date.upcoming.start)` dynamic tag in card template
- **Field Columns**: Column width picker for post field settings
  - Dropdown appears below CSS Classes field in post type field editor
  - Quick selection of Voxel utility classes (vx-1-1, vx-1-2, vx-1-3, etc.)
  - Auto-detects existing column class and pre-selects in dropdown
  - Replaces existing column class when selecting new width
  - Options: 100%, 75%, 66%, 50%, 33%, 25%, 16%
  - Column width badge in field list shows percentage at a glance
- **AI Bot**: AI-powered search assistant for natural language queries
  - Ask questions like "Find Italian restaurants near me" or "Events this weekend"
  - Configurable suggested queries shown as clickable chips above input
  - Quick filter chips after results (4+ Stars, Has Reviews)
  - Follow-up action buttons on result cards (Directions, Call, View)
  - Full appearance customization with live preview (colors, panel width, font size, border radius)
  - Messenger widget integration option - AI circle opens the assistant panel
  - Supports location-based searches with Google Geocoding
  - Conversation memory for follow-up questions
  - Rate limiting to control API usage
  - Per-post-type card template selection
- **Social Proof (Beta)**: Display toast notifications showing recent Voxel app events
  - Real-time event capture for bookings, orders, signups, reviews, posts, and more
  - Auto-rotation through recent events with configurable timing
  - Polling for new events with instant display
  - Activity Boost mode to generate simulated notifications during slow periods
  - Customizable position (4 corners), colors, animations, and avatar settings
  - Per-event type configuration with message templates using {user}, {post}, {time} placeholders
  - Live preview in settings panel
- **External Link Warning**: Show warning modal when users click external links
  - Customizable warning title and message
  - Domain whitelisting to bypass warnings for trusted sites
  - CSS selector exclusions for specific links (e.g., `.no-warning`)
  - Full color customization for modal, icon, and buttons
  - Live preview in settings panel
  - Option to display destination URL in the modal
- **RSVP System**: Complete event RSVP functionality with two widgets
  - RSVP Form widget for submitting/cancelling RSVPs
  - Attendee List widget for displaying RSVPs with admin actions
  - Guest RSVP support with configurable fields (name, email, comment, custom)
  - Approval workflow with pending/approved/rejected statuses
  - Max attendee limits with automatic closure
  - CSV export for attendee data
  - Full Elementor styling controls
  - **App Events**: RSVP Submitted, Approved, Rejected events per post type
  - **Per-post-type custom fields**: Define different RSVP fields for each post type, dynamic tags show only relevant fields
- **Compare Posts**: New feature to compare 2-4 posts of the same type side-by-side
  - Compare Button widget for single post templates
  - Comparison Table widget for dedicated comparison page
  - Floating comparison bar (bottom or side position)
  - Configurable fields per post type with custom labels and drag reordering
  - Supports all Voxel field types with full detail rendering
  - Work hours and time fields use WordPress time format setting
  - localStorage-based state with cross-tab sync
  - Print button to print comparison table (portrait format, clean layout)
- **Enhanced TinyMCE Editor**: Adds extra features to Voxel's WP Editor Advanced mode
  - Add Media button to upload/insert images, videos, and audio files
  - Text color and background color pickers
  - Character map for special symbols
  - Frontend CSS for image alignment with text wrap
  - Secure: only available to logged-in users, content sanitized via wp_kses_post()
- **Timeline Filters**: Add custom ordering options to Voxel Timeline widgets
  - "Unanswered" filter shows posts with no replies, sorted by newest
  - Works with all Timeline modes: Reviews, Wall, Timeline, Global Feed, User Feed
  - Custom label option for the filter button
  - Designed for future filter additions
- **Enhanced Post Relation**: Customize post display in Post Relation field dropdowns
  - Configure display templates per post type using dynamic tags
  - Example: Show "Title - City" for places, "Name (Membership)" for profiles
  - Supports all dynamic tags and modifiers including toolkit's custom ones
  - Native dynamic tag picker with post-type-specific fields
- **Settings Page Overhaul**: Redesigned settings page with tab-based navigation for better organization
- **Helpful Votes Sorting**: New order-by option for Voxel search to sort posts by Article Helpful widget votes
  - Most Helpful (Yes votes)
  - Most Disputed (No votes)
  - Total Votes (Yes + No combined)
- **Helpful Vote Timestamps**: Dynamic tags for last vote timestamps
  - `@post(article_helpful_latest_yes)` - Date of last yes vote
  - `@post(article_helpful_latest_no)` - Date of last no vote
  - Use with visibility rules to show/hide badges based on vote recency
- **Tools Page**: New Tools submenu with utility tools for site management
  - **Duplicate Post Fields**: Copy fields between Voxel post types
    - Select source and destination post types
    - Choose specific fields via checkbox list
    - Required key suffix prevents naming conflicts
    - Singleton field detection (title, description, location, etc.) with warnings
    - Handles nested repeater fields automatically
- **Initial Modifier**: New dynamic tag modifier that returns first letter with period
  - Usage: `@user(display_name).initial()` → "J."
  - Multibyte safe for international characters
- **Team Members**: Allow post authors to invite collaborators who can edit their posts
  - Team Members post field for managing invites by email
  - Invite system with configurable expiration (default 7 days)
  - Accept/decline flow with email notifications
  - App Events: Team member invited, accepted, declined
  - "User is team member of current post" visibility rule for Elementor
- **Saved Search**: Allow users to save search filters and quickly reload them
  - Save Search button added to Voxel search form widget
  - Saved Search (VT) widget displays user's saved searches in grid layout
  - Load Search dropdown to quickly apply saved filters
  - Auto-apply option remembers and loads last used search on page return
  - App Event notifications when new posts match saved searches (in-app, email, SMS)
  - Users can enable/disable notifications per saved search
  - localStorage persistence for seamless cross-session experience
  - Auto-delete expiration setting (7/14/30/90 days, 6 months, 1 year, or never)
  - Email batching to prevent server overload with high-volume notifications
    - Queue emails to database, process via WordPress cron in configurable batches
    - In-app and SMS notifications still send immediately
    - Configurable batch size (10, 25, 50, 100) and interval (1-30 minutes)
    - Retry logic for failed emails (max 3 attempts)
- **Timeline & Reviews Dynamic Tags**: Access latest and oldest entries from post timelines, walls, and reviews
  - `@post(vt_reviews.latest/oldest.content)` - Review content text
  - `@post(vt_reviews.latest/oldest.author)` - Reviewer display name
  - `@post(vt_reviews.latest/oldest.score)` - Review score (1-5 scale)
  - `@post(vt_reviews.latest/oldest.date)` - Review date (supports date modifiers)
  - `@post(vt_reviews.latest/oldest.link)` - Direct link to review
  - `@post(vt_timeline.latest/oldest.*)` - Same properties for timeline posts (no score)
  - `@post(vt_wall.latest/oldest.*)` - Same properties for wall posts (no score)
  - Results cached per request for performance
  - Returns empty string when no entries exist
- **Route Planner Widget**: Display interactive routes with turn-by-turn directions
  - Data sources: Repeater fields, Post relation fields, or Post fields (individual location fields)
  - Map providers: Google Maps, Mapbox, OpenStreetMap (OSRM)
  - Travel modes: Driving, walking, cycling, transit (user-switchable)
  - Starting point options: First waypoint, user GPS location, or custom address
  - Drag-to-reorder waypoints with automatic route recalculation
  - Route optimization using nearest neighbor algorithm
  - Directions panel with step-by-step instructions and step highlighting
  - Click markers to view stop details with popup
  - Full Elementor styling controls for route line, markers, and panel
  - Export options: Open in Google Maps, Open in Apple Maps, Download GPX
  - Custom icon uploads for travel mode, export, and summary icons
- **Advanced Phone Input**: Enhanced phone fields with international support
  - Country selector dropdown with flag and dial code (+X)
  - Per-field default country setting
  - Per-field country restrictions (only show specific countries)
  - Toggle for country selector dropdown visibility
  - Stores country code separately for E.164 formatting
  - Settings appear in Post Type editor under each phone field
- **Add Category**: Allow users to add new taxonomy terms from frontend Create Post form
  - Per-field toggle in Post Type editor under each taxonomy field
  - Voxel override system for role-based visibility (e.g., only editors can add terms)
  - Optional approval workflow with pending terms hidden until approved
  - App Events integration for admin notifications (in-app and email)
  - User instructions for pending term workflow
- **Message Moderation**: Admin page to view and moderate all direct messages
  - Top-level Messages (VT) menu in WordPress admin
  - View all messages with sender, receiver, content, status, and date
  - Filter by read/unread status, message ID, sender/receiver ID, sender type
  - Full-text search across message content
  - Row actions: mark read/unread, delete
  - Bulk actions: mark as read, mark as unread, delete
  - Links to sender admin profile and receiver frontend profile
  - Requires `edit_others_posts` capability (Administrators, Editors)
- **Messenger Widget - Persistent Admin Chat**: Add a persistent chat circle for a designated admin
  - Toggle to enable persistent admin chat in widget settings
  - Enter admin user ID to create always-visible chat circle
  - Chat circle cannot be closed, only minimized
  - Chat window hides close button (minimize only)
  - Green online indicator on persistent chat circle
  - Only visible to logged-in users (excludes the admin themselves)
- **Messenger Widget - Widget Default Avatar**: Per-widget default avatar upload
  - Override global default avatar at the widget level
  - Fallback chain: Widget avatar → Admin avatar → No avatar
- **Timeline Reply Summary**: AI-generated summaries of timeline post replies (TL;DR)
  - Support for OpenAI (GPT-4o-mini) and Anthropic (Claude 3 Haiku) AI providers
  - Configurable reply count threshold to trigger summaries
  - Customizable AI prompt template with {{replies}} placeholder
  - Summaries cached in database, auto-regenerate when replies change
  - Collapsible UI appears only when replies are expanded
  - Works on reviews, walls, timelines, and user feeds
  - Lazy loading - summary fetched only when user clicks to view
- **AI Settings**: Central configuration for AI providers used by AI-powered features
  - Support for OpenAI and Anthropic (Claude) providers
  - Model selection for each provider (GPT-4o, GPT-4o-mini, Claude 3.5 Haiku, Claude 3.5 Sonnet, etc.)
  - Single API key configuration shared across all AI features
  - Always enabled utility function in Settings under "Configuration" section
- **AI Post Summary**: Auto-generate AI summaries for posts on publish/update
  - Per-post-type configuration
  - Customizable prompt template with {{post_data}} placeholder
  - Access summaries via `@post(ai.summary)` dynamic tag
  - Bulk generate tool for existing posts without summaries
  - Summaries stored in post meta, regenerated on content changes
- **Synonym Search**: Add synonyms to taxonomy terms for enhanced keyword search
  - Synonyms field on all taxonomy term edit pages
  - AI-powered synonym generation with one click
  - Bulk generate synonyms for entire taxonomies with progress tracking
  - Automatic post re-indexing when synonyms are saved
  - Synonyms column in admin taxonomy list tables
  - Toggle "Taxonomy Synonyms" checkbox in Keywords filter settings
  - Skip existing option for bulk generation
  - Configurable number of synonyms to generate (1-20)

### Changed
- **Admin Menu Hide**: Updated with new menu item options
- **Poll Field**: Added translation support for all user-facing strings

### Fixed
- **Table of Contents Widget**: Fixed avatar/profile fields displaying as "voxel:name" instead of actual label (now shows "Profile Picture", "Display Name", etc.)
- **Table of Contents Widget**: Fixed list style switching between numbered/bullets not working
- **Table of Contents Widget**: Field completion indicators now update in real-time as you type (previously only updated on save)
- **Active Filters Widget**: Fixed clicking X to remove filter not refreshing search results
- **Active Filters Widget**: Fixed widget taking up white space when no filters are active


## [1.5.7] - 2025-12-07

### Added
- **Calendar Week Start**: New function that makes Voxel date pickers respect WordPress "Week Starts On" setting instead of always starting on Monday. Works with date fields and booking product calendars.
- **Docs Menu Link**: Added "Docs" submenu item under Voxel Toolkit that opens documentation in a new tab.
- **Auto Reply Dynamic Tag**: Auto Reply field now exposes its value as a dynamic tag `@post(field_key)` for use in templates.
- **Share Count Function**: Track share button clicks with dynamic tags
  - Tracks total shares and per-network shares (Facebook, Twitter, WhatsApp, etc.)
  - Use `@post(share_count)` for total, `@post(share_count.facebook)` for network-specific
  - Supports all Voxel share menu networks plus additional ones from Share Menu function
- **Dynamic Tags Page**: Added Share Count and Auto Reply Field sections to Dynamic Tags documentation page

### Fixed
- **Suggest Edits Widget**: Fixed modal appearing behind other page elements (cards, carousels, etc.)
  - Modal now moves to body element when opened to escape parent stacking contexts
  - Added Border Color control to "Suggest an Edit Button" style section
- **Messenger Settings**: Fixed "The link you followed has expired" error on Cloudways/cached hosting
  - Switched from form POST to AJAX-based saving to bypass server-level caching issues
- **Messenger Widget**: Fixed placeholder text issues when replying as a listing
  - Added translatable "Reply As Text" setting in Elementor (use %s for listing name)
  - Fixed scrollbar appearing when listing name is too long - now truncates with ellipsis
- **Membership Plan Filter**: Fixed filter not working on profiles post type
  - Added JSON validation to prevent database errors when user has no membership meta
  - Fixed reset behavior to properly restore default value when configured in Elementor
- **SMS Notifications**: Fixed "SMS Notifications not fully initialized" error when testing SMS on sites using Voxel child themes
- **Messenger Widget**: Fixed toggle and tooltip inconsistencies
  - Chat circles now reliably show/hide on repeated button clicks
  - Tooltips no longer flicker when hovering between chat circles

## [1.5.6.0.1] - 2025-12-02

### Added
- **Table of Contents Widget - Field Indicators**: Show individual fields under each step with completion status
  - Empty circle indicator when field has no value, filled circle when completed
  - Real-time field detection for all Voxel field types (taxonomy, select, product, description, gallery, etc.)
  - Sync step visibility with Voxel's conditional logic (hidden steps are hidden in TOC)
  - New Elementor style controls: indicator colors, sizes, field text colors, spacing, and typography

### Changed
- **Table of Contents Widget**: Renamed to "Table of Contents (VT)" in Elementor for clarity

### Fixed
- **AI Review Summary**: Fixed OpenAI API key not being saved when entering it in Toolkit Settings
  - API key was being overwritten during settings sanitization
- **Messenger Settings**: Fixed "The link has expired" error when saving Messenger configuration
  - Converted settings page to use custom form handler instead of WordPress Settings API
- **Membership Plan Filter**: Fixed filter not returning results on sites using Voxel's test mode
  - Filter now correctly uses `voxel:test_plan` meta key when Stripe test mode is enabled
  - Also handles multisite compatibility using Voxel's site-specific meta key helpers
- **SMS Notifications**: Fixed 400 error when toggling SMS in App Events page
  - Created separate AJAX handler class that doesn't depend on Voxel Base_Controller
  - SMS toggle now only appears when SMS Notifications is enabled in Toolkit Settings
- **Suggest Edits Widget**: Multiple fixes
  - Fixed taxonomy terms showing IDs instead of labels in pending suggestions
  - Added toggle to hide "Permanently Closed" option in widget settings
  - Button icon now scales with typography size settings
  - Fixed time display to use WordPress timezone

## [1.5.6] - 2025-11-29

### Changed
- **Admin Menu Icon**: Updated dashboard menu icon to custom VT logo
- **Messenger Widget**: Show "Reply as [listing name]" placeholder in chat window for listing conversations

### Added
- **Admin Columns - User Columns**: Configure custom columns for WordPress Users list
  - Select "Users" from the Admin Columns settings dropdown
  - Available fields: User ID, Username, Display Name, Full Name, First Name, Last Name, Nickname, Email, Role, Registered Date, Website, Profile Picture, Language, Post Count
  - Post Count column with configurable post type and status selection (Published, Pending, Draft, Private)
  - Sortable columns: User ID, Username, Display Name, Email, Registered Date, First Name, Last Name, Nickname, Language, Post Count
  - Filterable columns: Role, Language
  - Profile picture with customizable dimensions
  - Registered date with format options (date only, date & time, relative)
  - Edit Columns button on Users list page for quick access
- **Listing Plan Filter**: New search filter for filtering posts by their listing plan
  - Filter posts by their assigned listing plan (from Voxel's paid listings)
  - Includes "No Plan" option for listings without a plan assigned
  - Supports popup and buttons display modes
  - Multi-select support for filtering by multiple plans
- **Feed Position Dynamic Tag**: New `@post(feed_position)` tag for post feeds
  - Shows position number of post in feed (1, 2, 3, etc.)
  - Absolute positioning across pages (page 2 starts at 11 with 10 per page)
  - Works in preview card templates
  - Note: Refresh page after editing preview card in Elementor
- **Breadcrumbs Widget - Taxonomy Terms**: Add taxonomy terms to breadcrumb trail
  - Enable "Include Taxonomy Terms" and enter taxonomy field key
  - Supports parent term hierarchy (e.g., Home > Mechanic > Auto Repair > Listing)
  - Works with any Voxel post type and taxonomy field
- **Auto Reply Post Field**: New field type for automatic message responses
  - Add to any post type (listings, profiles, etc.) for automatic replies when receiving messages
  - Respects Voxel's 15-minute message throttle
  - Leave field empty to disable auto-reply
- **Media Gallery Widget**: New widget extending Voxel's Gallery with video support
  - Supports mixed photo and video files from Files field
  - Video thumbnail capture from configurable time (0-10 seconds, default 1s)
  - Play icon overlay on video items with full styling controls
  - Video lightbox with proper aspect ratio preservation
  - Grid layout controls (columns, gap, row height, aspect ratio)
  - Mobile responsive design

### Changed
- **QR Code Modifier**: Added option to hide download button
  - Leave button text parameter blank to hide the download button
  - Usage without button: `@post(permalink).generate_qr_code(,,,,)`
- **Messenger Widget**: Added position offset controls and fixed mobile alignment
  - New responsive Bottom Offset slider (0-200px) to adjust distance from screen bottom
  - New responsive Horizontal Offset slider (0-200px) to adjust distance from left/right edge
  - Fixed mobile avatar circles to stay aligned with main chat button
  - Useful for avoiding overlap with mobile app navigation bars

### Fixed
- **Tag Usage Page**: Fixed memory exhaustion on sites with many Elementor pages
  - Now processes posts in batches of 50 to prevent PHP memory limit errors
- **Admin Notifications**: Fixed settings not saving (user roles and selected users)
- **SMS Notifications**: Fixed "Send Test SMS" button not working on settings page

## [1.5.5] - 2025-11-28

### Added
- **Share Menu**: Added KakaoTalk share option
- **Post Fields Anywhere**: New dynamic tag to render any @post() tag in the context of a different post
  - Usage: `@site().render_post_tag(post_id, @post(...))`
  - Full access to all Voxel dynamic tag features (properties, modifiers)
  - Examples: `@site().render_post_tag(123, @post(taxonomy.slug))`, `@site().render_post_tag(123, @post(location.lng))`
- **Disable Gutenberg**: Disable the Gutenberg block editor site-wide and restore the classic editor
  - Disables block editor for all post types
  - Restores classic widgets (disables block-based widgets)
  - Removes Gutenberg plugin hooks if installed
- **Dynamic Tag Modifier - |see_more()**: Expandable text truncation with toggle
  - Truncates text by word count or character count
  - Adds "... **See More**" link after truncated content
  - Toggle functionality with customizable "See Less" text
  - Usage: `@post(description)|see_more(100, words, See More, See Less)`
  - Keyboard accessible (Enter/Space keys)

### Fixed
- **WP-CLI Compatibility**: Fixed fatal errors during cPanel staging-to-live deployments
  - Added class existence checks before extending Voxel theme classes
  - Prevents crashes when WordPress is bootstrapped via WP-CLI without full theme loading
  - Affected files: dynamic tags, filters, order-by, post fields, and event classes
- **Duplicate Title Checker**: Fixed error/success message settings not persisting
- **Messenger Widget**: Multiple fixes and improvements
  - Added input placeholder text control for customizing "Type a message..." text
  - Fixed send button icon not accepting custom icon/SVG changes
  - Fixed upload button icon not accepting custom icon/SVG changes
  - Added minimize/close button style controls (background, hover, icon color, size)
- **Active Filters Widget**: Fixed filter labels displaying URL keys instead of Voxel labels
  - Now displays "Category: Digital" instead of "Terms: Digital"
  - Uses Voxel's Post_Type API to get proper filter labels
  - Fixed preview mode showing on frontend (now only shows in Elementor editor)
- **Performance**: Removed excessive debug logging that was spamming production logs
  - Poll Field was logging on every page load
  - Removed ~120 lines of debug error_log() calls across multiple files

## [1.5.4.2] - 2025-11-26

### Fixed
- **Pre-Approve Posts**: Fixed roles not saving in settings

## [1.5.4.1] - 2025-11-26

### Added
- **Suggest Edits Widget**: Input placeholder control
  - Customize the "Enter new value..." placeholder text in Elementor widget settings

### Fixed
- **Suggest Edits Widget**: Now works on all Voxel post types by default
- **Onboarding Widget**: Removed focus outline/border from tour buttons
- **Duplicate Post**: Fixed settings not saving

## [1.5.4] - 2025-11-23

### Added
- **Messenger Widget**: Facebook-style floating chat widget with multi-chat support
  - **Floating Button**: Customizable position (bottom-right or bottom-left)
  - **Multi-Chat Support**: Open up to 5 simultaneous chat windows
  - **Unread Badge**: Shows count of unread messages with customizable styling
  - **Chat List**: View all conversations with user avatars and last message preview
  - **Real-Time Updates**: Polls for new messages at configurable intervals
  - **Preview Mode**: Test widget appearance in Elementor editor
  - **Comprehensive Styling**:
    - Main button: size, colors, border radius, shadow
    - Chat window: width, height, header colors, background
    - Messages: bubble colors, typography, timestamps
    - Input area: background, border, send button styling
    - Badge: colors, size, position
  - **Responsive Design**: Adapts to mobile with full-width chat windows
  - Integrates with Voxel's native messaging system
- **Suggest Edits Widget**: Allow users to suggest edits to post fields with admin review system
  - **Frontend Widget**: Button that opens a modal for suggesting edits to any post
  - **Field Selection**: Choose which post fields users can suggest edits for
  - **Supported Field Types**: Text, textarea, number, email, URL, phone, date, work hours, location
  - **Guest Support**: Allow guest users to submit suggestions with email
  - **Voxel App Event**: Triggers on new suggestion submission for notifications
  - **Pending Suggestions Widget**: Display pending suggestion count on frontend
- **Share Menu**: Add 8 additional share options to Voxel's share menu (always enabled)
  - **Pinterest**: Share images and links to Pinterest boards
  - **Email**: Share via email with pre-filled subject and body
  - **Threads**: Share to Meta's Threads platform
  - **Bluesky**: Share to the Bluesky decentralized social network
  - **SMS**: Share via text message (mobile devices)
  - **Line**: Share to Line messaging app (popular in Asia)
  - **Viber**: Share to Viber messaging app
  - **Snapchat**: Share to Snapchat
  - Uses Voxel's `voxel/share-links` filter for seamless integration
  - Add platforms via Voxel > General Settings > Share menu
- **Dynamic Tag Modifier - .sold()**: Track total quantity sold for products
  - **Usage**: `@post(id).sold()` returns total quantity sold from orders
  - Documented in Voxel Toolkit > Dynamic Tags admin page
- **Dynamic Tag Modifier - .summary()**: Generate email-friendly order summary tables
  - **Usage**: `@order(id).summary()` returns HTML table of all order items
  - **Regular Products**: Displays product name, quantity, unit price, and total
  - **Booking Products**: Includes date ranges, nights/days calculation, and addons
  - **Addon Support**: Formats numeric addons and custom-multiselect options with quantities
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
  - **Privacy-Focused**: Uses ephemeral cookies only, no permanent storage
- **Article Helpful Dynamic Tags**: Display article voting statistics using dynamic tags
  - **Dynamic Tags**: 4 new tags for use anywhere in Elementor
    - `@post(article_helpful_yes_count)` - Number of "Yes" votes
    - `@post(article_helpful_no_count)` - Number of "No" votes
    - `@post(article_helpful_total_votes)` - Total votes (yes + no)
    - `@post(article_helpful_percentage)` - Percentage of yes votes (0-100)
  - **Conditional Display**: Documentation appears in admin Dynamic Tags page when Article Helpful widget is enabled
- **Tag Usage Page**: New admin page to detect and display all dynamic tag usage across the site
  - **Location**: Voxel Toolkit > Tag Usage in admin menu
  - **Detection**: Scans Elementor data in all pages, posts, and templates
  - **Pattern Matching**: Detects @post(), @user(), @site(), @author(), @current_user() tags
  - **Modifier Support**: Captures tags with modifiers (e.g., @post(field).modifier())
  - **Live Search**: Real-time filtering of tags as you type
  - **Location Display**: Shows first 3 pages/posts per tag with "Show more" button
  - **Copy to Clipboard**: One-click copy of any tag with visual confirmation
  - **Usage Count**: Badge showing how many locations each tag appears in
  - **Direct Links**: Click any location to edit that page/post in new tab
  - **Post Type Icons**: Visual indicators for posts, pages, and templates
  - **Sorted Results**: Tags displayed by usage count (most used first)
- **SMS Notifications**: Send SMS notifications when Voxel app events occur
  - **SMS Providers**: Support for Twilio, Vonage, and MessageBird
  - **Event Integration**: Toggle SMS on/off per event directly in Voxel's App Events page
  - **Dynamic Messages**: Use Voxel's dynamic tags in SMS message templates
  - **Destinations**: Send to user, admin, or custom destinations per event
  - **Test SMS**: Send test messages to verify configuration
  - **Phone Field Selection**: Choose which profile field contains phone numbers
  - **Country Code Support**: Default country code setting for phone normalization
  - **Admin Notifications Integration**: Automatically sends SMS to users configured in Admin Notifications
- **Active Filters Widget**: Display active search filters as clickable tags
  - **URL Parameter Parsing**: Reads filters from URL (range, terms, keywords, sort, etc.)
  - **Human-Readable Labels**: Formats values nicely (e.g., "Price: $0 - $300", "Category: Apartments, Houses")
  - **Remove Filters**: Click any tag to remove that filter and refresh results
  - **Clear All Button**: Remove all filters at once with configurable position (before/after)
  - **Dynamic Updates**: Monitors URL changes for Voxel AJAX filtering without page reload
  - **Layout Options**: Horizontal (wrap) or vertical (stacked) display
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
- **Article Helpful Widget**: Fixed message customization bugs
  - Added customizable "Vote Updated Message" control for when users change their vote
  - Added customizable "Already Voted Message" control for duplicate votes
  - Fixed custom success message not displaying (was showing hardcoded "Thank you for your feedback!")
  - All messages now use widget settings instead of hardcoded AJAX responses
  - All messages are fully translatable and customizable per widget instance
