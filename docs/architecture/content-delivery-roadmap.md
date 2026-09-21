# Roadmap de contenido, templates y navegación

## Propósito y estado

Este documento conserva el orden previsto para entregar contenido editorial,
CMS Templates, localización pública, navegación y PageBuilder sin mezclar sus
fronteras. Está dirigido a contributors del core que reanuden el trabajo tras
completar un incremento.

> [!IMPORTANT]
> Este roadmap no aprueba comportamiento ni presenta capacidades como
> disponibles. Cada fila requiere su propia Spec concreta y aprobada antes de
> implementarse. El estado real se verifica siempre contra código, pruebas, Git,
> Specs y ADR vigentes.

Fuentes relacionadas:

- [SDD inicial](../context/SDD_Inicial.md): visión, `Page`/`PageTranslation`,
  publicación en cascada y menús independientes.
- [ADR-0001](../adr/ADR-0001-blade-vue-bajo-demanda.md): Blade como renderizado
  esencial y Vue bajo demanda.
- [ADR-0002](../adr/ADR-0002-ui-catalogs-json-modulares.md): textos estáticos
  de interfaz y propiedad de UI catalogs.
- [ADR-0004](../adr/ADR-0004-arquitectura-de-cms-templates.md): CMS Templates
  globales, Base, presentaciones y ciclo de vida.
- [Roadmap de localización](localization-roadmap.md): incrementos `LOC-*` y sus
  dependencias.

## Secuencia principal

| Orden | Incremento | Estado | Entrada | Resultado verificable |
| :--- | :--- | :--- | :--- | :--- |
| 1 | Fundación de CMS Templates | Completado | ADR-0004 aceptado | Base, manifiesto, ciclo de vida local y predeterminado gestionados por Core |
| 2 | Fundación de Pages | Previsto | Fundación de CMS Templates completada | `Page`/`PageTranslation`, jerarquía, publicación en cascada y primera página Base renderizada en Blade |
| 3 | Rutas públicas localizadas | Previsto | Pages especificado y registro de idiomas estable | Locale de petición, URL canónica, resolución a traducción, redirecciones y cambio de idioma sin salto a otra página |
| 4 | Fundación de Navigation | Previsto | Pages y rutas públicas localizadas completadas | Menús independientes, ítems traducibles y visibilidad según disponibilidad pública |
| 5 | Fundación de PageBuilder | Previsto | Pages y contrato de presentación estables | Bloques declarativos, valores por instancia y locale, validación y renderizado Blade |
| 6 | Extensiones de CMS Templates | Previsto | PageBuilder o una necesidad de presentación comprobable | UI catalogs propios, configuración, assets, presentaciones y bloques aportados por templates |
| 7 | Tematización de vistas de sistema | Previsto | Módulo funcional y contrato de cada vista disponibles | Apariencia intercambiable de login, recuperación o backoffice sin alterar sus rutas, autorización o lógica |

El primer paso está implementado mediante `SPEC-template-foundation` y su matriz
SQLite, MySQL y MariaDB está verificada. La finalización no autoriza
automáticamente el paso 2: requiere su propia Spec concreta y aprobación
explícita.

## Dependencias de localización

La secuencia principal no sustituye `LOC-02` a `LOC-08` ni altera sus estados.
Los hitos que deben coordinarse son:

- La fundación de Pages consume el registro de idiomas disponible desde LOC-01,
  pero no introduce todavía selección HTTP, prefijos ni contenido accesible en
  varios locales.
- Las rutas públicas localizadas reúnen el flujo HTTP previsto en LOC-05 y la
  parte de URL, locale y canonicidad de LOC-06. Su Spec decidirá la precedencia
  exacta sin asumirla en este roadmap.
- Navigation necesita la disponibilidad pública localizada para no ofrecer
  destinos sin traducción publicable; su integración con los items de menú de
  LOC-06 se concretará en su propia Spec.
- LOC-02 (overrides) puede retomarse como incremento de localización
  independiente. LOC-03 es obligatorio antes de exponer mutaciones de idiomas u
  overrides en el backoffice mediante LOC-04.
- PageBuilder corresponde a LOC-07 para sus valores traducibles; sus bloques no
  son CMS Templates ni UI catalogs.

## Cómo reanudar tras cada incremento

1. Confirmar que la Spec anterior está `Completada` con criterios, pruebas,
   documentación y quality gate cerrados.
2. Consultar esta tabla y el roadmap de localización, seleccionando solo un
   incremento cuya entrada esté satisfecha.
3. Leer los ADR, Specs y código afectados; realizar un clash check contra el
   estado real.
4. Crear o actualizar la siguiente Spec, mantenerla en `Borrador` o `Propuesta`
   hasta recibir aprobación explícita y no implementar comportamiento antes de
   esa aprobación.
5. Actualizar este roadmap y el de localización solo en el cambio que altere un
   estado real, sin inferir disponibilidad de un incremento futuro.
