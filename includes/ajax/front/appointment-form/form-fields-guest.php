<?php

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


	$email_required = get_option('booked_require_guest_email_address',false);
	$name_requirements = get_option('booked_registration_name_requirements',array('require_name'));
	$name_requirements = ( isset($name_requirements[0]) ? $name_requirements[0] : false );

?>

<div class="field">
	<label class="field-label"><?php esc_html_e("Tus datos:",'pw-calendario'); ?><i class="required-asterisk booked-icon booked-icon-required"></i></label>
	<p class="field-small-p"><?php
		echo ( $email_required ? ( $name_requirements == 'require_surname' ? esc_html__('Indica tu nombre, tus apellidos y tu correo electrónico:','pw-calendario') : esc_html__('Indica tu nombre y tu correo electrónico:','pw-calendario') ) : ( isset($name_requirements[0]) && $name_requirements[0] == 'require_surname' ? esc_html__('Indica tu nombre y tus apellidos:','pw-calendario') : esc_html__('Indica tu nombre:','pw-calendario') ) );
	?></p>
</div>

<?php if ( $name_requirements == 'require_surname' ): ?>
	<div class="field">
		<input value="" placeholder="<?php esc_html_e('Nombre','pw-calendario'); ?>..." type="text" class="textfield" name="guest_name" />
		<input value="" placeholder="<?php esc_html_e('Apellidos','pw-calendario'); ?>..." type="text" class="textfield" name="guest_surname" />
	</div>
<?php else: ?>
	<div class="field">
		<?php if ( $email_required ): ?>
			<input value="" placeholder="<?php esc_html_e('Nombre','pw-calendario'); ?>..." type="text" class="textfield" name="guest_name" />
			<input value="" placeholder="<?php esc_html_e('Correo electrónico','pw-calendario'); ?>..." type="email" class="textfield" name="guest_email" />
		<?php else: ?>
			<input value="" placeholder="<?php esc_html_e('Nombre','pw-calendario'); ?>..." type="text" class="large textfield" name="guest_name" />
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $email_required && $name_requirements == 'require_surname' ): ?>
	<div class="field">
		<input value="" placeholder="<?php esc_html_e('Correo electrónico','pw-calendario'); ?>..." type="email" class="large textfield" name="guest_email" />
	</div>
<?php endif; ?>
