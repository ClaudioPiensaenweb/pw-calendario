<?php
/**
 * Actualizaciones automáticas desde GitHub.
 *
 * Integra el plugin en el sistema de actualizaciones de WordPress: la
 * versión nueva aparece en **Escritorio → Actualizaciones** y en la
 * pantalla de **Plugins**, y se instala con el botón «Actualizar ahora»,
 * igual que un plugin del directorio oficial.
 *
 * Cómo funciona
 * -------------
 * 1. Consulta la última *release* del repositorio con la API de GitHub.
 * 2. Compara su etiqueta (`v3.0.1` → `3.0.1`) con la versión instalada.
 * 3. Si hay una posterior, la ofrece a WordPress.
 * 4. Al actualizar, descarga el ZIP adjunto a la release.
 *
 * No hace falta configurar nada: el repositorio es público, así que no hay
 * credenciales, ni tokens que caduquen, ni nada que rotar.
 *
 * Sobre por qué no se incrusta un token: si el plugin pudiera descifrar un
 * token por sí solo, la clave tendría que viajar dentro del propio plugin,
 * y cualquiera con los archivos tendría ambas cosas. Sería ofuscación, no
 * cifrado. Un repositorio público sin secreto alguno da la misma
 * protección efectiva y es mucho más robusto: nada caduca.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprobador de actualizaciones contra GitHub.
 */
class Pw_Calendario_Actualizador {

	/**
	 * Propietario del repositorio.
	 */
	const PROPIETARIO = 'ClaudioPiensaenweb';

	/**
	 * Nombre del repositorio.
	 */
	const REPOSITORIO = 'pw-calendario';

	/**
	 * Clave del transitorio donde se guarda la respuesta de la API.
	 */
	const CACHE = 'pwcal_release_github';

	/**
	 * Duración de la caché.
	 *
	 * La API de GitHub sin autenticar admite 60 peticiones por hora e IP.
	 * Con esta caché basta de sobra: WordPress comprueba actualizaciones
	 * dos veces al día.
	 */
	const CACHE_DURACION = 6 * HOUR_IN_SECONDS;

	/**
	 * Ruta relativa del archivo principal (`pw-calendario/pw-calendario.php`).
	 *
	 * @var string
	 */
	private $basename;

	/**
	 * Carpeta del plugin (`pw-calendario`).
	 *
	 * @var string
	 */
	private $carpeta;

	/**
	 * Registra los ganchos.
	 */
	public function __construct() {

		$this->basename = plugin_basename( PWCAL_PLUGIN_FILE );
		$this->carpeta  = dirname( $this->basename );

		// Inyecta la actualización en el listado que maneja WordPress.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'comprobar_actualizacion' ) );

		// Ficha de detalles del plugin («Ver detalles de la versión»).
		add_filter( 'plugins_api', array( $this, 'ficha_del_plugin' ), 10, 3 );

		// Corrige el nombre de la carpeta extraída si no coincide.
		add_filter( 'upgrader_source_selection', array( $this, 'corregir_carpeta' ), 10, 4 );

		// Enlace para forzar la comprobación sin esperar a la caché.
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'enlace_comprobar' ) );
		add_action( 'admin_init', array( $this, 'atender_comprobacion_manual' ) );

		// Al terminar una actualización, se vacía la caché.
		add_action( 'upgrader_process_complete', array( $this, 'vaciar_cache' ), 10, 2 );

		// Resultado de la comprobación manual.
		add_action( 'admin_notices', array( $this, 'aviso_resultado' ) );
	}

	/**
	 * Recupera la última release del repositorio.
	 *
	 * El resultado se guarda en un transitorio, incluido el fallo, para no
	 * repetir la consulta en cada carga si GitHub no responde.
	 *
	 * @param bool $forzar Si es cierto, ignora la caché.
	 * @return array|false Datos de la release, o false si no se puede obtener.
	 */
	private function obtener_release( $forzar = false ) {

		if ( ! $forzar ) {

			$cacheado = get_site_transient( self::CACHE );

			if ( 'sin-release' === $cacheado ) {
				return false;
			}

			if ( is_array( $cacheado ) ) {
				return $cacheado;
			}
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::PROPIETARIO,
			self::REPOSITORIO
		);

		$respuesta = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
					'User-Agent'           => 'Pw-Calendario/' . PWCAL_VERSION,
				),
			)
		);

		if ( is_wp_error( $respuesta ) || 200 !== (int) wp_remote_retrieve_response_code( $respuesta ) ) {
			// Se cachea el fallo un rato para no insistir en cada carga.
			set_site_transient( self::CACHE, 'sin-release', 30 * MINUTE_IN_SECONDS );
			return false;
		}

		$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );

		if ( ! is_array( $datos ) || empty( $datos['tag_name'] ) ) {
			set_site_transient( self::CACHE, 'sin-release', 30 * MINUTE_IN_SECONDS );
			return false;
		}

		$release = array(
			'version'    => ltrim( $datos['tag_name'], 'vV' ),
			'publicada'  => isset( $datos['published_at'] ) ? $datos['published_at'] : '',
			'notas'      => isset( $datos['body'] ) ? (string) $datos['body'] : '',
			'url'        => isset( $datos['html_url'] ) ? $datos['html_url'] : '',
			'descarga'   => '',
			'prerelease' => ! empty( $datos['prerelease'] ),
		);

		// Se prefiere el ZIP adjunto a la release: lleva la estructura de
		// carpetas correcta y se descarga sin pasar por la API.
		if ( ! empty( $datos['assets'] ) && is_array( $datos['assets'] ) ) {
			foreach ( $datos['assets'] as $adjunto ) {

				// No se usa `str_ends_with()`: existe desde PHP 8.0 y el
				// plugin declara compatibilidad con 7.4.
				$nombre = isset( $adjunto['name'] ) ? strtolower( (string) $adjunto['name'] ) : '';

				if ( '' === $nombre || '.zip' !== substr( $nombre, -4 ) ) {
					continue;
				}

				if ( empty( $adjunto['browser_download_url'] ) ) {
					continue;
				}

				$release['descarga'] = $adjunto['browser_download_url'];
				break;
			}
		}

		// Sin adjunto, se recurre al ZIP del código fuente que genera
		// GitHub. Se descomprime en una carpeta con el hash del commit, y
		// de eso se encarga `corregir_carpeta()`.
		if ( ! $release['descarga'] && ! empty( $datos['zipball_url'] ) ) {
			$release['descarga'] = $datos['zipball_url'];
		}

		if ( ! $release['descarga'] ) {
			set_site_transient( self::CACHE, 'sin-release', 30 * MINUTE_IN_SECONDS );
			return false;
		}

		set_site_transient( self::CACHE, $release, self::CACHE_DURACION );

		return $release;
	}

	/**
	 * Añade la actualización al transitorio de WordPress.
	 *
	 * @param mixed $transitorio Objeto de actualizaciones de WordPress.
	 * @return mixed
	 */
	public function comprobar_actualizacion( $transitorio ) {

		// WordPress puede pasar algo que no sea un objeto en las primeras
		// llamadas; en ese caso se devuelve tal cual.
		if ( ! is_object( $transitorio ) ) {
			return $transitorio;
		}

		$release = $this->obtener_release();

		if ( ! $release || $release['prerelease'] ) {
			return $transitorio;
		}

		if ( ! isset( $transitorio->response ) || ! is_array( $transitorio->response ) ) {
			$transitorio->response = array();
		}

		$ficha = array(
			'id'           => self::PROPIETARIO . '/' . self::REPOSITORIO,
			'slug'         => $this->carpeta,
			'plugin'       => $this->basename,
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['descarga'],
			'tested'       => '6.8',
			'requires'     => '6.0',
			'requires_php' => '7.4',
		);

		if ( version_compare( $release['version'], PWCAL_VERSION, '>' ) ) {

			$transitorio->response[ $this->basename ] = (object) $ficha;

			// Si estaba en la lista de «sin actualizaciones», se retira.
			if ( isset( $transitorio->no_update[ $this->basename ] ) ) {
				unset( $transitorio->no_update[ $this->basename ] );
			}

			return $transitorio;
		}

		// Al día: se declara igualmente para que WordPress no lo muestre
		// como plugin sin información de actualizaciones.
		if ( ! isset( $transitorio->no_update ) || ! is_array( $transitorio->no_update ) ) {
			$transitorio->no_update = array();
		}

		$ficha['new_version'] = PWCAL_VERSION;

		$transitorio->no_update[ $this->basename ] = (object) $ficha;

		return $transitorio;
	}

	/**
	 * Rellena la ficha que se muestra en «Ver detalles de la versión».
	 *
	 * @param mixed  $resultado  Resultado previo.
	 * @param string $accion     Acción solicitada.
	 * @param object $argumentos Argumentos de la consulta.
	 * @return mixed
	 */
	public function ficha_del_plugin( $resultado, $accion, $argumentos ) {

		if ( 'plugin_information' !== $accion ) {
			return $resultado;
		}

		if ( empty( $argumentos->slug ) || $argumentos->slug !== $this->carpeta ) {
			return $resultado;
		}

		$release = $this->obtener_release();

		if ( ! $release ) {
			return $resultado;
		}

		return (object) array(
			'name'          => 'Pw Calendario',
			'slug'          => $this->carpeta,
			'version'       => $release['version'],
			'author'        => '<a href="https://piensaenweb.com">Piensaenweb</a>',
			'homepage'      => $release['url'],
			'requires'      => '6.0',
			'tested'        => '6.8',
			'requires_php'  => '7.4',
			'last_updated'  => $release['publicada'],
			'download_link' => $release['descarga'],
			'sections'      => array(
				'changelog' => $this->notas_a_html( $release['notas'] ),
			),
		);
	}

	/**
	 * Convierte las notas de la release (Markdown) en HTML sencillo.
	 *
	 * @param string $notas Texto en Markdown.
	 * @return string
	 */
	private function notas_a_html( $notas ) {

		$notas = trim( (string) $notas );

		if ( '' === $notas ) {
			return esc_html__( 'No hay notas para esta versión.', 'pw-calendario' );
		}

		// Se escapa primero y se aplica formato después, para que el
		// contenido de la release no pueda inyectar HTML.
		$html = esc_html( $notas );

		$reemplazos = array(
			'/^\s*[\*\-]\s+(.*)$/m' => '<li>$1</li>',
			'/\*\*(.*?)\*\*/'       => '<strong>$1</strong>',
		);

		foreach ( $reemplazos as $patron => $sustituto ) {

			$resultado = preg_replace( $patron, $sustituto, $html );

			// Si un patrón fallara, se conserva el texto anterior en lugar
			// de propagar un null.
			if ( null !== $resultado ) {
				$html = $resultado;
			}
		}

		$agrupado = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );

		if ( null !== $agrupado ) {
			$html = $agrupado;
		}

		return wpautop( $html );
	}

	/**
	 * Ajusta el nombre de la carpeta extraída del ZIP.
	 *
	 * El ZIP que genera GitHub del código fuente se descomprime en una
	 * carpeta con el nombre del repositorio y el hash del commit
	 * (`pw-calendario-a1b2c3d/`). Si se instalara así, WordPress lo tomaría
	 * por un plugin distinto y quedarían dos copias.
	 *
	 * @param string $origen        Ruta de la carpeta extraída.
	 * @param string $origen_remoto Ruta remota original.
	 * @param object $actualizador  Instancia del actualizador.
	 * @param array  $extra         Datos adicionales del proceso.
	 * @return string|WP_Error
	 */
	public function corregir_carpeta( $origen, $origen_remoto, $actualizador, $extra = array() ) {

		global $wp_filesystem;

		if ( empty( $extra['plugin'] ) || $extra['plugin'] !== $this->basename ) {
			return $origen;
		}

		if ( ! $wp_filesystem ) {
			return $origen;
		}

		$destino = trailingslashit( dirname( untrailingslashit( $origen ) ) ) . $this->carpeta;

		if ( untrailingslashit( $origen ) === untrailingslashit( $destino ) ) {
			return $origen;
		}

		if ( $wp_filesystem->exists( $destino ) ) {
			$wp_filesystem->delete( $destino, true );
		}

		if ( ! $wp_filesystem->move( $origen, $destino ) ) {
			return new WP_Error(
				'pwcal_carpeta',
				esc_html__( 'No se ha podido preparar la carpeta del plugin para la actualización.', 'pw-calendario' )
			);
		}

		return trailingslashit( $destino );
	}

	/**
	 * Añade el enlace «Buscar actualizaciones» en la pantalla de plugins.
	 *
	 * @param array $enlaces Enlaces existentes.
	 * @return array
	 */
	public function enlace_comprobar( $enlaces ) {

		if ( ! current_user_can( 'update_plugins' ) ) {
			return $enlaces;
		}

		$url = wp_nonce_url(
			add_query_arg( 'pwcal_comprobar_actualizacion', '1', admin_url( 'plugins.php' ) ),
			'pwcal_comprobar_actualizacion'
		);

		$enlaces[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Buscar actualizaciones', 'pw-calendario' ) . '</a>';

		return $enlaces;
	}

	/**
	 * Atiende la comprobación manual de actualizaciones.
	 *
	 * @return void
	 */
	public function atender_comprobacion_manual() {

		if ( ! isset( $_GET['pwcal_comprobar_actualizacion'] ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		check_admin_referer( 'pwcal_comprobar_actualizacion' );

		delete_site_transient( self::CACHE );
		delete_site_transient( 'update_plugins' );

		$release = $this->obtener_release( true );

		$estado = 'error';

		if ( $release ) {
			$estado = version_compare( $release['version'], PWCAL_VERSION, '>' ) ? 'disponible' : 'al-dia';
		}

		wp_safe_redirect(
			add_query_arg( 'pwcal_resultado', $estado, admin_url( 'plugins.php' ) )
		);
		exit;
	}

	/**
	 * Muestra el resultado de la comprobación manual de actualizaciones.
	 *
	 * @return void
	 */
	public function aviso_resultado() {

		if ( ! isset( $_GET['pwcal_resultado'] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$resultado = sanitize_key( $_GET['pwcal_resultado'] );

		$avisos = array(
			'disponible' => array(
				'notice-warning',
				__( 'Hay una versión nueva de Pw Calendario disponible. La verás en el listado de plugins.', 'pw-calendario' ),
			),
			'al-dia'     => array(
				'notice-success',
				__( 'Pw Calendario está actualizado a la última versión.', 'pw-calendario' ),
			),
			'error'      => array(
				'notice-error',
				__( 'No se ha podido consultar GitHub para comprobar si hay actualizaciones. Vuelve a intentarlo en unos minutos.', 'pw-calendario' ),
			),
		);

		if ( ! isset( $avisos[ $resultado ] ) ) {
			return;
		}

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $avisos[ $resultado ][0] ),
			esc_html( $avisos[ $resultado ][1] )
		);
	}

	/**
	 * Vacía la caché cuando termina una actualización del plugin.
	 *
	 * @param object $actualizador Instancia del actualizador.
	 * @param array  $datos        Información del proceso.
	 * @return void
	 */
	public function vaciar_cache( $actualizador, $datos ) {

		if ( empty( $datos['type'] ) || 'plugin' !== $datos['type'] ) {
			return;
		}

		delete_site_transient( self::CACHE );
	}
}
