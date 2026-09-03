<?php
/**
 * Actualizaciones automáticas desde un repositorio privado de GitHub.
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
 * El repositorio es privado, así que hacen falta credenciales tanto para
 * consultar la API como para descargar el ZIP. El token se lee de la
 * constante `PWCAL_GITHUB_TOKEN`, que debe definirse en `wp-config.php`
 * (nunca en la base de datos ni en este archivo):
 *
 *     define( 'PWCAL_GITHUB_TOKEN', 'github_pat_...' );
 *
 * Sin token el plugin funciona con normalidad; simplemente no busca
 * actualizaciones.
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
	 * Duración de la caché. Evita consultar la API en cada carga.
	 */
	const CACHE_DURACION = 6 * HOUR_IN_SECONDS;

	/**
	 * Ruta relativa del archivo principal (por ejemplo `pw-calendario/pw-calendario.php`).
	 *
	 * @var string
	 */
	private $basename;

	/**
	 * Carpeta del plugin (por ejemplo `pw-calendario`).
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

		// Autoriza la descarga del ZIP desde un repositorio privado.
		add_filter( 'http_request_args', array( $this, 'autorizar_descarga' ), 10, 2 );

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
				__( 'No se ha podido consultar GitHub. Comprueba que la constante PWCAL_GITHUB_TOKEN está definida en wp-config.php y que el token sigue siendo válido.', 'pw-calendario' ),
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
	 * Devuelve el token de acceso a GitHub, si está configurado.
	 *
	 * @return string Cadena vacía si no hay token.
	 */
	private function token() {

		$token = defined( 'PWCAL_GITHUB_TOKEN' ) ? PWCAL_GITHUB_TOKEN : '';

		/**
		 * Permite proporcionar el token por otra vía (por ejemplo, un gestor
		 * de secretos) sin tocar `wp-config.php`.
		 *
		 * @param string $token Token actual.
		 */
		$token = apply_filters( 'pwcal_github_token', $token );

		return is_string( $token ) ? trim( $token ) : '';
	}

	/**
	 * Cabeceras comunes de las peticiones a la API de GitHub.
	 *
	 * @param string $accept Valor de la cabecera Accept.
	 * @return array
	 */
	private function cabeceras( $accept = 'application/vnd.github+json' ) {

		return array(
			'Accept'               => $accept,
			'X-GitHub-Api-Version' => '2022-11-28',
			'Authorization'        => 'Bearer ' . $this->token(),
			'User-Agent'           => 'Pw-Calendario/' . PWCAL_VERSION,
		);
	}

	/**
	 * Recupera la última release del repositorio.
	 *
	 * El resultado se guarda en un transitorio, incluido el fallo, para no
	 * repetir la consulta en cada carga si algo va mal.
	 *
	 * @param bool $forzar Si es cierto, ignora la caché.
	 * @return array|false Datos de la release, o false si no se puede obtener.
	 */
	private function obtener_release( $forzar = false ) {

		if ( ! $this->token() ) {
			return false;
		}

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
				'headers'   => $this->cabeceras(),
				'sslverify' => true,
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
			'version'     => ltrim( $datos['tag_name'], 'vV' ),
			'publicada'   => isset( $datos['published_at'] ) ? $datos['published_at'] : '',
			'notas'       => isset( $datos['body'] ) ? (string) $datos['body'] : '',
			'url'         => isset( $datos['html_url'] ) ? $datos['html_url'] : '',
			'descarga'    => '',
			'prerelease'  => ! empty( $datos['prerelease'] ),
		);

		// Se prefiere el ZIP adjunto a la release. En un repositorio privado
		// hay que pedirlo por su URL de API, no por `browser_download_url`.
		if ( ! empty( $datos['assets'] ) && is_array( $datos['assets'] ) ) {
			foreach ( $datos['assets'] as $adjunto ) {

				// No se usa `str_ends_with()`: existe desde PHP 8.0 y el
				// plugin declara compatibilidad con 7.4.
				$nombre = isset( $adjunto['name'] ) ? strtolower( (string) $adjunto['name'] ) : '';

				if ( '' === $nombre || '.zip' !== substr( $nombre, -4 ) ) {
					continue;
				}

				$release['descarga'] = sprintf(
					'https://api.github.com/repos/%s/%s/releases/assets/%d',
					self::PROPIETARIO,
					self::REPOSITORIO,
					(int) $adjunto['id']
				);

				break;
			}
		}

		// Sin adjunto, se recurre al ZIP que genera GitHub del código fuente.
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
			'id'          => self::PROPIETARIO . '/' . self::REPOSITORIO,
			'slug'        => $this->carpeta,
			'plugin'      => $this->basename,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['descarga'],
			'tested'      => '6.8',
			'requires'    => '6.0',
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
	 * @param mixed  $resultado Resultado previo.
	 * @param string $accion    Acción solicitada.
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

		// Las notas de la release vienen en Markdown; se convierte lo
		// mínimo para que se lean bien en el modal de WordPress.
		$notas = esc_html( $release['notas'] );
		$notas = preg_replace( '/^\s*[\*\-]\s+(.*)$/m', '<li>$1</li>', $notas );
		$notas = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $notas );
		$notas = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $notas );

		if ( null === $notas ) {
			$notas = esc_html( $release['notas'] );
		}

		return (object) array(
			'name'           => 'Pw Calendario',
			'slug'           => $this->carpeta,
			'version'        => $release['version'],
			'author'         => '<a href="https://piensaenweb.com">Piensaenweb</a>',
			'homepage'       => $release['url'],
			'requires'       => '6.0',
			'tested'         => '6.8',
			'requires_php'   => '7.4',
			'last_updated'   => $release['publicada'],
			'download_link'  => $release['descarga'],
			'sections'       => array(
				'changelog' => $notas ? $notas : esc_html__( 'No hay notas para esta versión.', 'pw-calendario' ),
			),
		);
	}

	/**
	 * Añade la autorización a la descarga del ZIP.
	 *
	 * `download_url()` no admite cabeceras propias, así que se inyectan
	 * aquí. Sin esto, un repositorio privado devuelve 404 al descargar.
	 *
	 * @param array  $argumentos Argumentos de la petición HTTP.
	 * @param string $url        URL solicitada.
	 * @return array
	 */
	public function autorizar_descarga( $argumentos, $url ) {

		if ( ! is_string( $url ) ) {
			return $argumentos;
		}

		$prefijo_activos = sprintf(
			'https://api.github.com/repos/%s/%s/',
			self::PROPIETARIO,
			self::REPOSITORIO
		);

		// Solo se toca lo que va a nuestro propio repositorio: nunca se
		// adjunta el token a peticiones de terceros.
		if ( 0 !== strpos( $url, $prefijo_activos ) ) {
			return $argumentos;
		}

		$token = $this->token();

		if ( ! $token ) {
			return $argumentos;
		}

		if ( ! isset( $argumentos['headers'] ) || ! is_array( $argumentos['headers'] ) ) {
			$argumentos['headers'] = array();
		}

		$argumentos['headers']['Authorization']        = 'Bearer ' . $token;
		$argumentos['headers']['X-GitHub-Api-Version'] = '2022-11-28';
		$argumentos['headers']['User-Agent']           = 'Pw-Calendario/' . PWCAL_VERSION;

		// Para bajar el binario del adjunto hay que pedir octet-stream.
		if ( false !== strpos( $url, '/releases/assets/' ) ) {
			$argumentos['headers']['Accept'] = 'application/octet-stream';
		}

		return $argumentos;
	}

	/**
	 * Ajusta el nombre de la carpeta extraída del ZIP.
	 *
	 * El ZIP que genera GitHub del código fuente se descomprime en una
	 * carpeta con el nombre del repositorio y el hash del commit
	 * (`pw-calendario-a1b2c3d/`). Si se instalara así, WordPress lo tomaría
	 * por un plugin distinto y quedarían dos copias. Se renombra a la
	 * carpeta correcta.
	 *
	 * @param string $origen      Ruta de la carpeta extraída.
	 * @param string $origen_remoto Ruta remota original.
	 * @param object $actualizador Instancia del actualizador.
	 * @param array  $extra       Datos adicionales del proceso.
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
