# Convenciones de estilos frontend

## Objetivo

La creación y revisión de estilos del CMS sigue una única metodología para
mantener clases, componentes y respuestas responsive coherentes a medida que
crecen los módulos y los CMS Templates.

## Regla obligatoria

Antes de crear, modificar o revisar CSS, SCSS, estilos de componentes Vue,
atributos `class` en Blade, Vue o HTML, animaciones, variables CSS o media
queries, se debe cargar y aplicar la skill
[`metodologia-css`](../../.agents/skills/metodologia-css/SKILL.md).

La metodología detallada es la fuente de verdad para:

- nomenclatura BEM + OOCSS y namespaces;
- estructura y orden de las hojas de estilo;
- variantes, estados, contenido opcional, temas y hooks JavaScript;
- variables CSS, animaciones y breakpoints mobile-first;
- accesibilidad WCAG AA y la checklist de revisión.

## Flujo de contribución

1. Determinar si el cambio afecta a alguna superficie de estilos incluida en la
   regla obligatoria.
2. Cargar la skill desde el índice canónico
   [`.agents/skills/INDEX.md`](../../.agents/skills/INDEX.md).
3. Aplicar sus convenciones y completar su checklist antes de cerrar el cambio.
4. Mantener los cambios funcionales, los contratos de CMS Templates y la carga
   de assets dentro de la Spec aprobada que corresponda.

La skill no se aplica al contenido editorial, traducciones, menús ni catálogos de
interfaz cuando no introducen marcado, clases o estilos.
