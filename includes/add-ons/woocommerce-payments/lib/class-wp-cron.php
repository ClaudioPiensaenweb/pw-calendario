<?php

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// https://codex.wordpress.org/Plugin_API/Filter_Reference/cron_schedules
add_filter('cron_schedules', array('Booked_WC_WP_Crons', 'cron_schedules'));

class Booked_WC_WP_Crons {

	private function __construct() {

		if ( Booked_WC_Settings::get_option('enable_auto_cleanup') === 'enable' ) {
			$this->activate_scheduler();
		}
	}

	public static function setup(){
		return new self();
	}

	public static function cron_schedules( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => 60 * 60 * 24 * 7,
			'display' => __('Semanal', 'pw-calendario')
		);

		$schedules['twiceweekly'] = array(
			'interval' => 60 * 60 * 24 * 3.5,
			'display' => __('Dos veces por semana', 'pw-calendario')
		);

		$schedules['monthly'] = array(
			'interval' => 60 * 60 * 24 * 30.5,
			'display' => __('Mensual', 'pw-calendario')
		);

		$schedules['twicemonthly'] = array(
			'interval' => 60 * 60 * 24 * 15,
			'display' => __('Dos veces al mes', 'pw-calendario')
		);

		$schedules['twicehourly'] = array(
			'interval' => 60 * 30,
			'display' => __('Cada 30 minutos', 'pw-calendario')
		);

		$schedules['everyfifteen'] = array(
			'interval' => 60 * 15,
			'display' => __('Cada 15 minutos', 'pw-calendario')
		);

		$schedules['everyfive'] = array(
			'interval' => 60 * 5,
			'display' => __('Cada 5 minutos', 'pw-calendario')
		);

		return $schedules;
	}

	protected function activate_scheduler() {
		$mode = Booked_WC_Settings::get_option('cleanup_mode');

		$recurrence = $mode;
		$schedule_name = BOOKED_WC_PLUGIN_PREFIX . 'cron_' . $recurrence;

		if ($recurrence && !wp_next_scheduled( $schedule_name) ) {
			wp_schedule_event(time(), $recurrence, $schedule_name);
		}

		add_action($schedule_name, array($this, 'execute_cron'), 20 );
	}

	public function execute_cron() {
		Booked_WC_Cleanup::start();
	}
}