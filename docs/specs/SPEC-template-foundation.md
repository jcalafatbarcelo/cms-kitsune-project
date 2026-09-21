# SPEC: Fundación de CMS Templates

- **Estado:** Aprobada
- **Perfil:** feature
- **Origen de la planificación:** Decisiones del responsable del proyecto sobre
  CMS Templates y [ADR-0004](../adr/ADR-0004-arquitectura-de-cms-templates.md).
- **Spec relacionada:** [SPEC-static-language-foundation](SPEC-static-language-foundation.md), completada.

## 1. Objetivo o problema

Entregar el primer incremento vertical de CMS Templates: Core registra paquetes
de template ya desplegados en `Templates/<Nombre>`, mantiene el CMS Template
Base integrado y permite sincronizar, listar, activar, desactivar y seleccionar
un template predeterminado mediante Artisan.

El incremento fija un contrato de manifiesto no ejecutable y de presentaciones
declaradas. No renderiza todavía páginas ni expone selección web: con un único
template Base, la operación reproducible y las invariantes del ciclo de vida son
el resultado verificable.

## 2. Contexto y evidencia

Core ya mantiene el registro de idiomas y los predeterminados globales mediante
Eloquent, migraciones y Artisan. ADR-0004 establece CMS Templates como paquetes
globales separados de módulos de dominio y PageBuilder, con Base integrado,
selección predeterminada e identidades de presentación estables.

El repositorio no contiene aún un directorio `Templates/`, registros de template,
configuración de template ni renderizado editorial. El cargador de UI catalogs
actual solo cubre Core; no hay aún una presentación de template que necesite
textos de interfaz propios.

## 3. Alcance

- Extender Core como propietario del registro, las invariantes, el descubrimiento
  y los comandos de CMS Templates.
- Crear el paquete desplegado `Templates/Base` y su manifiesto `template.json`.
- Crear el registro persistente de CMS Templates y el singleton que referencia el
  template predeterminado.
- Registrar Base, activo y predeterminado, al migrar una instalación limpia.
- Descubrir paquetes no Base desplegados manualmente bajo `Templates/<Nombre>`
  mediante un comando explícito de sincronización.
- Validar el manifiesto antes de registrar o actualizar un template descubierto.
- Registrar los templates descubiertos no Base inicialmente inactivos.
- Listar, sincronizar, activar, desactivar y cambiar el predeterminado mediante
  comandos Artisan no interactivos.
- Declarar presentaciones por clave estable y comprobar que cada una tiene su
  Blade convencional dentro del paquete, sin almacenar rutas de vistas en base de
  datos.
- Mantener Base activo, disponible y no desinstalable; este incremento no ofrece
  ninguna operación de eliminación o desinstalación.

### Fuera de alcance

- Crear `Pages`, `PageTranslation`, rutas públicas, renderizado de páginas,
  asignación de templates a páginas o el modo `inherits_default`.
- PageBuilder, bloques, schemas, configuración específica de templates, assets o
  componentes Vue.
- Carga y resolución de UI catalogs propiedad de templates.
- Login, recuperación de contraseña, backoffice o tematización de vistas de
  sistema.
- Descarga, subida, firma, extracción, instalación web, actualización remota o
  ejecución de paquetes aportados por terceros.
- Preferencias por usuario, autorización web y auditoría administrativa durable.

### Alcance diferido

- La primera presentación pública, `public.page.standard`, se consumirá en
  `SPEC-pages-foundation`, que definirá la asignación de template, el renderizado
  y los datos editoriales necesarios.
- Los UI catalogs de templates se integrarán cuando la primera presentación
  aporte textos estáticos. Esa Spec extenderá el contrato de propietarios de
  ADR-0002 sin registrar rutas ni archivos no validados.
- La activación del modo `inherits_default` y la protección de referencias
  explícitas se incorporarán con Pages; aún no existen referencias que proteger.
- La instalación dinámica se retomará solo con una Spec que cubra procedencia,
  autorización, auditoría, compatibilidad, integridad, instalación atómica y
  rollback de código ejecutable.

## 4. *Clash check*

- SDD inicial: concreta el monolito modular, Blade y los módulos futuros sin
  introducir SPA, Inertia ni PageBuilder. No hay conflicto.
- ADR-0001: no introduce Vue ni modifica el renderizado público esencial; la
  validación de Blade prepara una presentación futura sin cargar assets.
- ADR-0002: preserva UI catalogs JSON y su propiedad. La integración del
  propietario template queda diferida porque no hay textos de template en este
  incremento; no se sustituye el loader validado de Core.
- ADR-0003: las nuevas tablas y restricciones deben funcionar en SQLite 3.45+, MySQL
  8.4.x LTS y MariaDB 11.4.x LTS, con pruebas reales en la matriz existente.
- ADR-0004: esta Spec implementa el registro de paquetes desplegados, Base,
  claves de presentación y ciclo de vida limitado; no habilita instalación
  dinámica ni tematización de sistema.
- SPEC-static-language-foundation: sigue siendo propietaria del registro de
  idiomas y sus invariantes; los templates no modifican sus predeterminados ni
  sus UI catalogs actuales.
- Código y datos: no existen tablas, paquetes ni rutas de templates previos que
  migrar. No hay conflicto irresoluble.

## 5. Requisitos y bloques técnicos aplicables

### Dominio e invariantes

- `base` es el identificador reservado e inmutable del CMS Template Base.
- Base debe existir, estar activo y ser el template predeterminado después de una
  instalación limpia o de migrar Core.
- Base no se puede desactivar, eliminar ni desinstalar.
- Debe existir al menos un CMS Template registrado y activo.
- Debe existir exactamente un template predeterminado global, que debe estar
  registrado y activo.
- Un template predeterminado no se puede desactivar.
- Un template descubierto no Base queda inactivo hasta que se active en una
  operación separada.
- Todas las mutaciones que puedan afectar a estas invariantes son atómicas y
  serializan cambios concurrentes sobre los registros implicados.
- La ausencia posterior, el cambio o la invalidez de un paquete registrado no
  modifica estados persistidos durante `cms:template:sync`; la operación falla de
  forma explícita y deja los datos intactos.
- El identificador y el directorio de un template registrado son inmutables. Un
  cambio de identificador en el manifiesto de un directorio registrado, un cambio
  de directorio de un template registrado o la ausencia de uno registrado hace
  que `sync` falle sin crear un nuevo registro ni modificar el existente.
- Las transiciones de activación y de predeterminado también exigen que la
  identidad actual del manifiesto coincida con el identificador persistido; la
  revalidación no puede actualizar ni sustituir esa identidad.

### Paquetes, manifiesto y presentaciones

- La raíz de paquetes es `Templates/`. Cada paquete ocupa el directorio directo
  `Templates/<Nombre>`; no se recorren subdirectorios ni rutas configurables. El
  nombre del directorio debe tener entre 1 y 100 caracteres ASCII alfanuméricos,
  empezar por una letra y no incluir espacios, puntos ni separadores.
- La identidad de directorio usa `directory_key`, obtenido al convertir el nombre
  ASCII del directorio a minúsculas. Dos directorios cuyo nombre solo difiera en
  mayúsculas o minúsculas, como `Acme` y `acme`, colisionan y hacen que `sync`
  falle. Esta regla se aplica antes de persistir, al descubrir paquetes y al
  comparar los registros existentes en todos los sistemas de archivos.
- Base reside exactamente en `Templates/Base`.
- Cada paquete contiene un archivo regular `template.json`, nunca un enlace
  simbólico. Su ruta canónica debe permanecer dentro de su directorio de template.
- El manifiesto es JSON UTF-8 no ejecutable, no puede superar 64 KiB y usa el
  contrato versionado:

```json
{
  "schema_version": 1,
  "identifier": "base",
  "name": "Base",
  "presentations": [
    "public.page.standard"
  ]
}
```

- `schema_version` solo admite `1`. `identifier` usa kebab-case ASCII de 1 a 100
  caracteres y debe coincidir con el identificador esperado de Base cuando el
  directorio sea `Base`. `name` es texto UTF-8 no vacío de hasta 100 caracteres.
- `presentations` es un array no vacío, sin duplicados, con un máximo de 100
  claves. Cada clave sigue segmentos ASCII en minúscula separados por puntos,
  con al menos dos segmentos, por ejemplo `public.page.standard`; no admite
  barras, espacios, `..` ni caracteres de control.
- Cada presentación declarada debe corresponder a un archivo regular Blade bajo
  `Resources/views`, obtenido por convención al sustituir cada punto por un
  separador de directorio y añadir `.blade.php`. Por ejemplo,
  `public.page.standard` requiere
  `Resources/views/public/page/standard.blade.php`.
- El manifest no declara rutas Blade ni archivos PHP. La aplicación resuelve la
  vista solo por la clave validada y esta convención interna.
- El paquete Base debe declarar `public.page.standard` y proporcionar su Blade
  convencional. Ese Blade no se sirve ni ejecuta una ruta HTTP en este incremento.
- No se ejecutan datos del manifiesto. La confianza del código Blade procede solo
  de su despliegue versionado junto al CMS; el comando no descarga, copia ni
  ejecuta paquetes aportados por el usuario.
- Al registrar un paquete, Core persiste su nombre de directorio junto al
  identificador y su `directory_key`. En una sincronización posterior, el
  directorio registrado debe seguir presente con la misma grafía y su manifiesto
  debe conservar el mismo identificador. Un renombrado, incluso solo de
  mayúsculas o minúsculas, no está admitido en este incremento; se rechazará en
  vez de crear un segundo registro que pueda dejar el anterior activo o
  predeterminado.

### Entidades y migraciones

La migración del módulo Core creará:

```text
cms_templates
- id: bigint, PK
- directory: varchar(100), not null
- directory_key: varchar(100), unique, not null
- identifier: varchar(100), unique, not null
- name: varchar(100), not null
- manifest_hash: char(64), not null
- is_active: boolean, not null, default false
- registered_at: timestamp, not null
- created_at: timestamp, not null
- updated_at: timestamp, not null

cms_template_settings
- id: bigint, PK, fixed 1, check (id = 1); solo se admite el registro global
- default_template_id: bigint, FK cms_templates.id, restrict, not null
- created_at: timestamp, not null
- updated_at: timestamp, not null
```

- Antes de ejecutar cualquier `CREATE TABLE`, la migración debe leer una única
  instantánea de `Templates/Base/template.json` y validar todo el paquete Base,
  incluido su Blade convencional. Si el preflight falla, no debe ejecutar DDL ni
  dejar esquema creado, también en motores sin DDL transaccional.
- Solo después del preflight correcto, la migración crea las tablas e inserta el
  registro Base con el hash SHA-256 de la instantánea validada, `directory = Base`,
  `directory_key = base`, activo y referenciado como predeterminado global.
- `directory_key` debe contener solo la normalización ASCII en minúsculas de
  `directory`. La restricción única sobre esa columna, no la collation por defecto
  del motor, impone la política de colisiones de mayúsculas/minúsculas de forma
  uniforme en SQLite, MySQL y MariaDB.
- La base de datos debe imponer que `cms_template_settings.id` sea siempre `1`
  mediante una restricción `CHECK` portable o una construcción equivalente con
  la misma garantía en SQLite, MySQL y MariaDB.
- El rollback elimina primero `cms_template_settings` y después
  `cms_templates`. No hay backfill porque las tablas no existen previamente.
- No se usan borrados en cascada.

### Módulo y contratos Artisan

- Core es propietario del ciclo de vida, invariantes, comandos y validación de
  manifiestos. Los paquetes bajo `Templates/` no son módulos nWidart ni registran
  proveedores, rutas o comandos.
- Los contratos públicos son:

```text
cms:template:list
cms:template:sync
cms:template:activate {identifier}
cms:template:disable {identifier}
cms:template:set-default {identifier}
```

- `cms:template:list` muestra identificador, nombre, estado activo y si es el
  predeterminado.
- `cms:template:sync` valida todos los directorios directos presentes bajo
  `Templates/`, calcula sus `directory_key`, rechaza colisiones por diferencia
  exclusiva de mayúsculas/minúsculas y comprueba que todos los directorios
  registrados siguen presentes con la misma grafía. Registra los paquetes válidos
  nuevos como inactivos. Para los ya registrados, actualiza nombre y hash solo
  tras validar la instantánea del mismo archivo y confirmar que conserva su
  identificador. No activa, desactiva ni cambia el predeterminado.
- Si cualquier paquete descubierto o registrado es inválido, ausente, duplicado,
  inseguro, cambia su identificador o directorio, o colisiona por identificador o
  `directory_key` con otro paquete, `sync` falla y no modifica datos.
- `cms:template:activate` revalida el paquete desplegado en su directorio
  persistido y compara el identificador del manifiesto con el persistido antes de
  activar un template registrado e inactivo.
- `cms:template:disable` solo desactiva un template activo que no sea Base ni el
  predeterminado y mantiene al menos un template activo.
- `cms:template:set-default` revalida el paquete desplegado en su directorio
  persistido, compara el identificador del manifiesto con el persistido y solo
  acepta un template registrado y activo; el cambio es atómico.
- Los comandos correctos terminan con código `0` y muestran solo el estado
  resultante. Entradas inválidas, manifiestos inválidos, templates desconocidos o
  transiciones prohibidas terminan con código distinto de `0` y mensaje
  accionable, sin mostrar el contenido completo del manifiesto.
- Los comandos no solicitan confirmación interactiva.

### Seguridad, API, Vue 3, PageBuilder y asincronía

- Las rutas de filesystem se validan antes de leer y se rechazan enlaces
  simbólicos, dispositivos, entradas que no son archivos regulares y escapes de
  la raíz `Templates/`.
- La validación lee una sola instantánea de bytes para calcular hash, decodificar
  y validar un manifiesto; no vuelve a abrir el archivo entre esas operaciones.
- No hay rutas HTTP, contratos API, componentes Vue, schemas PageBuilder, colas
  ni integraciones externas en este incremento.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Aplicar TDD a las invariantes, las transiciones Artisan, el descubrimiento y la
  validación de manifiestos por ser comportamientos deterministas y de seguridad.
- Probar migración e integridad en SQLite 3.45+, MySQL 8.4.x LTS y MariaDB 11.4.x
  LTS, incluida la imposibilidad de insertar `cms_template_settings.id = 2`.
- Probar que un preflight inválido de Base aborta la migración antes de cualquier
  DDL y no deja las tablas de templates en SQLite, MySQL ni MariaDB.
- Probar Base en instalación limpia, sus protecciones y todos los cambios de
  estado permitidos y denegados.
- Probar manifiestos válidos, JSON inválido, schema desconocido, identificadores
  o presentaciones inválidos, claves duplicadas, vistas ausentes, archivos
  sobredimensionados, rutas inseguras y colisiones entre directorios, incluidas
  las que solo difieren en mayúsculas/minúsculas, en SQLite, MySQL y MariaDB.
- Probar que `sync` no deja escrituras parciales ante cualquier paquete inválido
  o registrado ausente/renombrado, y que usa el hash de la instantánea validada.
- Probar que activar o seleccionar como predeterminado revalida el paquete y
  rechaza el registro cuando falta, deja de ser válido o cambia su identificador
  después de `sync`.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build frontend y controles
  de seguridad configurados.
- Documentar el contrato de `template.json`, la operación por Artisan y el
  alcance diferido sin presentar como disponible la instalación dinámica.

### Riesgos aceptados

- Los Blades de un paquete ya desplegado son código confiable del release, no
  contenido que el comando de sincronización pueda convertir en seguro. Se acepta
  porque el MVP no admite entrada remota ni subida; la instalación dinámica
  exigirá otra Spec de cadena de suministro y autorización.
- Con Base como único paquete, los comandos de selección no demuestran aún un
  cambio visual. Se acepta porque prueban el ciclo de vida real que Pages usará y
  porque no se debe crear una ruta de demostración ajena al dominio.

### Deuda técnica

- No aplica. La carga de UI catalogs, renderizado y referencias de Pages se
  difieren con condiciones de entrada explícitas y no son necesarios para que el
  registro y la operación de templates sean correctos.

## 7. Criterios de aceptación

- **CA-01:** Una instalación limpia registra Base, activo y como único template
  predeterminado; SQLite, MySQL y MariaDB rechazan insertar un segundo
  `cms_template_settings` con `id = 2` sin alterar el singleton. Un Base inválido
  aborta antes de crear las tablas en los tres motores.
- **CA-02:** Base declara `public.page.standard` y su Blade convencional existe,
  pero el incremento no crea rutas HTTP ni ejecuta la presentación.
- **CA-03:** `cms:template:sync` registra un paquete desplegado válido como
  inactivo, conserva Base y no cambia el template predeterminado.
- **CA-04:** Un manifiesto o árbol de paquete inválido, inseguro, con colisiones
  o con una presentación sin Blade hace que `sync` falle sin modificar datos. Un
  paquete registrado ausente o cuyo identificador/directorio cambie también falla
  sin crear un registro de sustitución.
- **CA-05:** Solo un template registrado e inactivo puede activarse; solo uno
  activo y actualmente válido puede hacerse predeterminado; ambas operaciones son
  atómicas, revalidan el paquete desplegado y rechazan un identificador de
  manifiesto distinto del persistido.
- **CA-06:** No se puede desactivar Base, el predeterminado ni el último template
  activo.
- **CA-07:** Los comandos listan el estado y comunican errores mediante códigos
  deterministas sin mostrar manifiestos completos ni datos sensibles.
- **CA-08:** La documentación permite desplegar y sincronizar un template local
  compatible y distingue esta operación de la instalación dinámica diferida.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Sistema sin template utilizable o singleton no impuesto por un motor | Integración/BD multi-motor | Migración, Base y rechazo de `id = 2` verificados en los tres motores | Instalación y administración de templates |
| CA-02 | Contrato de presentación ambiguo o ruta Blade elegida por datos | Integración/filesystem | Manifiesto Base y Blade convencional validados sin ruta HTTP | Desarrollo de templates |
| CA-03 | Paquete desplegado no registrable o activado implícitamente | Integración/console | Sync registra el paquete inactivo y conserva el predeterminado | Operación por Artisan |
| CA-04 | Traversal, enlaces, colisión por casing o escritura parcial desde filesystem | Integración/filesystem | Errores deterministas y estado persistido intacto | Seguridad y diagnóstico |
| CA-05 | Predeterminado inactivo, identidad desplegada distinta o carrera de estados | Integración/BD | Revalidación de identidad y transiciones atómicas verificadas | Operación por Artisan |
| CA-06 | Base o último template activo indisponible | Integración/BD | Transiciones denegadas verificadas | Operación por Artisan |
| CA-07 | Automatización ambigua o fuga de contenido de manifiesto | Feature/console | Código y salida de cada comando | Referencia de comandos |
| CA-08 | Operación dependiente de conocimiento implícito | Validación documental | Procedimiento y límites revisados | Nuevas guías de templates |

HTTP, componente Vue, E2E, PageBuilder, assets y UI catalogs de templates no
aplican porque no existe aún una presentación pública consumida por Pages.

## 9. Plan de implementación

1. Crear pruebas rojas para Base, el singleton y la matriz de motores; añadir las
   migraciones, modelos y servicio de validación mínimos hasta cumplir CA-01 y
   CA-02.
2. Crear pruebas rojas de manifiestos, directorios y atomicidad; implementar el
   descubrimiento y sincronización hasta cumplir CA-03 y CA-04.
3. Crear pruebas rojas de comandos y transiciones; implementar listado,
   activación, desactivación y predeterminado hasta cumplir CA-05 a CA-07.
4. Documentar el despliegue local, manifiesto, comandos, diagnóstico y límites
   del MVP; validar CA-08.
5. Ejecutar pruebas enfocadas, suite, formato, build y controles de seguridad;
   revisar migraciones, diff, secretos y documentación antes de cerrar.

## 10. Decisiones abiertas

- No aplica.
