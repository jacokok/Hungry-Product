<?php

/**
 * Made with ❤ by themesfor.me
 *
 * Administration panel for plugin
 */

namespace Hungry\Controller;

use Hungry\Tool\Template;

class Admin 
{
    /**
     * Setup hooks
     */
    public function run()
    {	
        // WooCommerce tabs
        add_action('woocommerce_settings_tabs_array', array( $this, 'add_admin_tab' ), 50);
        add_action('woocommerce_settings_tabs_hungry', array( $this, 'add_admin_tab_settings'));
        add_action('woocommerce_update_options_hungry', array( $this, 'update_settings' ));

        // Product options
        add_action('admin_init', array($this, 'admin_init'));
    }

    /**
     * Init all things required for admin
     */
    public function admin_init()
    {
        // Add product meta box and hook into saving
        add_meta_box('hungry', __('Google Product Feed', 'google-product-feed' ), array($this, 'product_meta_box'), 'product', 'advanced');
        add_action('save_post', array($this, 'save_product'));

        // Categories meta box
        add_action('product_cat_add_form_fields', array($this, 'category_meta_box'), 99, 2); 
        add_action('product_cat_edit_form_fields', array($this, 'category_meta_box'), 99, 2);
        add_action('edited_product_cat', array( $this, 'save_category' ), 15 , 2 ); //After saved

        // Settings link in the plugins list
        add_filter('plugin_action_links_' . plugin_basename(\Hungry\__BASEFILE__), array($this, 'add_settings_link'));
    }

    public function add_settings_link($links) 
    {
        array_unshift($links, sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=wc-settings&tab=hungry'), __('Settings', 'hungry-google-product-feed')));

        return $links;
    }

    public function product_meta_box()
    {   
        global $post;
        
        $settings = get_post_meta($post->ID, 'hungry_settings', true);

        $google_categories = $this->get_google_categories();

        $template = new Template(\Hungry\__ASSETS__ . '/templates/product-meta-box.html');
        $template->settings = $settings;
        $template->google_categories = $google_categories;

        echo $template->render();
    }

    public function category_meta_box($catortax, $taxonomy = null)
    {
        if($taxonomy === null) {
            $taxonomy = $catortax;
            $category = null;
        } else {
            $category = $catortax;
        }

        if($category) {
            $settings = get_metadata('woocommerce_term', $category->term_id, 'hungry_settings', true);
        } else {
            $settings = array();
        }

        if($category == null) {
            echo sprintf('<h3>%s</h3><p>%s</p>', __('Google Product Feed', 'hungry-google-product-feed'), __('The settings are available in the category edit page.', 'hungry-google-product-feed'));     
            return;
        }

        $google_categories = $this->get_google_categories();

        $template = new Template(\Hungry\__ASSETS__ . '/templates/category-meta-box.html');
        $template->settings = $settings;
        $template->google_categories = $google_categories;
        $template->category = $category;

        echo $template->render();
    }

    public function save_category($id)
    {
        if(!empty($_POST['hungry_settings'])) {
            $settings['brand'] = sanitize_text_field($_POST['hungry_brand']);
            $settings['category'] = sanitize_text_field($_POST['hungry_category']);

            update_metadata('woocommerce_term', $id, 'hungry_settings', $settings);
        }
    }

    public function save_product($id)
    {
        if(defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE) {
            return;
        }

        if(empty($_POST['hungry_settings'])) {
            return;
        }

        $settings = get_post_meta($id, 'hungry_settings', true);

        if(empty($settings)) {
            $settings = array();
        }

        $settings['gtin'] = sanitize_text_field($_POST['hungry_gtin']);
        $settings['mpn'] = sanitize_text_field($_POST['hungry_mpn']);
        $settings['brand'] = sanitize_text_field($_POST['hungry_brand']);
        
        if(isset($_POST['hungry-identifier-exists'])) {
            $settings['identifier'] = 'unexist';
        } else {
            $settings['identifier'] = 'exist';
        }
        
        if(!empty($_POST['hungry_category'])) {
            $settings['category'] = sanitize_text_field($_POST['hungry_category']);
        }

        update_post_meta($id, 'hungry_settings', $settings);
    }

    /**
     * Add our tab to the WooCommerce settings
     *
     * @param array $tabs List of tabs
     * @return array List of tabs with our tab appended
     */
    public function add_admin_tab($tabs)
    {   
        $tabs['hungry'] = __('Google Product Feed', 'hungry-google-product-feed');
        return $tabs;
    }

    /**
     * Send the settings to the WooCommerce API
     */
    public function add_admin_tab_settings()
    {   
        woocommerce_admin_fields($this->get_settings());
    }

    /**
     * Update settings using WooCommerce API
     */
    public function update_settings()
    {
        woocommerce_update_options($this->get_settings());
    }

    /**
     * Get all settings in WooCommerce format
     *
     * @return array Settings in WooCommerce format accessible by 'hungry_product_feed' hook
     */
    private function get_settings()
    {
        // See options here: https://github.com/woothemes/woocommerce/blob/5dcd19f5fa133a25c7e025d7c73e04516bcf90da/includes/admin/class-wc-admin-settings.php#L195
        $settings = array(
            // Top header
            'description_heading' => array(
                'name' => __('Location of feed', 'hungry-google-product-feed'),
                'type' => 'title',
                'desc' => __('The feed with product ready for Google Merchants is under following address:<br /><br />', 'hungry-google-product-feed') . sprintf('<a href="%s">%s</a>', $this->get_feed_url(), $this->get_feed_url()),
                'id' => 'hungry_description_heading'
            ),

            'header_end' => array(
                 'type' => 'sectionend',
                 'id' => 'hungry_description_heading'
            ),

            // Settings
            'description_settings' => array(
                'name' => __('Settings', 'hungry-google-product-feed'),
                'type' => 'title',
                'desc' => '',
                'id' => 'hungry_description_settings'
            ),

            'condition' => array(
                'name' => __('Product condition', 'hungry-google-product-feed'),
                'type' => 'select',
                'desc' => __('Default condition of items sold in store'),
                'options' => array(
                    'new' => __('New', 'hungry-google-product-feed'),
                    'used' => __('Used', 'hungry-google-product-feed'),
                    'refubrished' => __('Refubrished', 'hungry-google-product-feed'),
                ),
                'id' => 'hungry_setting_condition'
            ),

            'cotegory' => array(
                'name' => __('Product category', 'hungry-google-product-feed'),
                'type' => 'select',
                'desc' => __('Default category of items sold in store'),
                'options' => $this->get_google_categories(),
                'id' => 'hungry_setting_category',
                'css' => 'width: 30%',
            ),

            'product_type' => array(
                'name' => __('Product type', 'hungry-google-product-feed'),
                'type' => 'select',
                'desc' => __('Type of items sold in store'),
                'options' => array(
                    'none' => __('None', 'hungry-google-product-feed'),
                    'use_category' => __('Use wordpress category as a product type', 'hungry-google-product-feed'),
                ),
                'id' => 'hungry_setting_type',
                'css' => 'width: 30%',
            ),

            'brand' => array(
                'name' => __('Brand', 'hungry-google-product-feed'),
                'type' => 'text',
                'desc' => __('Default brand of all items in the shop'),
                'id' => 'hungry_setting_brand'
            ),

            'settings_end' => array(
                 'type' => 'sectionend',
                 'id' => 'hungry_description_heading'
            ),
        );

        return apply_filters('hungry_product_feed', $settings);
    } 

    private function get_feed_url()
    {
        return get_site_url(null, '/?feed=google_feed');
    }

    private function get_google_categories()
    {
        $lang = get_bloginfo('language');
        
        $file = \Hungry\__ASSETS__ . '/categories/' . $lang . '.txt';

        if(!file_exists($file)) {
            $file = \Hungry\__ASSETS__ . '/categories/en-US.txt';
        }

        $categoriesFile = file($file);

        foreach($categoriesFile as $line) {
            if(substr($line, 0, 1) == '#') {
                continue;
            }
            $cleanLine = trim($line);
            $categories[$cleanLine] = $cleanLine;
        }

        return $categories;
    }

}