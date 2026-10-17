# Skill: metodologia-css

Skill de OpenCode con la metodología CSS de la casa (BEM + OOCSS con
namespaces). Instruye a los agentes para nombrar y estructurar clases CSS/HTML,
ordenar el archivo, usar variables y breakpoints.

Versión: 1.0.0

## Contenido

- `SKILL.md` — guía operativa que carga OpenCode.
- `docs/` — documentación completa de la metodología.

## Uso

### Dentro de este repositorio

OpenCode la descubre automáticamente: está en `.opencode/skills/`.

### En cualquier otro dispositivo

Desde la raíz del repo:

```bash
node tools/install-skill.mjs metodologia-css
```

O para instalar todas las skills del repo:

```bash
node tools/install-skill.mjs
```

El instalador copia la skill a `~/.config/opencode/skills/` (o
`$XDG_CONFIG_HOME/opencode/skills/`). Reinicia OpenCode si no aparece.

### Distribución por ZIP (sin clonar el repo)

Desde la raíz del repo:

```bash
node tools/package-skill.mjs metodologia-css
```

Genera `dist/metodologia-css-1.0.0.zip`. En el equipo destino: descomprimir el
ZIP y copiar la carpeta `metodologia-css/` a `~/.config/opencode/skills/`.

## Origen

Copia de la metodología CSS del equipo. Fuente original:
`G:\Unidades compartidas\templateOpenWeb\extras\Documents\MetodologiaCSS`.
