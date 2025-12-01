# Voxel Admin Columns - Development Spec

## Overview

Build a custom Admin Columns feature for the Voxel Toolkit plugin that allows users to configure which Voxel fields display as columns in the WordPress admin post list tables. This is similar to Admin Columns Pro but built specifically for Voxel's field system.

**Technology:** Vue.js (to match Voxel's admin architecture)

**Design:** Match the styling from Voxel's Widgets and Functions admin pages. Keep it clean and simple.

---

## Field Type Reference

### How Voxel Stores Field Data (wp_postmeta)

| Field Type | Meta Key Example | Storage Format |
|------------|------------------|----------------|
| title | `title` | Plain text |
| text | `{key}` | Plain text |
| number | `{key}` | Plain number |
| email | `email` | Plain text |
| phone | `phone` | Plain text |
| url | `website` | Plain text |
| date | `date` | `YYYY-MM-DD` |
| time | `time` | `HH:MM` |
| color | `color` | Hex string `#xxxxxx` |
| switcher | `switcher` | `1` or empty |
| select | `select` | `Value:key` format or plain value |
| multiselect | `multiselect` | JSON array `["One","Two"]` |
| image (single) | `logo`, `cover`, `_thumbnail_id` | Attachment ID (integer) |
| image (gallery) | `gallery` | Comma-separated attachment IDs `801,802,861` |
| file | `file` | Attachment ID (integer) |
| location | `location` | JSON `{"address":"...","latitude":...,"longitude":...}` |
| timezone | `timezone` | Timezone string e.g. `America/New_York` |
| work-hours | `work_hours` | JSON array of day/hours objects |
| recurring-date | `event_date` | JSON array with start/end/frequency/unit/until |
| repeater | `repeater` | JSON array of row objects |
| product | `product` | JSON with product_type, pricing, deliverables |
| taxonomy | N/A (wp_term_relationships) | Term IDs - use WordPress taxonomy functions |
| post-relation | N/A (Voxel relations table) | Related post IDs - query Voxel's relations |

### Post Type Configuration Location

Post type configs with all field definitions are stored in:
- **wp_options** table
- **option_name:** `voxel:post_types`
- **Format:** JSON object keyed by post type slug

Each post type has a `fields` array. Each field has:
- `type` - the field type
- `key` - the field key (used as meta_key)
- `label` - display label (optional, may need to be derived)
- Type-specific settings

**UI-only fields to skip:** `ui-step`, `ui-heading`, `ui-image` (these are form layout elements, not data fields)

---

## Phase 1: Core Infrastructure

### Objectives
- Create the Admin Columns settings page under Voxel Toolkit menu
- Build the Vue.js-based configuration interface
- Implement post type selector
- Build draggable column cards with expand/collapse
- Save and load column configurations to database

### Tasks

#### 1.1 Register Settings Page
- Add "Admin Columns" submenu page under Voxel Toolkit
- Enqueue Vue.js and necessary scripts
- Set up the page container for Vue to mount

#### 1.2 Vue Application Structure
```
/admin-columns/
  /assets/
    /js/
      admin-columns.js (main Vue app)
    /css/
      admin-columns.css
  /templates/
    admin-columns-page.php
```

#### 1.3 Settings Page UI Components

**Header Section:**
- Post type dropdown (populated from Voxel post types)
- "View [Post Type]" button linking to edit.php for that post type

**Main Column Configuration Area:**
- Draggable list of column cards
- Each card shows:
  - Drag handle
  - Column label
  - Field type indicator
  - Icon toolbar (expand, sorting toggle, filter toggle, remove)
  - Expand/collapse arrow

**Expanded Card Settings:**
- Type dropdown (field selection from Voxel fields)
- Label text input
- Width control (Auto / % / px with slider)
- Toggle switches:
  - Sorting (enable sortable column)
  - Inline Editing (where applicable)

**Sidebar:**
- "Update" save button
- "Restore Defaults" link
- Table Views section (placeholder for Phase 5)

**Footer Actions:**
- "+ Add Column" button

#### 1.4 Data Storage
- Store configurations in wp_options as `voxel_toolkit_admin_columns`
- Structure:
```json
{
  "places": {
    "columns": [
      {
        "id": "unique-id",
        "type": "voxel_field",
        "field_key": "title",
        "label": "Title",
        "width": {"mode": "auto", "value": null},
        "sortable": true,
        "inline_edit": false
      }
    ],
    "settings": {
      "default_sort": {"column": "date", "order": "desc"},
      "primary_column": "title"
    }
  }
}
```

#### 1.5 AJAX Endpoints
- `voxel_toolkit_get_post_types` - Return list of Voxel post types
- `voxel_toolkit_get_post_type_fields` - Return fields for a specific post type
- `voxel_toolkit_save_columns` - Save column configuration
- `voxel_toolkit_load_columns` - Load column configuration for a post type

#### 1.6 Drag and Drop
- Use Vue Draggable or similar library
- Smooth reordering animation
- Visual feedback during drag

### Deliverables
- Working settings page accessible from Voxel Toolkit menu
- Ability to select post type and see its available Voxel fields
- Add, remove, reorder columns via drag-and-drop
- Save and load configurations per post type
- Styling matches Voxel admin pages (reference Widgets and Functions pages)

---

## Phase 2: Field Type Display Handlers

### Objectives
- Register custom columns in WordPress admin for configured post types
- Build display handlers for each Voxel field type
- Render appropriate content in admin list tables

### Tasks

#### 2.1 Column Registration
Hook into:
- `manage_{post_type}_posts_columns` - Add column headers
- `manage_{post_type}_posts_custom_column` - Render column content

#### 2.2 Field Display Handlers

Create a handler class/system that renders each field type appropriately:

| Field Type | Display Format |
|------------|----------------|
| title | Plain text |
| text | Plain text (truncate if long) |
| number | Formatted number |
| email | Clickable mailto link |
| phone | Clickable tel link |
| url | Clickable link (truncated display) |
| date | Formatted date (WordPress date format) |
| time | Formatted time |
| color | Color swatch + hex code |
| switcher | Yes/No or checkmark icon |
| select | Display label (not raw value) |
| multiselect | Comma-separated labels |
| image (single) | Thumbnail (50x50) |
| image (gallery) | First image thumbnail + count badge |
| file | File icon + filename |
| location | Address text (truncated) |
| timezone | Timezone name |
| work-hours | "Open Now" badge or summary |
| recurring-date | Next occurrence date |
| repeater | Row count badge |
| product | Product type + price |
| taxonomy | Term names (linked) |
| post-relation | Related post titles (linked) |

#### 2.3 Empty Value Handling
- Display dash or "—" for empty/null values
- Consistent styling for empty states

#### 2.4 Truncation and Tooltips
- Long text truncated with "..." 
- Full value in title attribute for hover tooltip
- Configurable max character length

### Deliverables
- All configured columns display correctly in admin list table
- Each field type renders with appropriate formatting
- Clean, readable column output
- Proper handling of empty values

---

## Phase 3: Sorting and Filtering

### Objectives
- Enable sortable columns for appropriate field types
- Add filter dropdowns above the list table

### Tasks

#### 3.1 Sortable Columns
Hook into:
- `manage_edit-{post_type}_sortable_columns` - Register sortable columns
- `pre_get_posts` or `posts_clauses` - Modify query for sorting

**Sortable field types:**
- title, text, number, email, phone, url, date, time, color, switcher, select, timezone
- taxonomy (by term name)

**Not sortable:**
- multiselect, image, gallery, file, location, work-hours, recurring-date, repeater, product, post-relation

#### 3.2 Sort Query Modifications
- Meta value sorting for custom fields
- Proper orderby handling for different data types (numeric vs string)
- Handle JSON-stored fields where possible

#### 3.3 Filter Dropdowns (Restrict Manage Posts)
Hook into:
- `restrict_manage_posts` - Add filter dropdowns

**Filterable field types:**
- select (dropdown of choices)
- multiselect (dropdown of choices)
- taxonomy (term dropdown)
- switcher (Yes/No dropdown)

#### 3.4 Filter Query Modifications
- Apply meta_query for filtered fields
- Handle taxonomy filters via tax_query

### Deliverables
- Clickable column headers for sorting
- Visual sort direction indicator
- Filter dropdowns for applicable fields
- Filters properly modify the post list

---

## Phase 4: Inline Editing

### Objectives
- Enable inline editing for simple field types directly in the list view
- AJAX save without page reload

### Tasks

#### 4.1 Inline Edit UI
- Click to edit (or edit icon)
- Appropriate input type per field:
  - text, email, url, phone → text input
  - number → number input
  - date → date picker
  - time → time picker
  - color → color picker
  - switcher → checkbox/toggle
  - select → dropdown
  - multiselect → multi-select dropdown
  - taxonomy → term selector

#### 4.2 Inline Edit Fields NOT Supported
- image, gallery, file (too complex)
- location (needs map interface)
- work-hours (complex structure)
- recurring-date (complex structure)
- repeater (needs full editor)
- product (complex structure)
- post-relation (needs post selector)

#### 4.3 AJAX Save Handler
- `voxel_toolkit_inline_edit_save` endpoint
- Validate user permissions
- Validate field value
- Update post meta
- Return success/error response

#### 4.4 UI Feedback
- Loading indicator during save
- Success checkmark animation
- Error message display
- Escape key to cancel edit

### Deliverables
- Inline editing works for supported field types
- Changes save via AJAX
- Visual feedback for save states
- Proper permission checks

---

## Phase 5: Views System

### Objectives
- Save multiple column configurations ("views") per post type
- Conditional visibility based on user role or specific users
- Switch between views

### Tasks

#### 5.1 Views Data Structure
```json
{
  "places": {
    "views": [
      {
        "id": "view-1",
        "name": "Default",
        "is_default": true,
        "columns": [...],
        "conditionals": {
          "roles": [],
          "users": []
        }
      },
      {
        "id": "view-2", 
        "name": "Editor View",
        "is_default": false,
        "columns": [...],
        "conditionals": {
          "roles": ["editor"],
          "users": []
        }
      }
    ]
  }
}
```

#### 5.2 Views UI in Settings
- Sidebar "Table Views" section with "+ Add View" button
- List of existing views
- Click to switch/edit view
- View name editable
- Set default view
- Delete view (with confirmation)

#### 5.3 Conditionals UI
- "Make this view available only for specific users or roles"
- Role multi-select dropdown
- User search/select field

#### 5.4 View Selection Logic
When loading admin list table:
1. Get all views for this post type
2. Filter to views where current user matches conditionals
3. If multiple match, use the one set as default for user, or first match
4. Apply that view's column configuration

#### 5.5 View Switcher in Admin List
- Optional: Add view switcher dropdown above list table
- Allow users to switch between their available views

### Deliverables
- Multiple views per post type
- Role and user-based conditional visibility
- View management UI in settings
- Correct view auto-selection based on current user

---

## Phase 6: Table Elements and Preferences

### Objectives
- Global table settings for each post type
- Master toggles for features
- Default preferences

### Tasks

#### 6.1 Table Elements Toggles
In settings page, add "Table Elements" section:
- Inline Edit (master toggle)
- Sorting (master toggle)
- Filters (master toggle)
- Column Order (allow reorder on list page)

#### 6.2 Preferences Section
- **Sorting:** Default sort column + direction (Ascending/Descending)
- **Primary Column:** Which column gets the row actions (Edit, Trash, View)
- **Wrapping:** Wrap (default) / No wrap

#### 6.3 Apply Preferences
- Default sort applied via `pre_get_posts`
- Primary column handling via `list_table_primary_column` filter
- Wrapping via CSS class on table

### Deliverables
- Table elements master toggles working
- Default sort applying correctly
- Primary column configurable
- Text wrapping option functional

---

## File Structure

```
voxel-toolkit/
  /includes/
    /admin-columns/
      class-admin-columns.php (main loader)
      class-column-types.php (field type handlers)
      class-column-renderer.php (display logic)
      class-inline-editor.php (AJAX handlers)
      class-views-manager.php (views logic)
      /assets/
        /js/
          admin-columns-app.js (Vue application)
          admin-columns-inline.js (inline edit on list pages)
        /css/
          admin-columns.css
      /templates/
        admin-columns-page.php
```

---

## Technical Notes

### Getting Voxel Post Types
```php
$post_types = \Voxel\Post_Type::get_voxel_types();
```

### Getting Fields for a Post Type
```php
$post_type = \Voxel\Post_Type::get( 'places' );
$fields = $post_type->get_fields();
```

### Getting Field Value for a Post
```php
$post = \Voxel\Post::get( $post_id );
$field = $post->get_field( 'location' );
$value = $field->get_value();
```

### Voxel Relations Query
```php
// Check Voxel's relation methods for post-relation fields
```

---

## Design Guidelines

- **Match Voxel admin styling** - Reference the Widgets and Functions pages in Voxel for visual consistency
- **Keep it simple** - Clean, minimal interface
- **Vue.js** - Use Vue for reactivity, match Voxel's existing approach
- **Responsive** - Settings page should work on smaller screens
- **Accessibility** - Proper labels, keyboard navigation, focus states

---

## Implementation Order

1. **Phase 1** - Get the settings page and basic save/load working first
2. **Phase 2** - Make columns actually appear in the admin list
3. **Phase 3** - Add sorting and filtering
4. **Phase 4** - Inline editing
5. **Phase 5** - Views system
6. **Phase 6** - Polish with table elements and preferences

Each phase should be fully functional before moving to the next. Test thoroughly after each phase.
