# SPEC: Distribución de UI catalogs solo en inglés

- **Estado:** Completada
- **Perfil:** feature
- **Origen de la planificación:** Decisión de producto adoptada durante la revisión del PR #60: el CMS solo distribuye `en` y los paquetes de idioma deben aportar sus catálogos.
- **Spec relacionada:** `docs/specs/SPEC-ui-catalog-overrides.md`

## 1. Objetivo o problema

El CMS debe distribuirse únicamente con el idioma base `en`. Core y el template
Base no deben incluir catálogos `es_ES` ni de otros locales, porque su presencia
sugiere soporte nativo y hace que las pruebas dependan de archivos locales no
versionados o de contenido que no forma parte del producto.

El fallo de CI del PR #60 es la evidencia actual: una prueba nueva espera
`Idioma es_ES instalado.` desde `Modules/Core/Resources/lang/es_ES.json`, archivo
que existe en el entorno local pero no está versionado. En CI se resuelve el
catálogo `en` y la prueba falla con `Language es_ES installed.`.

## 2. Contexto y evidencia

- Una instalación limpia inserta solo el idioma `en` y lo deja como base,
  predeterminado de frontend y de backoffice.
- `Modules/Core/Resources/lang/es_ES.json` no está versionado. Solo existe en el
  entorno local y está excluido mediante `.git/info/exclude`.
- `Templates/Base/Resources/lang/es_ES.json` sí está versionado y se distribuye
  con el template Base.
- El ADR-0002 exige un catálogo `en` por propietario y permite omitir por
  completo los locales distintos de `en`. Los overrides pueden traducir claves
  para locales no distribuidos por el propietario.
- `docs/specs/SPEC-ui-catalog-overrides.md` afirma que Core y Base distribuyen
  `en` y `es_ES`. Esa afirmación contradice la decisión de producto y el
  comportamiento deseado.
- Las pruebas que dependen de catálogos `es_ES` distribuidos son:
  - `tests/Feature/Core/UiCatalogOverrideResolutionTest.php`;
  - `tests/Feature/Admin/AdminHttpTest.php`;
  - `tests/Feature/Admin/PagesBackofficeHttpTest.php`.

## 3. Alcance

- Retirar del producto el catálogo `es_ES` del template Base.
- No incorporar al producto el catálogo `es_ES` de Core.
- Aislar las pruebas de idiomas distintos de `en` mediante fixtures temporales
  explícitos, sin depender de archivos locales ni de catálogos distribuidos.
- Impedir que los catálogos localizados de Core y Base se añadan accidentalmente
  al repositorio.
- Corregir la Spec y la documentación que presenten `es_ES` como distribuido.
- Mantener sin cambios el contrato de instalación de idiomas: un idioma distinto
  de `en` sigue requiriendo su catálogo Core y su manifiesto.

### Fuera de alcance

- Diseñar o implementar el formato de un paquete de idioma que transporte
  catálogos de Core y de templates.
- Instalar catálogos de templates desde un paquete o desde el backoffice.
- Cambiar la resolución de catálogos, el fallback, los overrides o la precedencia
  de locale.
- LOC-04 (backoffice de idiomas y overrides) y LOC-08 (paquetes dinámicos).

### Alcance diferido

- Un paquete de idioma que aporte todos los catálogos necesarios, incluidos los
  del template efectivo, se retomará en LOC-08 o en la Spec que concrete los
  paquetes dinámicos. Condición para retomarlo: contrato de paquete y mecanismo
  de instalación aprobados.

## 4. *Clash check*

- **ADR-0002:** alineado. Exige `en` y permite omitir locales distintos de `en`;
  los overrides siguen cubriendo locales no distribuidos.
- **SPEC-ui-catalog-overrides:** contradicción puntual en la frase que afirma que
  Core y Base distribuyen `en` y `es_ES`. Se corrige como parte de este
  incremento; el resto del contrato de overrides no cambia.
- **Código:** `LanguageManager` solo exige el catálogo del locale que se instala;
  `TemplateUiCatalogs` usa `en` como inventario y permite fallback. No hay lógica
  que presuponga `es_ES`.
- **Pruebas:** tres archivos dependen de catálogos `es_ES` distribuidos. Se
  sustituyen por fixtures temporales.
- **Documentación:** guías de idiomas y UI catalogs deben explicar que solo `en`
  se distribuye y que el paquete externo aporta el resto.
- **SDD inicial:** no fija qué locales se distribuyen; no hay conflicto.

## 5. Requisitos y bloques técnicos aplicables

### Distribución

- Core solo versiona `Modules/Core/Resources/lang/en.json`.
- El template Base solo versiona `Templates/Base/Resources/lang/en.json`.
- Los catálogos de otros locales son artefactos de despliegue o fixtures de
  prueba y no forman parte del paquete distribuido.
- Las reglas de `.gitignore` deben impedir añadir catálogos localizados de Core y
  Base sin una decisión explícita.

### Pruebas

- Las pruebas que necesiten `es_ES` deben construir un catálogo temporal completo
  a partir del `en` real del propietario y de un mapa explícito de traducciones.
- La construcción debe validar que cada clave traducida existe en `en`.
- Las pruebas deben eliminar sus directorios temporales.
- La suite completa debe pasar sin `Modules/Core/Resources/lang/es_ES.json` y sin
  `Templates/Base/Resources/lang/es_ES.json` en el árbol de trabajo.

### Instalación y runtime

- `cms:language:install` sigue validando el catálogo Core del locale solicitado.
- Instalar `es_ES` sin catálogo Core sigue fallando de forma controlada.
- El template Base sin catálogo `es_ES` usa `en` en modo `base` y devuelve la
  clave en modo `key`, conforme al contrato vigente.
- No se añaden migraciones, columnas, tablas ni datos.

### Contratos, datos y PageBuilder

- **E-R y migraciones:** No aplica; no cambia el modelo de datos.
- **Contratos o firmas públicas:** No aplica; no cambia comandos, rutas ni API.
- **JSON de PageBuilder:** No aplica; no se modifica ningún schema ni bloque.
- **Lógica crítica:** la única lógica afectada es la selección de archivos
  distribuidos y la preparación de fixtures de prueba.
- **Validaciones:** las pruebas deben cubrir paridad de claves, resolución
  localizada y ausencia de dependencias de catálogos no versionados.
- **Aceptación:** se detalla en los criterios CA-01 a CA-05.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- La suite completa pasa en un clon limpio, sin catálogos locales distintos de
  `en`.
- Las pruebas siguen cubriendo la resolución en `es_ES`, el fallback a `en`, los
  overrides y la degradación segura.
- No se introducen secretos, datos personales ni artefactos generados.
- No se altera la autorización, la integridad de datos ni la instalación de
  idiomas.

### Riesgos aceptados

- Un idioma distinto de `en` no traduce automáticamente la interfaz del template
  Base. Es una consecuencia deliberada de distribuir solo `en`; el operador puede
  aportar un catálogo de template o usar overrides. Se documenta.

### Deuda técnica

- El mecanismo para que un paquete de idioma transporte catálogos de templates no
  existe. Se acepta hasta LOC-08 o la Spec de paquetes dinámicos. Impacto: una
  instalación de `es_ES` solo traduce las claves cuyo catálogo se haya aportado.
  Condición de retirada: contrato de paquete aprobado e implementado.

## 7. Criterios de aceptación

- **CA-01:** El repositorio solo versiona `en.json` como catálogo de Core y de
  Base; no hay catálogos `es_ES` distribuidos.
- **CA-02:** Las pruebas de UI en `es_ES` pasan usando fixtures temporales y no
  dependen de archivos locales no versionados.
- **CA-03:** La suite completa pasa en un árbol sin
  `Modules/Core/Resources/lang/es_ES.json` ni
  `Templates/Base/Resources/lang/es_ES.json`.
- **CA-04:** La instalación de un idioma distinto de `en` sigue requiriendo su
  catálogo Core y no cambia el esquema de datos.
- **CA-05:** La Spec y la documentación afirman que solo `en` se distribuye y que
  un paquete externo debe aportar sus catálogos.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Distribuir un idioma no soportado | Integración | `git ls-files` y reglas de ignore | `docs/developers/ui-catalogs.md`, `docs/developers/cms-templates.md` |
| CA-02 | Pruebas dependientes del entorno local | HTTP / integración | Pruebas de Admin y Core con fixture temporal | `docs/specs/SPEC-ui-catalog-overrides.md` |
| CA-03 | Falso verde por archivos locales | Suite completa | Suite en clon limpio y sin catálogos `es_ES` | `docs/architecture/localization-roadmap.md` |
| CA-04 | Regresión en instalación de idiomas | Integración | Pruebas de `LanguageManager` y comandos | `docs/administration/languages.md` |
| CA-05 | Expectativa incorrecta de soporte nativo | Documental | Revisión de Spec y guías | Specs y guías citadas |

## 9. Plan de implementación

1. Retirar `Templates/Base/Resources/lang/es_ES.json` del repositorio y añadir
   reglas de ignore para catálogos localizados de Core y Base.
2. Añadir un fixture de pruebas que genere catálogos temporales `en` + `es_ES` a
   partir del `en` real y de un mapa explícito de traducciones.
3. Actualizar las pruebas de Core y de backoffice para usar el fixture y eliminar
   la dependencia de catálogos distribuidos.
4. Corregir `SPEC-ui-catalog-overrides`, las guías de idiomas, UI catalogs y CMS
   Templates, el roadmap y el changelog.
5. Ejecutar pruebas enfocadas, suite completa, Pint, pruebas JavaScript y build de
   Vite; si Docker está disponible, la matriz SQLite, MySQL y MariaDB.

## 10. Decisiones abiertas

No aplica: el alcance, el perfil y los criterios están aprobados para este
incremento.
