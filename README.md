# CMS modular multiidioma

![CodeRabbit Pull Request Reviews](https://img.shields.io/coderabbit/prs/github/jcalafatbarcelo/cms-kitsune-project?utm_source=oss&utm_medium=github&utm_campaign=jcalafatbarcelo%2Fcms-kitsune-project&labelColor=171717&color=FF570A&link=https%3A%2F%2Fcoderabbit.ai&label=CodeRabbit+Reviews)

CMS modular multiidioma desarrollado como Proyecto de Fin de Máster (TFM) sobre
el ecosistema Laravel. El proyecto persigue un producto sostenible, desacoplado
y extensible, construido mediante **Spec Driven Development (SDD)**.

> [!WARNING]
> **Este repositorio se encuentra en desarrollo inicial.** Ya dispone de un
> bootstrap ejecutable, el módulo Core con fundación estática de idiomas y
> controles de calidad. Páginas, PageBuilder, medios, menús y administración web
> siguen previstos y no deben interpretarse como disponibles.

## Objetivos principales

- Organizar el sistema en módulos con fronteras claras y posibilidad de
  evolución independiente.
- Gestionar contenido multiidioma sin comprometer la identidad estable de las
  entidades ni sus relaciones estructurales.
- Ofrecer un PageBuilder reactivo y declarativo en el backoffice y renderizado
  público seguro; JSON es la alternativa inicial de persistencia, pendiente de
  evaluación en su Spec.
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

## Stack

- **Disponible:** Laravel 13 sobre PHP 8.3 o posterior, Eloquent ORM, Blade,
  Vite 8, Tailwind CSS 4 y Pest 5.
- **Persistencia de desarrollo:** SQLite `3.45.0` o posterior en la configuración
  inicial de Laravel; no se soporta como motor de producción.
- **Persistencia prevista para el CMS:** MySQL `8.4.x LTS` o MariaDB `11.4.x LTS`
  en su último patch mantenido, manteniendo Eloquent como capa de persistencia.
  Las versiones legacy y las ramas de innovación quedan fuera de soporte.
- **Infraestructura modular disponible:** `nWidart/laravel-modules` 13 para
  descubrir y cargar módulos. Core registra idiomas, predeterminados globales y
  `UI catalogs`; los demás módulos de dominio todavía no están implementados.
- **Frontend previsto:** Blade como base del backoffice y del sitio público, con
  Vue 3 y Composition API para el PageBuilder y otras islas interactivas. Vue
  todavía no está incorporado al proyecto.
- **Assets previstos:** Vue compartido entre módulos y cargado bajo demanda,
  con Vite para su compilación; sin SPA ni Inertia por defecto.

Los límites de integración se definen en el
[ADR-0001](docs/adr/ADR-0001-blade-vue-bajo-demanda.md). La persistencia del
PageBuilder se decidirá en su Spec, sin confundir el esquema de los bloques con
el formato de almacenamiento de su contenido.

## Alcance del MVP (MoSCoW)

| Prioridad | Alcance previsto |
| :--- | :--- |
| **Must** | Core como monolito modular; gestión de idiomas activos e inactivos; patrón Entity-Translation; PageBuilder con Vue 3, configuración declarativa y renderizado Blade, con persistencia pendiente de decisión; assets reutilizables con metadatos traducibles; navegación independiente de las URL canónicas. |
| **Should** | Visibilidad condicional en menús, publicación en cascada y selector de idioma inteligente. |
| **Could** | Integración con LLM para traducir contenido estructurado desde el editor. |
| **Won't (MVP)** | Instalador visual de plugins o módulos desde el backoffice. |

## Documentación y fuentes de referencia

- [`docs/context/SDD_Inicial.md`](docs/context/SDD_Inicial.md): visión inicial,
  arquitectura base, reglas de negocio y alcance MoSCoW.
- [`docs/architecture/documentation-strategy.md`](docs/architecture/documentation-strategy.md):
  estrategia de Documentation as Code, audiencias, versionado, publicación
  futura y criterios para evaluar wikis o asistentes basados en IA.
- [`docs/architecture/quality-roadmap.md`](docs/architecture/quality-roadmap.md):
  iniciativas candidatas y momentos de evaluación para quality gates, cabeceras
  HTTP, observabilidad y validación runtime.
- [`docs/architecture/localization-roadmap.md`](docs/architecture/localization-roadmap.md):
  secuencia prevista para idiomas, `UI catalogs`, overrides, negociación HTTP y
  contenido localizado, con su estado de avance.
- [`docs/administration/languages.md`](docs/administration/languages.md):
  operación de idiomas mediante los comandos Artisan disponibles.
- [`docs/developers/ui-catalogs.md`](docs/developers/ui-catalogs.md): contrato,
  validación y resolución de textos estáticos JSON.
- [`docs/getting-started/installation.md`](docs/getting-started/installation.md):
  requisitos, instalación y configuración de persistencia y fallback.
- [`docs/architecture/continuous-integration.md`](docs/architecture/continuous-integration.md):
  quality gate de CI, comandos locales y validación de workflows.
- [`docs/architecture/dependency-security.md`](docs/architecture/dependency-security.md):
  auditorías de dependencias, SBOM, Dependency Review y protección de `main`.
- [`docs/specs/`](docs/specs/): Specs que concretan el comportamiento y los
  criterios de aceptación.
- [`docs/adr/`](docs/adr/): decisiones arquitectónicas; incluye el
  [ADR-0001 sobre Blade y Vue](docs/adr/ADR-0001-blade-vue-bajo-demanda.md) y el
  [ADR-0002 sobre UI catalogs JSON](docs/adr/ADR-0002-ui-catalogs-json-modulares.md)
  y el [ADR-0003 sobre la baseline de bases de datos](docs/adr/ADR-0003-baseline-moderna-de-bases-de-datos.md).
- [`CHANGELOG.md`](CHANGELOG.md): registro histórico de cambios notables.
- [`.agents/skills/INDEX.md`](.agents/skills/INDEX.md): catálogo canónico derivado de skills disponibles
  en el repositorio.
- [`AGENTS.md`](AGENTS.md): reglas operativas, fuentes de verdad y flujo de
  trabajo para agentes.

## Flujo de trabajo SDD y TDD

El desarrollo parte de una especificación antes de cualquier implementación:

1. Usar `/plan` para analizar el contexto, comprobar conflictos y preparar o
   actualizar una Spec en `docs/specs/`.
2. Revisar y aprobar explícitamente la Spec.
3. Iniciar la implementación únicamente con una orden que identifique esa Spec
   concreta y aprobada:

   ```text
   /build docs/specs/SPEC-[nombre].md
   ```

4. Dividir el alcance aprobado en incrementos verticales pequeños y, cuando
   aporte una ventaja neta, construir cada uno mediante
   `Red → Green → Refactor`.
5. Cerrar cada incremento con pruebas proporcionales al riesgo, refactor con la
   suite en verde y el quality gate definido en `AGENTS.md`.

Una petición genérica de implementación o un `/build` sin una Spec concreta y
aprobada no autoriza cambios de aplicación. Las reglas completas se encuentran
en [`AGENTS.md`](AGENTS.md).

Las capacidades idiomáticas de Laravel y Vue tienen preferencia sobre patrones
adicionales cuando ofrecen una solución clara y mantenible. Los patrones se
incorporan para resolver una necesidad o variación demostrada, no como capas
preventivas que compliquen innecesariamente el código.

## Puesta en marcha

El bootstrap actual requiere PHP 8.3 o posterior, Composer, Node.js y npm. Desde
la raíz del repositorio:

```shell
composer run setup
composer run dev
```

El primer comando instala las dependencias, prepara `.env`, genera la clave de
aplicación, ejecuta las migraciones y compila los assets. El segundo inicia el
servidor de Laravel, el listener de colas y Vite para desarrollo.

Las comprobaciones disponibles se ejecutan con:

```shell
composer test
npm run build
composer validate --strict --no-check-publish --no-plugins --no-scripts
composer audit --locked --abandoned=fail --no-plugins --no-scripts
npm audit --package-lock-only --audit-level=low --ignore-scripts
```

Las auditorías de dependencias también se ejecutan automáticamente mediante
GitHub Actions en Pull Requests, pushes a `main`, diariamente y bajo demanda.

## Estado del proyecto

El repositorio contiene el bootstrap ejecutable de Laravel, configuración de
Pest, compilación frontend con Vite, auditorías automatizadas de dependencias y
el módulo Core con registro de idiomas, `UI catalogs`, fallback y administración
manual mediante Artisan. Los módulos de páginas, medios, menús y PageBuilder se
incorporarán mediante Specs concretas y aprobadas.

La documentación canónica se mantendrá en el repositorio. La selección de un
portal público y la posible incorporación de una wiki asistida por IA se
evaluarán cuando existan funcionalidades, contratos y documentación suficientes
para realizar una prueba representativa.

## Licencia

Este proyecto se distribuye bajo la [licencia MIT](LICENSE).
