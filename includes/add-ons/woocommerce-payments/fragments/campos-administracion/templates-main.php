<?php

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $field_type==='paid-service-label' ):
	$look_for_subs = 'paid-service';
	?>
	<li class="ui-state-default">
		<i class="main-handle booked-icon booked-icon-bars"></i>
		<small><?php _e('Selector de producto', 'pw-calendario'); ?></small>

		<p>
			<input class="cf-required-checkbox"<?php if ($is_required): echo ' checked="checked"'; endif; ?> type="checkbox" name="required---<?php echo $numbers_only; ?>" id="required---<?php echo $numbers_only; ?>">
			<label for="required---<?php echo $numbers_only; ?>"><?php _e('Campo obligatorio', 'pw-calendario'); ?></label>
		</p>

		<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo htmlentities($value, ENT_QUOTES | ENT_IGNORE, "UTF-8"); ?>" placeholder="<?php _e('Escribe una etiqueta para este grupo desplegable…', 'pw-calendario'); ?>" />

		<ul id="booked-cf-paid-service">
<?php
endif;

return $look_for_subs;