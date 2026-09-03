<?php if ( ! defined( 'ABSPATH' ) ) { exit; } // Salir si se accede directamente. ?>
<div class="field">
	<label class="field-label"><?php esc_html_e("Registro:",'pw-calendario'); ?><i class="required-asterisk booked-icon booked-icon-required"></i></label>
	<p class="field-small-p"><?php esc_html_e('Indica tu nombre y tu correo electrónico, y elige una contraseña para empezar.','pw-calendario'); ?></p>
</div>

<?php
	$name_requirements = get_option('booked_registration_name_requirements',array('require_name'));
	$name_requirements = ( isset($name_requirements[0]) ? $name_requirements[0] : false );
?>

<?php if ( $name_requirements == 'require_surname' ): ?>
	<div class="field">
		<input value="" placeholder="<?php esc_html_e('Nombre','pw-calendario'); ?>..." type="text" class="textfield" name="booked_appt_name" />
		<input value="" placeholder="<?php esc_html_e('Apellidos','pw-calendario'); ?>..." type="text" class="textfield" name="booked_appt_surname" />
	</div>
<?php else: ?>
	<div class="field">
		<input value="" placeholder="<?php esc_html_e('Nombre','pw-calendario'); ?>..." type="text" class="large textfield" name="booked_appt_name" />
	</div>
<?php endif; ?>

<div class="field">
	<input value="" placeholder="<?php esc_html_e('Correo electrónico','pw-calendario'); ?>..." type="email" class="textfield" name="booked_appt_email" />
	<input value="" placeholder="<?php esc_html_e('Elige una contraseña','pw-calendario'); ?>..." type="password" class="textfield" name="booked_appt_password" />
</div>
