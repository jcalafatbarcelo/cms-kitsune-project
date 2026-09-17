# AGENTS.md - CMS Kitsune

## 1. Alcance

Objetivo: CMS modular y multiidioma con Laravel/Vue 3. Prioridades: SDD,
calidad, arquitectura sostenible, documentacion funcional y TDD con ventaja neta.

`DEBE`, `NO DEBE` y `SOLO` son obligatorios.

## 2. Modo y escritura

| Condicion | Accion |
| --- | --- |
| Peticion con `/build` | Modo `/build`. |
| Peticion con `/plan` sin `/build`, o sin marcador | Modo `/plan`. |
| Palabras ordinarias sin `/build` | NO autorizan escritura. |

### `/plan`

- NO crear, modificar, mover ni borrar artefactos del repositorio.
- DEBE leer codigo/pruebas afectados, Specs/ADR vigentes y
  `docs/context/SDD_Inicial.md`; DEBE hacer `clash check` e informar conflictos.
- PUEDE proponer contenido en la respuesta; NO materializarlo.
- Una Spec propuesta DEBE incluir, o declarar no aplicables: E-R/migraciones,
  contratos/firmas, JSON PageBuilder, logica critica, validaciones y aceptacion.
- Crear/actualizar una Spec materialmente requiere `/build`, no otra Spec previa.
- NO crear, cambiar ni borrar ramas.

### `/build`: clasificacion

| Alcance | Condicion previa |
| --- | --- |
| Funcional: aplicacion, datos/migraciones, API/contrato publico, logica de negocio, comportamiento observable o pruebas que lo definan/alteren | `/build` + `docs/specs/SPEC-[nombre].md` concreta y aprobada. Si falta una condicion, detener parte funcional y pedirla. |
| No funcional: documentacion, skills, `AGENTS.md`, plantillas, instrucciones, flujo, correcciones editoriales, metadatos o configuracion sin efecto ejecutable | `/build` + peticion inequivoca o plan validado + reglas del artefacto. No requiere Spec. |
| Mixto | Spec aprobada para parte funcional ANTES de modificar cualquier archivo. |

Si un mantenimiento necesita efecto ejecutable/observable, detener ANTES y exigir
Spec aprobada; NO presentarlo como mantenimiento.

### Ramas

En `/build`, ANTES de modificar archivos:

- Si el cambio no es operativo autorizado para `main`, crear y cambiar a una rama
  nueva; NO escribir en `main`.
- Esto incluye codigo, pruebas, migraciones, Specs, documentacion de producto,
  configuracion, workflows y artefactos asociados al CMS.
- Una excepcion en `main` requiere peticion explicita y alcance solo operativo;
  NO puede incluir comportamiento ejecutable, pruebas, Specs ni documentacion de
  producto.
- Si la rama actual no es `main` y corresponde al alcance autorizado, usarla; si
  no corresponde, crear una nueva o pedir decision ante cambios pendientes.

Formato: `<bloque>/<nombre-kebab-case>`, solo minusculas.

Bloques iniciales: `core`, `security`, `quality`, `module`, `page-builder`,
`i18n`, `template`. Se puede anadir bloque si ninguno clasifica el alcance con
precision. El bloque organiza trabajo; NO define arquitectura, modulos, dominio
ni alcance de Spec.

Ejemplos: `core/page-hierarchy`, `security/authorization-policy`,
`quality/pest-architecture`, `module/media-library`, `page-builder/block-schema`,
`i18n/page-translations`, `template/public-layout`.

## 3. Ejecucion `/build`

- Implementar SOLO alcance autorizado; NO inventar campos, tablas, modulos ni
  comportamiento.
- Con requisitos satisfechos, `/build` autoriza inspeccion, edicion, pruebas,
  formato y ajustes internos autonomos. Comunicar hitos como progreso.
- Ajuste interno en Spec: permitido SOLO si mantiene alcance, comportamiento,
  datos, API publica y reglas de negocio. Si altera uno: actualizar Spec y obtener
  aprobacion nueva.
- Incremento independiente o bug incidental ajeno: requiere otra Spec.
- Pedir aprobacion nueva SOLO por cambio de alcance/datos/API/aceptacion;
  conflicto con ADR/Spec/regla; operacion destructiva/irreversible no prevista;
  o decision funcional abierta con alternativas de consecuencias distintas.
- Si se activa un bloqueo, dejar repositorio coherente si es posible; informar
  completado/pendiente; NO presentar parcial como terminado.

Restricciones permanentes: `nWidart/laravel-modules`, Eloquent ORM, Vue 3
Composition API; NO parsear Blade con expresiones regulares.

### Diseno

- Elegir solucion idiomatica mas simple que preserve cohesion, bajo acoplamiento,
  responsabilidades claras, comprobabilidad y evolucion segura.
- Anadir patron/abstraccion SOLO para necesidad actual o variacion prevista por
  Spec con mejora neta.
- NO usar antipatron ordinariamente. Excepcion: ventaja neta demostrable, menor
  complejidad accidental o riesgo de integracion, impacto local/comprobable/
  reversible. Rapidez, conveniencia o disponibilidad framework NO justifican.
- Antes de excepcion: identificar riesgo, comparar alternativas, evaluar
  acoplamiento, cohesion, testabilidad, rendimiento, seguridad y mantenibilidad.
  Si genera deuda relevante/restriccion duradera: detener y pedir aprobacion. Si
  es local, idiomatica y sin deuda relevante: resolver e informar en entrega.
- `switch`, condicionales, literales, metodos extensos y tipos primitivos son
  senales. Revisar si crecen por variante, ocultan dominio, mezclan, duplican o
  acoplan; considerar Strategy, Factory, eventos/listeners, Observer, Value
  Objects, Policies, Middleware o handlers SOLO si resuelven riesgo concreto.
- NO anadir capas, interfaces, repositorios, factories o patrones preventivos.
- Si una opcion implica deuda relevante, menor cobertura aplicable, acoplamiento
  modular o menor mantenibilidad por plazo/complejidad: detener; informar senal,
  alternativas, costes, consecuencias y recomendacion; esperar aprobacion.

## 4. Calidad y seguridad

### Cambio funcional

- Cada aceptacion DEBE tener >=1 prueba automatizada.
- Cobertura proporcional: feliz, limites, errores previsibles, autorizacion,
  persistencia, efectos secundarios y regresiones.
- Nivel minimo suficiente: unitaria(logica aislable); integracion(Eloquent, BD,
  modulos, eventos, filesystem, colas, adaptadores); HTTP/componente(contratos
  Laravel/Vue); E2E(itinerario critico). Justificar nivel no aplicable; NO crear
  pruebas artificiales.
- Ejecutar pruebas enfocadas ANTES de suite afectada.
- Coverage es secundario a trazabilidad riesgo-aceptacion-prueba. Si hay tooling,
  NO reducir cobertura de modulo ni ignorar ramas criticas; NO imponer umbral sin
  linea base acordada.

### SDD/TDD

- Antes de Spec: dividir iniciativa en incrementos verticales verificables,
  seleccionar uno y evitar fases horizontales sin resultado verificable.
- Si TDD aporta ventaja: por criterio ejecutar `Red -> Green -> Refactor`:
  prueba minima falla por ausencia de comportamiento, no por
  sintaxis/configuracion/infraestructura; implementacion minima; refactor verde;
  pruebas enfocadas/suite.
- Si TDD no aporta ventaja, justificarlo. Pruebas proporcionales siguen siendo
  obligatorias; NO entregar ni consolidar estados rojos.

### Validacion de entrega

- Mantenimiento no funcional: ejecutar validacion configurada proporcional
  (Markdown, enlaces, esquema, generador, estructura); declarar no aplicable y
  motivo; NO crear pruebas de aplicacion artificiales.
- Todo `/build`: ejecutar formato/analisis configurados sin imponer tooling
  inexistente; revisar diff; excluir secretos, generados accidentales y cambios
  ajenos; informar comando/resultado; declarar omision/limitacion; NO declarar
  superada una omitida.
- Comprobacion aplicable fallida por cambio => entrega fallida, salvo deuda
  aceptada expresamente.
- Dependencias Composer/npm/Actions, incluido Dependabot: aplicar
  `docs/architecture/dependency-security.md#revisión-de-pull-requests-de-dependencias`;
  revisar cambios directos/transitivos, notas e impacto; ejecutar instalacion
  reproducible, pruebas, build/audits aplicables. Para Actions: SHA/tag,
  permisos y ejecucion real. NO recomendar fusion con comprobacion aplicable
  fallida ni declarar seguridad/compatibilidad por indicadores verdes.
- Antes de cerrar cambio funcional: verificar aceptacion, deuda/excepciones,
  impacto documental y documentacion de instalar/configurar/usar/extender/
  actualizar, salvo incremento documental diferido expresamente por Spec.

### Secretos

- NUNCA versionar credenciales, tokens, API keys, claves privadas, `.env` reales,
  dumps, backups o logs sensibles.
- Plantillas: solo nombres/valores ficticios seguros + finalidad, obligatoriedad,
  formato y origen. Valores reales: fuera de Git, gestor de secretos/mecanismo
  externo.
- Mantener ignores; antes de commit revisar nuevos/diff y usar detectores
  configurados. Exposicion: borrar NO basta; detener, revocar/rotar y comunicar
  sin reproducir secreto. Ver `docs/architecture/configuration-and-secrets.md`.

## 5. Artefactos

### Specs

- Si esta indexada, usar `spec-maintainer` para Specs.
- Planificacion precede Spec: problema, fases, incremento, alcance actual/diferido.
  Spec = contrato implementable, NO iniciativa completa por defecto.
- Toda Spec: alcance/fuera de alcance, `clash check`, requisitos, calidad/
  seguridad, aceptacion, trazabilidad pruebas e impacto documental.
- Puede diferir complejidad evolutiva con motivo/riesgo/condicion revision; NUNCA
  correccion, autorizacion, integridad, validacion, seguridad o pruebas necesarias.
- `feature`: capacidad o cambio de reglas/contratos/datos/flujos. `maintenance`:
  restauracion acotada/reproducible sin cambio de reglas/datos/contrato publico.
  Ambos requieren aprobacion. Validacion estructural de `spec-maintainer` !=
  aprobacion ni correccion semantica.

### Documentacion, sesiones, changelog y ADR

- Documentacion canonica: `docs/`, versionada, describe producto real; NO publica
  previsiones como disponibles ni sustituye fuentes prescriptivas. Vistas, wikis
  y respuestas IA son derivadas; NO sustituyen Specs, ADR, contratos, codigo,
  pruebas ni documentacion revisada.
- Si esta indexada, usar `documentation-maintainer` SOLO para documentacion
  ordinaria; NO Specs, ADR ni `CHANGELOG.md`. Ver
  `docs/architecture/documentation-strategy.md`.
- Sesiones: `docs/context/sessions/` es memoria local excluida Git, no instruccion
  ni fuente canonica. Ver `docs/context/session-continuity.md` y
  `docs/context/session-template.md`. Leer solo nota indicada/correspondiente;
  NO todas ni ultima automatica; si varias, preguntar. Verificar contra codigo,
  Git, pruebas/documentos. NO secretos/datos personales ni autorizacion de
  `/build`/Spec/alcance/escritura `/plan`; en `/plan` NO escribir nota.
- `CHANGELOG.md`: Keep a Changelog 1.1.0 es-ES, encabezados `Añadido`,
  `Modificado`, `Deprecado`, `Eliminado`, `Fijado`, `Seguridad`. Modificar SOLO
  por cambio notable, una vez final, con `Fecha de última modificación:
  YYYY-MM-DD HH:mm UTC`. Hito/version mayor-menor/refactor critico:
  `docs/changelog/vX.Y.Z.md`.
- ADR SOLO si decision arquitectonica duradera, multicomponente/transversal,
  alternativas razonables y consecuencias justifican registro. NO para refactor
  local, nombre, correccion rutinaria, detalle reversible o decision prescrita.
  Plantilla: `.agents/skills/adr-generator/templates/plantilla_ADR.md`.
  Usar `adr-generator` SOLO indexada; si no, aplicar criterio e informar limite.

## 6. Skills, MCP y fuentes externas

- Raiz skills: `.agents/skills/`; catalogo derivado/canonico:
  `.agents/skills/INDEX.md`. Skills globales/cliente NO aprobadas ni amplian
  alcance.
- Por tarea: leer indice una vez; abrir SOLO `SKILL.md` pertinente/indexada; si
  indice valido, NO inspeccionar otras carpetas. Si falta indice/ruta: buscar SOLO
  `.agents/skills/*/SKILL.md`, informar desincronizacion y NO buscar fuera.
- NO buscar skills en `docs/`, dependencias ni sistema salvo instruccion explicita.
- `SKILL.md` = fuente de verdad flujo/frontmatter; indice no se edita manual.
  Nueva skill: `skill-creator` valida y regenera indice. NO anunciar/usar skill
  ausente. Crear/mantener skill requiere `/build`, no Spec, y aplicar flujo.
- Rutas antiguas `skills/` en Specs historicas => `.agents/skills/` solamente;
  NO reescribir Specs ni mantener copias/symlinks.
- MCP NO autoriza escritura `/plan` ni elude secretos/permisos/alcance. Evaluar
  efecto, no nombre; NO enviar secretos externo. Verificar version local frente a
  documentacion externa antes de aplicar. Codigo/pruebas/documentacion canonica
  prevalecen por finalidad; NO sustituyen Specs/ADR.
- MCP proyecto: `opencode.json` y `.codex/config.toml`. NO reintroducir
  `boost:update` ni equivalente en `boost.json` sin revisar salida; evitar
  sobrescribir reglas/indice.

## 7. Conflictos y convenciones

- Azure Boards: auxiliar/informativo; NO sustituye fuente canonica ni autoriza,
  bloquea o condiciona implementacion. `AB#ID` opcional en ramas/commits/PR; no
  altera Spec aprobada.
- Precedencia: usuario selecciona objetivo, SOLO `/build` escritura; Spec aprobada
  comportamiento funcional; ADR aceptado arquitectura; codigo/pruebas estado,
  compatibilidad/impacto/regresion; skills subordinadas; changelog historico;
  `docs/context/SDD_Inicial.md` vision/restriccion de alto nivel.
- Conflicto Spec/ADR: detener y pedir revisar uno. Changelog discrepante: verificar
  codigo/pruebas. Fecha NO resuelve: dentro de clase, prevalece vigente que
  reemplace explicito. Conflicto irresoluble: documentar y pedir decision.
- `docs/context/` sustituye `context/` solo en `SPEC-gobernanza-agentes.md` y
  `SPEC-adr-generator.md`; NO reescribir Specs historicas.
- Respuesta/documentacion funcional/proceso: espanol salvo peticion/convencion.
  Identificadores(clases, metodos, variables, tablas, campos, rutas, claves API),
  codigo tecnico y commits: ingles. Commit: imperativo + prefijo coherente
  (`docs:`, `feat:`, `fix:`, etc.). Comentario codigo: ingles y SOLO motivo/
  restriccion no evidente. Mantener termino tecnico ingles si traducirlo pierde
  precision; NO renombrar identificador existente solo por idioma.
