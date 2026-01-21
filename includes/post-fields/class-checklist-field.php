<?php
/**
 * Checklist Field
 *
 * A custom post field that allows users to create checklists with items
 * that can be checked off. Supports configurable permissions and scoping.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checklist Field Manager Class
 *
 * Handles initialization, AJAX, Vue templates, and app events.
 */
class Voxel_Toolkit_Checklist_Field {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Prevent duplicate initialization
        if (self::$instance !== null) {
            return;
        }
        self::$instance = $this;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_vt_checklist_toggle', array($this, 'handle_toggle'));
        add_action('wp_ajax_nopriv_vt_checklist_toggle', array($this, 'handle_toggle_nopriv'));

        // Frontend Vue template for create/edit form
        add_action('wp_head', array($this, 'add_frontend_template'));

        // Vue template for backend field config
        add_action('admin_footer', array($this, 'render_vue_templates'));

        // Register app events
        add_filter('voxel/app-events/register', array($this, 'register_app_events'));
        add_filter('voxel/app-events/categories', array($this, 'register_event_category'));

        // Register dynamic tags
        add_filter('voxel/dynamic-tags/post-field-groups', array($this, 'register_dynamic_tags'), 10, 2);

        // Initialize Elementor styles for Create Post widget
        $this->init_elementor_styles();
    }

    /**
     * Initialize Elementor styles class
     */
    private function init_elementor_styles() {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/post-fields/class-checklist-field-elementor-styles.php';
        Voxel_Toolkit_Checklist_Field_Elementor_Styles::instance();
    }

    /**
     * Add frontend Vue template for create/edit post form
     */
    public function add_frontend_template() {
        ?>
        <script>
        document.addEventListener('voxel/create-post/init', e => {
            const { app, config, el } = e.detail;

            app.component('field-checklist-vt', {
                template: `
                    <div class="ts-form-group vt-checklist-field">
                        <label>
                            {{ field.label }}
                            <slot name="errors"></slot>
                        </label>
                        <p v-if="field.description" class="ts-form-description">{{ field.description }}</p>

                        <div class="vt-checklist-items" v-if="field.value && field.value.length" ref="itemsList">
                            <div
                                v-for="(item, index) in field.value"
                                :key="item._key || index"
                                class="vt-checklist-item-row ts-repeater-item"
                                draggable="true"
                                @dragstart="dragStart($event, index)"
                                @dragover.prevent="dragOver($event, index)"
                                @drop="drop($event, index)"
                                @dragend="dragEnd"
                                :class="{ 'is-dragging': dragIndex === index }"
                            >
                                <div class="vt-checklist-drag-handle" title="<?php echo esc_attr(__('Drag to reorder', 'voxel-toolkit')); ?>">
                                    <svg viewBox="0 0 288 480" xmlns="http://www.w3.org/2000/svg"><path d="M48,96A48,48,0,1,0,0,48,48,48,0,0,0,48,96Zm0,192A48,48,0,1,0,0,240,48,48,0,0,0,48,288ZM96,432a48,48,0,1,1-48-48A48,48,0,0,1,96,432ZM240,96a48,48,0,1,0-48-48A48,48,0,0,0,240,96Zm48,144a48,48,0,1,1-48-48A48,48,0,0,1,288,240ZM240,480a48,48,0,1,0-48-48A48,48,0,0,0,240,480Z" fill="currentColor" style="fill-rule: evenodd;"></path></svg>
                                </div>
                                <div class="vt-checklist-item-content">
                                    <div class="ts-form-group">
                                        <input
                                            type="text"
                                            v-model="item.title"
                                            :placeholder="'<?php echo esc_js(__('Title (optional)', 'voxel-toolkit')); ?>'"
                                            class="ts-filter"
                                        >
                                    </div>
                                    <div class="ts-form-group">
                                        <textarea
                                            v-model="item.description"
                                            :placeholder="'<?php echo esc_js(__('Description', 'voxel-toolkit')); ?>'"
                                            rows="3"
                                            class="ts-filter autofocus"
                                        ></textarea>
                                    </div>
                                </div>
                                <div class="vt-checklist-item-actions">
                                    <a href="#" @click.prevent="removeItem(index)" class="ts-icon-btn">
                                        <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="m23.794 60.5h16.4121a7.61968 7.61968 0 0 0 7.59961-7.1709l1.99317-34.25586a1.50882 1.50882 0 0 0 -1.49707-1.58691h-32.60357a1.49982 1.49982 0 0 0 -1.49707 1.58691l1.99317 34.25586a7.61968 7.61968 0 0 0 7.59966 7.1709zm22.918-40.01367-1.90048 32.66894a4.61773 4.61773 0 0 1 -4.60547 4.34473h-16.41205a4.61773 4.61773 0 0 1 -4.60547-4.34473l-1.90044-32.66894z"/><path fill="currentColor" d="m35.751 3.5h-7.502a5.25762 5.25762 0 0 0 -5.252 5.251v2.25195h-11.00384a1.50017 1.50017 0 0 0 .00007 3h40.01361a1.5 1.5 0 0 0 0-3h-11.00391v-2.25195a5.25762 5.25762 0 0 0 -5.25193-5.251zm2.252 7.50293h-12.00593v-2.25193a2.25372 2.25372 0 0 1 2.25193-2.251h7.502a2.25372 2.25372 0 0 1 2.252 2.251z"/><path fill="currentColor" d="m27.169 51.60742a1.50127 1.50127 0 0 0 1.501-1.52929l-.38672-19.918a1.54491 1.54491 0 0 0 -1.52929-1.47071 1.50131 1.50131 0 0 0 -1.47071 1.5293l.38672 19.918a1.50048 1.50048 0 0 0 1.499 1.4707z"/><path fill="currentColor" d="m36.80078 51.60742a1.50159 1.50159 0 0 0 1.5293-1.4707l.38672-19.918a1.50029 1.50029 0 1 0 -3-.05859l-.38672 19.918a1.50129 1.50129 0 0 0 1.4707 1.52929z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="ts-form-group">
                            <a href="#" @click.prevent="addItem" class="ts-btn ts-btn-2">
                                <i class="las la-plus icon-sm"></i>
                                <span><?php echo esc_js(__('Add Item', 'voxel-toolkit')); ?></span>
                            </a>
                        </div>
                    </div>
                `,
                props: {
                    field: Object
                },
                data() {
                    return {
                        dragIndex: null,
                        dragOverIndex: null,
                        keyCounter: 0
                    };
                },
                mounted() {
                    // Initialize field value as array if empty
                    if (!this.field.value || !Array.isArray(this.field.value)) {
                        this.field.value = [];
                    }
                    // Add unique keys to existing items
                    this.field.value.forEach(item => {
                        if (!item._key) {
                            item._key = ++this.keyCounter;
                        }
                    });
                },
                methods: {
                    addItem() {
                        this.field.value.push({ title: '', description: '', _key: ++this.keyCounter });
                    },
                    removeItem(index) {
                        this.field.value.splice(index, 1);
                    },
                    dragStart(e, index) {
                        this.dragIndex = index;
                        e.dataTransfer.effectAllowed = 'move';
                    },
                    dragOver(e, index) {
                        e.dataTransfer.dropEffect = 'move';
                        this.dragOverIndex = index;
                    },
                    drop(e, index) {
                        if (this.dragIndex !== null && this.dragIndex !== index) {
                            const item = this.field.value.splice(this.dragIndex, 1)[0];
                            this.field.value.splice(index, 0, item);
                        }
                        this.dragIndex = null;
                        this.dragOverIndex = null;
                    },
                    dragEnd() {
                        this.dragIndex = null;
                        this.dragOverIndex = null;
                    },
                    validate() {
                        if (this.field.required) {
                            return this.field.value.length > 0 && this.field.value.some(item => item.description && item.description.trim());
                        }
                        return true;
                    }
                }
            });
        });
        </script>

        <style>
        .vt-checklist-field .vt-checklist-items {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 15px;
        }

        .vt-checklist-field .vt-checklist-item-row {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #fff;
            transition: opacity 0.2s ease, border-color 0.2s ease;
        }

        .vt-checklist-field .vt-checklist-item-row:hover {
            border-color: #ccc;
        }

        .vt-checklist-field .vt-checklist-item-row.is-dragging {
            opacity: 0.5;
        }

        .vt-checklist-field .vt-checklist-drag-handle {
            flex-shrink: 0;
            width: 24px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: grab;
            color: #888;
            transition: color 0.2s ease;
        }

        .vt-checklist-field .vt-checklist-drag-handle:hover {
            color: #333;
        }

        .vt-checklist-field .vt-checklist-drag-handle:active {
            cursor: grabbing;
        }

        .vt-checklist-field .vt-checklist-drag-handle svg {
            width: 14px;
            height: 22px;
        }

        .vt-checklist-field .vt-checklist-item-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .vt-checklist-field .vt-checklist-item-content .ts-form-group {
            margin-bottom: 0;
        }

        .vt-checklist-field .vt-checklist-item-actions {
            flex-shrink: 0;
        }

        .vt-checklist-field .vt-checklist-item-actions .ts-icon-btn {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #e0e0e0;
            color: #666;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .vt-checklist-field .vt-checklist-item-actions .ts-icon-btn:hover {
            background: #f5f5f5;
            border-color: #ccc;
            color: #333;
        }

        .vt-checklist-field .vt-checklist-item-actions .ts-icon-btn svg {
            width: 20px;
            height: 20px;
        }

        .vt-checklist-field > .ts-form-group > .ts-btn {
            width: 100%;
            justify-content: center;
        }
        </style>
        <?php
    }

    /**
     * Handle checklist item toggle (logged in users)
     */
    public function handle_toggle() {
        check_ajax_referer('vt_checklist_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $field_key = isset($_POST['field_key']) ? sanitize_key($_POST['field_key']) : '';
        $item_index = isset($_POST['item_index']) ? absint($_POST['item_index']) : 0;
        $checked = isset($_POST['checked']) ? (bool) $_POST['checked'] : false;

        if (!$post_id || !$field_key) {
            wp_send_json_error(array('message' => __('Invalid request.', 'voxel-toolkit')));
        }

        $post = \Voxel\Post::get($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'voxel-toolkit')));
        }

        $field = $post->get_field($field_key);
        if (!$field || $field->get_type() !== 'checklist-vt') {
            wp_send_json_error(array('message' => __('Field not found.', 'voxel-toolkit')));
        }

        // Check permissions
        $props = $field->get_props();
        $check_permission = isset($props['check_permission']) ? $props['check_permission'] : 'author';
        $current_user_id = get_current_user_id();
        $post_author_id = $post->get_author_id();

        if ($check_permission === 'author' && $current_user_id !== $post_author_id) {
            wp_send_json_error(array('message' => __('You do not have permission to modify this checklist.', 'voxel-toolkit')));
        }

        if ($check_permission === 'logged_in' && !$current_user_id) {
            wp_send_json_error(array('message' => __('You must be logged in to modify this checklist.', 'voxel-toolkit')));
        }

        // Get current checklist data
        $checklist_data = get_post_meta($post_id, $field_key, true);

        // Decode JSON if stored as string
        if (is_string($checklist_data)) {
            $checklist_data = json_decode($checklist_data, true);
        }

        if (!is_array($checklist_data)) {
            $checklist_data = array('items' => array());
        }

        if (!isset($checklist_data['items'][$item_index])) {
            wp_send_json_error(array('message' => __('Item not found.', 'voxel-toolkit')));
        }

        // Determine scope
        $check_scope = isset($props['check_scope']) ? $props['check_scope'] : 'global';
        $timestamp = current_time('timestamp');

        if ($check_scope === 'per_user') {
            // Per-user: store in user meta
            $user_checks = get_user_meta($current_user_id, '_vt_checklist_' . $post_id . '_' . $field_key, true);
            if (!is_array($user_checks)) {
                $user_checks = array();
            }

            if ($checked) {
                $user_checks[$item_index] = array(
                    'checked' => true,
                    'timestamp' => $timestamp,
                );
            } else {
                unset($user_checks[$item_index]);
            }

            update_user_meta($current_user_id, '_vt_checklist_' . $post_id . '_' . $field_key, $user_checks);
        } else {
            // Global: store in post meta
            if ($checked) {
                $checklist_data['items'][$item_index]['checked'] = true;
                $checklist_data['items'][$item_index]['checked_timestamp'] = $timestamp;
                $checklist_data['items'][$item_index]['checked_by'] = $current_user_id;
            } else {
                $checklist_data['items'][$item_index]['checked'] = false;
                unset($checklist_data['items'][$item_index]['checked_timestamp']);
                unset($checklist_data['items'][$item_index]['checked_by']);
            }

            update_post_meta($post_id, $field_key, wp_slash(wp_json_encode($checklist_data)));
        }

        // Trigger app event if item was checked (not unchecked)
        if ($checked) {
            $item = $checklist_data['items'][$item_index];
            do_action('voxel_toolkit/checklist/item_checked', array(
                'post_id' => $post_id,
                'post' => $post,
                'field_key' => $field_key,
                'item_index' => $item_index,
                'item_title' => isset($item['title']) ? $item['title'] : '',
                'item_description' => isset($item['description']) ? $item['description'] : '',
                'user_id' => $current_user_id,
                'timestamp' => $timestamp,
            ));
        }

        // Calculate progress
        $progress = $this->calculate_progress($post_id, $field_key, $checklist_data, $check_scope, $current_user_id);

        wp_send_json_success(array(
            'message' => $checked ? __('Item checked.', 'voxel-toolkit') : __('Item unchecked.', 'voxel-toolkit'),
            'progress' => $progress,
            'timestamp' => $checked ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : null,
        ));
    }

    /**
     * Handle toggle for non-logged-in users (returns error)
     */
    public function handle_toggle_nopriv() {
        wp_send_json_error(array('message' => __('You must be logged in to modify this checklist.', 'voxel-toolkit')));
    }

    /**
     * Calculate checklist progress
     */
    public function calculate_progress($post_id, $field_key, $checklist_data, $scope, $user_id = null) {
        $items = isset($checklist_data['items']) ? $checklist_data['items'] : array();
        $total = count($items);

        if ($total === 0) {
            return array(
                'total' => 0,
                'checked' => 0,
                'percentage' => 0,
            );
        }

        $checked_count = 0;

        if ($scope === 'per_user' && $user_id) {
            $user_checks = get_user_meta($user_id, '_vt_checklist_' . $post_id . '_' . $field_key, true);
            if (is_array($user_checks)) {
                foreach ($items as $index => $item) {
                    if (isset($user_checks[$index]['checked']) && $user_checks[$index]['checked']) {
                        $checked_count++;
                    }
                }
            }
        } else {
            foreach ($items as $item) {
                if (!empty($item['checked'])) {
                    $checked_count++;
                }
            }
        }

        return array(
            'total' => $total,
            'checked' => $checked_count,
            'percentage' => round(($checked_count / $total) * 100),
        );
    }

    /**
     * Get checklist items with checked state
     */
    public function get_items_with_state($post_id, $field_key, $scope = 'global', $user_id = null) {
        $checklist_data = get_post_meta($post_id, $field_key, true);

        // Decode JSON if stored as string
        if (is_string($checklist_data)) {
            $checklist_data = json_decode($checklist_data, true);
        }

        if (!is_array($checklist_data) || !isset($checklist_data['items'])) {
            return array();
        }

        $items = $checklist_data['items'];
        $user_checks = array();

        if ($scope === 'per_user' && $user_id) {
            $user_checks = get_user_meta($user_id, '_vt_checklist_' . $post_id . '_' . $field_key, true);
            if (!is_array($user_checks)) {
                $user_checks = array();
            }
        }

        foreach ($items as $index => &$item) {
            if ($scope === 'per_user') {
                $item['checked'] = isset($user_checks[$index]['checked']) && $user_checks[$index]['checked'];
                $item['checked_timestamp'] = isset($user_checks[$index]['timestamp']) ? $user_checks[$index]['timestamp'] : null;
            } else {
                $item['checked'] = !empty($item['checked']);
            }
        }

        return $items;
    }

    /**
     * Render Vue templates for backend field configuration
     */
    public function render_vue_templates() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'voxel_page_voxel-post-types') {
            return;
        }
        ?>
        <script type="text/html" id="vt-checklist-field-props-template">
            <div class="ts-group">
                <div class="ts-group-head" @click="toggleGroup('checklist_basic')">
                    <span>Checklist Settings</span>
                    <span class="vx-toggle-indicator" :class="{'vx-toggle-active': isGroupOpen('checklist_basic')}"></span>
                </div>
                <div class="ts-group-body" v-show="isGroupOpen('checklist_basic')">
                    <div class="ts-form-group">
                        <label>Who can check items?</label>
                        <select v-model="field.check_permission">
                            <option value="author">Post author only</option>
                            <option value="logged_in">Any logged-in user</option>
                        </select>
                    </div>
                    <div class="ts-form-group">
                        <label>Check scope</label>
                        <select v-model="field.check_scope">
                            <option value="global">Global (shared checklist)</option>
                            <option value="per_user">Per-user (each user has own progress)</option>
                        </select>
                        <p class="ts-description">
                            Global: Everyone sees the same checked items.<br>
                            Per-user: Each user tracks their own progress.
                        </p>
                    </div>
                    <div class="ts-form-group">
                        <label>
                            <input type="checkbox" v-model="field.show_timestamps" :true-value="true" :false-value="false">
                            Show timestamps when items are checked
                        </label>
                    </div>
                </div>
            </div>
        </script>

        <script type="text/html" id="vt-checklist-field-input-template">
            <div class="ts-form-group vt-checklist-input">
                <label>{{ field.label }}</label>
                <p v-if="field.description" class="ts-description">{{ field.description }}</p>

                <div class="vt-checklist-items">
                    <div v-for="(item, index) in (field.value || [])" :key="index" class="vt-checklist-item-row">
                        <div class="vt-checklist-item-inputs">
                            <input type="text" v-model="item.title" placeholder="Title (optional)" class="vt-checklist-title-input">
                            <textarea v-model="item.description" placeholder="Description" rows="2" class="vt-checklist-desc-input"></textarea>
                        </div>
                        <button type="button" class="ts-button ts-transparent" @click="removeItem(index)">
                            <i class="las la-trash"></i>
                        </button>
                    </div>
                </div>

                <button type="button" class="ts-button ts-outline" @click="addItem">
                    <i class="las la-plus"></i> Add checklist item
                </button>
            </div>
        </script>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.Voxel === 'undefined' || typeof window.Voxel.PostTypeEditor === 'undefined') {
                return;
            }

            // Register field type with Vue
            if (window.Voxel.PostTypeEditor.fieldTypes) {
                window.Voxel.PostTypeEditor.fieldTypes['checklist-vt'] = {
                    props: {
                        check_permission: 'author',
                        check_scope: 'global',
                        show_timestamps: false,
                    },
                    propsTemplate: 'vt-checklist-field-props-template',
                };
            }
        });
        </script>

        <style>
        .vt-checklist-items {
            margin-bottom: 15px;
        }
        .vt-checklist-item-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        .vt-checklist-item-inputs {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .vt-checklist-title-input,
        .vt-checklist-desc-input {
            width: 100%;
        }
        </style>
        <?php
    }

    /**
     * Register app event category
     */
    public function register_event_category($categories) {
        $post_types = \Voxel\Post_Type::get_voxel_types();

        if (empty($post_types)) {
            return $categories;
        }

        $first_post_type = reset($post_types);

        // Parent category
        $categories[sprintf('checklist:%s', $first_post_type->get_key())] = [
            'key' => sprintf('checklist:%s', $first_post_type->get_key()),
            'label' => __('Checklist (VT)', 'voxel-toolkit'),
        ];

        // Subcategories for each post type
        foreach ($post_types as $post_type) {
            $categories[sprintf('checklist:%s', $post_type->get_key())] = [
                'key' => sprintf('checklist:%s', $post_type->get_key()),
                'label' => sprintf('— %s', $post_type->get_label()),
            ];
        }

        return $categories;
    }

    /**
     * Register app events
     */
    public function register_app_events($events) {
        if (!class_exists('\\Voxel\\Events\\Base_Event')) {
            return $events;
        }

        // Register item checked event for each post type
        foreach (\Voxel\Post_Type::get_voxel_types() as $post_type) {
            $event = new Voxel_Toolkit_Checklist_Item_Checked_Event($post_type);
            $events[$event->get_key()] = $event;
        }

        return $events;
    }

    /**
     * Register dynamic tags for checklist field
     */
    public function register_dynamic_tags($groups, $field) {
        if ($field->get_type() !== 'checklist-vt') {
            return $groups;
        }

        $groups[] = new Voxel_Toolkit_Checklist_Dynamic_Tag_Group($field);

        return $groups;
    }
}

/**
 * Checklist Field Type Class
 *
 * The actual field type that integrates with Voxel.
 */
class Voxel_Toolkit_Checklist_Field_Type extends \Voxel\Post_Types\Fields\Base_Post_Field {

    protected $props = [
        'type' => 'checklist-vt',
        'label' => 'Checklist (VT)',
        'check_permission' => 'author',
        'check_scope' => 'global',
        'show_timestamps' => false,
    ];

    /**
     * Check if this field type is supported (enabled)
     */
    public function is_supported(): bool {
        $settings = Voxel_Toolkit_Settings::instance();
        return $settings->is_function_enabled('post_field_checklist_field');
    }

    /**
     * Get models for field configuration in admin
     */
    public function get_models(): array {
        return [
            'label' => $this->get_label_model(),
            'key' => $this->get_key_model(),
            'description' => $this->get_description_model(),
            'required' => $this->get_required_model(),
            'css_class' => $this->get_css_class_model(),
            'check_permission' => [
                'type' => \Voxel\Form_Models\Select_Model::class,
                'label' => 'Who can check items?',
                'classes' => 'x-col-6',
                'choices' => [
                    'author' => 'Post author only',
                    'logged_in' => 'Any logged-in user',
                ],
            ],
            'check_scope' => [
                'type' => \Voxel\Form_Models\Select_Model::class,
                'label' => 'Check scope',
                'classes' => 'x-col-6',
                'choices' => [
                    'global' => 'Global (shared checklist)',
                    'per_user' => 'Per-user (each user has own progress)',
                ],
            ],
            'show_timestamps' => [
                'type' => \Voxel\Form_Models\Switcher_Model::class,
                'label' => 'Show timestamps when items are checked',
                'classes' => 'x-col-12',
            ],
        ];
    }

    /**
     * Get field type label
     */
    public function get_label(): string {
        return $this->props['label'] ?? 'Checklist (VT)';
    }

    /**
     * Sanitize field value on save
     */
    public function sanitize($value) {
        if (!is_array($value)) {
            return array('items' => array());
        }

        $sanitized = array('items' => array());

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Only save title and description, not _key or other frontend properties
            $sanitized['items'][] = array(
                'title' => isset($item['title']) ? sanitize_text_field($item['title']) : '',
                'description' => isset($item['description']) ? sanitize_textarea_field($item['description']) : '',
                'checked' => false,
            );
        }

        return $sanitized;
    }

    /**
     * Validate field value
     */
    public function validate($value): void {
        // No validation needed - items are optional
    }

    /**
     * Get field value for frontend
     */
    public function get_value() {
        if (!$this->post) {
            return array();
        }

        $value = get_post_meta($this->post->get_id(), $this->get_key(), true);

        // Decode JSON if stored as string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value) || !isset($value['items'])) {
            return array();
        }

        return $value['items'];
    }

    /**
     * Check if field has content
     */
    public function has_content(): bool {
        $value = $this->get_value();
        return !empty($value);
    }

    /**
     * Get props for editor
     */
    public function get_props(): array {
        return $this->props;
    }

    /**
     * Export field value
     */
    public function exports(): array {
        return [
            'label' => $this->get_label(),
            'type' => $this->get_type(),
            'value' => $this->get_value(),
        ];
    }

    /**
     * Frontend props for create/edit form
     */
    protected function frontend_props(): array {
        return [
            'check_permission' => $this->props['check_permission'] ?? 'author',
            'check_scope' => $this->props['check_scope'] ?? 'global',
            'show_timestamps' => $this->props['show_timestamps'] ?? false,
        ];
    }

    /**
     * Check if editing is allowed
     */
    public function is_editable(): bool {
        return true;
    }

    /**
     * Get value from post for editing
     * Returns the items array for the Vue component
     */
    public function get_value_from_post() {
        if (!$this->post) {
            return [];
        }

        $value = get_post_meta($this->post->get_id(), $this->get_key(), true);

        // Decode JSON if stored as string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        // Return items array if properly structured
        if (is_array($value) && isset($value['items'])) {
            // Return just the items for editing (without checked status for clean editing)
            return array_map(function($item) {
                return [
                    'title' => $item['title'] ?? '',
                    'description' => $item['description'] ?? '',
                ];
            }, $value['items']);
        }

        return [];
    }

    /**
     * Update field value
     * Note: Voxel calls sanitize() before update(), so $value is already sanitized
     */
    public function update($value): void {
        $is_empty = !is_array($value) || empty($value['items']);

        if ($is_empty) {
            delete_post_meta($this->post->get_id(), $this->get_key());
        } else {
            $json = wp_json_encode($value);
            update_post_meta($this->post->get_id(), $this->get_key(), wp_slash($json));
        }
    }

    /**
     * Check if value is empty
     */
    public function is_empty($value): bool {
        if (!is_array($value)) {
            return true;
        }
        // Check if any items have content
        foreach ($value as $item) {
            if (!empty($item['description'])) {
                return false;
            }
        }
        return true;
    }
}

/**
 * Checklist Item Checked Event
 *
 * Triggered when a checklist item is checked off.
 */
class Voxel_Toolkit_Checklist_Item_Checked_Event extends \Voxel\Events\Base_Event {

    protected $post_type;
    protected $post;
    protected $user;
    protected $item_data;

    public function __construct($post_type) {
        $this->post_type = $post_type;
    }

    public function get_key(): string {
        return sprintf('checklist/%s/item:checked', $this->post_type->get_key());
    }

    public function get_label(): string {
        return __('Item Checked', 'voxel-toolkit');
    }

    public function get_category() {
        return sprintf('checklist:%s', $this->post_type->get_key());
    }

    protected function init(): void {
        add_action('voxel_toolkit/checklist/item_checked', function($data) {
            if (!isset($data['post']) || $data['post']->post_type->get_key() !== $this->post_type->get_key()) {
                return;
            }

            $this->post = $data['post'];
            $this->user = \Voxel\User::get($data['user_id']);
            $this->item_data = $data;
            $this->dispatch();
        });
    }

    public function prepare($data): void {
        $this->post = $data['post'];
        $this->user = \Voxel\User::get($data['user_id']);
        $this->item_data = $data;
    }

    protected function get_recipients(): array {
        return [
            'author' => [
                'label' => __('Notify post author', 'voxel-toolkit'),
                'callback' => function() {
                    if ($this->post && ($author = $this->post->get_author())) {
                        return [$author];
                    }
                    return [];
                },
                'default' => [
                    'enabled' => true,
                ],
            ],
            'admin' => [
                'label' => __('Notify admin', 'voxel-toolkit'),
                'callback' => function() {
                    $admin_email = get_option('admin_email');
                    if ($admin_email) {
                        return [['email' => $admin_email]];
                    }
                    return [];
                },
                'default' => [
                    'enabled' => false,
                ],
            ],
        ];
    }

    protected function get_default_message(): string {
        return __('A checklist item was checked on @post(:title)', 'voxel-toolkit');
    }

    protected function get_default_subject(): string {
        return __('Checklist item completed', 'voxel-toolkit');
    }

    public function dynamic_tags(): array {
        // Use mock data as fallback when event data isn't prepared
        $post = $this->post ?: \Voxel\Post::mock(['post_type' => $this->post_type->get_key()]);
        $author = $post ? $post->get_author() : \Voxel\User::mock();
        $user = $this->user ?: \Voxel\User::mock();

        return [
            'post' => \Voxel\Dynamic_Data\Group::Post($post),
            'author' => \Voxel\Dynamic_Data\Group::User($author ?: \Voxel\User::mock()),
            'user' => \Voxel\Dynamic_Data\Group::User($user),
            'checklist' => new Voxel_Toolkit_Checklist_Event_Data_Group($this->item_data),
        ];
    }

    public function set_mock_props(): void {
        $posts = get_posts([
            'post_type' => $this->post_type->get_key(),
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);

        if (!empty($posts)) {
            $this->post = \Voxel\Post::get($posts[0]);
        }

        $this->user = \Voxel\User::get(get_current_user_id());
        $this->item_data = [
            'item_title' => __('Sample checklist item', 'voxel-toolkit'),
            'item_description' => __('This is a sample item description.', 'voxel-toolkit'),
            'timestamp' => current_time('timestamp'),
        ];
    }
}

/**
 * Checklist Event Data Group
 *
 * Provides dynamic tags for checklist item data in app events.
 */
class Voxel_Toolkit_Checklist_Event_Data_Group extends \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group {

    protected $item_data;

    public function __construct($item_data = null) {
        $this->item_data = $item_data;
    }

    public function get_type(): string {
        return 'vt_checklist_event';
    }

    public function get_key(): string {
        return 'checklist';
    }

    public function get_label(): string {
        return __('Checklist Item', 'voxel-toolkit');
    }

    protected function properties(): array {
        $item_data = $this->item_data;
        return [
            'title' => \Voxel\Dynamic_Data\Tag::String(__('Item Title', 'voxel-toolkit'))->render(function() use ($item_data) {
                return $item_data['item_title'] ?? '';
            }),
            'description' => \Voxel\Dynamic_Data\Tag::String(__('Item Description', 'voxel-toolkit'))->render(function() use ($item_data) {
                return $item_data['item_description'] ?? '';
            }),
            'timestamp' => \Voxel\Dynamic_Data\Tag::String(__('Checked Timestamp', 'voxel-toolkit'))->render(function() use ($item_data) {
                if (isset($item_data['timestamp'])) {
                    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $item_data['timestamp']);
                }
                return '';
            }),
            'timestamp_raw' => \Voxel\Dynamic_Data\Tag::Number(__('Checked Timestamp (Unix)', 'voxel-toolkit'))->render(function() use ($item_data) {
                return $item_data['timestamp'] ?? '';
            }),
        ];
    }
}

/**
 * Checklist Dynamic Tag Group
 *
 * Provides dynamic tags for checklist field data.
 */
class Voxel_Toolkit_Checklist_Dynamic_Tag_Group extends \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group {

    protected $field;

    public function __construct($field) {
        $this->field = $field;
    }

    public function get_type(): string {
        return 'vt_checklist_field';
    }

    public function get_key(): string {
        return $this->field->get_key();
    }

    public function get_label(): string {
        return $this->field->get_label();
    }

    protected function properties(): array {
        $field = $this->field;
        $self = $this;
        return [
            'total' => \Voxel\Dynamic_Data\Tag::Number(__('Total Items', 'voxel-toolkit'))->render(function() use ($field) {
                $items = $field->get_value();
                return count($items);
            }),
            'checked' => \Voxel\Dynamic_Data\Tag::Number(__('Checked Items', 'voxel-toolkit'))->render(function() use ($self) {
                return $self->get_checked_count();
            }),
            'percentage' => \Voxel\Dynamic_Data\Tag::Number(__('Completion Percentage', 'voxel-toolkit'))->render(function() use ($field, $self) {
                $items = $field->get_value();
                $total = count($items);
                if ($total === 0) {
                    return 0;
                }
                $checked = $self->get_checked_count();
                return round(($checked / $total) * 100);
            }),
        ];
    }

    public function get_checked_count() {
        $post = $this->field->get_post();
        if (!$post) {
            return 0;
        }

        $props = $this->field->get_props();
        $scope = isset($props['check_scope']) ? $props['check_scope'] : 'global';
        $field_key = $this->field->get_key();
        $post_id = $post->get_id();

        $items = $this->field->get_value();
        $checked = 0;

        if ($scope === 'per_user') {
            $user_id = get_current_user_id();
            if ($user_id) {
                $user_checks = get_user_meta($user_id, '_vt_checklist_' . $post_id . '_' . $field_key, true);
                if (is_array($user_checks)) {
                    foreach ($items as $index => $item) {
                        if (isset($user_checks[$index]['checked']) && $user_checks[$index]['checked']) {
                            $checked++;
                        }
                    }
                }
            }
        } else {
            $checklist_data = get_post_meta($post_id, $field_key, true);
            if (is_array($checklist_data) && isset($checklist_data['items'])) {
                foreach ($checklist_data['items'] as $item) {
                    if (!empty($item['checked'])) {
                        $checked++;
                    }
                }
            }
        }

        return $checked;
    }
}
