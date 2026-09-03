<?php

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


do_action('booked_before_creating_appointment');

$date = isset($_POST['date']) ? pwcal_post_fecha( 'date' ) : '';
$title = isset($_POST['title']) ? pwcal_post_texto( 'title' ) : '';
$timestamp = isset($_POST['timestamp']) ? pwcal_post_entero( 'timestamp' ) : '';
$timeslot = isset($_POST['timeslot']) ? pwcal_post_intervalo( 'timeslot' ) : '';
$customer_type = isset($_POST['customer_type']) ? pwcal_post_texto( 'customer_type' ) : '';

$calendar_id = (isset($_POST['calendar_id']) ? pwcal_post_calendario( 'calendar_id' ) : false);
$calendar_id_for_cf = $calendar_id;
if ($calendar_id):
	$calendar_id = array($calendar_id);
	$calendar_id = array_map( 'intval', $calendar_id );
	$calendar_id = array_unique( $calendar_id );
endif;

$name_requirements = get_option('booked_registration_name_requirements',array('require_name'));
$name_requirements = ( isset($name_requirements[0]) ? $name_requirements[0] : false );
$is_new_registration = $customer_type == 'new' && ! isset($_POST['date']) && ! isset($_POST['timestamp']) && ! isset($_POST['timeslot']);

if ( !$is_new_registration && $date && $timeslot && isset($calendar_id_for_cf) ):

	$appt_is_available = booked_appt_is_available($date,$timeslot,$calendar_id_for_cf);

else:

	wp_die();

endif;

/*
 * Numero de personas de la reserva.
 *
 * El aforo se cuenta en personas, asi que hay que comprobar en el servidor
 * que caben todas antes de crear la cita. Fiarse solo del maximo del
 * selector del formulario no basta: cualquiera puede enviar otro valor, y
 * ademas otra persona puede haber reservado mientras rellenaba el
 * formulario.
 */
$personas = pwcal_personas_solicitadas();

$personas_validas = pwcal_validar_personas( $date, $timeslot, $personas, $calendar_id_for_cf );

if ( is_wp_error( $personas_validas ) ) {
	echo 'error###' . esc_html( $personas_validas->get_error_message() );
	wp_die();
}

if ($appt_is_available):

	$time_format = get_option('time_format');
	$date_format = get_option('date_format');
	$appointment_default_status = get_option('booked_new_appointment_default','draft');
	$hide_end_times = get_option('booked_hide_end_times',false);

	// Get custom field data (new in v1.2)
	$custom_fields = array();

	if ( $calendar_id_for_cf ) {
		$custom_fields = json_decode(stripslashes(get_option('booked_custom_fields_'.$calendar_id_for_cf)),true);
	}

	if ( !$custom_fields ) {
		$custom_fields = json_decode(stripslashes(get_option('booked_custom_fields')),true);
	}

	$custom_field_data = array();
	$cf_meta_value = '';

	if (!empty($custom_fields)):

		$previous_field = false;

		foreach($custom_fields as $key => $field):

			$field_name = $field['name'];
			$field_title = $field['value'];

			$field_title_parts = explode('---',$field_name);
			if ($field_title_parts[0] == 'radio-buttons-label' || $field_title_parts[0] == 'checkboxes-label'):
				$current_group_name = $field_title;
			elseif ($field_title_parts[0] == 'single-radio-button' || $field_title_parts[0] == 'single-checkbox'):
				// Don't change the group name yet
			else :
				$current_group_name = $field_title;
			endif;

			if ($field_name != $previous_field){

				if (isset($_POST[$field_name]) && $_POST[$field_name]):

					// Se sanea aqui: el valor acaba dentro del HTML que se
					// muestra en el escritorio y en los correos.
					$field_value = pwcal_sanear_campo_personalizado( $_POST[$field_name] );

					$custom_field_data[$key] = array(
						'label' => $current_group_name,
						'value' => $field_value
					);

				endif;

				$previous_field = $field_name;

			}

		endforeach;

		$custom_field_data = apply_filters('booked_custom_field_data', $custom_field_data);

		if (!empty($custom_field_data)):
			foreach($custom_field_data as $key => $data):
				$cf_meta_value .= '<p class="cf-meta-value"><strong>' . esc_html( $data['label'] ) . '</strong><br>' . esc_html( $data['value'] ) . '</p>';
			endforeach;
		endif;

	endif;
	// END Get custom field data

	if ($customer_type == 'guest'):

		$name = pwcal_post_texto( 'guest_name' );
		$surname = isset($_POST['guest_surname']) && $_POST['guest_surname'] ? pwcal_post_texto( 'guest_surname' ) : false;
		$fullname = ( $surname ? $name . ' ' . $surname : $name );
		$email = isset($_POST['guest_email']) ? pwcal_post_texto( 'guest_email' ) : '';
		$email_required = get_option('booked_require_guest_email_address',false);

		if ( $name_requirements == 'require_surname' && !$surname ):

			echo 'error###'.esc_html__('Necesitamos tu nombre completo para reservar la cita.','pw-calendario');

		else:

			if ($email && is_email($email) && $name || !$email && !$email_required && $name):

				// Create a new appointment post for a guest customer
				$new_post = apply_filters('booked_new_appointment_args', array(
					'post_title' => date_i18n($date_format,$timestamp).' @ '.date_i18n($time_format,$timestamp).' (User: Guest)',
					'post_content' => '',
					'post_status' => $appointment_default_status,
					'post_date' => date_i18n('Y',strtotime($date)).'-'.date_i18n('m',strtotime($date)).'-01 00:00:00',
					'post_type' => 'booked_appointments'
				));
				$post_id = wp_insert_post($new_post);

				update_post_meta($post_id, '_appointment_title', $title);
				update_post_meta($post_id, '_appointment_guest_name', $name);
				update_post_meta($post_id, '_appointment_guest_surname', $surname);
				update_post_meta($post_id, '_appointment_guest_email', $email);
				update_post_meta($post_id, '_appointment_timestamp', $timestamp);
				update_post_meta($post_id, '_appointment_timeslot', $timeslot);
				pwcal_guardar_personas( $post_id, $personas );

				if ($appointment_default_status == 'publish'): wp_publish_post($post_id); endif;

				if ( apply_filters('booked_update_cf_meta_value', true) ) {
					update_post_meta($post_id, '_cf_meta_value', $cf_meta_value);
				}

				if ( apply_filters('booked_update_appointment_calendar', true) ) {
					if (!empty($calendar_id)): $calendar_term = get_term_by('id',$calendar_id[0],'booked_custom_calendars'); $calendar_name = $calendar_term->name; wp_set_object_terms($post_id,$calendar_id,'booked_custom_calendars'); else: $calendar_name = false; endif;
				}

				// Send a confirmation email to the User?
				$email_content = get_option('booked_appt_confirmation_email_content',false);
				$email_subject = get_option('booked_appt_confirmation_email_subject',false);

				$token_replacements = booked_get_appointment_tokens( $post_id );

				if ( $email_content && $email_subject ):

					$admin_email = booked_which_admin_to_send_email( pwcal_post_calendario( 'calendar_id' ) );
					$email_content = booked_token_replacement( $email_content,$token_replacements );
					$email_subject = booked_token_replacement( $email_subject,$token_replacements );
					do_action( 'booked_confirmation_email', $email, $email_subject, $email_content, $admin_email );

				endif;

				// Send an email to the Admin?
				$email_content = get_option('booked_admin_appointment_email_content',false);
				$email_subject = get_option('booked_admin_appointment_email_subject',false);
				if ($email_content && $email_subject):

					$admin_email = booked_which_admin_to_send_email( pwcal_post_calendario( 'calendar_id' ) );
					$email_content = booked_token_replacement( $email_content,$token_replacements );
					$email_subject = booked_token_replacement( $email_subject,$token_replacements );
					do_action( 'booked_admin_confirmation_email', $admin_email, $email_subject, $email_content, $token_replacements['email'], $token_replacements['name'] );

				endif;

				do_action('booked_new_appointment_created', $post_id);

				echo 'success###'.$date;

			else :

				if ($email && !is_email($email)):
					$errors[] = esc_html__('El correo electrónico que has escrito no parece válido.','pw-calendario');
				elseif ($email_required && !$email):
					$errors[] = esc_html__('Necesitamos tu nombre y un correo electrónico válido para reservar la cita.','pw-calendario');
				elseif (!$name):
					$errors[] = esc_html__('Necesitamos tu nombre para reservar la cita.','pw-calendario');
				else:
					$errors[] = esc_html__('Se ha producido un error desconocido.','pw-calendario');
				endif;

				echo 'error###'.implode('
',$errors);

			endif;

		endif;

	elseif ($customer_type == 'current'):

		$user_id = ! empty($_POST['user_id']) ? intval($_POST['user_id']) : false;
		if ( ! $user_id && is_user_logged_in() ) {
			$user = wp_get_current_user();
			$user_id = $user->ID;
		}

		// Create a new appointment post for a current customer
		$new_post = apply_filters('booked_new_appointment_args', array(
			'post_title' => date_i18n($date_format,$timestamp).' @ '.date_i18n($time_format,$timestamp).' (User: '.$user_id.')',
			'post_content' => '',
			'post_status' => $appointment_default_status,
			'post_date' => date_i18n('Y',strtotime($date)).'-'.date_i18n('m',strtotime($date)).'-01 00:00:00',
			'post_author' => $user_id,
			'post_type' => 'booked_appointments'
		));
		$post_id = wp_insert_post($new_post);

		update_post_meta($post_id, '_appointment_title', $title);
		update_post_meta($post_id, '_appointment_timestamp', $timestamp);
		update_post_meta($post_id, '_appointment_timeslot', $timeslot);
				pwcal_guardar_personas( $post_id, $personas );
		update_post_meta($post_id, '_appointment_user', $user_id);

		if ($appointment_default_status == 'publish'): wp_publish_post($post_id); endif;

		if (apply_filters('booked_update_cf_meta_value', true)) {
			update_post_meta($post_id, '_cf_meta_value', $cf_meta_value);
		}

		if (apply_filters('booked_update_appointment_calendar', true)) {
			if (!empty($calendar_id)): $calendar_term = get_term_by('id',$calendar_id[0],'booked_custom_calendars'); $calendar_name = $calendar_term->name; wp_set_object_terms($post_id,$calendar_id,'booked_custom_calendars'); else: $calendar_name = false; endif;
		}

		// Send a confirmation email to the User?
		$email_content = get_option('booked_appt_confirmation_email_content');
		$email_subject = get_option('booked_appt_confirmation_email_subject');

		$token_replacements = booked_get_appointment_tokens( $post_id );

		if ($email_content && $email_subject):

			$admin_email = booked_which_admin_to_send_email(pwcal_post_calendario( 'calendar_id' ));
			$email_content = booked_token_replacement( $email_content,$token_replacements );
			$email_subject = booked_token_replacement( $email_subject,$token_replacements );

			do_action( 'booked_confirmation_email', $token_replacements['email'], $email_subject, $email_content, $admin_email );

		endif;

		// Send an email to the Admin?
		$email_content = get_option('booked_admin_appointment_email_content');
		$email_subject = get_option('booked_admin_appointment_email_subject');

		if ($email_content && $email_subject):

			$admin_email = booked_which_admin_to_send_email(pwcal_post_calendario( 'calendar_id' ));
			$email_content = booked_token_replacement( $email_content,$token_replacements );
			$email_subject = booked_token_replacement( $email_subject,$token_replacements );

			do_action( 'booked_admin_confirmation_email', $admin_email, $email_subject, $email_content, $token_replacements['email'], $token_replacements['name'] );

		endif;

		if ( apply_filters( 'booked_sessions_enabled', true ) ):
			$_SESSION['appt_requested'] = 1;
		endif;

		do_action('booked_new_appointment_created', $post_id);

		echo 'success###'.$date;

	elseif ($customer_type == 'new'):

		$name = pwcal_post_texto( 'booked_appt_name' );
		$surname = ( isset($_POST['booked_appt_surname']) && $_POST['booked_appt_surname'] ? pwcal_post_texto( 'booked_appt_surname' ) : false );
		$fullname = ( $surname ? $name . ' ' . $surname : $name );
		$email = pwcal_post_texto( 'booked_appt_email' );
		// Las contrasenas no se sanean: `sanitize_text_field` altera los
	// caracteres especiales y el usuario no podria volver a entrar
	// con la contrasena que ha escrito.
	$password = isset( $_POST['booked_appt_password'] ) ? (string) wp_unslash( $_POST['booked_appt_password'] ) : '';

		if ( $name_requirements == 'require_surname' && !$surname ):

			echo 'error###'.esc_html__('Necesitamos tu nombre completo para reservar la cita.','pw-calendario');

		else:

			if (isset($_POST['captcha_word'])):
		    	$captcha_word = strtolower(pwcal_post_texto( 'captcha_word' ));
				$captcha_code = strtolower(pwcal_post_texto( 'captcha_code' ));
		    else :
		    	$captcha_word = false;
				$captcha_code = false;
		    endif;

			$errors = booked_registration_validation($email,$password,$captcha_word,$captcha_code);

			if (empty($errors)):

				$userdata = array(
		        	'user_login'    =>  $email,
					'user_email'    =>  $email,
					'user_pass'     =>  $password,
					'first_name'	=>	$name,
					'last_name'		=>	$surname
		        );
		        $user_id = wp_insert_user( $userdata );

		        update_user_meta( $user_id, 'nickname', $name );
				wp_update_user( array ('ID' => $user_id, 'display_name' => $name ) );

		        $creds = array();
				$creds['user_login'] = $email;
				$creds['user_password'] = $password;
				$creds['remember'] = true;
				$user_signon = wp_signon( $creds, false );
				if ( is_wp_error($user_signon) ){
					$signin_errors = $user_signon->get_error_message();
				}

				// Create a new appointment post for this new customer
				$new_post = apply_filters('booked_new_appointment_args', array(
					'post_title' => date_i18n($date_format,$timestamp).' @ '.date_i18n($time_format,$timestamp).' (User: '.$user_id.')',
					'post_content' => '',
					'post_status' => $appointment_default_status,
					'post_date' => date_i18n('Y',strtotime($date)).'-'.date_i18n('m',strtotime($date)).'-01 00:00:00',
					'post_author' => $user_id,
					'post_type' => 'booked_appointments'
				));
				$post_id = wp_insert_post($new_post);

				update_post_meta( $post_id, '_appointment_title', $title );
				update_post_meta( $post_id, '_appointment_timestamp', $timestamp );
				update_post_meta( $post_id, '_appointment_timeslot', $timeslot );
				pwcal_guardar_personas( $post_id, $personas );
				update_post_meta( $post_id, '_appointment_user', $user_id );

				if ($appointment_default_status == 'publish'): wp_publish_post( $post_id ); endif;

				if (apply_filters('booked_update_cf_meta_value', true)) {
					update_post_meta($post_id, '_cf_meta_value', $cf_meta_value);
				}

				if (apply_filters('booked_update_appointment_calendar', true)) {
					if (!empty($calendar_id)): wp_set_object_terms($post_id,$calendar_id,'booked_custom_calendars'); endif;
				}

				if (apply_filters('booked_update_appointment_calendar', true)) {
					if (!empty($calendar_id)): $calendar_term = get_term_by('id',$calendar_id[0],'booked_custom_calendars'); $calendar_name = $calendar_term->name; wp_set_object_terms($post_id,$calendar_id,'booked_custom_calendars'); else: $calendar_name = false; endif;
				}

				$token_replacements = booked_get_appointment_tokens( $post_id );

		        // Send an email to the Admin?
		        $email_content = get_option('booked_admin_appointment_email_content');
				$email_subject = get_option('booked_admin_appointment_email_subject');

				if ($email_content && $email_subject):

					$email_calendar_id = pwcal_post_calendario( 'calendar_id' );

					$admin_email = booked_which_admin_to_send_email( $email_calendar_id );
					$email_content = booked_token_replacement( $email_content,$token_replacements );
					$email_subject = booked_token_replacement( $email_subject,$token_replacements );

					do_action( 'booked_admin_confirmation_email', $admin_email, $email_subject, $email_content, $token_replacements['email'], $token_replacements['name'] );

				endif;

				// Send a registration welcome email to the new user?
				$email_content = get_option('booked_registration_email_content');
				$email_subject = get_option('booked_registration_email_subject');
				if ($email_content && $email_subject):

					$registration_token_replacements = array(
						'name' => $fullname,
						'email' => $email,
						'username' => $email,
						'password' => $password
					);

					$admin_email = booked_which_admin_to_send_email( pwcal_post_calendario( 'calendar_id' ) );
					$email_content = booked_token_replacement( $email_content,$registration_token_replacements,'user' );
					$email_subject = booked_token_replacement( $email_subject,$registration_token_replacements,'user' );

					do_action( 'booked_registration_email', $registration_token_replacements['email'], $email_subject, $email_content, $admin_email );

				endif;

				// Send an email to the User?
				$email_content = get_option('booked_appt_confirmation_email_content');
				$email_subject = get_option('booked_appt_confirmation_email_subject');

				if ($email_content && $email_subject):

					$admin_email = booked_which_admin_to_send_email( pwcal_post_calendario( 'calendar_id' ) );
					$email_content = booked_token_replacement( $email_content,$token_replacements );
					$email_subject = booked_token_replacement( $email_subject,$token_replacements );

					do_action( 'booked_confirmation_email', $token_replacements['email'], $email_subject, $email_content , $admin_email);

				endif;

				if ( apply_filters( 'booked_sessions_enabled', true ) ):
					$_SESSION['appt_requested'] = 1;
					$_SESSION['new_account'] = 1;
				endif;

				do_action('booked_new_appointment_created', $post_id);

		        echo 'success###'.$date;

			else :

				echo 'error###'.implode('
',$errors);
			endif;

		endif;

	endif;

// register the user only
elseif ( $is_new_registration ):

	$name = pwcal_post_texto( 'booked_appt_name' );
	$surname = ( isset($_POST['booked_appt_surname']) && $_POST['booked_appt_surname'] ? pwcal_post_texto( 'booked_appt_surname' ) : false );
	$fullname = ( $surname ? $name . ' ' . $surname : $name );
	$email = pwcal_post_texto( 'booked_appt_email' );
	// Las contrasenas no se sanean: `sanitize_text_field` altera los
	// caracteres especiales y el usuario no podria volver a entrar
	// con la contrasena que ha escrito.
	$password = isset( $_POST['booked_appt_password'] ) ? (string) wp_unslash( $_POST['booked_appt_password'] ) : '';

	if ( $name_requirements == 'require_surname' && !$surname ):

		echo 'error###'.esc_html__('Necesitamos tu nombre completo para reservar la cita.','pw-calendario');

	else:

		if (isset($_POST['captcha_word'])):
	    	$captcha_word = strtolower(pwcal_post_texto( 'captcha_word' ));
			$captcha_code = strtolower(pwcal_post_texto( 'captcha_code' ));
	    else :
	    	$captcha_word = false;
			$captcha_code = false;
	    endif;

		$errors = booked_registration_validation($email,$password,$captcha_word,$captcha_code);

		if (empty($errors)):

			$userdata = array(
	        	'user_login'    =>  $email,
				'user_email'    =>  $email,
				'user_pass'     =>  $password,
				'first_name'	=>	$name,
				'last_name'		=>	$surname
	        );
	        $user_id = wp_insert_user( $userdata );

	        if ($surname): $name = $name . ' ' . $surname; endif;

	        update_user_meta( $user_id, 'nickname', $name );
			wp_update_user( array ('ID' => $user_id, 'display_name' => $name ) );

	        $creds = array();
			$creds['user_login'] = $email;
			$creds['user_password'] = $password;
			$creds['remember'] = true;
			$user_signon = wp_signon( $creds, false );
			if ( is_wp_error($user_signon) ){
				$signin_errors = $user_signon->get_error_message();
			}

			// Send a registration welcome email to the new user?
			$email_content = get_option('booked_registration_email_content');
			$email_subject = get_option('booked_registration_email_subject');
			if ($email_content && $email_subject):

				$token_replacements = array(
					'name' => $fullname,
					'email' => $email,
					'username' => $email,
					'password' => $password
				);

				$email_content = booked_token_replacement( $email_content,$token_replacements,'user' );
				$email_subject = booked_token_replacement( $email_subject,$token_replacements,'user' );

				do_action( 'booked_registration_email', $token_replacements['email'], $email_subject, $email_content );

			endif;

			if ( apply_filters( 'booked_sessions_enabled', true ) ):
				$_SESSION['appt_requested'] = 1;
				$_SESSION['new_account'] = 1;
			endif;

			do_action('booked_new_appointment_created', $post_id);

	        echo 'success###' . esc_html__('El registro se ha completado correctamente.','pw-calendario');

		else :

			echo 'error###'.implode('
',$errors);
		endif;

	endif;

else:

	$error_message = apply_filters(
		'booked_availability_error_message',
		esc_html__('Alguien ha reservado esta cita justo antes que tú. Elige otra hora.','pw-calendario')
	);
	echo 'error###' . $error_message;

endif;

session_write_close();
