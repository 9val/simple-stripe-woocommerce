<?php
/**
 * The Template for displaying the credit card form on the checkout page
 *
 * Override this template by copying it to yourtheme/woocommerce/s4wc/payment-fields.php
 *
 * @author      Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?>

<fieldset id="stripe-cc-form">

	<p class="form-row form-row-wide validate-required">
		<label for="stripe-card-number">Card Number <span class="required">*</span></label>
		<input id="stripe-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" name="stripe-card-number">
	</p>

	<p class="form-row form-row-first validate-required">
		<label for="stripe-card-expiry">Exp Date<span class="required">*</span></label>
		<input id="stripe-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / YY" name="stripe-card-expiry">
	</p>

	<p class="form-row form-row-last validate-required">
		<label for="stripe-card-cvc">CVC<span class="required">*</span></label>
		<input id="stripe-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="CVC" name="stripe-card-cvc">
	</p>

	<div class="form-row place-order">

		<noscript><?php _e( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the <em>Update Totals</em> button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ); ?><br/><input type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>" /></noscript>

		<?php wp_nonce_field( 'woocommerce-process_checkout' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php echo apply_filters( 'woocommerce_order_button_html', '<input type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '" />' ); ?>

		<?php if ( wc_get_page_id( 'terms' ) > 0 && apply_filters( 'woocommerce_checkout_show_terms', true ) ) : ?>
			<p class="form-row terms">
				<label for="terms" class="checkbox"><?php printf( __( 'I&rsquo;ve read and accept the <a href="%s" target="_blank">terms &amp; conditions</a>', 'woocommerce' ), esc_url( wc_get_page_permalink( 'terms' ) ) ); ?></label>
				<input type="checkbox" class="input-checkbox" name="terms" <?php checked( apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ), true ); ?> id="terms" />
			</p>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

	</div>
</fieldset>
