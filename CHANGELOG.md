# Changelog

All notable changes to Voxel Toolkit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.1] - 2025-01-14

### Added
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
- Timeline Photos Widget: Added "Photo Limit" control to limit the maximum number of photos displayed
- Timeline Photos Widget: Added "Photo Offset" control to skip photos from the beginning of the gallery
- Dynamic Tags: New `@post(reading_time)` property showing estimated reading time (e.g., "5 min" or "1 hr 30 min")
- Dynamic Tags: New `@post(word_count)` property showing total word count in post content
- Dynamic Tags: New `@user(membership_expiration)` and `@author(membership_expiration)` properties showing membership expiration date
- Dynamic Tags: Added admin documentation page with usage examples and syntax guide
- Modifiers: New `.file_size()` modifier to get formatted file size from file ID (e.g., `@post(upload-media.id).file_size()`)
- Modifiers: New `.file_extension()` modifier to get file extension from file ID (e.g., `@post(upload-media.id).file_extension()`)
- Modifiers: New `.address_part()` modifier to extract specific components from address fields
  - Supports: street number, street name, city, state, postal code, country
  - Uses Google Geocoding API
  - Works with international addresses
  - Usage: `@post(location.address).address_part(city)` or `@post(location.address).address_part(postal_code)`
- Campaign Progress Widget: GoFundMe-style donation/crowdfunding progress tracker
  - Display campaign goal with visual progress bar
  - Show total raised, remaining amount, and percentage complete
  - Recent donor list with avatars, names, dates, and amounts
  - Integrates with Voxel orders system (wp_vx_orders)
  - Customizable text labels for all elements
  - Full styling controls: progress bar colors, donor list styling, typography
- Search Order: View Count sorting option
  - Sort posts by view counts in ascending or descending order
  - Support for multiple time periods: all time, 30 days, 7 days, 24 hours
  - Queries view count data from post meta using JSON extraction
  - "Most viewed" preset button for one-click access
  - Also available through Custom order option for advanced configuration

### Changed
- Widgets page: Widgets now display in alphabetical order by name
- Configure button styling on Functions page now matches Usage badge styling
- Required PHP version adjusted to 8.1 for broader compatibility

### Improved
- Functions page: Added enabled/disabled status badges to match Widgets page design
- Functions page: Cards now match exact styling of Widgets page (hover effects, toggle switches, colors)
- Admin UI: Updated all purple accent colors to navy (#1e3a5f) for consistent branding
- Search inputs: Improved styling with consistent 40px height and navy focus color
- All page controls: Updated buttons and filters to use navy color scheme

## [1.5.0] - Previous Release

### Features
- Initial widget redesign implementation
- Modern card-based UI for widgets and functions pages
- Enhanced widget usage tracking and display
- Improved admin interface styling

