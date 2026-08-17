# Registro de cambios

Todos los cambios notables de este proyecto se documentarán en este archivo.

El formato está basado en [Keep a Changelog 1.1.0](https://keepachangelog.com/es-ES/1.1.0/).

## [Sin publicar]

### Añadido

- Flujo combinado SDD y TDD, criterios de diseño sostenible y política de
  gestión de secretos previa al inicio del desarrollo de aplicación.
- Skill `adr-generator` para guiar la creación, revisión y validación
  estructural de ADR con plantilla canónica interna.
- Skill `skill-creator` para generar, validar y registrar de forma determinista nuevas skills de proyecto.
- Índice derivado `skills/INDEX.md` para descubrir skills sin cargar instrucciones
  no relacionadas con la tarea.
- Pruebas automatizadas del registro atómico de skills, validación de frontmatter,
  duplicados y límites de ruta.

### Modificado

- Reforzada la estrategia de pruebas con trazabilidad por criterio, cobertura
  proporcional al riesgo y justificación de niveles no aplicables.
- Movida la plantilla ADR canónica a `skills/adr-generator/templates/` y
  conservado `docs/plantilla_ADR.md` solo como referencia documental.
- Aclarado que la agnosticidad de `skill-creator` se refiere al agente ejecutor y no obliga a que las skills generadas sean genéricas; estas deben orientarse al contexto del proyecto.
- Documentada la estructura estándar de cada skill, diferenciando los archivos base de los directorios opcionales de scripts, referencias y recursos.
- Simplificado `AGENTS.md` para delegar en `skill-creator` la estructura y las condiciones de creación, evitando mantener reglas duplicadas.
- Definida la gobernanza SDD sobre fuentes de verdad, aprobaciones de `/build`,
  calidad, ADR, changelog e idioma.
- Adaptada `skill-creator` para regenerar el índice desde los `SKILL.md`
  existentes sin modificar `AGENTS.md`.

Fecha de última modificación: 2026-08-17 23:19 UTC
