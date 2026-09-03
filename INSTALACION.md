# Pw Calendario 3.0.0 — Instalación y notas de migración

Sustituye al plugin **Booked 2.3** conservando todos los datos existentes.

## Antes de empezar

Haz una copia de seguridad de la base de datos. Aunque esta versión **no
modifica ningún dato**, el paso de activación reescribe los permisos de los
perfiles de usuario y conviene poder volver atrás.

La carpeta original `booked/` se ha conservado intacta como copia de
seguridad. No la borres hasta haber comprobado que todo funciona.

## Instalación

1. Sube la carpeta `pw-calendario/` a `wp-content/plugins/`.
2. En **Plugins**, desactiva **Booked**.
3. Activa **Pw Calendario**.
4. Borra la carpeta `booked/` del servidor cuando hayas verificado el
   funcionamiento.

El plugin queda desactivado al cambiar el nombre de la carpeta porque
WordPress guarda la ruta del archivo principal (`booked/booked.php`) en la
opción `active_plugins`. Es normal y no afecta a los datos.

## Qué se conserva

No hace falta ninguna migración. Se mantienen sin cambios:

| Elemento | Identificador |
|---|---|
| Tipo de contenido de las citas | `booked_appointments` |
| Taxonomía de calendarios | `booked_custom_calendars` |
| Opciones de configuración | `booked_*` (unas 50) |
| Metadatos de las citas | `_appointment_*`, `_cf_meta_value` |
| Perfil de usuario | `booked_booking_agent` |
| Capacidades | `edit_booked_appointments`, `manage_booked_options` |
| Shortcodes | `[booked-calendar]`, `[booked-profile]`, `[booked-appointments]`, `[booked-login]`, `[booked-fea-appointments]` |
| Ganchos para desarrolladores | `booked_*` (filtros y acciones) |

Las páginas que ya usan los shortcodes siguen funcionando sin tocarlas.

## Qué cambia y requiere tu atención

### 1. Las URL de los feeds de calendario dejan de ser válidas

**Esto es intencionado.** La clave de acceso a los feeds iCalendar se
calculaba con `md5( 'booked_ical_feed_' . url_del_sitio )`, un valor que
cualquiera podía reproducir conociendo la dirección de la web. Con esa URL
se descargaba el listado completo de citas con nombres y correos, sin estar
identificado.

Ahora la clave es un secreto aleatorio guardado en la base de datos. Tras
activar el plugin:

1. Ve a **Citas → Ajustes → Feeds de calendario**.
2. Copia las URL nuevas.
3. Vuelve a suscribirte en Google Calendar, Outlook o Calendario de Apple.

Si en algún momento sospechas que una URL se ha filtrado, puedes invalidar
todas las anteriores borrando la opción `pwcal_feed_secreto`; se generará
una nueva automáticamente.

### 2. Las direcciones del escritorio han cambiado

Los slugs de las pantallas pasan de `booked-*` a `pwcal-*`:

| Antes | Ahora |
|---|---|
| `admin.php?page=booked-appointments` | `admin.php?page=pwcal-appointments` |
| `admin.php?page=booked-pending` | `admin.php?page=pwcal-pending` |
| `admin.php?page=booked-settings` | `admin.php?page=pwcal-settings` |

Si tenías marcadores del navegador guardados, actualízalos.

### 3. Las constantes del plugin se han renombrado

Si algún archivo del tema o un plugin propio usa las constantes antiguas,
hay que actualizarlo:

| Antes | Ahora |
|---|---|
| `BOOKED_VERSION` | `PWCAL_VERSION` |
| `BOOKED_PLUGIN_DIR` | `PWCAL_PLUGIN_DIR` |
| `BOOKED_PLUGIN_URL` | `PWCAL_PLUGIN_URL` |
| `BOOKED_PLUGIN_TEMPLATES_DIR` | `PWCAL_PLANTILLAS_DIR` |
| `BOOKED_AJAX_INCLUDES_DIR` | `PWCAL_AJAX_INCLUDES_DIR` |
| `BOOKED_WELCOME_SCREEN` | `PWCAL_PANTALLA_BIENVENIDA` |
| `BOOKED_DEMO_MODE` | (eliminada, ver más abajo) |

La clase principal pasa de `booked_plugin` a `Pw_Calendario`. Los nombres de
los **ganchos** no han cambiado, así que cualquier `add_filter` o
`add_action` sobre `booked_*` sigue funcionando igual.

### 4. El dominio de traducción es `pw-calendario`

Si tenías traducciones propias del dominio `booked`, hay que renombrarlas.
El plugin incluye `languages/pw-calendario.pot` como plantilla.

## Comprobaciones tras activar

- [ ] El calendario se muestra en la página pública y se puede navegar entre meses.
- [ ] Una reserva de prueba se crea correctamente y llega el correo de confirmación.
- [ ] La cita aparece en **Citas → Pendientes** y se puede aprobar.
- [ ] Un cliente identificado puede cancelar su cita desde la página de perfil.
- [ ] La exportación a CSV descarga el archivo y los acentos se ven bien en Excel.
- [ ] Las franjas horarias se pueden añadir y eliminar desde el escritorio.
- [ ] Los feeds de calendario funcionan con las URL nuevas.

Si alguna acción del escritorio deja de responder, vacía la caché del
navegador: los archivos JavaScript adjuntan ahora un token de seguridad a
cada petición y una versión antigua en caché lo enviaría sin él.

## Sin licencias ni funciones condicionadas

El plugin queda operativo al completo con solo instalarlo y activarlo. No hay
claves, activaciones, modos de prueba ni funciones reservadas. Se ha verificado
que no existe:

- ninguna comprobación de licencia, clave de compra o código de activación;
- ningún gating de tipo «premium», «pro» o de versión reducida;
- ninguna llamada de red desde PHP: el plugin no contacta con ningún servidor
  externo, ni para actualizarse ni para validarse;
- ningún recurso cargado desde un dominio de terceros (la tipografía de iconos
  va incrustada en el propio CSS).

Se han retirado en esta versión:

| Elemento | Motivo |
|---|---|
| Comprobador de actualizaciones | Consultaba `boxyupdates.com`, dominio del autor original |
| Llamada a `api.ticksy.com` | Traía una clave de API ajena incrustada en el código |
| Librería AddEvent (`atc.min.js`) | Clave de licencia ajena, marca propia visible y envío de los datos de la cita a `addevent.com` |
| TGM Plugin Activation | Código de terceros antiguo y sin mantenimiento |
| «Modo demostración» | Si su opción se activaba en la base de datos, desactivaba en silencio el cambio de avatar, nombre, correo y contraseña |

### Rastro del vendor original: qué se ha retirado

Además de lo anterior, se han eliminado los restos de identidad del autor
original:

| Elemento | Antes | Ahora |
|---|---|---|
| Banner de la pantalla de novedades | `welcome-banner.png`, 2,4 MB, con el logotipo y la marca «Booked» | `banner-bienvenida.svg`, 2 KB, con la identidad de Piensaenweb |
| Imágenes huérfanas | `welcome-banner.jpg`, `badge.png` | Eliminadas (no se referenciaban) |
| Tipografía de iconos | `BookedIcons` | `PwCalendarioIcons` |
| Hoja de estilos del front | `dist/booked.css` | `dist/pw-calendario.css` |
| Hoja de estilos del escritorio | `dist/booked-admin.css` | `dist/pw-calendario-admin.css` |
| Tipo de contenido (archivo) | `post-types/booked_appointments.php` | `post-types/tipo-contenido-citas.php` |
| Complemento de feeds | `booked-calendar-feeds.php` | `feeds-calendario.php` |
| Complemento de agentes | `booked-frontend-agents.php` | `agentes-frontend.php` |
| Complemento de pagos | `booked-woocommerce-payments.php` | `pagos-woocommerce.php` |
| Fragmentos de WooCommerce | `booked-administration-fields/`, `booked-frontend-fields/` | `campos-administracion/`, `campos-frontend/` |
| CSS muerto | Reglas `.addeventatc_*` de la librería retirada | Eliminadas |

Si algún archivo del tema incluye directamente alguno de esos archivos por su
ruta antigua, hay que actualizarlo.

### Lo que conserva el nombre antiguo, y por qué

Siguen usando el prefijo `booked`:

- **Los identificadores de base de datos** (`booked_appointments`,
  `booked_custom_calendars`, opciones `booked_*`, metadatos). Cambiarlos
  obligaría a migrar los datos y perderías las citas y la configuración.
- **Los shortcodes** (`[booked-calendar]`, `[booked-profile]`, …), porque están
  escritos dentro del contenido de las páginas del sitio.
- **Los nombres de los ganchos** (`booked_*`), que son la API que consumen los
  tres complementos incluidos.
- **Las clases CSS** (`booked-calendar`, `booked-icon`, …): 168 clases
  distintas con unas 2.200 apariciones entre las hojas de estilo, las
  plantillas y el JavaScript. Se han conservado a propósito, porque si el tema
  o un CSS personalizado del sitio apunta a alguna de esas clases, renombrarlas
  rompería el diseño sin previo aviso.

El botón «Añadir al calendario» se ha reimplementado sin dependencias: genera
los enlaces de Google Calendar y Outlook en el servidor y ofrece la descarga de
un archivo `.ics` para Apple Calendario y Outlook de escritorio.

## Estructura del plugin

Sigue la disposición que recomienda WordPress en su skill
[`wp-plugin-development`](https://github.com/WordPress/agent-skills):

```
pw-calendario/
├── pw-calendario.php                      solo arranque: cabecera, constantes, carga
├── uninstall.php                          limpieza al borrar el plugin
├── readme.txt                             metadatos y documentación
├── languages/                             traducciones (.pot, .po, .mo)
├── includes/
│   ├── class-pw-calendario.php            núcleo: front-end y lógica compartida
│   ├── class-pw-calendario-admin.php      pantallas del escritorio
│   ├── class-pw-calendario-loader.php     registro centralizado de ganchos
│   ├── seguridad.php                      nonces, permisos y saneado
│   ├── anadir-al-calendario.php
│   ├── ajax/                              puntos de entrada AJAX
│   ├── add-ons/                           complementos incluidos
│   └── email-templates/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── templates/                             plantillas de las pantallas
├── post-types/
└── dist/                                  hojas de estilo compiladas
```

Cambios respecto a la disposición anterior:

- **El archivo principal es solo el arranque.** Contiene la cabecera, las
  constantes y la carga de dependencias. La clase, que ocupaba unas 1.400
  líneas dentro de él, vive ahora en `includes/`.
- **Registro de ganchos centralizado** en `Pw_Calendario_Loader`. Toda la
  tabla de acciones y filtros se lee de un vistazo, en lugar de estar
  repartida por los constructores.
- **El código del escritorio está aislado** en `Pw_Calendario_Admin`, que
  solo se instancia cuando `is_admin()` es cierto. Sus ganchos
  (`admin_init`, `admin_menu`, `admin_notices`, `parent_file`,
  `manage_users_*`, `admin_enqueue_scripts`) se disparan únicamente dentro
  de wp-admin, así que aislarlos no cambia el comportamiento. Se han dejado
  fuera a propósito los que sí actúan en el front-end, como
  `admin_bar_menu`, y los que pueden dispararse desde WP-CLI o la API REST,
  como el guardado de los calendarios.
- **`uninstall.php`** nuevo. Por defecto **no borra ninguna cita ni
  configuración**: solo retira lo regenerable (tareas programadas,
  transitorios, capacidades y el secreto de los feeds). El borrado completo
  es voluntario y hay que activarlo antes de desinstalar:
  `update_option( 'pwcal_borrar_datos_al_desinstalar', 1 );`
- **Las imágenes pasan a `assets/images/`**, donde las espera la
  recomendación.
- **Se ha retirado del arranque el trabajo innecesario:** una constante que
  no se usaba en ningún sitio y una consulta a la base de datos que se hacía
  en cada petición, incluidas las del front-end. Ahora se consulta solo en
  el escritorio, donde se necesita.
- **`readme.txt` completo** con las cabeceras estándar (`Contributors`,
  `Stable tag`, `License URI`) y las secciones `Description`,
  `Installation` y `Frequently Asked Questions`.

### Verificación de que no cambia el comportamiento

Al ser una reorganización, se comprobó de forma mecánica:

- **La tabla de ganchos es la misma** antes y después: mismo gancho, misma
  función, misma prioridad y mismos argumentos.
- **Los cuerpos de los 38 métodos son byte a byte idénticos**, salvo tres
  con cambios equivalentes y declarados: dos pasan de `Pw_Calendario::` a
  `self::` (lo mismo dentro de la clase) y uno sustituye una constante por
  la consulta directa de su opción.
- Los 84 archivos PHP pasan `php -l` **sin errores ni avisos** en PHP 8.2,
  8.4 y 8.5.

### Dos fallos que salieron al hacer esta comprobación

Probar el lector del `readme.txt` con PHP real destapó defectos que las
comprobaciones estáticas no detectan:

1. **La pantalla «Novedades» aparecía en blanco.** Las expresiones regulares
   de `booked_parse_readme_changelog()` llevaban una barra invertida
   sobrante antes de la N, la T y la F. PCRE2, el motor que usa PHP desde la
   versión 7.3, rechaza esas secuencias: `preg_replace()` devolvía `null` y
   el nulo se arrastraba hasta dejar la pantalla vacía. Era un fallo
   heredado del plugin original, no introducido en esta revisión.
2. **Tres deprecaciones de PHP 8** por declarar un parámetro opcional antes
   de uno obligatorio, en `booked_timeslots_select()`,
   `booked_admin_calendar_date_loop()` y `booked_mailer()`. Se han resuelto
   dando valor por defecto a los parámetros posteriores, sin reordenarlos,
   porque todas las llamadas los pasan por posición.

## Notas sobre el origen del código

Este plugin parte de **Booked 2.3** de Boxy Studio, distribuido bajo GPL-2.0.
La licencia permite modificarlo y usarlo, incluida esta versión renombrada,
mientras se mantenga la misma licencia. Al haber eliminado el comprobador de
actualizaciones que consultaba el dominio del autor original, el plugin ya no
recibirá actualizaciones de terceros: el mantenimiento pasa a ser propio.
