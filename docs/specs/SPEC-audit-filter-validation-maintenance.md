# SPEC: Mantenimiento de validación de filtros de auditoría

- **Estado:** Borrador
- **Perfil:** maintenance
- **Origen de la planificación:** Hallazgos de CodeRabbit tras completar la
  fundación de auditoría administrativa.
- **Spec relacionada:** [SPEC-admin-audit-foundation](SPEC-admin-audit-foundation.md),
  completada.

## 1. Objetivo o problema

Restaurar la comunicación de los errores previsibles de los filtros de la
consulta de auditoría administrativa y alinear el roadmap de calidad con la
fundación de auditoría ya implementada.

## 2. Contexto y evidencia

La pantalla `GET /admin/audit` valida `operation`, `entity_type`, `from`,
`until` y `per_page` en `AuditController`. Ante una entrada no válida Laravel
redirecciona con errores de validación; la prueba existente verifica el error en
sesión para `operation=invalid`. Sin embargo,
`Templates/Base/Resources/views/system/admin/audit.blade.php` no renderiza la
bolsa de errores, por lo que el superadministrador no recibe una explicación
visible ni accesible.

**Fuente del comportamiento esperado:**
`SPEC-admin-audit-foundation.md`, CA-05 y sus requisitos de filtros validados;
la prueba HTTP existente que verifica el error de `operation`; y el flujo de
validación de Laravel, que redirige a la URL previa y entrega la bolsa de errores
predeterminada a la solicitud siguiente.

El apartado "Auditoría administrativa durable" de
`docs/architecture/quality-roadmap.md` todavía afirma que la iniciativa se
abordará en una Spec futura. La
`SPEC-admin-audit-foundation.md` está completada y la funcionalidad base ya se
ha entregado.

**Pasos de reproducción:** como superadministrador, enviar desde el formulario
de `/admin/audit` un valor de `operation` no admitido.

**Resultado actual:** la respuesta redirigida conserva el error de validación
en sesión, pero la pantalla no lo muestra; el roadmap describe la fundación
entregada como trabajo futuro.

**Resultado esperado:** Laravel redirige de vuelta a `/admin/audit` y la pantalla
muestra los errores de filtro en una alerta accesible; el roadmap distingue la
fundación ya implementada de las capacidades de auditoría que siguen pendientes.

## 3. Alcance

- Mostrar en la pantalla de auditoría los errores de validación de filtros con
  semántica accesible para su anuncio como alerta, conservando la redirección y
  la bolsa de errores predeterminadas de Laravel.
- Conservar los valores de filtro enviados mediante el mecanismo `old()` de
  Laravel al volver desde un error de validación.
- Añadir una prueba HTTP de regresión que compruebe la presencia de la alerta y
  del mensaje de validación ante un filtro inválido.
- Actualizar el estado narrativo de la auditoría administrativa en el roadmap de
  calidad, sin presentar como terminadas capacidades fuera de la fundación.

### Fuera de alcance

- Cambiar las reglas de validación, filtros disponibles, paginación, datos
  auditados, retención o autorización de `GET /admin/audit`.
- Añadir traducciones, API JSON, Vue, PageBuilder, auditoría de intentos
  denegados o mutaciones administrativas nuevas.
- Modificar el contenido histórico de los eventos de auditoría.

### Alcance diferido

- La auditoría de idiomas, overrides e intentos denegados sigue el alcance y las
  condiciones ya definidos por `LOC-04`; esta reparación no los anticipa.

## 4. *Clash check*

- SDD inicial y ADR-0001: se mantiene el backoffice Blade y no se altera la
  arquitectura modular ni el renderizado. No hay conflicto.
- `SPEC-admin-audit-foundation`, completada: su CA-05 exige filtros validados y
  una consulta autorizada; hacer visible el error restaura ese comportamiento sin
  cambiar el contrato, datos ni autorización. No hay conflicto.
- Código y prueba de `AuditController`: las reglas vigentes ya producen los
  errores y la prueba verifica la bolsa de sesión; la reparación solo completa
  su comunicación en la vista. No hay conflicto.
- `quality-roadmap.md`: se corrige una previsión superada por la Spec completada
  sin declarar implementadas las extensiones diferidas. No hay conflicto.

## 5. Requisitos y bloques técnicos aplicables

### Interfaz Blade y validación

- Cuando Laravel entregue errores de validación de los filtros en su bolsa
  predeterminada, la pantalla debe exponerlos antes del formulario dentro de un
  elemento con `role="alert"` y una lista semántica de mensajes.
- La alerta debe mostrar todos los mensajes suministrados por el validador con el
  escape Blade por defecto, sin interpolar HTML no confiable.
- Los campos del formulario deben preferir los valores de `old()` y usar los
  filtros validados existentes solo cuando no haya entrada previa.
- La reparación no modifica el controlador: conserva `Request::validate()` y la
  redirección estándar de Laravel a la URL previa.
- Las solicitudes con filtros válidos conservan la consulta, filtrado y
  paginación existentes.

### Seguridad, datos y compatibilidad

- La corrección conserva la autorización exclusiva de superadministrador y el
  escape Blade por defecto.
- No cambian las reglas, el modelo de datos ni el contrato público; tampoco hay
  migración, rutas, permisos, secretos ni datos persistidos nuevos.
- Vue 3, PageBuilder, módulos, asincronía e integraciones externas no aplican:
  la reparación es una vista Blade server-side y documentación de estado.

### Documentación

- El párrafo de `quality-roadmap.md` que prevé una Spec futura debe sustituirse
  por el estado real: la fundación de auditoría administrativa durable está
  implementada para Pages, Menus y publicación.
- El roadmap debe conservar explícitamente como diferidas la cobertura de
  idiomas, overrides e intentos denegados bajo `LOC-04`; no debe declararlas
  implementadas por esta reparación.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Prueba HTTP autenticada como superadministrador para un filtro inválido que
  establezca `/admin/audit` como URL previa y compruebe la redirección de Laravel,
  `role="alert"`, la lista de mensajes y el mensaje de validación al volver a la
  pantalla.
- Mantener la comprobación existente de que el error pertenece a `operation`.
- Ejecutar la prueba enfocada, la suite afectada y el formateo configurado.

### Riesgos aceptados

No aplica: la reparación usa los mensajes y el escape de Laravel ya vigentes y
no amplía la superficie de entrada ni los datos expuestos.

### Deuda técnica

No aplica.

## 7. Criterios de aceptación

- **CA-01:** Al enviar desde `/admin/audit` un filtro inválido, el
  superadministrador vuelve mediante la redirección estándar de Laravel a esa
  pantalla y ve una lista de errores con `role="alert"` y el mensaje de
  validación correspondiente.
- **CA-02:** La prueba HTTP establece `/admin/audit` como URL previa, verifica la
  redirección, CA-01 y que el error de `operation` está asociado a la validación
  del filtro.
- **CA-03:** El roadmap de calidad describe la fundación de auditoría como
  implementada para Pages, Menus y publicación, y conserva como diferidas bajo
  `LOC-04` la cobertura de idiomas, overrides e intentos denegados.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Error previsible invisible o redirección no determinista | HTTP | Redirección a auditoría, `role="alert"`, lista y mensaje mostrado | No aplica: la interfaz conserva su contrato |
| CA-02 | Regresión de la comunicación estándar del validador | HTTP | URL previa, error de `operation` en sesión y contenido mostrado | No aplica: prueba interna de regresión |
| CA-03 | Roadmap que anuncia una capacidad ya entregada | Documentación | Fundación y diferidos de LOC-04 alineados | `docs/architecture/quality-roadmap.md` |

## 9. Plan de implementación

1. Ampliar la prueba HTTP de filtro inválido con URL previa hasta comprobar la
   redirección, la alerta, la lista y el mensaje de validación; adaptar la vista
   Blade mediante la bolsa de errores y `old()` de Laravel para satisfacer CA-01
   y CA-02.
2. Corregir el párrafo desfasado del roadmap hasta CA-03 y ejecutar las
   validaciones configuradas.

## 10. Decisiones abiertas

No aplica.
