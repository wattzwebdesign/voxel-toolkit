<?php
/**
 * Messages Admin - Main controller for the Messages admin page
 *
 * @package Voxel_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Voxel_Toolkit_Messages_Admin {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // AJAX handlers for row actions
        add_action( 'wp_ajax_vt_messages_mark_read', array( $this, 'ajax_mark_read' ) );
        add_action( 'wp_ajax_vt_messages_mark_unread', array( $this, 'ajax_mark_unread' ) );
        add_action( 'wp_ajax_vt_messages_delete', array( $this, 'ajax_delete_message' ) );
    }

    public function add_menu_page() {
        add_menu_page(
            __( 'Messages (VT)', 'voxel-toolkit' ),
            __( 'Messages (VT)', 'voxel-toolkit' ),
            'edit_others_posts',
            'voxel-toolkit-messages',
            array( $this, 'render_page' ),
            $this->get_menu_icon(),
            '0.286' // Position after Timeline (0.285)
        );
    }

    private function get_menu_icon() {
        $svg_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/icons/message-admin.svg';
        if ( file_exists( $svg_file ) ) {
            $svg = file_get_contents( $svg_file );
            // Add fill for WordPress admin compatibility
            $svg = str_replace( '<path', '<path fill="currentColor"', $svg );
            return sprintf( 'data:image/svg+xml;base64,%s', base64_encode( $svg ) );
        }
        // Fallback
        return 'dashicons-email';
    }

    public function render_page() {
        $table = new Voxel_Toolkit_Messages_Table();
        $table->prepare_items();
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/admin/messages/messages-template.php';
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_voxel-toolkit-messages' ) {
            return;
        }

        wp_enqueue_style(
            'vt-messages-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/messages-admin.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_enqueue_script(
            'vt-messages-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/messages-admin.js',
            array( 'jquery' ),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_localize_script( 'vt-messages-admin', 'vtMessagesAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'vt_messages_admin' ),
            'i18n'    => array(
                'confirmDelete' => __( 'Are you sure you want to delete this message? This action cannot be undone.', 'voxel-toolkit' ),
                'ajaxError'     => __( 'An error occurred. Please try again.', 'voxel-toolkit' ),
                'markingRead'   => __( 'Marking as read...', 'voxel-toolkit' ),
                'markingUnread' => __( 'Marking as unread...', 'voxel-toolkit' ),
                'deleting'      => __( 'Deleting...', 'voxel-toolkit' ),
            ),
        ) );
    }

    public function ajax_mark_read() {
        try {
            check_ajax_referer( 'vt_messages_admin', 'nonce' );

            if ( ! current_user_can( 'edit_others_posts' ) ) {
                throw new Exception( __( 'Permission denied.', 'voxel-toolkit' ) );
            }

            $message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
            if ( ! $message_id ) {
                throw new Exception( __( 'Invalid message ID.', 'voxel-toolkit' ) );
            }

            global $wpdb;
            $updated = $wpdb->update(
                $wpdb->prefix . 'voxel_messages',
                array( 'seen' => 1 ),
                array( 'id' => $message_id ),
                array( '%d' ),
                array( '%d' )
            );

            if ( $updated === false ) {
                throw new Exception( __( 'Failed to update message.', 'voxel-toolkit' ) );
            }

            wp_send_json_success( array(
                'message' => __( 'Message marked as read.', 'voxel-toolkit' ),
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => $e->getMessage(),
            ) );
        }
    }

    public function ajax_mark_unread() {
        try {
            check_ajax_referer( 'vt_messages_admin', 'nonce' );

            if ( ! current_user_can( 'edit_others_posts' ) ) {
                throw new Exception( __( 'Permission denied.', 'voxel-toolkit' ) );
            }

            $message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
            if ( ! $message_id ) {
                throw new Exception( __( 'Invalid message ID.', 'voxel-toolkit' ) );
            }

            global $wpdb;
            $updated = $wpdb->update(
                $wpdb->prefix . 'voxel_messages',
                array( 'seen' => 0 ),
                array( 'id' => $message_id ),
                array( '%d' ),
                array( '%d' )
            );

            if ( $updated === false ) {
                throw new Exception( __( 'Failed to update message.', 'voxel-toolkit' ) );
            }

            wp_send_json_success( array(
                'message' => __( 'Message marked as unread.', 'voxel-toolkit' ),
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => $e->getMessage(),
            ) );
        }
    }

    public function ajax_delete_message() {
        try {
            check_ajax_referer( 'vt_messages_admin', 'nonce' );

            if ( ! current_user_can( 'edit_others_posts' ) ) {
                throw new Exception( __( 'Permission denied.', 'voxel-toolkit' ) );
            }

            $message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
            if ( ! $message_id ) {
                throw new Exception( __( 'Invalid message ID.', 'voxel-toolkit' ) );
            }

            // Use Voxel's Message class for deletion if available
            if ( class_exists( '\Voxel\Modules\Direct_Messages\Message' ) ) {
                $message = \Voxel\Modules\Direct_Messages\Message::get( $message_id );
                if ( ! $message ) {
                    throw new Exception( __( 'Message not found.', 'voxel-toolkit' ) );
                }
                $message->delete();
            } else {
                // Fallback to direct deletion
                global $wpdb;
                $deleted = $wpdb->delete(
                    $wpdb->prefix . 'voxel_messages',
                    array( 'id' => $message_id ),
                    array( '%d' )
                );

                if ( $deleted === false ) {
                    throw new Exception( __( 'Failed to delete message.', 'voxel-toolkit' ) );
                }
            }

            wp_send_json_success( array(
                'message' => __( 'Message deleted.', 'voxel-toolkit' ),
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => $e->getMessage(),
            ) );
        }
    }
}
