# CMS modular multiidioma

CMS modular multiidioma desarrollado como Proyecto de Fin de Máster (TFM) sobre
el ecosistema Laravel. El proyecto persigue un producto sostenible, desacoplado
y extensible, construido mediante **Spec Driven Development (SDD)**.

> [!WARNING]
> **Este repositorio se encuentra en fase de diseño inicial.** Las capacidades
> descritas en este documento representan el objetivo del producto y no deben
> interpretarse como funcionalidad ya implementada o disponible.

## Objetivos principales

- Organizar el sistema en módulos con fronteras claras y posibilidad de
  evolución independiente.
- Gestionar contenido multiidioma sin comprometer la identidad estable de las
  entidades ni sus relaciones estructurales.
- Ofrecer un PageBuilder reactivo y declarativo, con esquemas JSON en el
  backoffice y renderizado público seguro.
- Tratar los assets como entidades reutilizables, con metadatos traducibles y
  perfiles de uso diferenciados.
- Mantener los árboles de navegación independientes de la jerarquía de URL
  canónicas.

## Arquitectura prevista

La visión de producto propone un **monolito modular** basado en
[`nWidart/laravel-modules`](https://github.com/nWidart/laravel-modules). El
contenido seguirá el patrón **Entity-Translation**: una entidad estable
conservará la identidad y las relaciones, mientras sus traducciones contendrán
el contenido localizado, el `slug` público y los metadatos SEO.

La disponibilidad pública seguirá una regla de **publicación en cascada**. Una
traducción solo será accesible cuando estén publicados y activos para ese idioma
la propia página, el idioma y todos los ascendientes de la página.

Estas decisiones describen el diseño previsto; su implementación deberá quedar
definida en Specs concretas y aprobadas.

## Stack previsto

- **Backend:** Laravel sobre PHP 8.x.
- **Persistencia:** Eloquent ORM con MySQL o MariaDB.
- **Backoffice:** Vue 3 con Composition API.
- **Frontend público:** renderizado del lado del servidor mediante Laravel
  Blade.

## Alcance del MVP (MoSCoW)

| Prioridad | Alcance previsto |
| :--- | :--- |
| **Must** | Core como monolito modular; gestión de idiomas activos e inactivos; patrón Entity-Translation; PageBuilder con Vue 3, esquemas JSON y renderizado Blade; assets reutilizables con metadatos traducibles; navegación independiente de las URL canónicas. |
| **Should** | Visibilidad condicional en menús, publicación en cascada y selector de idioma inteligente. |
| **Could** | Integración con LLM para traducir contenido JSON desde el editor. |
| **Won't (MVP)** | Instalador visual de plugins o módulos desde el backoffice. |

## Documentación y fuentes de referencia

- [`context/SDD_Inicial.md`](context/SDD_Inicial.md): visión inicial,
  arquitectura base, reglas de negocio y alcance MoSCoW.
- [`docs/specs/`](docs/specs/): Specs que concretan el comportamiento y los
  criterios de aceptación.
- [`docs/adr/`](docs/adr/): ubicación prevista para ADR cuando exista una
  decisión arquitectónica que justifique su creación; todavía no contiene ADR.
- [`CHANGELOG.md`](CHANGELOG.md): registro histórico de cambios notables.
- [`skills/INDEX.md`](skills/INDEX.md): catálogo derivado de skills disponibles
  en el repositorio.
- [`AGENTS.md`](AGENTS.md): reglas operativas, fuentes de verdad y flujo de
  trabajo para agentes.

## Flujo de trabajo SDD

El desarrollo parte de una especificación antes de cualquier implementación:

1. Usar `/plan` para analizar el contexto, comprobar conflictos y preparar o
   actualizar una Spec en `docs/specs/`.
2. Revisar y aprobar explícitamente la Spec.
3. Iniciar la implementación únicamente con una orden que identifique esa Spec
   concreta y aprobada:

   ```text
   /build docs/specs/SPEC-[nombre].md
   ```

Una petición genérica de implementación o un `/build` sin una Spec concreta y
aprobada no autoriza cambios de aplicación. Las reglas completas se encuentran
en [`AGENTS.md`](AGENTS.md).

## Estado del proyecto

El repositorio contiene actualmente documentación de visión, gobernanza SDD,
Specs y tooling de skills. Todavía no hay una aplicación Laravel configurada,
por lo que no se documentan comandos de instalación, ejecución ni pruebas. Se
añadirán cuando existan los artefactos ejecutables y el procedimiento pueda
verificarse en el propio repositorio.

## Licencia

**Pendiente.** El proyecto todavía no dispone de un archivo `LICENSE`.
