=== Pw Calendario ===
Contributors: piensaenweb
Tags: citas, reservas, calendario, visitas, bodega
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestion de citas y reservas de visitas: calendario publico, aprobacion y recordatorios.

== Description ==

Pw Calendario gestiona las reservas de visitas desde WordPress:

* Calendario publico con franjas horarias configurables por dia de la semana.
* Reserva con registro de usuario o como invitado.
* Aprobacion manual de las solicitudes, o alta directa en el calendario.
* Calendarios multiples, cada uno con su responsable y sus avisos.
* Campos personalizados en el formulario de reserva.
* Correos de confirmacion, aprobacion, cancelacion y recordatorio.
* Exportacion de las citas a CSV.
* Feeds iCalendar protegidos con clave, para suscribirse desde Google
  Calendar, Outlook o Calendario de Apple.
* Cobro de las citas mediante WooCommerce (opcional).
* Panel de gestion para agentes desde el front-end (opcional).

Toda la interfaz esta en castellano y el plugin no depende de ningun
servicio externo.

= Shortcodes =

* `[booked-calendar]` — calendario de reservas.
* `[booked-calendar calendar="12"]` — un calendario concreto.
* `[booked-calendar switcher="true"]` — con selector de calendario.
* `[booked-profile]` — perfil del cliente con sus citas.
* `[booked-appointments]` — solo el listado de citas del cliente.
* `[booked-login]` — formulario de acceso y registro.

== Installation ==

1. Sube la carpeta `pw-calendario` a `/wp-content/plugins/`.
2. Activa el plugin en la pantalla **Plugins**.
3. Crea una pagina con el shortcode `[booked-calendar]`.
4. Crea otra pagina con el shortcode `[booked-profile]`.
5. Configura las franjas horarias en **Citas > Ajustes**.

Consulta INSTALACION.md si vienes de la version anterior del calendario: se
conservan todas las citas y la configuracion, pero hay que renovar las URL
de los feeds.

== Frequently Asked Questions ==

= Se borran las citas al desinstalar el plugin? =

No. Al borrar el plugin solo se retiran los datos regenerables (tareas
programadas, transitorios y capacidades). Las citas y la configuracion se
conservan.

Si quieres que se borre todo, activa antes la opcion correspondiente:

`update_option( 'pwcal_borrar_datos_al_desinstalar', 1 );`

= Por que han dejado de funcionar mis URL de feed de calendario? =

Porque la clave anterior era predecible y cualquiera podia descargar el
listado de citas. Ahora se genera un secreto aleatorio. Copia las URL nuevas
en **Citas > Ajustes > Feeds de calendario**.

= Necesita WooCommerce? =

No. WooCommerce solo hace falta si quieres cobrar las citas.

== Changelog ==

= 3.0.0 =
* **SEGURIDAD:** Todas las peticiones AJAX del escritorio (31 puntos de entrada) exigen ahora nonce y comprobación de permisos. Antes no verificaban ninguno de los dos.
* **SEGURIDAD:** Corregida una vulnerabilidad crítica en el complemento de agentes: cualquier usuario identificado, incluido un suscriptor, podía borrar de forma permanente cualquier entrada del sitio o publicar borradores ajenos.
* **SEGURIDAD:** El feed de calendario usaba un hash predecible calculado a partir de la URL del sitio. Ahora usa un secreto aleatorio y comparación en tiempo constante.
* **SEGURIDAD:** Corregido un XSS almacenado a través de los campos personalizados del formulario público de reserva.
* **SEGURIDAD:** La exportación a CSV exige permisos y nonce; antes cualquier usuario identificado podía descargar los datos de todas las citas.
* **SEGURIDAD:** Corregida la inyección de fórmulas en el CSV exportado y la inyección de campos en el feed iCalendar.
* **SEGURIDAD:** El formulario de edición del perfil exige nonce, lo que impide el cambio de correo por CSRF y la apropiación de cuentas.
* **SEGURIDAD:** Los ajustes de «no permitir cancelaciones» y «margen de cancelación» se aplican también en el servidor, no solo en la interfaz.
* **SEGURIDAD:** La cancelación de citas valida que el identificador corresponda realmente a una cita.
* **SEGURIDAD:** Limitados los intentos de acceso y de recuperación de contraseña desde el calendario.
* **SEGURIDAD:** Eliminadas las llamadas a servicios externos que hacía el plugin, incluida una que enviaba peticiones con una clave de API incrustada en el código.
* **SEGURIDAD:** Consultas SQL preparadas, guardas de acceso directo en todos los archivos PHP y escapado de la salida en los puntos donde se muestran datos introducidos por los clientes.
* **NUEVO:** Botón «Añadir al calendario» propio (Google Calendar, Outlook y descarga .ics). Sustituye a una librería de terceros que mostraba su propia marca y enviaba los datos de la cita a un servicio externo.
* **CORREGIDO:** El botón «Añadir al calendario» no llegaba a cargarse por un desajuste en el identificador del script.
* **ELIMINADO:** El «modo demostración», que desactivaba en silencio el cambio de avatar, nombre, correo y contraseña del perfil si alguien activaba su opción en la base de datos.
* **ELIMINADO:** Recursos e identidad visual heredados: el banner de la pantalla de novedades (sustituido por uno propio en SVG, de 2,4 MB a 2 KB), dos imágenes huérfanas sin usar, la tipografía de iconos y los nombres de archivo y carpeta con el prefijo antiguo.
* **NUEVO:** El plugin pasa a llamarse Pw Calendario y toda la interfaz está en castellano.
* **AJUSTE:** Estructura reorganizada segun la recomendacion de WordPress: archivo principal reducido a arranque, clase principal y pantallas del escritorio en `includes/`, registro de ganchos centralizado en un cargador, `uninstall.php` (que por defecto no borra datos), imagenes en `assets/images/` y `readme.txt` con las cabeceras estandar.
* **CORREGIDO:** La pantalla «Novedades» aparecia en blanco. Las expresiones regulares del lector del readme usaban secuencias que PCRE2 rechaza desde PHP 7.3, y `preg_replace()` devolvia null.
* **CORREGIDO:** Tres deprecaciones de PHP 8 por declarar un parametro opcional antes de uno obligatorio.
* **AJUSTE:** Compatibilidad con PHP 8: corregidos los accesos a índices y propiedades que provocaban errores fatales.
* **AJUSTE:** El perfil «Gestor de citas» y sus capacidades se registran al activar el plugin, no en cada carga de página.
* **AJUSTE:** El CSV se exporta con marca UTF-8 y punto y coma como separador, para que Excel lo abra bien en español.

== Upgrade Notice ==

= 3.0.0 =
Actualización de seguridad importante. Tras actualizar, revisa las URL de los feeds de calendario en Citas > Ajustes: las anteriores han dejado de ser válidas.
