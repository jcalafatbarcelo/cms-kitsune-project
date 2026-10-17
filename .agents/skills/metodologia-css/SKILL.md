---
name: metodologia-css
description: "Define, nombra y revisa clases CSS y HTML siguiendo la metodología BEM + OOCSS con namespaces (c-, __, --, is-, has-, t-, js-, a-) y breakpoints. Usar cuando haya que crear o modificar HTML/CSS, elegir nombres de clases, decidir entre variante, estado, contenido o tema, montar un componente reutilizable, ordenar el archivo CSS, usar variables CSS o trabajar con responsive y media queries; incluye los disparadores definir clases CSS, nombrar clases, metodología CSS, BEM, OOCSS, namespaces, prefijos CSS, c-, is-, has-, t-, js-, a-, variante, especificación, estado CSS, tema transversal, contenido opcional, maquetar componente, maquetación, media queries, breakpoints, @custom-media, custom media, PostCSS, sufijo responsive, orden del CSS, estructura del CSS, variables CSS, custom properties, :root, tipografías, @font-face, mobile-first, WCAG AA y escalable."
---

# Metodología CSS (BEM + OOCSS)

Instrucciones para nombrar y estructurar clases CSS/HTML en proyectos web. Aplícala siempre que escribas o revises maquetación: nombres
de clases, variantes, estados, contenidos opcionales, temas, hooks JavaScript,
variables, orden del archivo y media queries.

**BEM para la estructura** (`c-`, `__`, `--`) **+ OOCSS para la reutilización**
(bloques reutilizables, `t-` transversal) **+ namespaces para comunicar
propósito desde el nombre**.

Orden de referencia del documento:
**tipografías → `:root` → modificaciones globales de etiquetas → temas (`t-`) →
componentes (`c-`)**, con cada media query pegada a su bloque base y las
variables al inicio de su ámbito.

## 1. Documentación (fuente de la verdad)

La documentación completa de la metodología está copiada dentro de esta skill,
en `docs/` (relativa a la carpeta de la skill). Esta skill es **autosuficiente**:
no depende de ninguna carpeta externa. Consulta `docs/` cuando necesites detalle
o ejemplos.

- `docs/00 - index.md` — índice de la wiki.
- `docs/01.*` – `docs/08.*` — notas por bloque (namespaces, patrones, media
  queries, workflow, requisitos, recursos, orden y variables).
- `docs/maquetacion_faqs.md` — problemas habituales (troubleshooting).

Reglas de autoridad:

- **Autoritativas:** las notas numeradas `docs/01.*`–`docs/08.*`.
- `docs/documentacion_metodologia_css.md` es un resumen previo **no
  autoritativo**: si discrepa con las notas numeradas, ignóralo.

Las secciones siguientes son el extracto operativo de esa documentación; ante
detalle o duda, manda `docs/`.

> La fuente original del equipo está en
> `G:\Unidades compartidas\templateOpenWeb\extras\Documents\MetodologiaCSS`.
> Esta copia local es independiente; re-sincronízala solo si quieres incorporar
> cambios del equipo.

## 2. Namespaces

| Namespace | Uso principal | Ejemplos |
|-----------|---------------|----------|
| `c-` | Componentes (bloques base) | `c-slider`, `c-ficha` |
| `c-[bloque]__[elemento]` | Elementos dependientes del bloque | `c-slider__item`, `c-ficha__text` |
| `c--[especificacion]` | Especificaciones / variantes del bloque completo | `c--deportes`, `c--adoptado` |
| `t-` | Temas o estilos transversales | `t-especial`, `t-warning` |
| `js-` | Punteros para scripts (nunca se estilizan) | `js-slider` |
| `is-` | Estado temporal del objeto | `is-destacado`, `is-disabled` |
| `has-` | El mismo objeto con contenido extra | `has-picture`, `has-icon` |
| `a-` | Nombres de animaciones declaradas en CSS | `a-fade-in`, `a-slide` |

## 3. Árbol de decisiones

1. ¿Existe como pieza reutilizable por sí sola? → `c-[bloque]`.
2. ¿Depende de un bloque? → `c-[bloque]__[elemento]`.
3. ¿La variación es estable y afecta al bloque completo? → `c--[especificacion]`.
4. ¿Es un estado temporal? → `is-[estado]`.
5. ¿Solo cambia porque hay contenido opcional? → `has-[contenido]`.
6. ¿El estilo aplica a cualquier componente? → `t-[tema]`.
7. ¿Solo es un hook de comportamiento? → `js-[hook]`.

**Regla general:** si el cambio afecta a toda la estructura de forma permanente,
usa `c--`. Si es puntual o depende de lógica/JS, usa `is-`. Si es por la
presencia de un subelemento, usa `has-`.

## 4. Reglas clave

- Las especificaciones (`c--x`) **siempre se escriben junto al bloque**:
  `.c-ficha.c--promo { ... }`.
- Los componentes anidados **repiten ambas clases** cuando corresponde:
  `class="c-padre__hijo c-hijo"`.
- Si un hijo necesita demasiadas excepciones dentro de un bloque, **conviértelo
  en bloque independiente**.
- `is-` y `has-` **se suman** sobre la clase `c-`; no la sustituyen.
- No mezcles estilos de temas (`t-`) con estados (`is-`).
- Para estados controlados por JavaScript, combina `js-` + `is-`:
  `.js-modal.is-open`.
- `js-` **jamás** lleva estilos: es un hook de comportamiento.
- `t-` no depende de la estructura interna del bloque: sirve para color,
  tipografía, espaciados o mensajes que se repiten.

### Animaciones (`a-`)

- `a-` se usa **solo** para nombrar `@keyframes` y `animation-name`.
- La activación va con clases ya existentes (`is-`, `c--`, `t-`), **nunca** con
  clases `a-` en el HTML.
- Reutiliza el mismo nombre `a-` en todos los sitios donde se declare o use la
  animación; no dupliques un mismo efecto con nombres distintos.
- Documenta duración y timing cuando no sean obvios:
  `animation: a-fade-in 160ms ease-out;`.
- Prioriza animar `transform` y `opacity`; evita animar `top`, `left`, `width`,
  `height` cuando exista alternativa basada en `transform`.

## 5. Orden del archivo CSS

El orden forma parte de la metodología. Mantén siempre esta secuencia:

1. **Tipografías** (`@font-face` / carga de fuentes). Si se declaran, van **al
   inicio del documento**, **antes de `:root`**.
2. **Variables globales** (`:root { ... }`).
3. **Tokens de breakpoints** (`@custom-media`, solo si aplica) y **animaciones**
   (`@keyframes` con prefijo `a-`). No afectan a la cascada; mantenlos en la zona
   de configuración.
4. **Modificaciones globales de etiquetas** (`html`, `body`, `a`, `h1`, `p`, …).
   Van **después de `:root`** y **antes de los temas**.
5. **Temas (`t-`)**. Van **siempre antes** de los componentes.
6. **Componentes (`c-`)**. Cada uno con este orden interno: **variables al
   inicio** → base, variantes, elementos y estados → **media queries**.

### Regla: los temas van antes que los componentes

Los `t-` son genéricos y transversales: no se vinculan a un componente ni
dependen de su estructura. Por eso se declaran **siempre antes** de los `c-`,
**nunca después**.

### Regla: cada media query va después de su bloque base

Las media queries (breakpoints) de un componente o tema se colocan
**inmediatamente después** de sus reglas sin media, no todas juntas al final del
archivo. El objetivo es que todo lo que afecta a un componente o a un tema quede
localizado en un único punto. Ordena las media de menor a mayor (mobile-first).

Esto **no altera la jerarquía ni la cascada** del CSS: el orden entre selectores
de bloques distintos no cambia la especificidad.

## 6. Variables CSS

Las variables son la base para reducir la reedición constante en los
breakpoints: si basta con cambiar una variable, no hay que reescribir la regla
completa.

### Variables globales

En `:root`, al inicio del documento.

```css
:root {
  --color-primario: #1f5fbf;
  --color-texto: #33404f;
  --radio-base: 12px;
}
```

### Variables por componente

Al inicio de cada componente, con este formato:

```css
.c-ejemplo,
.c-ejemplo * {
  /* Tus variables de componente aquí */
  --c-ejemplo-padding: 1rem;
  --c-ejemplo-radio: var(--radio-base);
}
```

### Uso en breakpoints

En los breakpoints, **modifica las variables si eso basta** para aplicar el
rediseño responsive deseado. Esto **no restringe** incluir otros ajustes que no
vayan por esta vía cuando sean necesarios.

```css
.c-ejemplo,
.c-ejemplo * {
  --c-ejemplo-padding: 1rem;
}

@media (min-width: 1024px) {
  .c-ejemplo,
  .c-ejemplo * {
    --c-ejemplo-padding: 1.5rem;   /* basta con la variable */
  }
}
```

> Convención de nombres: `--c-[bloque]-[propiedad]`.

## 7. Media queries y breakpoints

### `@custom-media`: uso condicional

Por norma general **no se usa `@custom-media`**, porque los proyectos no
disponen de PostCSS y el navegador no lo interpreta. Declara los breakpoints con
valores literales (`px`) siguiendo la tabla de abajo y la nomenclatura de
sufijos.

Usa `@custom-media` **solo cuando esté garantizada la disponibilidad de
PostCSS** (por ejemplo `postcss-custom-media`). Si no puedes confirmarlo, usa el
valor literal.

```css
/* Por defecto (sin PostCSS): valor literal */
@media (min-width: 768px) { /* ... */ }

/* Solo si se garantiza PostCSS */
@custom-media --bp-nm (min-width: 768px);
@media (--bp-nm) { /* ... */ }
```

### Tabla de breakpoints y sufijos

| Token `@custom-media` | Sufijo | Media query | Uso recomendado |
|-----------------------|--------|-------------|-----------------|
| `--bp-sm` | `-sm` | `(max-width: 399px)` | Mobile muy pequeño |
| `--bp-m` | `-m` | `(max-width: 767px)` | Mobile general |
| `--bp-t` | `-t` | `(min-width: 768px) and (max-width: 1023px)` | Tablet |
| `--bp-nm` | `-nm` | `(min-width: 768px)` | No mobile |
| `--bp-nt` | `-nt` | `(min-width: 1024px)` | No tablet (desktop base) |
| `--bp-d1` | `-d1` | `(min-width: 1024px) and (max-width: 1359px)` | Desktop compacto |
| `--bp-d2` | `-d2` | `(min-width: 1360px) and (max-width: 1599px)` | Laptop grande / 1440px |
| `--bp-d3` | `-d3` | `(min-width: 1600px) and (max-width: 1919px)` | Desktop ancho |
| `--bp-xl` | `-xl` | `(min-width: 1920px)` | Ultra ancho / 4K inicial |
| `--bp-om`, `--bp-ot`, … | `-om`, `-ot`, … | `only (rango)` | Overrides exclusivos |
| `--bp-c-*` | `-c*` | `(custom)` | Rangos específicos por componente |

### Nomenclatura de sufijos responsive

Estructura sugerida: `[condicion][tamano][rango]`

- Condiciones: `n` (no), `o` (solo), `c` (custom).
- Tamaños opcionales: `s` (small), `x` (extra).
- Rango base: `sm`, `m`, `t`, `nt`, `d1`, `d2`, `d3`, `xl`.

Ejemplos:

- `.c-card-nm` → aplicable a todo lo que no sea mobile (`min-width: 768px`).
- `.c-slider-od2` → override que solo vive en `1360px`–`1599px`.

## 8. Flujo de trabajo

1. Declara tipografías y variables globales (`:root`).
2. Define las modificaciones globales de etiquetas.
3. Define los temas (`t-`).
4. Diseña o detecta el bloque base (`c-`) y declara sus variables al inicio.
5. Define variantes (`c--`) solo cuando afectan al bloque completo.
6. Añade elementos (`__`) con semántica clara.
7. Etiqueta estados (`is-`) y añadidos (`has-`).
8. Declara punteros (`js-`) para hooks JavaScript.
9. Añade las media queries de cada bloque justo después de su base, cambiando
   variables cuando baste.
10. Revisa el orden del documento y la checklist antes de finalizar.

## 9. Ejemplo canónico

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
/* --- 1. Tipografías --- */
@font-face {
  font-family: "Aria";
  src: url("/fonts/aria.woff2") format("woff2");
  font-display: swap;
}

/* --- 2. Variables globales --- */
:root {
  --color-primario: #1f5fbf;
  --color-texto: #33404f;
  --radio-base: 12px;
}

/* --- 3. Animaciones (a-) --- */
@keyframes a-aparecer {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* --- 4. Modificaciones globales de etiquetas --- */
html {
  font-family: "Aria", system-ui, sans-serif;
  line-height: 1.5;
}

body {
  margin: 0;
  color: var(--color-texto);
}

h1, p { margin: 0; }

a { color: var(--color-primario); }

/* --- 5. Temas (t-) --- */
.t-especial { font-weight: 700; }
.t-warning, .t-error { font-weight: 700; }

/* --- 6. Componentes (c-) --- */
.c-slider,
.c-slider * {
  --c-slider-gap: 1rem;
}

.c-slider { /* Estilos genéricos del slider */ }
.c-slider.c--deportes { /* Ajustes de la variante deportes */ }
.c-slider__item { /* Elementos dentro del slider */ }

/* Media de c-slider, justo después de su base */
@media (min-width: 768px) {
  .c-slider,
  .c-slider * {
    --c-slider-gap: 1.5rem;   /* basta con la variable */
  }
}

.c-ficha,
.c-ficha * {
  --c-ficha-radio: var(--radio-base);
}

.c-ficha { /* Definición base de la ficha reutilizable */ }
.c-ficha.has-picture { /* Ajustes cuando incorpora <picture> */ }
.c-ficha.is-destacado { /* Estado destacado puntual */ }
.c-ficha__picture { /* Dependiente directo del bloque */ }
.c-ficha__text { /* Texto dentro de la ficha */ }

/* Media de c-ficha, justo después de su base */
@media (min-width: 1024px) {
  .c-ficha,
  .c-ficha * {
    --c-ficha-radio: 16px;
  }
}
```

## 10. Requisitos obligatorios del proyecto

1. **Accesibilidad WCAG AA (prioridad).** Todas las soluciones de maquetación
   deben cumplirla:
   - Semántica correcta en HTML (roles y jerarquías).
   - Contraste suficiente y foco visible.
   - Estados y mensajes accesibles, no solo por color.
   - Respeta `prefers-reduced-motion` cuando haya animaciones.
2. **Diseño fácilmente escalable y responsive:**
   - Trabaja con enfoque **mobile-first** usando los breakpoints.
   - Evita tamaños rígidos; prioriza unidades relativas y layouts flexibles.
   - Diseña para que estados (`is-`), contenidos opcionales (`has-`) y variantes
     (`c--`) puedan crecer sin romper la base.

## 11. Checklist antes de entregar

- [ ] El orden del documento es correcto: tipografías → `:root` → etiquetas →
      `t-` → `c-`.
- [ ] Las tipografías (si las hay) están antes de `:root`.
- [ ] Las variables globales están en `:root`.
- [ ] Cada componente declara sus variables al inicio con `.c-x, .c-x * { ... }`.
- [ ] Las modificaciones globales de etiquetas están después de `:root` y antes
      de los temas.
- [ ] El bloque tiene un único nombre `c-` semántico.
- [ ] Los elementos usan `c-[bloque]__[elemento]` con significado claro.
- [ ] Las variantes permanentes están modeladas con `c--`.
- [ ] Los estados temporales usan `is-` y los contenidos extra `has-`.
- [ ] Los punteros de JS están aislados (`js-`) y fuera del CSS.
- [ ] Los estilos transversales (`t-`) no dependen de la estructura del bloque.
- [ ] Los `.t-` están **antes** de los `.c-`.
- [ ] Cada media query está **después** de su bloque base (tema o componente).
- [ ] En los breakpoints se han reutilizado las variables cuando bastaban.
- [ ] No se usa `@custom-media` salvo con PostCSS garantizado.
- [ ] La solución cumple WCAG AA (semántica, contraste y foco).
- [ ] El diseño facilita su adaptación responsive y su evolución futura.

## 12. Anti-patrones

- Estilizar clases `js-` (deben ser solo punteros de comportamiento).
- Poner clases `a-` en el HTML para activar animaciones.
- Usar `c--` cuando bastaría `has-` (ajuste de un solo descendiente).
- Usar `is-` para variaciones permanentes del bloque completo.
- Definir `t-` que dependan de la estructura interna de un bloque.
- Colocar bloques `.t-` **después** de los `.c-`.
- Poner las tipografías o las variables globales fuera de su posición inicial.
- Colocar modificaciones globales de etiquetas después de los temas.
- Declarar variables de componente fuera del propio componente.
- Rehacer reglas completas en cada breakpoint pudiendo cambiar solo la variable.
- Agrupar todas las media queries al final del archivo en vez de junto a su base.
- Usar `@custom-media` sin PostCSS garantizado (no se interpreta en el navegador).
- Duplicar un mismo efecto de animación con nombres `a-` distintos.
- Encadenar demasiadas excepciones en un hijo en vez de promoverlo a bloque.

## 13. Troubleshooting de maquetación

Antes de aplicar cambios complejos, valida:

- El CSS cargado es la última versión.
- Caché limpia (Joomla + navegador).
- El error está localizado por dispositivo/navegador y por media query.
- Los selectores están bien escritos y apuntan al elemento correcto (errores
  tipográficos típicos: `.c-textofoto` vs `.c-textfoto`).
- No hay herencias inesperadas ni problemas de especificidad.
- Si sobrescribes estilos, el selector nuevo tiene autoridad suficiente.

**Caso frecuente — slider que rompe en next/prev:** suele ser un error de
maquetación en el `media` donde falla. Comprueba que el contenedor del slider se
muestra correctamente **incluso sin inicializar el JS** (si no, el script de
slider no lee bien los tamaños y calcula mal las animaciones de `next`/`prev`).
Asegura dimensiones coherentes del contenedor y de los items en el media que
falla.
