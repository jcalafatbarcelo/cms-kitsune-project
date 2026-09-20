# SPEC: Fundación de CMS Templates

- **Estado:** Borrador
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
- Descubrir paquetes ya desplegados bajo `Templates/<Nombre>` mediante un comando
  explícito de sincronización.
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
- La ausencia posterior, el cambio o la invalidez de un paquete desplegado no
  modifica estados persistidos durante `cms:template:sync`; la operación falla de
  forma explícita y deja los datos intactos.

### Paquetes, manifiesto y presentaciones

- La raíz de paquetes es `Templates/`. Cada paquete ocupa el directorio directo
  `Templates/<Nombre>`; no se recorren subdirectorios ni rutas configurables.
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

### Entidades y migraciones

La migración del módulo Core creará:

```text
cms_templates
- id: bigint, PK
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

- La migración insertará el registro Base con el hash SHA-256 de una única
  instantánea de `Templates/Base/template.json`, activo y referenciado como
  predeterminado global.
- La migración debe validar el paquete Base antes de insertar datos y fallar sin
  dejar tablas parcialmente migradas si falta, es inseguro o no satisface el
  contrato.
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
  `Templates/`, registra los paquetes válidos nuevos como inactivos y actualiza
  nombre y hash de los ya registrados solo tras validar la instantánea del mismo
  archivo. No activa, desactiva ni cambia el predeterminado.
- Si cualquier paquete descubierto es inválido, duplicado, inseguro o colisiona
  con un identificador de otro directorio, `sync` falla y no modifica datos.
- `cms:template:activate` solo activa un template registrado e inactivo.
- `cms:template:disable` solo desactiva un template activo que no sea Base ni el
  predeterminado y mantiene al menos un template activo.
- `cms:template:set-default` solo acepta un template registrado y activo; el
  cambio es atómico.
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
- Probar Base en instalación limpia, sus protecciones y todos los cambios de
  estado permitidos y denegados.
- Probar manifiestos válidos, JSON inválido, schema desconocido, identificadores
  o presentaciones inválidos, claves duplicadas, vistas ausentes, archivos
  sobredimensionados, rutas inseguras y colisiones entre directorios.
- Probar que `sync` no deja escrituras parciales ante cualquier paquete inválido
  y que usa el hash de la instantánea validada.
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
  `cms_template_settings` con `id = 2` sin alterar el singleton.
- **CA-02:** Base declara `public.page.standard` y su Blade convencional existe,
  pero el incremento no crea rutas HTTP ni ejecuta la presentación.
- **CA-03:** `cms:template:sync` registra un paquete desplegado válido como
  inactivo, conserva Base y no cambia el template predeterminado.
- **CA-04:** Un manifiesto o árbol de paquete inválido, inseguro, con colisiones
  o con una presentación sin Blade hace que `sync` falle sin modificar datos.
- **CA-05:** Solo un template registrado e inactivo puede activarse; solo uno
  activo puede hacerse predeterminado; ambas operaciones son atómicas.
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
| CA-04 | Traversal, enlaces o escritura parcial desde filesystem | Integración/filesystem | Errores deterministas y estado persistido intacto | Seguridad y diagnóstico |
| CA-05 | Predeterminado inactivo o carrera de estados | Integración/BD | Transiciones permitidas y atómicas verificadas | Operación por Artisan |
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
