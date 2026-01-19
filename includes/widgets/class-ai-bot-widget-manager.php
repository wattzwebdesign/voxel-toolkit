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
                'panelBehavior' => isset($ai_bot_settings['panel_behavior']) ? $ai_bot_settings['panel_behavior'] : 'push',
                'welcomeMessage' => $ai_bot_settings['welcome_message'],
                'placeholderText' => $ai_bot_settings['placeholder_text'],
                'panelTitle' => $ai_bot_settings['panel_title'],
                'suggestedQueries' => isset($ai_bot_settings['suggested_queries']) ? (array) $ai_bot_settings['suggested_queries'] : array(),
                'conversationMemory' => (bool) $ai_bot_settings['conversation_memory'],
                'maxMemoryMessages' => absint($ai_bot_settings['max_memory_messages']),
                'accessControl' => $ai_bot_settings['access_control'],
            ),
            'i18n' => $this->get_localized_strings($ai_bot_settings),
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
     * Get localized strings based on AI response language setting
     *
     * @param array $ai_bot_settings AI Bot settings
     * @return array Localized strings
     */
    private function get_localized_strings($ai_bot_settings) {
        // Get the AI response language setting
        $ai_settings = Voxel_Toolkit_Settings::instance()->get_function_settings('ai_settings', array());
        $language_code = isset($ai_settings['response_language']) ? $ai_settings['response_language'] : 'en';

        // Localized strings for each supported language
        $localized = array(
            'en' => array(
                'noResults' => "I couldn't find any matching results. Try rephrasing your question.",
                'error' => 'Something went wrong. Please try again.',
                'rateLimit' => 'Please wait a moment before asking again.',
                'loginRequired' => 'Please log in to use the AI assistant.',
            ),
            'ru' => array(
                'noResults' => 'Не удалось найти подходящие результаты. Попробуйте переформулировать вопрос.',
                'error' => 'Что-то пошло не так. Пожалуйста, попробуйте снова.',
                'rateLimit' => 'Пожалуйста, подождите немного перед следующим вопросом.',
                'loginRequired' => 'Пожалуйста, войдите в систему, чтобы использовать ИИ-ассистент.',
            ),
            'uk' => array(
                'noResults' => 'Не вдалося знайти відповідні результати. Спробуйте перефразувати запитання.',
                'error' => 'Щось пішло не так. Будь ласка, спробуйте ще раз.',
                'rateLimit' => 'Будь ласка, зачекайте трохи перед наступним запитанням.',
                'loginRequired' => 'Будь ласка, увійдіть, щоб використовувати ШІ-асистент.',
            ),
            'es' => array(
                'noResults' => 'No pude encontrar resultados coincidentes. Intenta reformular tu pregunta.',
                'error' => 'Algo salió mal. Por favor, inténtalo de nuevo.',
                'rateLimit' => 'Por favor, espera un momento antes de preguntar de nuevo.',
                'loginRequired' => 'Por favor, inicia sesión para usar el asistente de IA.',
            ),
            'fr' => array(
                'noResults' => "Je n'ai pas trouvé de résultats correspondants. Essayez de reformuler votre question.",
                'error' => "Une erreur s'est produite. Veuillez réessayer.",
                'rateLimit' => 'Veuillez patienter un moment avant de poser une nouvelle question.',
                'loginRequired' => "Veuillez vous connecter pour utiliser l'assistant IA.",
            ),
            'de' => array(
                'noResults' => 'Keine passenden Ergebnisse gefunden. Versuchen Sie, Ihre Frage umzuformulieren.',
                'error' => 'Etwas ist schief gelaufen. Bitte versuchen Sie es erneut.',
                'rateLimit' => 'Bitte warten Sie einen Moment, bevor Sie erneut fragen.',
                'loginRequired' => 'Bitte melden Sie sich an, um den KI-Assistenten zu nutzen.',
            ),
            'pl' => array(
                'noResults' => 'Nie znaleziono pasujących wyników. Spróbuj przeformułować pytanie.',
                'error' => 'Coś poszło nie tak. Spróbuj ponownie.',
                'rateLimit' => 'Poczekaj chwilę przed zadaniem kolejnego pytania.',
                'loginRequired' => 'Zaloguj się, aby korzystać z asystenta AI.',
            ),
            'pt' => array(
                'noResults' => 'Não encontrei resultados correspondentes. Tente reformular sua pergunta.',
                'error' => 'Algo deu errado. Por favor, tente novamente.',
                'rateLimit' => 'Por favor, aguarde um momento antes de perguntar novamente.',
                'loginRequired' => 'Por favor, faça login para usar o assistente de IA.',
            ),
            'it' => array(
                'noResults' => 'Non ho trovato risultati corrispondenti. Prova a riformulare la tua domanda.',
                'error' => 'Qualcosa è andato storto. Per favore riprova.',
                'rateLimit' => 'Per favore attendi un momento prima di chiedere di nuovo.',
                'loginRequired' => "Per favore accedi per utilizzare l'assistente AI.",
            ),
            'nl' => array(
                'noResults' => 'Geen overeenkomende resultaten gevonden. Probeer je vraag anders te formuleren.',
                'error' => 'Er is iets misgegaan. Probeer het opnieuw.',
                'rateLimit' => 'Wacht even voordat je opnieuw vraagt.',
                'loginRequired' => 'Log in om de AI-assistent te gebruiken.',
            ),
            'tr' => array(
                'noResults' => 'Eşleşen sonuç bulunamadı. Sorunuzu yeniden ifade etmeyi deneyin.',
                'error' => 'Bir şeyler yanlış gitti. Lütfen tekrar deneyin.',
                'rateLimit' => 'Lütfen tekrar sormadan önce bir süre bekleyin.',
                'loginRequired' => 'Yapay zeka asistanını kullanmak için lütfen giriş yapın.',
            ),
            'ar' => array(
                'noResults' => 'لم أتمكن من العثور على نتائج مطابقة. حاول إعادة صياغة سؤالك.',
                'error' => 'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
                'rateLimit' => 'يرجى الانتظار لحظة قبل السؤال مرة أخرى.',
                'loginRequired' => 'يرجى تسجيل الدخول لاستخدام مساعد الذكاء الاصطناعي.',
            ),
            'ja' => array(
                'noResults' => '一致する結果が見つかりませんでした。質問を言い換えてみてください。',
                'error' => '問題が発生しました。もう一度お試しください。',
                'rateLimit' => '次の質問をする前に少しお待ちください。',
                'loginRequired' => 'AIアシスタントを使用するにはログインしてください。',
            ),
            'ko' => array(
                'noResults' => '일치하는 결과를 찾을 수 없습니다. 질문을 다시 작성해 보세요.',
                'error' => '문제가 발생했습니다. 다시 시도해 주세요.',
                'rateLimit' => '다시 질문하기 전에 잠시 기다려 주세요.',
                'loginRequired' => 'AI 어시스턴트를 사용하려면 로그인하세요.',
            ),
            'zh' => array(
                'noResults' => '未找到匹配结果。请尝试重新表述您的问题。',
                'error' => '出了点问题。请重试。',
                'rateLimit' => '请稍等片刻再提问。',
                'loginRequired' => '请登录以使用AI助手。',
            ),
        );

        // Get strings for the selected language, fallback to English
        $strings = isset($localized[$language_code]) ? $localized[$language_code] : $localized['en'];

        return array(
            'send' => __('Send', 'voxel-toolkit'),
            'thinking' => isset($ai_bot_settings['thinking_text']) && !empty($ai_bot_settings['thinking_text']) ? $ai_bot_settings['thinking_text'] : __('AI is thinking', 'voxel-toolkit'),
            'error' => $strings['error'],
            'rateLimit' => $strings['rateLimit'],
            'loginRequired' => $strings['loginRequired'],
            'noResults' => $strings['noResults'],
            'close' => __('Close', 'voxel-toolkit'),
        );
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

        // Get icon and text settings from action config
        $initial_icon = !empty($action['ts_acw_initial_icon']['value']) ? $action['ts_acw_initial_icon'] : null;
        $initial_text = isset($action['ts_acw_initial_text']) && $action['ts_acw_initial_text'] !== '' ? $action['ts_acw_initial_text'] : '';
        ?>
        <li class="elementor-repeater-item-<?php echo esc_attr($action['_id']); ?> flexify ts-action">
            <a href="#"
               class="ts-action-con vt-ai-bot-trigger"
               role="button">
                <?php if ($initial_icon) : ?>
                    <div class="ts-action-icon"><?php \Voxel\render_icon($initial_icon); ?></div>
                <?php endif; ?>
                <?php if ($initial_text !== '') : ?>
                    <?php echo esc_html($initial_text); ?>
                <?php endif; ?>
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

        // Styling options
        $style_primary_color = isset($settings['style_primary_color']) ? $settings['style_primary_color'] : '#0084ff';
        $style_header_text_color = isset($settings['style_header_text_color']) ? $settings['style_header_text_color'] : '#ffffff';
        $style_ai_bubble_color = isset($settings['style_ai_bubble_color']) ? $settings['style_ai_bubble_color'] : '#f0f2f5';
        $style_ai_text_color = isset($settings['style_ai_text_color']) ? $settings['style_ai_text_color'] : '#050505';
        $style_user_bubble_color = isset($settings['style_user_bubble_color']) ? $settings['style_user_bubble_color'] : '#0084ff';
        $style_user_text_color = isset($settings['style_user_text_color']) ? $settings['style_user_text_color'] : '#ffffff';
        $style_panel_width = isset($settings['style_panel_width']) ? absint($settings['style_panel_width']) : 400;
        $style_font_size = isset($settings['style_font_size']) ? absint($settings['style_font_size']) : 14;
        $style_border_radius = isset($settings['style_border_radius']) ? absint($settings['style_border_radius']) : 18;
        ?>
        <div class="vt-ai-bot-container position-<?php echo esc_attr($panel_position); ?>"
             data-position="<?php echo esc_attr($panel_position); ?>"
             data-welcome="<?php echo esc_attr($settings['welcome_message']); ?>"
             data-placeholder="<?php echo esc_attr($placeholder); ?>"
             style="--vt-ai-primary: <?php echo esc_attr($style_primary_color); ?>;
                    --vt-ai-header-text: <?php echo esc_attr($style_header_text_color); ?>;
                    --vt-ai-bubble: <?php echo esc_attr($style_ai_bubble_color); ?>;
                    --vt-ai-text: <?php echo esc_attr($style_ai_text_color); ?>;
                    --vt-ai-user-bubble: <?php echo esc_attr($style_user_bubble_color); ?>;
                    --vt-ai-user-text: <?php echo esc_attr($style_user_text_color); ?>;
                    --vt-ai-panel-width: <?php echo esc_attr($style_panel_width); ?>px;
                    --vt-ai-font-size: <?php echo esc_attr($style_font_size); ?>px;
                    --vt-ai-border-radius: <?php echo esc_attr($style_border_radius); ?>px;">

            <!-- Panel -->
            <div class="vt-ai-bot-panel">
                <!-- Header -->
                <div class="vt-ai-bot-header">
                    <span class="vt-ai-bot-header-title"><?php echo esc_html($panel_title); ?></span>
                    <button type="button" class="vt-ai-bot-close" aria-label="<?php esc_attr_e('Close', 'voxel-toolkit'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
