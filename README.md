# Pw Calendario

Plugin de WordPress para la gestión de citas y reservas de visitas.
Desarrollado por [Piensaenweb](https://piensaenweb.com).

- **Versión:** 3.4.1
- **Requiere:** WordPress 6.0+ · PHP 7.4+
- **Licencia:** GPL-2.0-or-later

## Qué hace

- Calendario público con franjas horarias configurables por día de la semana.
- **Aforo por personas**, no por reservas: el cliente elige cuántos son y el
  cupo se descuenta de verdad.
- **Rangos de fechas con días de la semana**: «de jueves a domingo, del 16 de
  julio al 28 de agosto» es una sola entrada.
- Reserva con registro de usuario o como invitado.
- Aprobación manual de las solicitudes, o alta directa en el calendario.
- Calendarios múltiples, cada uno con su responsable y sus avisos.
- Campos personalizados en el formulario de reserva.
- Correos de confirmación, aprobación, cancelación y recordatorio.
- Exportación de las citas a CSV.
- Feeds iCalendar protegidos con clave.
- Cobro de las citas mediante WooCommerce (opcional), con **un solo
  producto por tipo de visita**: la cantidad es el número de personas.
- Panel de gestión para agentes desde el front-end (opcional).

Toda la interfaz está en castellano y el plugin no depende de ningún
servicio externo en tiempo de ejecución.

## Instalación

Descarga el ZIP de la [última release](../../releases/latest) y súbelo desde
**Plugins → Añadir nuevo → Subir plugin**.

Si vienes de la versión anterior del calendario, lee
[INSTALACION.md](INSTALACION.md): se conservan todas las citas y la
configuración, pero hay que renovar las URL de los feeds de calendario.

## Actualizaciones automáticas

El plugin se actualiza solo desde este repositorio: la versión nueva aparece
en **Escritorio → Actualizaciones** y en la pantalla de **Plugins**, igual
que un plugin del directorio oficial.

**No hay que configurar nada.** Ni tokens, ni claves, ni tocar
`wp-config.php`: el repositorio es público, así que el plugin consulta la
API de GitHub y descarga el ZIP sin credenciales. Nada caduca y nada hay
que rotar.

### Forzar una comprobación

En la pantalla de **Plugins**, enlace **«Buscar actualizaciones»** bajo Pw
Calendario. Sin eso, la comprobación se cachea 6 horas, que es de sobra:
WordPress busca actualizaciones dos veces al día por su cuenta.

## Publicar una versión nueva

El ZIP lo construye GitHub Actions. El proceso es:

1. Actualiza la versión en **tres sitios**, que deben coincidir:
   - la cabecera `Version:` de `pw-calendario.php`
   - la constante `PWCAL_VERSION` de `pw-calendario.php`
   - el `Stable tag:` de `readme.txt`
2. Añade la entrada al `== Changelog ==` de `readme.txt`.
3. Haz commit, etiqueta y empuja:

```bash
git commit -am "Version 3.0.1"
git tag v3.0.1
git push origin main --tags
```

El workflow comprueba que la etiqueta coincide con las tres versiones,
valida la sintaxis PHP, construye el ZIP con la estructura correcta y lo
adjunta a la release. Si algo no cuadra, falla antes de publicar.

## Estructura

Sigue la disposición recomendada en la skill
[`wp-plugin-development`](https://github.com/WordPress/agent-skills) de
WordPress:

```
pw-calendario.php                      solo arranque
uninstall.php                          limpieza al borrar el plugin
includes/
  class-pw-calendario.php              núcleo: front-end y lógica compartida
  class-pw-calendario-admin.php        pantallas del escritorio
  class-pw-calendario-loader.php       registro centralizado de ganchos
  class-pw-calendario-actualizador.php actualizaciones desde GitHub
  seguridad.php                        nonces, permisos y saneado
  ajax/                                puntos de entrada AJAX
  add-ons/                             complementos incluidos
assets/                                css, js e imágenes
templates/                             plantillas de las pantallas
languages/                             traducciones
```

## Desinstalación

Al **borrar** el plugin no se elimina ninguna cita ni la configuración: solo
se retira lo regenerable (tareas programadas, transitorios, capacidades y el
secreto de los feeds).

Para que se borre todo, activa la opción antes de desinstalar:

```php
update_option( 'pwcal_borrar_datos_al_desinstalar', 1 );
```
