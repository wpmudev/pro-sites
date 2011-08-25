<?php
/*
Pro Sites (Gateway: Manual Payments Gateway)
*/
class ProSites_Gateway_Manual {
	
	function ProSites_Gateway_Manual() {
		$this->__construct();
	}

  function __construct() {
    //settings
		add_action( 'psts_gateway_settings', array(&$this, 'settings') );
		
		//checkout stuff
		add_filter( 'psts_checkout_output', array(&$this, 'checkout_screen'), 10, 2 );
	}

	function settings() {
	  global $psts;
		?>
		<div class="postbox">
	  <h3 class='hndle'><span><?php _e('Manual Payments', 'psts') ?></span> - <span class="description"><?php _e('Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts'); ?></span></h3>
    <div class="inside">
      <table class="form-table">
			  <tr>
				<th scope="row"><?php _e('Method Name', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('Enter a public name for this payment method that is displayed to users - No HTML', 'psts') ?></span>
			    <p>
			    <input value="<?php echo esc_attr($psts->get_setting("mp_name")); ?>" size="100" name="psts[mp_name]" type="text" />
			    </p>
			  </td>
			  </tr>
			  <tr valign="top">
			  <th scope="row"><?php _e('User Instructions', 'psts') ?></th>
			  <td>
					<span class="description"><?php _e('These are the manual payment instructions to display on the payments screen - HTML allowed', 'psts') ?></span>
			    <textarea name="psts[mp_instructions]" type="text" rows="4" wrap="soft" id="mp_instructions" style="width: 95%"/><?php echo esc_textarea($psts->get_setting('mp_instructions')); ?></textarea>
			  </td>
			  </tr>
			 </table>
		  </div>
		</div>
	  <?php
	}

  function payment_info($payment_info, $blog_id) {
  	global $psts;

    $payment_info .= __('Payment Method: PayPal Account', 'psts')."\n";
    $payment_info .= sprintf(__('Next Payment Date: %s', 'psts'), date_i18n(get_option('date_format'), $psts->get_expire($blog_id)))."\n";

    return $payment_info;
  }

	function checkout_screen($content, $blog_id) {
	  global $psts, $current_site, $current_user;

	  if (!$blog_id)
	    return $content;
    
    //hide top part of content if its a pro blog
		if ( is_pro_blog($blog_id) )
			$content = '';
			
		if ($errmsg = $psts->errors->get_error_message('general')) {
			$content .= '<div id="psts-general-error" class="psts-error">'.$errmsg.'</div>';
		}
    
    $content .= '<form action="'.$psts->checkout_url($blog_id).'" method="post">';
    
    //print the checkout grid
    $content .= $psts->checkout_grid($blog_id);
    
    $content .= '<div id="psts-paypal-checkout">
			<h2>' . $psts->get_setting('mp_name') . '</h2>
			' . $psts->get_setting('mp_instructions') . '
			</div>';

    $content .= '</form>';

	  return $content;
	}
}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_Manual', __('Manual Payments', 'psts'), __('Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts') );
?>