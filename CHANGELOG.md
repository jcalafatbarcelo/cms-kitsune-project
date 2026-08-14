# Registro de cambios

Todos los cambios notables de este proyecto se documentarán en este archivo.

El formato está basado en [Keep a Changelog 1.1.0](https://keepachangelog.com/es-ES/1.1.0/).

## [Sin publicar]

### Añadido

- Skill `skill-creator` para generar, validar y registrar de forma determinista nuevas skills de proyecto.

### Modificado

- Aclarado que la agnosticidad de `skill-creator` se refiere al agente ejecutor y no obliga a que las skills generadas sean genéricas; estas deben orientarse al contexto del proyecto.
- Documentada la estructura estándar de cada skill, diferenciando los archivos base de los directorios opcionales de scripts, referencias y recursos.
- Simplificado `AGENTS.md` para delegar en `skill-creator` la estructura y las condiciones de creación, evitando mantener reglas duplicadas.

Fecha de última modificación: 2026-08-14 15:06
