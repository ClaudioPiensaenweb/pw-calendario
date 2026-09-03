<?php
/**
 * Registro centralizado de ganchos.
 *
 * Concentra en un solo lugar todas las acciones y filtros del plugin, en
 * lugar de repartir llamadas a `add_action()` por los constructores. Así
 * la tabla de ganchos se puede leer de un vistazo y se registra toda a la
 * vez, en `ejecutar()`.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Acumula los ganchos y los registra en WordPress.
 */
class Pw_Calendario_Loader {

	/**
	 * Acciones pendientes de registrar.
	 *
	 * @var array
	 */
	private $acciones = array();

	/**
	 * Filtros pendientes de registrar.
	 *
	 * @var array
	 */
	private $filtros = array();

	/**
	 * Añade una acción a la cola.
	 *
	 * @param string   $gancho     Nombre del gancho.
	 * @param callable $devolucion Función a ejecutar.
	 * @param int      $prioridad  Prioridad.
	 * @param int      $argumentos Número de argumentos aceptados.
	 * @return void
	 */
	public function accion( $gancho, $devolucion, $prioridad = 10, $argumentos = 1 ) {

		$this->acciones[] = array(
			'gancho'     => $gancho,
			'devolucion' => $devolucion,
			'prioridad'  => $prioridad,
			'argumentos' => $argumentos,
		);
	}

	/**
	 * Añade un filtro a la cola.
	 *
	 * @param string   $gancho     Nombre del gancho.
	 * @param callable $devolucion Función a ejecutar.
	 * @param int      $prioridad  Prioridad.
	 * @param int      $argumentos Número de argumentos aceptados.
	 * @return void
	 */
	public function filtro( $gancho, $devolucion, $prioridad = 10, $argumentos = 1 ) {

		$this->filtros[] = array(
			'gancho'     => $gancho,
			'devolucion' => $devolucion,
			'prioridad'  => $prioridad,
			'argumentos' => $argumentos,
		);
	}

	/**
	 * Registra en WordPress todos los ganchos acumulados.
	 *
	 * @return void
	 */
	public function ejecutar() {

		foreach ( $this->acciones as $a ) {
			add_action( $a['gancho'], $a['devolucion'], $a['prioridad'], $a['argumentos'] );
		}

		foreach ( $this->filtros as $f ) {
			add_filter( $f['gancho'], $f['devolucion'], $f['prioridad'], $f['argumentos'] );
		}
	}
}
