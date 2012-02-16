<?php
add_action('admin_menu', 'hlik_admin_page');

function hlik_admin_page() {
    if (function_exists('add_menu_page'))
        add_menu_page(__('HotLink Image Cacher'), __('HLIC'), 'administrator', "hlik-admin-main", "hlik_admin_main", '', 130);
        add_submenu_page('hlik-admin-main', __('HotLink Image Cacher'), __('HotLink Image Cacher'), 'manage_options', 'hlik-admin-main', 'hlik_admin_main');
        add_submenu_page('hlik-admin-main', __('Image cache keywords'), __('Image cache keywords'), 'manage_options', 'hlik-admin-cachekw', 'hlik_admin_cache_kw');
}

function hlik_admin_cache_kw() {
     if (isset($_POST['submit'])) {
        if (function_exists('current_user_can') && !current_user_can('administrator'))
            die(__('Cheatin&#8217; uh?'));
        //process here
        if ( isset( $_POST['hlik_image_keywords'] ) ){
            update_option( 'hlik_image_keywords', hlik_cleanup_text($_POST['hlik_image_keywords']));
        }
    }
    ?>
    <?php if (!empty($_POST['submit'])) : ?>
        <div id="message" class="updated fade"><p><strong><?php _e('Saved Image Cache Keywords.') ?></strong></p></div>
    <?php endif; ?>
    <div class="wrap">
        <h2><?php _e('HotLink Cache Image Keywords settings'); ?></h2>
        <div class="narrow">
        <form action="" method="post" id="pap-process" style="margin: auto; width: 400px; ">
            <h3><label for="keywords"><?php _e('Image Cache Keywords'); ?></label></h3>
            <p><textarea id="keywords" name="hlik_image_keywords" rows="10" cols="90"><?php echo get_option('hlik_image_keywords'); ?></textarea> (<?php _e('Separate Keywords by a comma'); ?>)</p>
            <p class="submit"><input type="submit" name="submit" value="<?php _e('Save Keywords&raquo;'); ?>" /></p>
        </form>
        </div>
    </div>
    <?php
}

function hlik_admin_main(){
    global $wpdb;
    ?>
    <div class="wrap">
        <h2>HotLink Image Cacher</h2>
        <?php
        set_time_limit(0);
        if ($_POST['step'] == '2') {
            $url_method = trim($_POST['url_method']);
            $postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE post_content LIKE ('%<img%')");
            if (!$postid_list){
                    die('No posts with images were found.');
            }
            foreach ($postid_list as $v) {
                $post_id = $v->ID;
                $options['url_method'] = $url_method;
                hlik_cache_img($post_id, $options);
            }
            ?>
            <div id="message" class="updated fade"><p><strong>Processed all post</strong></p></div>
            <?php
        }
        ?>
        <?php if (!isset($_POST['step'])):?>
            <p>Clicking on Process All Posts will process all posts with hotlinked images.</p>
            <div class="narrow">
                <form action="" method="post">
                    <input name="step" type="hidden" id="step" value="2">
                    <p class="submit"><input type="submit" name="Submit" value="Process All Posts &raquo;" /></p>
                </form>
            </div>
        <?php endif;?>
        
    </div>
        <?php
}