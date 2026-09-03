<?php if ( ! defined( 'ABSPATH' ) ) { exit; } // Salir si se accede directamente. ?>
<div class="field"<?php echo ( get_option('users_can_register') ? ' style="margin-top:0;"' : '' ); ?>>
	<label class="field-label"<?php echo ( get_option('users_can_register') ? ' style="padding-top:0;"' : '' ); ?>><?php esc_html_e("Hola de nuevo. Accede con tus datos:",'pw-calendario'); ?></label>
</div>
	
<div class="field">
	<input value="" placeholder="<?php esc_html_e('Correo electrónico','pw-calendario'); ?> ..." class="textfield large" id="username" name="username" type="text" >
</div>
<div class="field">
	<input value="" placeholder="<?php esc_html_e('Contraseña','pw-calendario'); ?> ..." class="textfield large" id="password" name="password" type="password" >
</div>