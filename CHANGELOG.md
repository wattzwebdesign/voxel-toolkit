# Changelog

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

### Social Proof

- **Translation Support** - Time ago strings (minutes, hours, days, ago) are now translatable for boost events

### Saved Search

- **Alert Width Fix** - Fixed alert popup text overlapping when using translated languages (e.g., Polish)
