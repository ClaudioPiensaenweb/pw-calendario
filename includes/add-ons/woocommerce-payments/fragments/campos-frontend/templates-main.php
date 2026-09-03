<?php if ( ! defined( 'ABSPATH' ) ) { exit; } // Salir si se accede directamente. ?>
<div class="field field-paid-service">
	<label class="field-label"><?php echo htmlentities($value, ENT_QUOTES | ENT_IGNORE, "UTF-8"); ?><?php if ($is_required): ?><i class="required-asterisk booked-icon booked-icon-required"></i><?php endif; ?></label>
	<input type="hidden" name="booked_wc_product[<?php echo esc_attr( $name ); ?>]" value="1" />
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>" />
	<select <?php echo $data_attributes ?> <?php if ($is_required): echo ' required="required"'; endif; ?> class="field-paid-service-select" name="<?php echo esc_attr( $name ); ?>">
		<option value=""><?php _e('Elige un producto', 'pw-calendario'); ?></option>
