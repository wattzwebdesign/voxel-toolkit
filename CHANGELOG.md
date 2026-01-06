# Changelog

All notable changes to Voxel Toolkit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.1] - 2026-01-06

### Added
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

### Fixed
- **AI Bot Post Type Matching**: Fixed AI using plural post type names (e.g., "members") instead of exact schema keys (e.g., "member")

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

