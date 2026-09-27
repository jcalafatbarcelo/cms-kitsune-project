# SPEC: Mantenimiento de rutas públicas localizadas

- **Estado:** Completada
- **Perfil:** maintenance
- **Origen de la planificación:** Correcciones verificadas por revisión de PR
  sobre la implementación de rutas públicas localizadas.
- **Spec relacionada:** [SPEC-public-localized-routes](SPEC-public-localized-routes.md), completada.

## 1. Objetivo o problema

Restaurar la estabilidad de las rutas públicas localizadas ante cambios de
configuración, preservar la ruta de salud de Laravel y evitar que una activación
de idioma haga que un alias corto deje de resolver su idioma general.

## 2. Contexto y evidencia

**Fuente del comportamiento esperado:**
[SPEC-public-localized-routes](SPEC-public-localized-routes.md), sus pruebas
HTTP y la ruta de salud declarada por Laravel en `bootstrap/app.php`.

**Pasos de reproducción:** configurar `es_ES` como predeterminado de frontend y
solicitar `/es/` o `/es-es/`; solicitar `/up`; o activar el idioma `es` no
general cuando `es_ES` general ya está activo.

**Resultado actual:** las URLs del idioma predeterminado reciben una redirección
permanente, el catch-all puede interceptar la salud y la activación de `es`
permite una familia ambigua.

**Resultado esperado:** esas URLs reciben una redirección temporal privada, la
salud conserva el callback de Laravel y la activación ambigua se rechaza sin
mutar el estado. La promoción de una variante regional no puede reemplazar un
prefijo corto activo como idioma general, y una negociación de navegador nunca
trunca ni selecciona un idioma excluido.

## 3. Alcance

- Redirigir con `302` y `Cache-Control: private, no-store` toda URL con prefijo
  o alias que resuelva el idioma predeterminado de frontend.
- Excluir la ruta de salud configurada del catch-all de Pages.
- Rechazar la activación de un prefijo corto si ya existe un idioma general
  regional activo de la misma familia.
- Rechazar la promoción de una variante regional si existe un prefijo corto
  activo de la misma familia que debe conservar la designación general.
- Limitar la negociación `Accept-Language` a idiomas base ISO 639-1 de dos
  letras y regiones alfabéticas opcionales de dos letras; ignorar entradas no
  soportadas y preferencias con `q=0`.
- Actualizar documentación, roadmap, pruebas y changelog para reflejar el
  comportamiento restaurado.

### Fuera de alcance

- Cambiar la precedencia de locale, los formatos de prefijo, la jerarquía de
  rutas, los aliases, la selección de idioma general o las reglas de slashes.
- Añadir rutas de salud nuevas o alterar la configuración de Laravel.
- Admitir idiomas base de tres letras, regiones numéricas o ampliar el formato de
  `url_prefix`.

### Alcance diferido

No aplica: las correcciones restauran el contrato ya definido por las rutas
localizadas.

## 4. *Clash check*

- SPEC-public-localized-routes: restaura el uso temporal de `302` cuando el
  destino depende de un predeterminado configurable, sin modificar las reglas de
  canonicidad de sintaxis. No hay conflicto.
- SDD inicial y ADR-0001: preserva el renderizado Blade y no amplía contenido,
  navegación ni UI. No hay conflicto.
- Código y rutas de Laravel: excluir la salud evita que Pages sustituya una
  superficie operativa del framework. No hay conflicto.
- Documentación de rutas y roadmaps: deben describir la precedencia ya entregada,
  no una decisión pendiente. No hay conflicto.

## 5. Requisitos y bloques técnicos aplicables

### Dominio y HTTP

No cambian reglas de negocio, modelo de datos ni contrato público: se restaura
el comportamiento especificado para rutas localizadas.

- Un alias o prefijo que resuelve el idioma predeterminado debe usar `302` y
  `Cache-Control: private, no-store`, porque el predeterminado puede cambiar.
- Una normalización sintáctica de barra final conserva `301`; no depende del
  idioma predeterminado.
- La ruta de salud configurada debe conservar el callback de Laravel y no entrar
  en el resolvedor de Pages.

### Invariantes de idiomas

- Si existe un idioma general regional activo de una familia, no puede activarse
  un idioma de prefijo corto no general de esa misma familia.
- Si existe un prefijo corto activo, una variante regional de su familia no puede
  sustituirlo como idioma general.
- El rechazo no modifica el estado de activación ni el idioma general existente.

### Negociación del navegador

- Solo se consideran etiquetas `Accept-Language` con idioma base ISO 639-1 de
  dos letras y región alfabética opcional de dos letras.
- Una preferencia con calidad `q=0` no participa en la negociación.
- Las etiquetas fuera de este contrato se ignoran; no se truncan para intentar
  resolver otro idioma instalado.

### Seguridad y validación

- La exclusión de salud no puede introducir un bypass hacia Pages ni permitir que
  segmentos de URL seleccionen código o vistas.
- Los errores de activación deben ser accionables y no filtrar datos de sesión o
  contenido editorial.

### API, Vue 3, PageBuilder e integración externa

No aplicable: no se introducen APIs, Vue, PageBuilder, colas ni servicios
externos.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Pruebas HTTP para `302`, cabecera privada y normalización `301` separadas.
- Prueba de integración que garantiza la respuesta de salud configurada.
- Prueba de integración para la activación denegada del prefijo corto.
- Prueba de integración para impedir que una variante regional sustituya un
  prefijo corto activo como idioma general.
- Pruebas HTTP para una preferencia `q=0` y una etiqueta base de tres letras que
  no pueden seleccionar otro idioma.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build y controles de seguridad
  configurados.

### Riesgos aceptados

No aplica.

### Deuda técnica

No aplica.

## 7. Criterios de aceptación

- **CA-01:** Una URL que resuelve el idioma predeterminado redirige con `302` y
  `Cache-Control: private, no-store`; una normalización de barra final conserva
  `301`.
- **CA-02:** La ruta de salud configurada responde mediante el callback de
  Laravel y no invoca el catch-all de Pages.
- **CA-03:** Activar un prefijo corto no general cuando existe un general regional
  activo de su familia falla sin alterar idiomas ni alias.
- **CA-04:** La documentación de desarrolladores y roadmaps describe la
  precedencia y redirecciones realmente implementadas.
- **CA-05:** Promover una variante regional no puede reemplazar el idioma general
  de un prefijo corto activo de su familia.
- **CA-06:** `Accept-Language` solo negocia idiomas base ISO 639-1 admitidos y
  con calidad positiva, sin truncar etiquetas no soportadas.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Caché de un predeterminado obsoleto | HTTP | Estado, destino y cabecera | Rutas públicas |
| CA-02 | Degradación de salud operativa | HTTP | Callback de Laravel responde | Operación |
| CA-03 | Alias de familia inconsistente | Integración | Activación rechazada y estado intacto | Administración de idiomas |
| CA-04 | Contrato documental erróneo | Documentación | Referencias y comportamiento alineados | Roadmaps y guías |
| CA-05 | Alias corto que sirve otro idioma | Integración | Promoción rechazada y general intacto | Administración de idiomas |
| CA-06 | Selección de idioma no aceptado o erróneo | HTTP | Redirección al idioma permitido o fallback | Rutas públicas |

## 9. Plan de implementación

1. Crear pruebas rojas para los dos tipos de redirección y la ruta de salud;
   restaurar los contratos HTTP hasta CA-01 y CA-02.
2. Crear prueba roja para la activación de prefijo corto; reforzar el servicio de
   idioma hasta CA-03.
3. Actualizar documentación, ejecutar quality gates y cerrar la Spec hasta CA-04.
4. Añadir regresiones de promoción de idioma general y negociación HTTP hasta
   CA-05 y CA-06.

## 10. Decisiones abiertas

No aplica.
