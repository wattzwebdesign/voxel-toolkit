# Changelog

All notable changes to Voxel Toolkit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.1] - 2026-01-06

### Added
- **Coupon Manager Widget**: New Elementor widget for creating and managing Stripe coupons. Features include:
  - Create percent-off or fixed-amount discount coupons
  - Set duration (once, repeating, forever)
  - Optional restrictions: max redemptions, expiration date, minimum order value, limit to specific customer email, first-time customers only
  - View list of coupons created by the current user
  - Delete coupons
  - Automatic Stripe provider check (widget only displays when Stripe is enabled)
  - Full Elementor styling controls for form, buttons, coupon cards, promo codes, expired states, and delete button
- **Open Now Search Order**: New search order option to sort listings by open/closed status based on work hours field. Features include:
  - Open listings appear first (or closed first if reversed)
  - Configurable work hours field selector
  - Timezone modes: site timezone or individual post timezone
- **Pin Timeline Post**: Allow post authors to pin a timeline post to the top of their post's timeline feed. Features include:
  - "Pin to Top" / "Unpin" action in post dropdown menu (visible only to parent post author)
  - Pinned badge displayed on pinned posts
  - Pin icon next to the actions button on pinned posts
  - Pinned post automatically moves to top of timeline (below filters)
  - Per-widget toggle to enable/disable pin functionality in Timeline widget settings
  - Styling controls in Timeline Style Kit widget for border color, badge (alignment, background, text color, typography, padding, border radius), and pin icon (color, size)
- **Checklist Field**: New custom post field type for creating checklists with title (optional) and description per item. Features include:
  - Configurable permissions (post author only or any logged-in user)
  - Check scope options (global shared or per-user tracking)
  - Drag-and-drop reordering of items
  - Timestamp recording when items are checked
  - App event triggered on item check
  - Dynamic tags for completion percentage
  - Full Elementor widget for frontend display with progress bar, styling controls
  - Create Post (VX) widget styling controls for checklist cards, inputs, icons, and buttons
- **AI Bot Location Awareness**: AI Bot now understands "near me" queries using browser geolocation, visitor location cookie, or IP-based fallback
- **AI Bot Taxonomy Filtering**: Enhanced search with complex taxonomy filters (contains, doesn't contain, has, does not have, empty, not empty)
- **AI Bot Comprehensive Search**: Added `_search_all` filter for searching across all fields, taxonomies, and content
- **AI Bot Quick Actions Toggle**: New setting to enable/disable quick action buttons (Directions, Call, View) below result cards
- **AI Bot Thinking Text**: New setting to customize the "AI is thinking" loading message
- **AI Response Language**: New global setting in AI Settings tab to configure the response language for all AI features (29 languages supported). Affects:
  - AI Bot responses and explanations
  - AI Post Summary generation
  - Timeline Reply Summary
  - Synonym Search generation
  - AI Review Summary
- **Telnyx SMS Provider**: New SMS provider option with API v2 Bearer token authentication
- **Solapi SMS Provider**: New SMS provider for Korean market with HMAC-SHA256 authentication
- **External Link Warning Button Styling**: Added button styling controls for the External Link Warning modal:
  - Border radius control (0-50px)
  - Border width control (0-10px)
  - Individual border colors for Continue and Cancel buttons
  - Sticky live preview that follows scroll in settings page
- **Saved Search Widget Styling**: Comprehensive styling enhancements for the Saved Search widget:
  - Template selector: Detailed (filter tags) or Simple (filter summary)
  - Show/hide controls for Search, Notification, and Delete action buttons
  - Filter Tags styling: border width/color/style, 4-corner border radius, gap, icon color/size, box shadow
  - Filter Summary styling section for Simple template
  - Header/Footer divider color and thickness controls

### Fixed
- **AI Bot Post Type Matching**: Fixed AI using plural post type names (e.g., "members") instead of exact schema keys (e.g., "member")
- **Compare Action Improvements**: Fixed multiple issues with the Compare action in Edit Actions widget:
  - Blank text field now shows nothing instead of defaulting to "Compare"
  - Tooltips now work correctly (using Voxel's tooltip-inactive/tooltip-active pattern)
  - Active state now uses configured icon instead of showing a checkmark
  - Added Active State controls (text, icon, tooltip) matching Save to Collection action
  - Added Initial State icon/text controls so icon stays consistent between states
  - Added customizable notification messages in settings (Different Post Type, Max Posts Reached)
- **Recurring Events Date Display**: Fixed partial date formats not updating for recurring event occurrences. Now supports all PHP date format characters (d, j, D, l, F, M, m, n, Y, y, S, etc.) so individual day numbers, month names, weekday names, and other partial formats correctly update for each occurrence.
- **Add Category with Custom Popup Style**: Fixed "Add Category" button not appearing in taxonomy popups when "Popups: Custom style" is enabled in the Create Post widget. Improved form group detection for teleported popups.

## [1.6.0.1] - 2026-01-05

### Fixed
- **AI Functions Settings**: Fixed enabling AI Post Summary or AI Bot without API key configured causing all settings tabs to appear blank
- **AI Bot Avatar Upload**: Fixed upload button not working in AI Bot settings
- **Saved Search**: Fixed infinite page refresh loop when loading a saved search
- **Promotion Create Form**: Fixed currency symbol display to match site's configured currency instead of hardcoded $
- **Save Search**: Fixed save button in popup not working

### Added
- **AI Bot Panel Behavior**: New setting to choose between "Push content" (default) or "Overlay content" when panel opens
- **Load Search Button Styling**: Added full style controls for Load Search button (alignment, icon size, height, border radius, typography, colors, padding, margin, border, box shadow, hover states)
- **Load Search Button Width**: Added button width control for Load Search button

