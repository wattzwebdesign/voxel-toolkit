<?php
/**
 * Messages Table - WP_List_Table implementation for displaying messages
 *
 * @package Voxel_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Voxel_Toolkit_Messages_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'message',
            'plural'   => 'messages',
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'cb'       => '<input type="checkbox">',
            'sender'   => __( 'Sender', 'voxel-toolkit' ),
            'receiver' => __( 'Receiver', 'voxel-toolkit' ),
            'content'  => __( 'Content', 'voxel-toolkit' ),
            'status'   => __( 'Status', 'voxel-toolkit' ),
            'date'     => __( 'Sent on', 'voxel-toolkit' ),
        );
    }

    protected function get_sortable_columns() {
        return array(
            'date' => array( 'date', 'desc' ),
        );
    }

    protected function column_cb( $message ) {
        return sprintf( '<input type="checkbox" name="items[]" value="%d">', $message->get_id() );
    }

    protected function column_sender( $message ) {
        $sender = $message->get_sender();
        $sender_type = $message->get_sender_type();
        $sender_id = $message->get_sender_id();

        ob_start();
        ?>
        <div class="vt-message-party">
            <?php if ( $sender ): ?>
                <?= $sender->get_avatar_markup( 24 ) ?>
                <div class="item-title">
                    <a href="<?= esc_url( $sender->get_edit_link() ) ?>">
                        <b><?= esc_html( $sender->get_display_name() ) ?></b>
                    </a>
                    <div class="item-subtitle">
                        <?= $sender_type === 'post' ? __( 'Post', 'voxel-toolkit' ) : __( 'User', 'voxel-toolkit' ) ?>
                        (ID: <?= esc_html( $sender_id ) ?>)
                    </div>
                    <div class="row-actions" data-message-id="<?= esc_attr( $message->get_id() ) ?>">
                        <?php if ( ! $message->is_seen() ): ?>
                            <span>
                                <a class="msg__action" data-action="mark_read" href="#"><?php _e( 'Mark Read', 'voxel-toolkit' ); ?></a> |
                            </span>
                        <?php else: ?>
                            <span>
                                <a class="msg__action" data-action="mark_unread" href="#"><?php _e( 'Mark Unread', 'voxel-toolkit' ); ?></a> |
                            </span>
                        <?php endif; ?>
                        <span>
                            <a class="msg__action msg__action-delete" data-action="delete" href="#"><?php _e( 'Delete', 'voxel-toolkit' ); ?></a>
                        </span>
                        <span class="vt-message-id">
                            | ID: <?= esc_html( $message->get_id() ) ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <span class="vt-deleted-party">&mdash; <?php _e( 'Deleted', 'voxel-toolkit' ); ?></span>
                <div class="item-subtitle">
                    <?= $sender_type === 'post' ? __( 'Post', 'voxel-toolkit' ) : __( 'User', 'voxel-toolkit' ) ?>
                    (ID: <?= esc_html( $sender_id ) ?>)
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function column_receiver( $message ) {
        $receiver = $message->get_receiver();
        $receiver_type = $message->get_receiver_type();
        $receiver_id = $message->get_receiver_id();

        ob_start();
        ?>
        <div class="vt-message-party">
            <?php if ( $receiver ): ?>
                <?= $receiver->get_avatar_markup( 24 ) ?>
                <div class="item-title">
                    <a href="<?= esc_url( $receiver->get_link() ) ?>" target="_blank">
                        <b><?= esc_html( $receiver->get_display_name() ) ?></b>
                    </a>
                    <div class="item-subtitle">
                        <?= $receiver_type === 'post' ? __( 'Post', 'voxel-toolkit' ) : __( 'User', 'voxel-toolkit' ) ?>
                        (ID: <?= esc_html( $receiver_id ) ?>)
                    </div>
                </div>
            <?php else: ?>
                <span class="vt-deleted-party">&mdash; <?php _e( 'Deleted', 'voxel-toolkit' ); ?></span>
                <div class="item-subtitle">
                    <?= $receiver_type === 'post' ? __( 'Post', 'voxel-toolkit' ) : __( 'User', 'voxel-toolkit' ) ?>
                    (ID: <?= esc_html( $receiver_id ) ?>)
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function column_content( $message ) {
        $content = $message->get_content_for_display();
        $details = $message->get_details();

        ob_start();
        ?>
        <?php if ( ! empty( $content ) ): ?>
            <div class="vt-message-content"><?= $content ?></div>
        <?php endif; ?>

        <?php if ( ! empty( $details['files'] ) ): ?>
            <div class="vt-message-files">
                <label><b><?php _e( 'Attachments', 'voxel-toolkit' ); ?></b></label>
                <?php
                $file_ids = explode( ',', (string) $details['files'] );
                $file_ids = array_filter( array_map( 'absint', $file_ids ) );
                foreach ( $file_ids as $file_id ):
                    $file_url = wp_get_attachment_url( $file_id );
                    $file_name = basename( get_attached_file( $file_id ) );
                    if ( $file_url ):
                ?>
                    <a href="<?= esc_url( $file_url ) ?>" target="_blank"><?= esc_html( $file_name ) ?></a>
                <?php endif; endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( empty( $content ) && empty( $details['files'] ) ): ?>
            <span class="vt-empty-content">&mdash;</span>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    protected function column_status( $message ) {
        if ( $message->is_seen() ) {
            return '<span class="vt-status-badge vt-status-badge--read">' . __( 'Read', 'voxel-toolkit' ) . '</span>';
        } else {
            return '<span class="vt-status-badge vt-status-badge--unread">' . __( 'Unread', 'voxel-toolkit' ) . '</span>';
        }
    }

    protected function column_date( $message ) {
        $created_at = strtotime( $message->get_created_at() );
        return sprintf(
            '%s %s',
            date_i18n( 'Y/m/d', $created_at ),
            date_i18n( get_option( 'time_format' ), $created_at )
        );
    }

    protected function column_default( $message, $column_name ) {
        return '&mdash;';
    }

    protected function get_views() {
        global $wpdb;

        $counts = $wpdb->get_results( "
            SELECT seen, COUNT(*) AS total
            FROM {$wpdb->prefix}voxel_messages
            GROUP BY seen
        " );

        $read_count = 0;
        $unread_count = 0;
        $total_count = 0;

        foreach ( $counts as $count ) {
            if ( $count->seen == 1 ) {
                $read_count = absint( $count->total );
            } else {
                $unread_count = absint( $count->total );
            }
            $total_count += absint( $count->total );
        }

        $active = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : null;

        $views = array();

        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
            admin_url( 'admin.php?page=voxel-toolkit-messages' ),
            empty( $active ) ? 'current' : '',
            __( 'All', 'voxel-toolkit' ),
            number_format_i18n( $total_count )
        );

        $views['unread'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
            admin_url( 'admin.php?page=voxel-toolkit-messages&status=unread' ),
            $active === 'unread' ? 'current' : '',
            __( 'Unread', 'voxel-toolkit' ),
            number_format_i18n( $unread_count )
        );

        $views['read'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
            admin_url( 'admin.php?page=voxel-toolkit-messages&status=read' ),
            $active === 'read' ? 'current' : '',
            __( 'Read', 'voxel-toolkit' ),
            number_format_i18n( $read_count )
        );

        return $views;
    }

    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) {
            return;
        }

        $search_id = isset( $_GET['search_id'] ) ? sanitize_text_field( $_GET['search_id'] ) : '';
        $search_sender_id = isset( $_GET['search_sender_id'] ) ? sanitize_text_field( $_GET['search_sender_id'] ) : '';
        $search_receiver_id = isset( $_GET['search_receiver_id'] ) ? sanitize_text_field( $_GET['search_receiver_id'] ) : '';
        $search_sender_type = isset( $_GET['search_sender_type'] ) ? sanitize_text_field( $_GET['search_sender_type'] ) : '';
        ?>
        <input type="number" name="search_id" placeholder="<?php esc_attr_e( 'Message ID', 'voxel-toolkit' ); ?>" value="<?= esc_attr( $search_id ) ?>">
        <input type="number" name="search_sender_id" placeholder="<?php esc_attr_e( 'Sender ID', 'voxel-toolkit' ); ?>" value="<?= esc_attr( $search_sender_id ) ?>">
        <input type="number" name="search_receiver_id" placeholder="<?php esc_attr_e( 'Receiver ID', 'voxel-toolkit' ); ?>" value="<?= esc_attr( $search_receiver_id ) ?>">
        <select name="search_sender_type">
            <option value=""><?php _e( 'All sender types', 'voxel-toolkit' ); ?></option>
            <option value="user" <?php selected( $search_sender_type, 'user' ); ?>><?php _e( 'User', 'voxel-toolkit' ); ?></option>
            <option value="post" <?php selected( $search_sender_type, 'post' ); ?>><?php _e( 'Post', 'voxel-toolkit' ); ?></option>
        </select>
        <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'voxel-toolkit' ); ?>">
        <?php
    }

    public function get_bulk_actions() {
        return array(
            'mark_read'   => __( 'Mark as read', 'voxel-toolkit' ),
            'mark_unread' => __( 'Mark as unread', 'voxel-toolkit' ),
            'delete'      => __( 'Delete', 'voxel-toolkit' ),
        );
    }

    public function process_bulk_action() {
        $action = $this->current_action();
        if ( empty( $action ) ) {
            return;
        }

        check_admin_referer( 'bulk-' . $this->_args['plural'] );

        if ( empty( $_GET['items'] ) || ! is_array( $_GET['items'] ) ) {
            return;
        }

        $item_ids = array_map( 'absint', $_GET['items'] );
        $item_ids = array_filter( $item_ids );

        if ( empty( $item_ids ) ) {
            return;
        }

        global $wpdb;
        $ids_placeholder = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );

        switch ( $action ) {
            case 'mark_read':
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}voxel_messages SET seen = 1 WHERE id IN ({$ids_placeholder})",
                    ...$item_ids
                ) );
                break;

            case 'mark_unread':
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}voxel_messages SET seen = 0 WHERE id IN ({$ids_placeholder})",
                    ...$item_ids
                ) );
                break;

            case 'delete':
                if ( class_exists( '\Voxel\Modules\Direct_Messages\Message' ) ) {
                    foreach ( $item_ids as $message_id ) {
                        $message = \Voxel\Modules\Direct_Messages\Message::get( $message_id );
                        if ( $message ) {
                            $message->delete();
                        }
                    }
                } else {
                    $wpdb->query( $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}voxel_messages WHERE id IN ({$ids_placeholder})",
                        ...$item_ids
                    ) );
                }
                break;
        }

        // Redirect to clean URL
        ?>
        <script type="text/javascript">
            jQuery(function() {
                const url = new URL(window.location.href);
                url.searchParams.delete('action');
                url.searchParams.delete('action2');
                url.searchParams.delete('items');
                url.searchParams.delete('_wpnonce');
                window.location.href = url.toString();
            });
        </script>
        <?php
    }

    public function prepare_items() {
        // Handle bulk actions first
        $this->process_bulk_action();

        global $wpdb;

        $page = $this->get_pagenum();
        $per_page = 25;
        $offset = $per_page * ( $page - 1 );

        // Build WHERE clauses
        $where_clauses = array( '1=1' );

        // Status filter (read/unread)
        $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : null;
        if ( $status === 'read' ) {
            $where_clauses[] = 'seen = 1';
        } elseif ( $status === 'unread' ) {
            $where_clauses[] = 'seen = 0';
        }

        // Search filters
        if ( ! empty( $_GET['search_id'] ) && is_numeric( $_GET['search_id'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'id = %d', absint( $_GET['search_id'] ) );
        }

        if ( ! empty( $_GET['search_sender_id'] ) && is_numeric( $_GET['search_sender_id'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'sender_id = %d', absint( $_GET['search_sender_id'] ) );
        }

        if ( ! empty( $_GET['search_receiver_id'] ) && is_numeric( $_GET['search_receiver_id'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'receiver_id = %d', absint( $_GET['search_receiver_id'] ) );
        }

        if ( ! empty( $_GET['search_sender_type'] ) && in_array( $_GET['search_sender_type'], array( 'user', 'post' ), true ) ) {
            $where_clauses[] = $wpdb->prepare( 'sender_type = %s', sanitize_text_field( $_GET['search_sender_type'] ) );
        }

        // Search query (content search)
        if ( ! empty( $_REQUEST['s'] ) && is_string( $_REQUEST['s'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) . '%';
            $where_clauses[] = $wpdb->prepare( 'content LIKE %s', $search );
        }

        $where = implode( ' AND ', $where_clauses );

        // Order
        $order = ( isset( $_GET['order'] ) && $_GET['order'] === 'asc' ) ? 'ASC' : 'DESC';

        // Get total count
        $total_items = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$wpdb->prefix}voxel_messages WHERE {$where}
        " );

        // Get items
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT * FROM {$wpdb->prefix}voxel_messages
            WHERE {$where}
            ORDER BY created_at {$order}
            LIMIT %d OFFSET %d
        ", $per_page, $offset ), ARRAY_A );

        // Convert to Message objects
        if ( class_exists( '\Voxel\Modules\Direct_Messages\Message' ) ) {
            $this->items = array_map( function( $row ) {
                return \Voxel\Modules\Direct_Messages\Message::get( $row );
            }, $results );
        } else {
            $this->items = array();
        }

        // Set pagination
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );

        // Set column headers
        $this->_column_headers = array(
            $this->get_columns(),
            array(), // Hidden columns
            $this->get_sortable_columns(),
        );
    }
}
