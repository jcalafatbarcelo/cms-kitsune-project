# SPEC: Fundación de auditoría administrativa durable

- **Estado:** Borrador
- **Perfil:** feature
- **Origen de la planificación:** Decisión del responsable de dotar al backoffice
  de auditoría durable antes de gestionar Pages y Menus desde la web, y de
  instrumentar las mutaciones de dominio existentes.
- **Spec relacionada:** [SPEC-minimal-admin-panel](SPEC-minimal-admin-panel.md),
  [SPEC-pages-foundation](SPEC-pages-foundation.md) y
  [SPEC-navigation-foundation](SPEC-navigation-foundation.md), completadas.

## 1. Objetivo o problema

Proporcionar un historial durable, inmutable y transaccional de las mutaciones
administrativas de Pages, Menus y publicación, con identidad histórica del actor
y consulta autorizada desde el backoffice. Es el requisito previo para exponer
mutaciones web y evita depender de logs operativos rotatorios.

## 2. Contexto y evidencia

El backoffice mínimo está completado con un único superadministrador y sin roles.
Pages y Navigation están completas y sus mutaciones solo se exponen por Artisan a
través de `PageManager` y `MenuManager`, con transacciones e invariantes propias.

El roadmap de calidad exige auditoría durable antes del primer backoffice que
modifique publicación o configuración, con evento y mutación en la misma
transacción y sin claves foráneas hacia usuarios que puedan eliminarse
(`docs/architecture/quality-roadmap.md:47-48,142-171`). El roadmap de
localización mantiene `LOC-03` pendiente con la entrada "antes del primer
backoffice mutable" (`docs/architecture/localization-roadmap.md:53,159-185`). Las
Specs de Pages y Navigation ya declaraban que su backoffice requiere autorización
y auditoría previamente especificadas (`SPEC-pages-foundation.md:220-223`,
`SPEC-navigation-foundation.md:71-72`).

## 3. Alcance

- Crear un almacén de eventos de auditoría insert-only con operación, entidad
  (tipo e id sin clave foránea), actor histórico, instante, origen mínimo y
  representación anterior/posterior de los campos auditables.
- Impedir la modificación y el borrado de eventos mediante restricciones de
  aplicación y triggers equivalentes para SQLite, MySQL y MariaDB.
- Registrar un evento por cada mutación de Pages y Menus, dentro de la misma
  transacción que la mutación.
- Instrumentar `PageManager` y `MenuManager` para emitir los eventos sin cambiar
  sus contratos públicos ni sus invariantes.
- Resolver el actor de forma contextual: superadministrador autenticado cuando la
  mutación procede de la web, `console` cuando procede de Artisan y `system`
  cuando no exista actor humano.
- Filtrar la representación anterior/posterior a un conjunto de campos
  auditables por entidad, sin secretos ni contenido innecesario.
- Exponer una consulta de historial en el backoffice, de solo lectura, paginada y
  filtrable, accesible solo al superadministrador, mediante una clave cerrada de
  presentación de sistema y sus textos de UI.

### Fuera de alcance

- Roles, permisos granulares o delegación de administración.
- Mutaciones de Pages y Menus desde la web (serán incrementos posteriores que
  reutilizarán esta auditoría).
- Auditoría de idiomas, overrides, usuarios, CMS Templates, Media o PageBuilder.
- Alertas, detección de anomalías, exportación, firma o encadenado por hash.
- Consulta pública o API del historial.
- Cambiar las invariantes de dominio, las URLs canónicas o la publicación en
  cascada.

### Alcance diferido

- La retención y el tratamiento del actor eliminado deben fijarse antes de
  aprobar; se describen como decisiones abiertas.
- La auditoría de idiomas, overrides y publicación global se retomará con `LOC-04`.
- Exportación, búsqueda avanzada y retención automatizada se retomarán cuando
  exista un volumen o una exigencia legal comprobables.
- La integración exacta de la pantalla de auditoría con la navegación del
  backoffice se completará al disponer de las pantallas de Pages y Menus.

## 4. *Clash check*

- SDD inicial: no altera la separación Page/PageTranslation, menús, publicación en
  cascada ni el renderizado. No hay conflicto.
- ADR-0001: la consulta de historial es Blade y no introduce Vue. No hay
  conflicto.
- ADR-0003: la migración y los triggers de inmutabilidad se prueban en SQLite,
  MySQL y MariaDB. No hay conflicto.
- ADR-0004 y ADR-0005: no cambia la selección de template ni el contrato de URL
  pública; registra como auditoría datos ya validados por el dominio. No hay
  conflicto.
- ADR-0006: añade una clave cerrada de sistema `system.admin.audit` al catálogo de
  presentaciones, sin permitir que datos editables o HTTP seleccionen Blades o
  rutas. No hay conflicto.
- `SPEC-minimal-admin-panel`, completada: extiende el shell existente sin cambiar
  autenticación, autorización (sigue siendo el superadministrador) ni HTTPS.
- `SPEC-pages-foundation` y `SPEC-navigation-foundation`, completadas: sus
  managers pasan a escribir auditoría en la misma transacción; no cambian
  contratos públicos, invariantes ni datos de dominio.
- Roadmap de calidad/localización: implementa la iniciativa diferida de auditoría
  durable y cierra la condición de `LOC-03` sin adelantar `LOC-04`.

## 5. Requisitos y bloques técnicos aplicables

### Datos, migración e inmutabilidad

- Tabla `admin_audit_events` insert-only con: identificador; `occurred_at`
  indexado; `operation`; `entity_type`; `entity_id` sin clave foránea;
  `actor_type` (`user`, `console`, `system`); `actor_id` y etiqueta de actor
  opcionales y sin clave foránea; `origin` opcional de mínimo privilegio;
  representación anterior y posterior de los campos auditables; y marca de versión
  del esquema de auditoría.
- Las entidades y usuarios referenciados no usan claves foráneas, para no perder
  trazabilidad ni bloquear borrados.
- Restricciones: `operation`, `entity_type` y `actor_type` con listas admitidas
  validadas por la aplicación; sin columnas obligatorias que revelen secretos.
- Triggers equivalentes en SQLite, MySQL y MariaDB que rechazan `UPDATE` y
  `DELETE` sobre `admin_audit_events`. El `down()` elimina los triggers y la tabla.
- Índices por `occurred_at`, por `(entity_type, entity_id)` y por `operation`.

### Registro transaccional

- Un evento se escribe dentro de la transacción de la mutación correspondiente;
  si el registro falla, la mutación se revierte.
- `PageManager` y `MenuManager` invocan el registro para: `page.create`,
  `page.translate`, `page.publish`, `page.unpublish`, `page.set_home`,
  `menu.create`, `menu.item.create`, `menu.item.update`, `menu.item.move` y
  `menu.item.remove`.
- Una mutación sin cambio efectivo no genera evento espurio.
- La representación anterior/posterior se limita a campos auditables por entidad:
  identificadores, jerarquía, idioma, slug, título, banderas de publicación,
  posición, etiqueta y referencia a Page; la asignación de template se registra
  como parte de `page.create`.
- Los servicios de dominio conservan sus contratos públicos y su comportamiento;
  la auditoría es un efecto secundario transaccional.

### Actor y origen

- El actor se resuelve desde el contexto: superadministrador autenticado cuando la
  mutación procede de la web; `console` cuando procede de Artisan; `system` cuando
  no existe actor humano.
- La identidad del actor es histórica y no depende de que el usuario siga
  existiendo.
- El origen (por ejemplo, dirección o etiqueta de canal) se registra con el mínimo
  necesario; su inclusión exacta se fija con la decisión de retención.

### Consulta en el backoffice

- Pantalla Blade de solo lectura, paginada y filtrable por operación, entidad y
  rango temporal, accesible solo al superadministrador.
- La pantalla usa la clave cerrada `system.admin.audit` de Base y textos de UI
  propios en el `UI catalog` Base.
- El panel enlaza a la auditoría; los enlaces a Pages y Menus se añadirán en sus
  incrementos.
- Filtros validados; paginación acotada; sin exponer secretos, hashes, sesiones
  ni contenido innecesario.

### API, Vue, PageBuilder e integraciones

No aplicable: no se añaden API JSON, Vue, PageBuilder, colas ni integraciones
externas. La consulta es Blade server-side.

### Seguridad y validación

- Solo el superadministrador accede a la consulta; se reutiliza
  `RequireSuperAdmin`.
- La representación auditada no incluye contraseñas, hashes, tokens, sesiones ni
  cabeceras; se aplica una lista de campos permitidos por entidad.
- Blade conserva el escape por defecto; los filtros y la paginación validan su
  entrada.
- Los triggers garantizan la inmutabilidad aunque la aplicación intente
  modificarlos.

### Entrega y compatibilidad

- La migración se ejecuta con el flujo normal y no requiere backfill; el historial
  comienza en la fecha de despliegue.
- Los despliegues existentes deben ejecutar `php artisan migrate` antes de mutar
  Pages o Menus con auditoría activa.
- No hay cambio de contrato público ni de rutas públicas localizadas.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Probar que cada operación auditada escribe exactamente un evento con operación,
  entidad y diff correctos.
- Probar que la escritura del evento comparte transacción con la mutación: un
  fallo de auditoría revierte la mutación.
- Probar que `UPDATE` y `DELETE` sobre `admin_audit_events` se rechazan en SQLite,
  MySQL y MariaDB.
- Probar que la identidad del actor sobrevive a la eliminación del usuario y que
  no existe clave foránea hacia `users`.
- Probar que la consulta de historial exige superadministrador, pagina y filtra
  con entradas válidas, y no expone secretos.
- Probar que las mutaciones de Pages y Navigation existentes siguen pasando sus
  invariantes y pruebas tras instrumentarlas.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build de Vite, validación de
  manifiestos y la matriz SQLite, MySQL y MariaDB.

### Riesgos aceptados

- El historial crecerá con cada mutación; se acepta sin retención automatizada en
  este incremento, con volumen reducido por el uso previsto.

### Deuda técnica

No aplica mientras retención y tratamiento del actor eliminado se resuelvan antes
de aprobar.

## 7. Criterios de aceptación

- **CA-01:** Cada operación auditada de Pages y Menus escribe un evento con
  operación, entidad, actor y representación anterior/posterior correctos.
- **CA-02:** Si el registro del evento falla, la mutación de dominio se revierte
  por completo.
- **CA-03:** Los eventos no pueden modificarse ni borrarse; la restricción se
  verifica en SQLite, MySQL y MariaDB.
- **CA-04:** El actor se registra de forma histórica sin clave foránea; eliminar
  el usuario no rompe el historial.
- **CA-05:** La consulta de auditoría exige superadministrador, permite paginar y
  filtrar, y no expone secretos.
- **CA-06:** Las mutaciones de Pages y Navigation conservan sus invariantes y
  pruebas tras instrumentar la auditoría.
- **CA-07:** La migración, los triggers y la consulta se comportan igual en
  SQLite, MySQL y MariaDB.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Mutación sin trazabilidad | Integración | Evento con diff correcto por operación | Guía de auditoría administrativa |
| CA-02 | Mutación aplicada sin auditoría | Integración | Rollback conjunto verificado | Guía de auditoría administrativa |
| CA-03 | Manipulación del historial | Integración/migración | `UPDATE`/`DELETE` rechazados en tres motores | Guía de auditoría administrativa |
| CA-04 | Pérdida de actor o FK borrable | Integración | Historial intacto tras eliminar usuario | Guía de auditoría administrativa |
| CA-05 | Fuga de información o acceso indebido | HTTP/seguridad | Solo superadmin, filtros validados, sin secretos | Guía de acceso al backoffice |
| CA-06 | Regresión en dominio | Integración | Pages/Navigation siguen verdes | Guía de Pages y Navigation |
| CA-07 | Diferencia entre motores | Integración/matriz | Versión efectiva y comportamiento iguales | Baseline de bases de datos |

## 9. Plan de implementación

1. Crear pruebas rojas de migración, inmutabilidad y modelo de eventos;
   implementar la tabla y los triggers hasta CA-03 y CA-07.
2. Crear pruebas rojas del registro transaccional y del actor; instrumentar
   `PageManager` y `MenuManager` hasta CA-01, CA-02, CA-04 y CA-06.
3. Crear pruebas rojas de la consulta autorizada; implementar la clave de sistema,
   la pantalla Blade y los textos hasta CA-05.
4. Documentar la auditoría y ejecutar el quality gate y la matriz antes de
   completar la Spec.

## 10. Decisiones abiertas

- Retención del historial (indefinida o plazo concreto) y criterio de purga.
- Tratamiento del actor eliminado: qué identificador y qué etiqueta histórica se
  conservan sin retener datos personales innecesarios.
- Inclusión del origen (IP u otro canal) y su nivel de detalle.
