<?php

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( !is_user_logged_in() ) {
	return;
}

$parameters = array('action', 'date', 'timeslot', 'app_id', 'app_action', 'source');
foreach ($parameters as $parameter_name) {
	if ( !isset($_POST[$parameter_name]) || empty($_POST[$parameter_name]) ) {
		return;
	}
}

$app_id = pwcal_post_entero( 'app_id' );
$app_action = pwcal_post_texto( 'app_action' );
$source = pwcal_post_texto( 'source' );
$appt_obj = Booked_WC_Appointment::get($app_id);
$calendar_id = ( isset($appt_obj->calendar->calendar_obj->term_id) ? (int)$appt_obj->calendar->calendar_obj->term_id : 0 );

if (
	!$app_id
	|| $app_action!=='edit'
	|| $source!=='booked_wc_extension'
) {
	return;
}

$date = pwcal_post_fecha( 'date' );

$timeslot = pwcal_post_intervalo( 'timeslot' );
$timeslot_parts = explode('-',$timeslot);

$date_format = get_option('date_format');
$time_format = get_option('time_format');

if ($timeslot_parts[0] == '0000' && $timeslot_parts[1] == '2400') {
	$timeslotText = 'All day';
} else {
	$timeslotText = date_i18n($time_format,strtotime($timeslot_parts[0])) . (!get_option('booked_hide_end_times') ? ' &ndash; '.date_i18n($time_format,strtotime($timeslot_parts[1])) : '');
}

$reached_limit = false;

$input_date = date('Y-m-j', strtotime($date));
$input_timestamp = strtotime($date.' '.$timeslot_parts[0]);
$input_customer_type = 'current';

$current_user = wp_get_current_user();

$appt_timeslot = $timeslotText ? $timeslotText : '';
$appt_date_name = date_i18n( $date_format, strtotime( $date ) );

$user_nickname = get_user_meta($current_user->ID, 'nickname', true);
?>
<div class="booked-form booked-scrollable">

	<p class="booked-title-bar"><small><?php echo __('Actualizar la fecha de la cita', 'pw-calendario'); ?></small></p>

	<form action="" method="post" id="newAppointmentForm" data-calendar-id="<?php echo $calendar_id; ?>">

		<input type="hidden" name="date" value="<?php echo $input_date ?>" />
		<input type="hidden" name="timestamp" value="<?php echo $input_timestamp ?>" />
		<input type="hidden" name="timeslot" value="<?php echo $timeslot; ?>" />
		<input type="hidden" name="customer_type" value="<?php echo $input_customer_type ?>" />
		<input type="hidden" name="action" value="booked_add_appt" />

		<input type="hidden" name="user_id" value="<?php echo $current_user->ID ?>" />

		<input type="hidden" name="calendar_id" value="<?php echo $calendar_id; ?>" />
		<input type="hidden" name="app_id" value="<?php echo $app_id ?>" />
		<input type="hidden" name="app_action" value="<?php echo $app_action ?>" />
		<input type="hidden" name="source" value="<?php echo $source ?>" />

		<p><?php echo sprintf(__('Confirma que quieres cambiar la fecha de la cita de %s a la siguiente:', 'pw-calendario'), $user_nickname); ?></p>

		<div class="booked-appointments">
			<div class="booked-appointment-details">
				<p class="appointment-info"><i class="booked-icon booked-icon-calendar"></i>&nbsp;&nbsp;&nbsp;<?php echo sprintf( esc_html__( '%s a las %s','pw-calendario' ), $appt_date_name, $appt_timeslot ); ?></p>
			</div>
		</div>

		<input type="hidden" name="user_id" value="<?php echo $current_user->ID; ?>" />

		<div class="field">
			<p class="status"></p>
		</div>

		<div class="field">
			<?php if (!$reached_limit): ?>
				<input type="submit" id="submit-edit-request-appointment" class="button button-primary" value="<?php _e('Actualizar la fecha de la cita', 'pw-calendario'); ?>">
				<button class="cancel button"><?php _e('Cancelar', 'pw-calendario'); ?></button>
			<?php else: ?>
				<button class="cancel button"><?php _e('De acuerdo', 'pw-calendario'); ?></button>
			<?php endif; ?>
		</div>

	</form>
</div>

<a href="#" class="close"><i class="booked-icon booked-icon-close"></i></a>