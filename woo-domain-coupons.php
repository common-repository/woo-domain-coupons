<?php
/*
Plugin Name: Woo Domain Coupons (WDC)
Description: Allows Woo Coupons to be restricted by domain
Version: 1.02.00
Author: Two Row Studio
Text Domain: woo_domain_coupons
*/


/*************************************************************************/
/* Woo Domain Coupons (WDC) Plugin                                       */
/*                                                                       */
/* Plugin to extend the restrictions for WooCommerce Coupons to a        */
/* specific domains for a customer's registered email address            */
/*                                                                       */
/*                                                                       */
/* WDC is free software: you can redistribute it and/or modify           */
/* it under the terms of the GNU General Public License as published by  */
/* the Free Software Foundation, either version 2 of the License, or     */
/* any later version.                                                    */
/*                                                                       */
/* WDC is distributed in the hope that it will be useful,                */
/* but WITHOUT ANY WARRANTY; without even the implied warranty of        */
/* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the          */
/* GNU General Public License for more details.                          */
/*                                                                       */
/* You should have received a copy of the GNU General Public License     */
/* along with WDC. If not, see http://www.gnu.org/licenses/gpl.html.     */
/*************************************************************************/

defined ('ABSPATH') or die('No Direct access!');

define('WDC_BASE_DIR',plugin_dir_path(__FILE__));
define('WDC_BASE_URL',plugin_dir_url(__FILE__));

add_action('admin_enqueue_scripts','enqueue_woodomaincoup_styles');

function enqueue_woodomaincoup_styles(){
  wp_enqueue_style('WDC_styles',WDC_BASE_URL.'/assets/css/woo_domain_coupon.css');
}

add_filter( 'woocommerce_coupon_data_tabs','woodomaincoup_add_domain_restriction_section',20,1);

function woodomaincoup_add_domain_restriction_section($sections){
  $sections['domain_restriction'] = array(
      'label' => __('Domain Specific Coupons','woo_domain_coupons'),
      'target' => 'domain_restriction_data',
      'class' => 'domain_restriction_data',
  );
  return $sections;
}


add_action('woocommerce_coupon_data_panels','woodomaincoup_add_domain_restriction_settings',10,2);

  function woodomaincoup_add_domain_restriction_settings($coupon_id,$coupon){
    ?>
    <div id="domain_restriction_data" class="panel woocommerce_options_panel"><?php
    $label = get_post_meta($coupon_id,'_wdc_cust_label',true);
    $domain = get_post_meta($coupon_id,'_wdc_cust_domain',true);

    echo '<div class="options_group">';
      // Customer label
    woocommerce_wp_text_input( array(
        'label'     => __( 'Customer', 'woo_domain_coupons' ),
  			'description' => __( 'What comapany name should be displayed as the customer for this coupon?', 'woo_domain_coupons' ),
  			'id'       => 'dom_restrict_cust_label',
  			'type'     => 'text',
  			'desc_tip'     => true,
        'value' => $label
      )
    );
    // Customer Domains
    woocommerce_wp_text_input(  array(
      'label'     => __( 'Customer Domain', 'woo_domain_coupons' ),
      'description' => __( 'What domain should be required for the coupon to be applied?', 'woo_domain_coupons' ),
      'id'       => 'dom_restrict_domain',
      'type'     => 'text',
      'desc_tip'     => true,
      'value' => $domain
      )
    );
    echo'<p>If a domain is set here for this coupon, only email addresses with that domain will be able to use the coupon.</p>';
    echo '</div></div>';
  }

//save domain restriction domain restriction data

add_action('woocommerce_coupon_options_save','woodomaincoup_save_domain_restriction_data',20,2);

function woodomaincoup_save_domain_restriction_data($post_id, $coupon){
  $data['_wdc_cust_label'] = sanitize_text_field($_POST['dom_restrict_cust_label']);
  $data['_wdc_cust_domain'] = sanitize_text_field($_POST['dom_restrict_domain']);

  foreach ($data as $key=>$value){
    if (get_post_meta($post_id,$key,true)) {
      if ($value){
        update_post_meta($post_id,$key,$value);
      }else{
        delete_post_meta($post_id, $key);
      }
    }else {
      add_post_meta($post_id, $key, $value);
    }
  }

}

  add_action('woocommerce_after_checkout_validation','woodomaincoup_check_domain_coupon',2);

  function woodomaincoup_check_domain_coupon($posted, $errors=null){
    $cart = new WC_Cart();
    $cart->get_cart_from_session();
    if (! empty($cart->applied_coupons)){
      $coupons = $cart->applied_coupons;
      $domains = array();
      foreach($coupons as $code){
        $coupon_id = wc_get_coupon_id_by_code($code);
        $coupon = new WC_Coupon($code);
        $domain = get_post_meta($coupon_id,'_wdc_cust_domain',true);
        if ($domain =='' || !$domain){ // quit check on coupon if not domain restricted
          continue;
        }
        array_push($domains,$domain);
      }
      foreach($coupons as $code){
      $label = get_post_meta($coupon_id,'_wdc_cust_label',true);
        if ($domains){
          $cust_domain = array();
          if (is_user_logged_in()){
            $current_user = wp_get_current_user();
            $cust_email = $current_user->user_email;
            $cust_domain[] = woodomaincoup_find_domain($cust_email);
          }
          $form_email = $posted['billing_email'];
          array_push($cust_domain,woodomaincoup_find_domain($form_email));
          error_log('matched domains: '.sizeof(array_intersect($cust_domain,$domains)));
          if (0>=sizeof(array_intersect($cust_domain,$domains))){
            wc_add_notice ("A coupon was removed from your order. This coupon cannot be applied since this code is reserved for ".$label.". <b>Please use your ".$label." email address and re-apply the coupon</b> if you wish to use this coupon.",'error');
            $cart->remove_coupon ($code);
            WC()->session->set('refresh_totals',true);
            $cart->calculate_totals();
            $cart->total = max( 0, apply_filters( 'woocommerce_calculated_total', round( $cart->cart_contents_total + $cart->tax_total + $cart->shipping_tax_total + $cart->shipping_total + $cart->fee_total, $cart->dp ), $cart ) );
          }
        }
      }
    }
  }

  function woodomaincoup_find_domain ($email){
    $dom_delimit = strpos($email,"@");
    $domain = substr($email,$dom_delimit+1);
    return $domain;
  }

  ?>
