=== Pw Calendario ===
Contributors: piensaenweb
Tags: citas, reservas, calendario, visitas, bodega
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.4.1
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

= 3.4.1 =
* **CORREGIDO:** En el calendario publico se podia avanzar de mes pero no volver atras: la flecha izquierda no aparecia nunca. Al endurecer la entrada se convirtio en un si/no el campo que el JavaScript usa para enviar el mes desde el que se ha navegado, y sin ese dato el calendario no sabe que se ha movido de sitio.


= 3.4.0 =
* **NUEVO:** La importacion puede traer tambien los horarios del sitio de origen: las franjas semanales de cada calendario, los rangos de fechas con sus cierres y las franjas apagadas dia a dia, traduciendo los identificadores de calendario. Va aparte y solo si se pide, porque SUSTITUYE el calendario del sitio de destino.
* **AJUSTE:** La importacion trae ahora tambien los ajustes de comportamiento del origen: si se reserva como invitado, si las citas se aprueban solas, los margenes de reserva y cancelacion, si se oculta el calendario por defecto, los colores. Antes solo venian las plantillas de correo, y eso obligaba a reconfigurar el sitio a mano adivinando lo que habia. Los campos personalizados siguen quedando fuera, porque apuntan a productos que en el destino no existen.


= 3.3.1 =
* **CORREGIDO:** Si una importacion se interrumpia a medias (una peticion que se corta, el tiempo de ejecucion agotado), podia quedar una cita sin su metadato de procedencia: no se reconocia al relanzar, la pasada siguiente la duplicaba, y deshacer la importacion no se la llevaba. Ahora la procedencia se escribe lo primero y una marca de "terminada" lo ultimo, asi que una cita a medias se reconoce y se sustituye entera.


= 3.3.0 =
* **NUEVO:** La importacion trae tambien las plantillas de correo del sitio de origen: confirmacion, aprobacion, cancelacion, registro y los dos recordatorios, con su margen de aviso, el remitente y el logo. Solo entra esa lista, y solo si en el destino esta vacia: nunca se pisa lo que ya haya configurado. Lo que describe la estructura del sitio (franjas, campos personalizados, rangos de fechas) queda fuera a proposito, porque importarlo se llevaria por delante la configuracion del destino.


= 3.2.3 =
* **CORREGIDO:** El «he olvidado la contrasena» del calendario abortaba con un error critico. El plugin aplicaba los filtros `retrieve_password_title` y `retrieve_password_message` con menos argumentos de los que pasa WordPress (1 de 3 y 2 de 4), asi que cualquier tema o plugin enganchado a ellos con la firma completa recibia menos de los que exige. Con el tema Bricks activo fallaba siempre.


= 3.2.2 =
* **NUEVO:** Visor del registro de errores para administradores. En un sitio sin acceso al servidor, un error critico solo deja el mensaje generico de WordPress y el detalle se queda en un archivo inaccesible.


= 3.2.1 =
* **CORREGIDO:** El «he olvidado la contraseña» del calendario abortaba con un error critico en las versiones recientes de WordPress. La funcion se fabricaba la clave de restablecimiento a mano con `class-phpass.php`, que el nucleo ya no incluye.
* **CORREGIDO (llevaba anos):** Ademas, la clave se guardaba sin el prefijo de tiempo que WordPress necesita (`time():hash`) para comprobar la caducidad, asi que TODOS los enlaces de restablecimiento que generaba el plugin se rechazaban como invalidos. Ahora la clave la genera WordPress con `get_password_reset_key()`, que se ocupa del hash y del formato.


= 3.2.0 =
* **NUEVO:** Control de en que dominio se envian los correos. Una copia del sitio en un dominio de pruebas manda confirmaciones y recordatorios de verdad a clientes de verdad. En Citas > Ajustes > Correos se indica el dominio definitivo y el plugin solo envia cuando el sitio responde en el: en cualquier otro se calla, y vuelve a enviar por si solo el dia que se publica la web. Si se deja vacio, se envia con normalidad, asi que una instalacion nueva se comporta igual que antes.
* **NUEVO:** Se respeta el tipo de entorno de WordPress. Si `WP_ENVIRONMENT_TYPE` declara el sitio como staging, development o local, no se envia nada.
* **NUEVO:** Ningun correo se descarta en silencio. Los bloqueados quedan anotados con destinatario, asunto y motivo, se ven en los ajustes, y mientras los envios esten detenidos hay un aviso en todas las pantallas del escritorio para que no se olvide reactivarlos.
* **NUEVO:** Importacion de citas desde otra instalacion. Lee el archivo del exportador y crea las citas con su fecha, franja, cliente, calendario y numero de personas. Es repetible: cada cita guarda de donde viene, asi que volver a lanzarla no duplica nada, y se puede deshacer sin tocar las citas creadas en el sitio.
* **AJUSTE:** Al importar no se copia el identificador del pedido de WooCommerce. El pedido no existe en el sitio de destino y el complemento de pagos consulta su estado sin comprobar que exista, lo que provocaria un error fatal en cualquier pantalla que muestre el estado de pago. Las citas que estaban pagadas se marcan como pagadas a mano y el numero original se conserva aparte.
* **AJUSTE:** Al importar tampoco se copian los marcadores de producto ni la basura que dejan los maquetadores en las citas. El texto visible de la experiencia y su importe se conservan intactos.


= 3.1.5 =
* **CORREGIDO:** Un numero de personas negativo se convertia en positivo, asi que enviar -5 creaba una reserva de 5 personas que nadie habia pedido. El saneador de enteros aplica valor absoluto, que es razonable para un identificador pero no para una cantidad.


= 3.1.4 =
* **CORREGIDO (importante):** El aforo de la rejilla del mes seguia contando reservas en lugar de personas. Era el quinto punto de calculo del plugin y el primero que ve el cliente: con una reserva de 3 personas sobre 20 plazas, el calendario anunciaba 19 libres en lugar de 17. La vista del dia y la validacion al reservar si contaban bien, asi que no se sobrevendia, pero el numero del calendario enganaba y las reservas se rechazaban antes de lo que el cliente esperaba.


= 3.1.3 =
* **CORREGIDO:** Dos propiedades sin declarar provocaban un aviso de obsolescencia en PHP 8.2 y superiores. Con los errores visibles, el aviso se imprimia dentro de las respuestas del carrito, de la pantalla de pago y de la API de la tienda, con riesgo de romper el JSON que consume el proceso de compra.


= 3.1.2 =
* **CORREGIDO (importante):** Las citas con producto no llegaban al carrito, asi que se creaban como "pendiente de pago" y el cliente no tenia forma de pagarlas. El complemento de WooCommerce identifica el producto de una cita mediante un marcador en forma de comentario HTML dentro de los campos personalizados, y el escapado de la salida lo convertia en texto visible, con lo que dejaba de encontrarse. Afecta a cualquier cita de pago creada desde el calendario o desde el escritorio.
* **CORREGIDO:** El titulo del producto se escapaba dos veces en la ficha de la cita y en los correos, asi que un titulo con "&" salia como "&amp;".
* **CORREGIDO:** Reservar varias franjas horarias en la misma peticion abortaba con un error fatal. El valor recibido es un array y se estaba pasando por un saneador que solo admite texto, que devolvia una cadena vacia; el recuento posterior fallaba. Ahora se valida la estructura entera y lo que no encaja se descarta.


= 3.1.1 =
* **CORREGIDO:** El filtro por dias de la semana de los rangos de fechas no surtia efecto en la vista del dia. El plugin recorre el rango en dos sitios, y el segundo (el que anade los titulos de las franjas) se ejecuta despues y volvia a escribir las franjas de los dias excluidos. La rejilla del mes ocultaba bien los dias, pero al abrir uno excluido aparecian las franjas igualmente. Ahora los dos recorridos aplican el mismo filtro.
* **CORREGIDO:** Un calendario sin franjas semanales guardadas provocaba un aviso de obsolescencia en PHP 8.1 y superiores (conversion automatica de false a array) al aplicar los rangos de fechas personalizados. En PHP 9 habria sido un error.


= 3.1.0 =
* **IMPORTANTE:** El aforo se cuenta ahora en PERSONAS, no en reservas. Antes cada cita descontaba una sola plaza sin importar cuanta gente viniera: en una franja de 20 plazas entraban 20 reservas, y si cada una era de 6 personas se presentaban 120. El numero de personas solo existia en el titulo del producto de WooCommerce, que el plugin no leia nunca. Revisa el aforo configurado en tus franjas: ahora significa lo que parece.
* **NUEVO:** El cliente elige cuantas personas son al reservar, con el maximo puesto en las plazas que quedan libres de verdad. Se valida tambien en el servidor, para que no se pueda saltar ni haya sobreventa si dos personas reservan a la vez.
* **NUEVO:** Un solo producto de WooCommerce por tipo de visita. La cantidad del carrito es el numero de personas, asi que el importe sale solo (precio por persona, sin descuento por grupo) y desaparecen los productos duplicados del tipo "Visita - 1 persona", "- 2 personas", "- 6 personas".
* **NUEVO:** Las franjas personalizadas con rango de fechas admiten dias de la semana. Para "de jueves a domingo, del 16 de julio al 28 de agosto" hacian falta 26 entradas de fecha unica, o el rango completo mas 18 cierres, y repetirlo cada temporada. Ahora es una entrada con dos fechas y cuatro casillas.
* **NUEVO:** Etiqueta `%personas%` para los correos, y el tamano del grupo se ve en el calendario del escritorio, en el listado de pendientes, en la ficha de la cita y en la exportacion a CSV.


= 3.0.1 =
* **CORREGIDO:** Error critico que dejaba el sitio inaccesible cuando WooCommerce estaba activo. El complemento de pagos llamaba a `method_exists()` sobre el carrito de WooCommerce, que es `null` fuera del front-end. En PHP 7 eso era un aviso; desde PHP 8 es un error fatal. El gancho colgaba de `wp_loaded`, asi que se disparaba en todas las peticiones, incluida la pantalla de plugins.
* **CORREGIDO:** El mismo fallo en otros tres accesos al carrito y en los tres accesos a la sesion de WooCommerce. Ahora todos pasan por `Booked_WC_Helper::get_cart()` y `::get_session()`, que comprueban que el objeto existe.
* **NUEVO:** Si el calendario de citas anterior sigue activo, el plugin ya no carga y muestra un aviso, en lugar de abortar con "Cannot redeclare". Los dos comparten 79 nombres de funcion y no pueden convivir.


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
