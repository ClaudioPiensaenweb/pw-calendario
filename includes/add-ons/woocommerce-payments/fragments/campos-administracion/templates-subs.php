<?php

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 $products = Booked_WC_Functions::get_products(); ?>
<?php if ( $field_type==='single-paid-service' ): ?>
	<li id="bookedCFTemplate-single-paid-service" class="ui-state-default"><i class="sub-handle booked-icon booked-icon-bars"></i>
		<select name="<?php echo esc_attr( $name ); ?>" >
			<option value=""><?php _e('Elige un producto', 'pw-calendario'); ?></option>
			<?php foreach ($products['options'] as $product_id => $product_title): ?>
				<?php $product = Booked_WC_Product::get( intval($product_id) ); ?>
				<option <?php echo intval($value)===$product_id ? 'selected="selected"' : '' ?> value="<?php echo $product_id ?>"><?php echo esc_html($product->title); ?></option>
			<?php endforeach ?>
		</select>
		<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
	</li>
<?php endif ?>