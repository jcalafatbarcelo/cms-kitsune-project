# Roadmap de localización

## Propósito y estado

Este documento conserva la secuencia prevista para idiomas, `UI catalogs`,
overrides y contenido localizado. Está dirigido a contributors del Core,
desarrolladores de extensiones y agentes que necesiten continuar el trabajo sin
mezclar incrementos ni presentar capacidades previstas como disponibles.

> [!IMPORTANT]
> El proyecto solo dispone actualmente de la fundación modular. No hay idiomas,
> `UI catalogs`, overrides, negociación HTTP ni contenido multiidioma
> implementados. La primera Spec está `Aprobada`, pero su implementación todavía
> no se ha iniciado.

Fuentes prescriptivas relacionadas:

- [SDD inicial](../context/SDD_Inicial.md): visión y reglas de negocio.
- [ADR-0002](../adr/ADR-0002-ui-catalogs-json-modulares.md): formato y propiedad
  de los `UI catalogs`.
- [SPEC de fundación estática](../specs/SPEC-static-language-foundation.md):
  contrato candidato del primer incremento.
- [Roadmap de calidad](quality-roadmap.md): momento de evaluación de auditoría,
  seguridad y observabilidad.

Este roadmap ordena trabajo; no aprueba Specs ni sustituye sus criterios.

## Vocabulario

| Término | Significado |
| :--- | :--- |
| Locale instalado | Idioma conocido por el CMS, como `en` o `es_ES`. |
| `UI catalog` | Diccionario JSON clave-valor de textos estáticos de interfaz. |
| `UI catalog` base | `UI catalog` `en` obligatorio de cada propietario. |
| `UI catalog` localizado | `UI catalog` completo de un propietario para otro locale. |
| Propietario | Core, módulo o template que declara las claves. |
| Override | Valor persistido que sustituye o aporta una traducción de una clave base válida. |
| Paquete de idioma | Artefacto futuro que distribuye manifiesto y uno o más `UI catalogs`. |
| Contenido traducible | Datos editoriales de páginas, assets o PageBuilder; no es un `UI catalog`. |

En conversación puede usarse “catálogo” o “catálogo de interfaz”, pero la
documentación técnica empleará siempre `UI catalog`.

## Estado de avance

| ID | Incremento | Estado | Entrada | Resultado esperado |
| :--- | :--- | :--- | :--- | :--- |
| LOC-00 | Planificación y decisión de `UI catalogs` | Completado | Fundación modular disponible | ADR aceptado, vocabulario y secuencia documentados |
| LOC-01 | Fundación de idiomas estáticos | Spec aprobada; implementación pendiente | Ejecutar `/build docs/specs/SPEC-static-language-foundation.md` | Core, `en`, registro, predeterminados, JSON, fallback y Artisan |
| LOC-02 | Overrides | Pendiente | LOC-01 completado | Sustituciones y traducciones locales validadas, con actor y caché |
| LOC-03 | Auditoría administrativa | Pendiente | Antes del primer backoffice mutable | Historial durable independiente de logs operativos |
| LOC-04 | Backoffice de idiomas y overrides | Pendiente | LOC-01, LOC-02 y garantías de LOC-03 | Gestión autorizada, protegida y auditable |
| LOC-05 | Selección temporal y negociación HTTP | Pendiente | Registro de idiomas estable | Sesión, cookie consentida y primera visita por navegador |
| LOC-06 | URL amigables, idioma e items de menú | Pendiente | Modelo de páginas y menús especificado | Precedencia URL/locale y navegación coherente |
| LOC-07 | Contenido y PageBuilder traducibles | Pendiente | Entidades y schemas aprobados | Valores por locale e instancia, separados de la UI |
| LOC-08 | Paquetes dinámicos de idioma | Fuera del MVP/TFM | Distribución real y auditoría disponibles | Instalación segura sin ejecutar código del paquete |

Estados de LOC-01:

- [x] Fundación de `nwidart/laravel-modules` instalada y verificada.
- [x] Problema, fases y primer incremento seleccionados.
- [x] Idioma base `en` y predeterminados globales decididos.
- [x] Modos de fallback `base` y `key` decididos.
- [x] Contrato arquitectónico de `UI catalogs` registrado en ADR-0002.
- [x] Primera Spec creada y validada estructuralmente.
- [x] Revisión semántica y aprobación explícita de la Spec.
- [ ] Implementación TDD de LOC-01.
- [ ] Documentación de administración y extensibilidad basada en código real.
- [ ] Cierre de criterios, suite, quality gate y estado `Completada`.

## Reglas transversales decididas

### Idioma base y predeterminados

- Inglés neutro `en` es el idioma base integrado y nunca se desinstala.
- `en` comienza activo y como predeterminado global de frontend y backoffice.
- Los predeterminados pueden cambiar, pero siempre apuntan a idiomas activos.
- Un idioma predeterminado no puede desactivarse.
- Siempre debe permanecer al menos un idioma instalado y activo.
- `en` puede quedar inactivo si no es predeterminado y existe otro idioma activo;
  sigue disponible como `UI catalog` base cuando el modo de fallback es `base`.

### Propiedad y completitud de UI catalogs

- Un `UI catalog` pertenece a Core, a un módulo o a un template.
- Todo propietario declara en `en` la totalidad de sus claves.
- Las claves localizadas ausentes de `en` se ignoran y la validación las trata
  como error.
- Core proporciona un `UI catalog` completo para cada idioma que declare instalado.
- Un módulo o template puede distribuir solo `en`.
- Si un módulo o template declara otro locale, debe cubrir todas sus claves `en`.
- Un override futuro puede traducir claves válidas cuando el propietario no
  distribuya ese locale.

Los módulos usarán `Resources/lang`. Los templates deberán funcionar de forma
equivalente, registrar sus rutas en el traductor mediante su ciclo de carga y
conservar `Resources/lang` bajo su raíz. Una ruta conceptual es:

```text
<template-root>/<TemplateName>/Resources/lang/en.json
```

No se decide todavía si la raíz o el directorio llevarán prefijos como
`template_` o `tmp_`: esa convención pertenece a la futura arquitectura de
templates y no cambia el formato del `UI catalog`.

### Fallback

Modo `base`:

```text
override del locale
-> UI catalog del locale
-> UI catalog en
-> clave literal
```

Modo `key`:

```text
override del locale
-> UI catalog del locale
-> clave literal
```

Los overrides no existen en LOC-01, por lo que esa primera precedencia comienza
temporalmente en el `UI catalog`. El valor por defecto será `base` en producción
y `key` en desarrollo, testing y E2E; una configuración explícita válida podrá
cambiarlo en cualquier entorno.

## Incrementos pendientes

### LOC-02: overrides

La Spec de overrides deberá definir:

- clave con propietario y locale instalado;
- referencia obligatoria a una clave existente en el `UI catalog` `en`;
- validación de placeholders, pluralización, UTF-8, tamaño y contenido;
- precedencia sobre el `UI catalog` localizado;
- interruptor global que permita ignorarlos sin borrarlos;
- invalidación de caché tras commit;
- código del usuario creador y del último modificador, almacenados sin claves
  foráneas hacia `users`;
- actor `console` o `system` cuando no exista usuario autenticado;
- comportamiento al desactivar o retirar el propietario, que se decidirá con el
  ciclo de vida de módulos y templates.

Los códigos de actor son snapshots históricos y no deben reutilizarse. Antes de
implementar borrado de usuarios se comparará conservar el snapshot en auditoría
con mantener un registro mínimo de identificadores eliminados. No se duplicarán
datos personales ni se fijará retención sin una necesidad aprobada.

### LOC-03: auditoría administrativa

Los campos de creador y modificador no sustituyen un historial. Antes de las
primeras pantallas administrativas mutables se especificará una auditoría durable
que cubra, como mínimo:

- instalación, activación y desactivación de idiomas;
- cambios de predeterminados;
- creación, modificación y eliminación de overrides;
- activación global de overrides;
- intentos denegados sobre idioma base o predeterminados;
- actor, origen, operación, entidad y momento;
- retención, minimización, acceso y tratamiento de actores eliminados.

La auditoría no dependerá de logs operativos rotatorios ni de claves foráneas que
puedan borrar o bloquear el histórico.

LOC-01 permite mutaciones por Artisan antes de implementar LOC-03. Se acepta que
esas operaciones privilegiadas solo tengan logs operativos y no se reconstruirá
su historial con un backfill ficticio. La auditoría durable comenzará cuando se
instale LOC-03 y será obligatoria antes de exponer mutaciones en el backoffice.

Un intento denegado es un evento de seguridad y puede auditarse sin estado
anterior/posterior. Una mutación confirmada solo genera su evento durable después
del commit; una transacción fallida no debe registrarse como cambio realizado.

### LOC-05: selección temporal y navegador

No se añadirá una preferencia de locale al perfil persistente de un usuario
registrado dentro del TFM. La selección será propia del navegador:

- Si la política aplicable permite una cookie persistente y el visitante la
  acepta, recordar la selección durante un plazo configurable y razonable.
- Sin esa aceptación, conservarla en la sesión del servidor. La futura Spec
  decidirá si el identificador técnico de esa sesión usa una cookie de sesión y
  cómo se integra con la política de cookies aplicable.
- Validar siempre que el locale recordado continúa instalado y activo.
- En la primera visita sin elección previa, poder negociar `Accept-Language` y
  usar la mejor coincidencia solo si está instalada y activa.
- Si no existe coincidencia válida, usar el predeterminado global del frontend.
- Tratar una elección explícita posterior como superior a la detección automática.

La Spec HTTP decidirá la precedencia definitiva. La candidata que debe evaluarse
es: locale explícito de URL, elección persistente válida, sesión de servidor válida,
negociación del navegador solo en primera visita y predeterminado global. También
deberá resolver redirecciones, caché HTTP, SEO, privacidad y consentimiento.

### LOC-06 y LOC-07: URL y contenido

La localización de frontend se especificará con URL amigables e items de menú.
No se fijará ahora si el locale predeterminado lleva prefijo.

El contenido de páginas y PageBuilder se mantendrá separado de los `UI catalogs`.
Una plantilla del PageBuilder define claves de campos; cada instancia mantiene
sus propios valores por locale. Compartir una clave de campo no comparte el
contenido entre instancias.

### LOC-08: paquetes dinámicos

La futura instalación desde el CMS queda fuera del MVP/TFM. Antes de habilitarla
deberán existir autorización, auditoría, gestión del ciclo de vida y un formato
versionado. El instalador deberá validar schema, compatibilidad, integridad,
tamaño, rutas y propietarios; impedir path traversal y archivos ejecutables; y
aplicar instalación atómica con rollback.

## Cómo reanudar el trabajo

Antes de continuar cualquier incremento:

1. Verificar el estado real en código, Git, pruebas, Specs y ADR; no inferirlo de
   esta tabla únicamente.
2. Seleccionar una sola fila `LOC-*` cuya entrada esté satisfecha.
3. Leer solo la Spec y los ADR relacionados con ese incremento.
4. Realizar clash check contra SDD, Specs, ADR, código y documentación vigentes.
5. Crear o actualizar la Spec y obtener aprobación antes de modificar
   comportamiento funcional.
6. Mantener esta tabla y los checkboxes en el mismo cambio que altere el estado.
7. No marcar un incremento como completado hasta cerrar aceptación, pruebas,
   documentación y quality gate.

Próximo paso autorizado pendiente: iniciar LOC-01 mediante una orden `/build` que
identifique `SPEC-static-language-foundation.md`, aplicar TDD por criterio y
mantener este roadmap actualizado sin incorporar incrementos posteriores.
