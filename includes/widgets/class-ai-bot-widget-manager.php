<?php
/**
 * AI Bot Widget Manager
 *
 * Manages the AI Bot functionality including:
 * - Action (VX) widget integration
 * - Panel rendering in footer
 * - Script/style registration
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_AI_Bot_Widget_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Render panel in footer
        add_action('wp_footer', array($this, 'render_panel'));

        // Voxel Action (VX) widget integration
        add_filter('voxel/advanced-list/actions', array($this, 'register_ai_bot_action'));
        add_action('voxel/advanced-list/action:open_ai_assistant', array($this, 'render_ai_bot_action'), 10, 2);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        // Enqueue styles
        wp_enqueue_style(
            'voxel-toolkit-ai-bot',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/ai-bot.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'voxel-toolkit-ai-bot',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/ai-bot.js',
            array('jquery', 'wp-util'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Get AI Bot settings
        $ai_bot_settings = $this->get_settings();

        // Localize script with configuration
        wp_localize_script('voxel-toolkit-ai-bot', 'vtAiBot', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_ai_bot'),
            'isLoggedIn' => is_user_logged_in(),
            'settings' => array(
                'panelPosition' => $ai_bot_settings['panel_position'],
                'welcomeMessage' => $ai_bot_settings['welcome_message'],
                'placeholderText' => $ai_bot_settings['placeholder_text'],
                'panelTitle' => $ai_bot_settings['panel_title'],
                'conversationMemory' => (bool) $ai_bot_settings['conversation_memory'],
                'maxMemoryMessages' => absint($ai_bot_settings['max_memory_messages']),
                'accessControl' => $ai_bot_settings['access_control'],
            ),
            'i18n' => array(
                'send' => __('Send', 'voxel-toolkit'),
                'thinking' => __('Thinking...', 'voxel-toolkit'),
                'error' => __('Something went wrong. Please try again.', 'voxel-toolkit'),
                'rateLimit' => __('Please wait a moment before asking again.', 'voxel-toolkit'),
                'loginRequired' => __('Please log in to use the AI assistant.', 'voxel-toolkit'),
                'noResults' => __('I couldn\'t find any matching results. Try rephrasing your question.', 'voxel-toolkit'),
                'close' => __('Close', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * Get AI Bot settings with defaults
     */
    private function get_settings() {
        $defaults = array(
            'panel_position' => 'right',
            'access_control' => 'everyone',
            'welcome_message' => __('Hi! How can I help you find what you\'re looking for?', 'voxel-toolkit'),
            'placeholder_text' => __('Ask me anything...', 'voxel-toolkit'),
            'panel_title' => __('AI Assistant', 'voxel-toolkit'),
            'conversation_memory' => true,
            'max_memory_messages' => 10,
        );

        $settings = Voxel_Toolkit_Settings::instance()->get_function_settings('ai_bot', array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Register "Open AI Assistant" action in Voxel Action (VX) widget
     *
     * @param array $actions Available actions
     * @return array Modified actions
     */
    public function register_ai_bot_action($actions) {
        $actions['open_ai_assistant'] = __('Open AI Assistant', 'voxel-toolkit');
        return $actions;
    }

    /**
     * Render "Open AI Assistant" action in Voxel Action (VX) widget
     *
     * @param object $widget Widget instance
     * @param array $action Action settings
     */
    public function render_ai_bot_action($widget, $action) {
        $settings = $this->get_settings();

        // Check access
        if ($settings['access_control'] === 'logged_in' && !is_user_logged_in()) {
            return;
        }

        // Get icon settings from action config
        $initial_icon = !empty($action['ts_acw_initial_icon']['value']) ? $action['ts_acw_initial_icon'] : ['library' => 'la-solid', 'value' => 'las la-robot'];
        $initial_text = !empty($action['ts_acw_initial_text']) ? $action['ts_acw_initial_text'] : __('Ask AI', 'voxel-toolkit');
        ?>
        <li class="elementor-repeater-item-<?php echo esc_attr($action['_id']); ?> flexify ts-action">
            <a href="#"
               class="ts-action-con vt-ai-bot-trigger"
               role="button">
                <div class="ts-action-icon"><?php \Voxel\render_icon($initial_icon); ?></div>
                <?php echo esc_html($initial_text); ?>
            </a>
        </li>
        <?php
    }

    /**
     * Render the AI Bot panel in footer
     */
    public function render_panel() {
        $settings = $this->get_settings();

        // Check access
        if ($settings['access_control'] === 'logged_in' && !is_user_logged_in()) {
            return;
        }

        $panel_position = $settings['panel_position'];
        $panel_title = $settings['panel_title'];
        $placeholder = $settings['placeholder_text'];
        ?>
        <div class="vt-ai-bot-container position-<?php echo esc_attr($panel_position); ?>"
             data-position="<?php echo esc_attr($panel_position); ?>"
             data-welcome="<?php echo esc_attr($settings['welcome_message']); ?>"
             data-placeholder="<?php echo esc_attr($placeholder); ?>">

            <!-- Panel -->
            <div class="vt-ai-bot-panel">
                <!-- Header -->
                <div class="vt-ai-bot-header">
                    <span class="vt-ai-bot-header-title"><?php echo esc_html($panel_title); ?></span>
                    <button type="button" class="vt-ai-bot-close" aria-label="<?php esc_attr_e('Close', 'voxel-toolkit'); ?>">
                        <i class="las la-times"></i>
                    </button>
                </div>

                <!-- Messages Area -->
                <div class="vt-ai-bot-messages">
                    <!-- Welcome message will be added by JS -->
                </div>

                <!-- Input Area -->
                <div class="vt-ai-bot-input-area">
                    <form class="vt-ai-bot-form">
                        <input type="text"
                               class="vt-ai-bot-input"
                               placeholder="<?php echo esc_attr($placeholder); ?>"
                               autocomplete="off">
                        <button type="submit" class="vt-ai-bot-send" aria-label="<?php esc_attr_e('Send', 'voxel-toolkit'); ?>">
                            <i class="las la-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
