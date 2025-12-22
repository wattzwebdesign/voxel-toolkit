<?php
/**
 * Messages Admin Template
 *
 * @package Voxel_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap vt-messages-wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />

        <?php if ( isset( $_GET['status'] ) ): ?>
            <input type="hidden" name="status" value="<?php echo esc_attr( $_GET['status'] ); ?>" />
        <?php endif; ?>

        <?php $table->views(); ?>
        <?php $table->search_box( __( 'Search Messages', 'voxel-toolkit' ), 'search' ); ?>
        <?php $table->display(); ?>
    </form>
</div>
