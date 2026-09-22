# Registro de cambios

Todos los cambios notables de este proyecto se documentarán en este archivo.

El formato está basado en [Keep a Changelog 1.1.0](https://keepachangelog.com/es-ES/1.1.0/).

## [Sin publicar]

### Añadido

- Fundación de monolito modular con `nwidart/laravel-modules` 13, incluyendo
  descubrimiento Laravel y autoload preparado para módulos futuros.
- ADR-0001 aceptado para la arquitectura prevista: Blade como base y Vue
  compartido bajo demanda, con separación de assets públicos y del editor.
- Roadmap de calidad, seguridad y observabilidad con criterios y fases para
  evaluar CI, Husky, cabeceras HTTP, Sentry y Zod.
- Skill `spec-maintainer` con planificación previa, perfiles `feature` y
  `maintenance`, plantilla canónica, revisión semántica y validación estructural
  de Specs.
- Estrategia de Documentation as Code y skill `documentation-maintainer` para
  crear, organizar y validar la documentación canónica del producto.
- Flujo combinado SDD y TDD, criterios de diseño sostenible y política de
  gestión de secretos previa al inicio del desarrollo de aplicación.
- Skill `adr-generator` para guiar la creación, revisión y validación
  estructural de ADR con plantilla canónica interna.
- Skill `skill-creator` para generar, validar y registrar de forma determinista nuevas skills de proyecto.
- Índice derivado `skills/INDEX.md` para descubrir skills sin cargar instrucciones
  no relacionadas con la tarea.
- Pruebas automatizadas del registro atómico de skills, validación de frontmatter,
  duplicados y límites de ruta.
- Integración del servidor MCP de Laravel Boost para OpenCode y Codex, restringida
  a herramientas de solo lectura de contexto y documentación.
- Flujo de continuidad de sesión con guía y plantilla en `docs/context/` y notas
  locales excluidas de Git en `docs/context/sessions/`.
- Auditoría diaria de dependencias Composer y npm en GitHub Actions, con PHP 8.5,
  Node 24 LTS, política estricta de advisories y mantenimiento mediante
  Dependabot.
- Generación automatizada del SBOM SPDX 2.3 desde el Dependency Graph, con
  validación de Composer y npm y conservación durante 90 días en GitHub Actions.
- Dependency Review para impedir que los Pull Requests introduzcan dependencias
  con vulnerabilidades conocidas de cualquier severidad y scope.
- Quality gate de integración continua con formato Pint, suite de Pest,
  compilación de assets con Vite y validación de workflows mediante `actionlint`
  con versión fijada y verificada por checksum.
- ADR-0002 aceptado para representar los textos estáticos mediante `UI catalogs`
  JSON modulares, con claves estables, propietario y fallback configurable.
- Roadmap de localización para secuenciar la fundación estática, overrides,
  negociación HTTP y evolución futura.
- Primera Spec aprobada, limitada a la fundación de idiomas estáticos de Core,
  sus `UI catalogs`, fallback y administración manual mediante Artisan.
- Iniciativa planificada de auditoría administrativa durable antes de incorporar
  las primeras pantallas mutables del backoffice.
- ADR-0003 aceptado para limitar la persistencia a SQLite moderno en desarrollo
  y a las series LTS MySQL 8.4 y MariaDB 11.4 en producción.
- Módulo Core con registro persistente de idiomas, inglés base, predeterminados
  globales, `UI catalogs` JSON validados y fallback configurable.
- Comandos Artisan para instalar, validar, listar, activar, desactivar y cambiar
  los idiomas predeterminados sin depender de un backoffice.
- Guías de instalación, administración de idiomas y desarrollo de `UI catalogs`.
- Fundación de CMS Templates: Base integrado, manifiestos JSON validados,
  sincronización local, invariantes persistentes y comandos Artisan para su
  ciclo de vida.

### Modificado

- Movidas las skills canónicas a `.agents/skills/` y regenerado su índice derivado;
  actualizadas las referencias operativas de `AGENTS.md` y la documentación.
- Renombrado el proyecto en `composer.json` (`kitsune/cms`) y `package.json`
  (`kitsune-cms`), sustituyendo la identidad del esqueleto de Laravel.
- Consolidado `context/` en `docs/context/` y actualizadas las referencias
  operativas de `AGENTS.md`, `README.md`, el ADR-0001 y las skills.
- Eliminada la plantilla SPEC obsoleta `context/plantilla_SPEC.md`, sin
  referencias y superada por la plantilla canónica de `spec-maintainer`.
- Desactivadas en Boost las guidelines, las skills y la integración con Laravel
  Cloud, y retirado el hook automático `boost:update` de Composer para controlar
  las actualizaciones.
- Alineados el SDD, el README y el roadmap con la integración Blade/Vue y con
  JSON como alternativa inicial, no definitiva, de persistencia del PageBuilder.
- Diferenciado el alcance de una Spec abierta de los ajustes internos permitidos
  durante su implementación y definido un flujo proporcional para bugs y
  regresiones sin rebajar las garantías funcionales.
- Incorporado el impacto documental a la plantilla de Specs y a la verificación
  final de las implementaciones funcionales.
- Reforzada la estrategia de pruebas con trazabilidad por criterio, cobertura
  proporcional al riesgo y justificación de niveles no aplicables.
- Movida la plantilla ADR canónica a `.agents/skills/adr-generator/templates/` y
  conservado `docs/plantilla_ADR.md` solo como referencia documental.
- Aclarado que la agnosticidad de `skill-creator` se refiere al agente ejecutor y no obliga a que las skills generadas sean genéricas; estas deben orientarse al contexto del proyecto.
- Documentada la estructura estándar de cada skill, diferenciando los archivos base de los directorios opcionales de scripts, referencias y recursos.
- Simplificado `AGENTS.md` para delegar en `skill-creator` la estructura y las condiciones de creación, evitando mantener reglas duplicadas.
- Definida la gobernanza SDD sobre fuentes de verdad, aprobaciones de `/build`,
  calidad, ADR, changelog e idioma.
- Establecida para agentes y mantenedores la revisión obligatoria de
  compatibilidad, tests, build y procedencia de Actions antes de integrar
  actualizaciones de dependencias.
- Adaptada `skill-creator` para regenerar el índice desde los `SKILL.md`
  existentes sin modificar `AGENTS.md`.
- Activado el ruleset `Protect main`, que exige Pull Request, resolución de
  conversaciones y los checks de auditoría y Dependency Review antes de fusionar.
- Reforzada la Spec de fundación de idiomas con una matriz CI multi-motor que
  verifica versiones y demuestra en SQLite, MySQL y MariaDB el rechazo de un
  segundo registro de `language_settings`.
- Ampliado el quality gate con una matriz de integración fijada para SQLite,
  MySQL 8.4.11 y MariaDB 11.4.13.
- Elevado el requisito mínimo del CMS a PHP 8.4 para alinear la configuración
  TLS de MySQL y MariaDB con la API `Pdo\Mysql` utilizada.

### Eliminado

- Skills genéricas instaladas por Boost que no aportaban una ventaja neta frente
  a `AGENTS.md`, la documentación consultable por MCP y las skills propias.
- Configuración generada para Claude Code (`.claude/`, `.mcp.json`), no utilizada
  por el proyecto.

Fecha de última modificación: 2026-09-21 18:46 UTC
