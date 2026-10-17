# Metodologia CSS (BEM + OOCSS)

```meta
title: "Metodologia CSS openWeb"
summary: "Guia practica para nombrar y estructurar CSS usando BEM con capas OOCSS y namespaces de apoyo."
tags: ["css", "bem", "oocss", "naming", "wiki"]
version: "1.1.0"
owner: "openWeb"
last-review: "2025-11-07"
```

> **No autoritativo.** Este documento es un resumen previo. Ante cualquier
> discrepancia con las notas numeradas (`01.*`–`08.*`), mandan las notas.

## Fast Track

1. Identifica el bloque principal con `c-[bloque]`.
2. Anade elementos dependientes con `c-[bloque]__[elemento]`.
3. Ajusta el bloque completo con `c--[especificacion]`.
4. Usa `is-` para estados y `has-` para contenidos extra.
5. Aplica temas transversales con `t-`.
6. Anade `js-` solo para punteros JavaScript.

## 1. Proposito

- Unificar la semantica de las clases para facilitar mantenimiento.
- Reducir redundancias combinando BEM (estructura) con OOCSS (reutilizacion).
- Documentar namespaces y convenciones de media queries en un unico punto.

## 2. Namespaces

| Namespace | Uso principal | Ejemplos |
|-----------|---------------|----------|
| `c-`      | Componentes y sus bloques base | `c-slider`, `c-ficha` |
| `c__`     | Elementos dependientes del bloque | `c-slider__item`, `c-ficha__text` |
| `c--`     | Especificaciones/variantes del bloque completo | `c--deportes`, `c--adoptado` |
| `t-`      | Temas o estilos transversales | `t-especial`, `t-warning` |
| `js-`     | Punteros para scripts (no se estilizan) | `js-slider` |
| `is-`     | Estado del objeto conservando su esencia | `is-destacado`, `is-disabled` |
| `has-`    | El mismo objeto con contenido extra | `has-picture`, `has-icon` |
| `a-`      | Nombres de animaciones declaradas en CSS | `a-fade-in`, `a-slide` |

### Reglas de rendimiento para animaciones

- Usa `a-` para nombrar `@keyframes` y `animation-name`.
- Activa las animaciones con clases de estado (`is-`) o variantes (`c--`), no con clases `a-` en el HTML.
- Prioriza animar `transform` y `opacity` para mejorar rendimiento.
- Evita animar `top`, `left`, `width`, `height` cuando haya alternativa basada en `transform`.

<details>
<summary>Cuando usar <code>is-</code> vs <code>c--</code>?</summary>

- `is-` describe un estado temporal del bloque o elemento (activo, deshabilitado, destacado).
- `c--` redefine el bloque completo como variante estable (slider de deportes, card promocional).
- Si el cambio afecta a todos los elementos internos de forma permanente, usa `c--`.
- Si el cambio es puntual o dependiente de logica, usa `is-`.
</details>

<details>
<summary>Cuando usar <code>has-</code> vs <code>c--</code>?</summary>

- `has-` indica que el componente incluye un sub-elemento opcional (imagen, icono, badge).
- Evita `c--` cuando solo un descendiente requiere ajustes por la presencia de ese sub-elemento.
</details>

## 3. Ciclo de un componente

```html
<div class="c-slider c--deportes js-slider">
  <div class="c-slider__item c-ficha has-picture">
    <picture class="c-ficha__picture"></picture>
    <p class="c-ficha__text t-especial">Lorem Ipsum</p>
    <p class="c-ficha__text">
      <span class="t-warning">Warning message</span>
    </p>
  </div>

  <div class="c-slider__item c-ficha is-destacado">
    <p class="c-ficha__text t-especial">Lorem Ipsum</p>
    <p class="c-ficha__text">
      <span class="t-error">Error message</span>
    </p>
  </div>
</div>
```

```css
.c-slider { /* Estilos genericos del slider */ }
.c-slider.c--deportes { /* Ajustes de la variante deportes */ }
.c-slider__item { /* Elementos dentro del slider */ }

.c-ficha { /* Definicion base de la ficha reutilizable */ }
.c-ficha.has-picture { /* Ajustes cuando incorpora <picture> */ }
.c-ficha.is-destacado { /* Estado destacado puntual */ }

.c-ficha__picture { /* Dependiente directo del bloque */ }
.c-ficha__text { /* Texto dentro de la ficha */ }

.t-especial, .t-warning, .t-error { /* Temas transversales */ }
```

### Reglas clave

- Las especificaciones (`c--x`) siempre se escriben junto al bloque (`.c-ficha.c--promo {...}`).
- Componentes anidados repiten ambas clases (`c-padre__hijo c-nuevopadre`).
- Si un hijo necesita demasiadas excepciones dentro de un bloque, probablemente deba convertirse en un bloque independiente.

## 4. Checklist de implementacion (Actualizado)

- [ ] El bloque tiene un unico nombre `c-` semantico.
- [ ] Los elementos usan `c-[bloque]__[elemento]` con significado claro.
- [ ] Las variantes permanentes estan modeladas con `c--`.
- [ ] Los estados temporales usan `is-` y los contenidos extra `has-`.
- [ ] Los punteros de JS estan aislados (`js-`) y fuera del CSS.
- [ ] Los estilos transversales (`t-`) no dependen de la estructura del bloque.
- [ ] **✅ La solución cumple con las pautas WCAG AA (prioridad en semántica y contraste).**
- [ ] **✅ El diseño propuesto facilita su adaptación responsive y escalabilidad futura (Punto 7.2).**
- [ ] Revisa la checklist antes de finalizar.

## 5. Nomenclatura para media queries

### Tokens `@custom-media`

Declarar cada rango una sola vez usando `@custom-media` con nombres alineados a los sufijos:

```css
@custom-media --bp-sm (max-width: 399px);
@custom-media --bp-m (max-width: 767px);
@custom-media --bp-t (min-width: 768px) and (max-width: 1023px);
@custom-media --bp-nm (min-width: 768px);
@custom-media --bp-nt (min-width: 1024px);
@custom-media --bp-d1 (min-width: 1024px) and (max-width: 1359px);
@custom-media --bp-d2 (min-width: 1360px) and (max-width: 1599px);
@custom-media --bp-d3 (min-width: 1600px) and (max-width: 1919px);
@custom-media --bp-xl (min-width: 1920px);
/* Opcionales: --bp-om/--bp-ot/... para rangos solo; --bp-c-* para medias custom */
```

Uso ejemplo:

```css
.c-ficha { border-radius: 12px; }

@media (--bp-m) {
  .c-ficha { border-radius: 8px; }
}

@media (--bp-d2) {
  .c-ficha { border-radius: 16px; }
}
```

### Tabla de equivalencias

| Token `@custom-media` | Sufijo | Media query | Uso recomendado |
|-----------------------|--------|-------------|-----------------|
| `--bp-sm`             | `-sm`  | `@media (max-width: 399px)` | Mobile ultra pequeno. |
| `--bp-m`              | `-m`   | `@media (max-width: 767px)` | Mobile general. |
| `--bp-t`              | `-t`   | `@media (768px-1023px)` | Tablet. |
| `--bp-nm`             | `-nm`  | `@media (min-width: 768px)` | No mobile. |
| `--bp-nt`             | `-nt`  | `@media (min-width: 1024px)` | No tablet (desktop base). |
| `--bp-d1`             | `-d1`  | `@media (1024px-1359px)` | Desktop compacto. |
| `--bp-d2`             | `-d2`  | `@media (1360px-1599px)` | Laptop grande / 1440px. |
| `--bp-d3`             | `-d3`  | `@media (1600px-1919px)` | Desktop ancho. |
| `--bp-xl`             | `-xl`  | `@media (min-width: 1920px)` | Ultra ancho / 4K inicial. |
| `--bp-om`, `--bp-ot`, etc. | `-om`, `-ot`, ... | `@media only (rango)` | Overrides exclusivos. |
| `--bp-c-*`            | `-c*`  | `@media (custom)` | Rangos especificos por componente. |

**Estructura sugerida para los sufijos:** `[condicion][tamano][rango]`

- Condiciones: `n` (no), `o` (solo), `c` (custom).
- Tamanos opcionales: `s` (small), `x` (extra).
- Rango base: `sm`, `m`, `t`, `nt`, `d1`, `d2`, `d3`, `xl`.

Ejemplos:

- `.c-card-nm` -> estilos aplicables a todo lo que no sea mobile (`@media (--bp-nm)`).
- `.c-slider-od2` -> override que solo vive en `@media (--bp-d2)`.

## 6. Flujo sugerido

1. Disena o detecta el bloque base (`c-`).
2. Define variantes (`c--`) solo cuando afectan al bloque completo.
3. Anade elementos (`__`) con semantica clara.
4. Etiqueta estados (`is-`) y anadidos (`has-`).
5. Aplica temas (`t-`) para estilos transversales.
6. Declara punteros (`js-`) para hooks JavaScript.
7. Usa los tokens `@custom-media` y sufijos correspondientes.
8. Revisa la checklist antes de finalizar.

## 6.1 Escalabilidad responsive (criterios obligatorios)

- Trabaja en enfoque **mobile-first** usando los tokens `@custom-media` definidos.
- Evita tamaños rigidos; prioriza unidades relativas y layouts flexibles.
- Diseña pensando en evolucion del componente: estados (`is-`), contenidos opcionales (`has-`) y variantes (`c--`) deben poder crecer sin romper la base.

## 7. Condiciones para la maquetación

1. **Accesibilida como prioridad:** Todas las soluciones que se creen para la maquetación deberán cumplir con los requisitos **WCAG AA** ya que es una exigencia constante en **todos** los desarrollos web que generemos desde el cambio de legislación.
2. **Diseño fácilmente escalable:** Cualquier propuesta de maquetación se trabajará siguiendo esta guía, pero **siempre buscando la solución que permita, con más facilidad, su adaptación responsive** y su evolución/modificación a futuro.

## 8. Recursos

- https://css-tricks.com/bem-101/
- https://csswizardry.com/2015/03/more-transparent-ui-code-with-namespaces/
- https://www.webfx.com/blog/web-design/css-methodologies/

## 9. Troubleshooting rapido de maquetacion

Antes de aplicar cambios complejos, valida este checklist operativo:

- El CSS cargado es la ultima version.
- Cache limpia (Joomla + navegador).
- El error esta localizado por dispositivo/navegador y por media query.
- Los selectores estan bien escritos y apuntan al elemento correcto.
- No hay herencias inesperadas ni problemas de especificidad.
- Si se sobrescriben estilos, el selector nuevo tiene autoridad suficiente.

### Caso frecuente: slider que rompe en next/prev

Si un slider cambia de alto o muestra contenido de mas al navegar:

- Verifica la maquetacion base del slider sin inicializar JS.
- Asegura que el contenedor y los items tienen dimensiones coherentes en el media donde falla.
- Revisa que el script de slider pueda leer correctamente los tamanos iniciales.
