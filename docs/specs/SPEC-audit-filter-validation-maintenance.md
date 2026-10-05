# SPEC: Mantenimiento de validación de filtros de auditoría

- **Estado:** Completada
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

El proyecto no dispone de un catálogo de mensajes de validación para su locale
predeterminado `en`. Por ello, Laravel resuelve el error de la regla `in` como la
clave técnica `validation.in`, que no es una explicación útil para el
superadministrador. El locale `es_ES` del backoffice se utiliza para probar la
presentación multiidioma y no altera el locale de validación de Laravel.

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
muestra los errores de filtro con mensajes legibles en una alerta accesible; el
roadmap distingue la fundación ya implementada de las capacidades de auditoría
que siguen pendientes.

## 3. Alcance

- Mostrar en la pantalla de auditoría los errores de validación de filtros con
  semántica accesible para su anuncio como alerta, conservando la redirección y
  la bolsa de errores predeterminadas de Laravel.
- Conservar los valores de filtro enviados mediante el mecanismo `old()` de
  Laravel al volver desde un error de validación.
- Registrar el mensaje de la regla de validación `in` para el locale
  predeterminado `en` mediante el catálogo de Laravel, para que la alerta no
  muestre `validation.in`.
- Añadir una prueba HTTP de regresión que compruebe la presencia de la alerta y
  del mensaje de validación ante un filtro inválido.
- Actualizar el estado narrativo de la auditoría administrativa en el roadmap de
  calidad, sin presentar como terminadas capacidades fuera de la fundación.

### Fuera de alcance

- Cambiar las reglas de validación, filtros disponibles, paginación, datos
  auditados, retención o autorización de `GET /admin/audit`.
- Añadir traducciones de interfaz ajenas al mensaje Laravel de la regla `in`, API
  JSON, Vue, PageBuilder, auditoría de intentos denegados o mutaciones
  administrativas nuevas.
- Cambiar el locale de Laravel según la presentación del backoffice o añadir
  catálogos de validación para locales de prueba, incluido `es_ES`.
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
- El catálogo Laravel del locale predeterminado `en` debe resolver la regla `in` con un
  mensaje legible que incluya el atributo validado; la alerta no puede mostrar la
  clave técnica `validation.in`.
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
- La adición al catálogo Laravel `en` no modifica los catálogos de UI del CMS ni
  introduce contenido editorial traducible; el locale de presentación
  multiidioma permanece independiente.
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
  pantalla; debe enviar también `entity_type=page` y comprobar que ese valor se
  restaura en el formulario mediante `old()`.
- La prueba HTTP debe comprobar que la alerta muestra el mensaje legible de la
  regla `in` y no la clave `validation.in`.
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
  validación correspondiente, legible y distinto de `validation.in`.
- **CA-02:** La prueba HTTP establece `/admin/audit` como URL previa, verifica la
  redirección, CA-01 y que el error de `operation` está asociado a la validación
  del filtro; al enviar también `entity_type=page`, comprueba que el formulario
  restaurado contiene ese valor y que la alerta no contiene `validation.in`.
- **CA-03:** El roadmap de calidad describe la fundación de auditoría como
  implementada para Pages, Menus y publicación, y conserva como diferidas bajo
  `LOC-04` la cobertura de idiomas, overrides e intentos denegados.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Error previsible invisible, técnico o redirección no determinista | HTTP | Redirección a auditoría, `role="alert"`, lista y mensaje legible mostrado | Catálogo Laravel de validación `en` |
| CA-02 | Regresión de la comunicación estándar del validador o pérdida de filtros enviados | HTTP | URL previa, error de `operation` en sesión, mensaje sin `validation.in` y `entity_type=page` restaurado | No aplica: prueba interna de regresión |
| CA-03 | Roadmap que anuncia una capacidad ya entregada | Documentación | Fundación y diferidos de LOC-04 alineados | `docs/architecture/quality-roadmap.md` |

## 9. Plan de implementación

1. Ampliar la prueba HTTP de filtro inválido con URL previa hasta comprobar la
   redirección, la alerta, la lista, el mensaje de validación y la restauración
   de `entity_type=page`; registrar el mensaje Laravel de `in` y adaptar la vista
   Blade mediante la bolsa de errores y `old()` para satisfacer CA-01 y CA-02.
2. Corregir el párrafo desfasado del roadmap hasta CA-03 y ejecutar las
   validaciones configuradas.

## 10. Decisiones abiertas

No aplica.
