# SPEC: Detalle de errores de diagnóstico

- **Estado:** Completada
- **Perfil:** feature
- **Origen de la planificación:** Petición del responsable para poder localizar
  errores del sistema durante las pruebas, aprobada con `/build aplica`.
- **Spec relacionada:** [SPEC-minimal-admin-panel](SPEC-minimal-admin-panel.md)
  (completada).

## 1. Objetivo o problema

Permitir que un operador autorizado vea el detalle de excepciones 5XX en
entornos de prueba sin habilitar el modo debug global, y que el panel recuerde de
forma permanente que ese modo está activo. El objetivo es diagnosticar fallos
como un `503` de configuración sin exponer trazas en producción.

## 2. Contexto y evidencia

La aplicación responde `503` sin detalle cuando la resolución de presentaciones
falla, por ejemplo porque el manifiesto de un template cambió y no se
re-sincronizó. Igualmente, `EnsureSecureTransport` fuerza `app.debug=false` fuera
de `local` y `testing`, de modo que `APP_DEBUG` no basta para diagnosticar. No
existe una variable dedicada ni una superficie de diagnóstico, y la
documentación de despliegue no indicaba re-sincronizar templates tras
modificarlos.

## 3. Alcance

- Añadir la variable `CMS_ERROR_DETAILS` (booleana, por defecto `false`) y la
  configuración `diagnostics.error_details` que la representa.
- Cuando está activa y el entorno no es `production`, las respuestas 5XX se
  renderizan con una vista de diagnóstico propia que muestra estado, clase,
  mensaje, `archivo:línea` y traza de la excepción, incluida la cadena de
  excepciones previas.
- La vista se aplica a cualquier petición, para poder diagnosticar el login y
  fallos anteriores a la autenticación.
- El panel de administración muestra un aviso visible y permanente mientras el
  modo esté activo, mediante la clave de UI `base::admin.diagnostics.warning`.
- En `production` la variable se ignora: la aplicación renderiza el error
  genérico y registra una advertencia como máximo una vez por hora.
- Documentar la variable y el modo diagnóstico, y añadir la re-sincronización
  `php artisan cms:template:sync` a las instrucciones de instalación y
  despliegue.

### Fuera de alcance

- Habilitar el detalle de errores en `production`.
- Reporte remoto de errores (Sentry u otro), agrupación o alertas.
- Redacción automática de datos sensibles en mensajes o trazas.
- Cambiar el comportamiento de errores 4XX o de validación.

### Alcance diferido

- Habilitar el modo diagnóstico en `production` con garantías adicionales
  (autorización explícita, caducidad o canal restringido) se retomará mediante una
  Spec propia cuando se necesite para el triaje de un lanzamiento. Condición:
  definir quién puede verlo y cómo evitar la exposición pública.
- La integración con un servicio de observabilidad se mantiene fuera de alcance
  según el roadmap de calidad.

## 4. *Clash check*

- SDD inicial: no altera Blade, Vue ni el renderizado público esencial; solo
  sustituye la página de error 5XX en entornos no productivos. No hay conflicto.
- ADR-0001: no introduce Vue ni cambia la base Blade. No hay conflicto.
- ADR-0003: no afecta a persistencia ni a los motores soportados. No hay
  conflicto.
- ADR-0006: el render de diagnóstico no pasa por el resolvedor de presentaciones;
  es una superficie de sistema de la aplicación y por eso reside en
  `resources/views/errors`, no en Base. Así sigue disponible incluso cuando la
  resolución de templates falla. No hay conflicto, se concreta el caso de error.
- `SPEC-minimal-admin-panel`, completada: el aviso del panel extiende su shell sin
  añadir capacidades mutables ni de gestión; el dashboard sigue siendo de solo
  lectura.
- Roadmap de calidad: la observabilidad con Sentry sigue pendiente; este
  incremento no la sustituye ni declara disponibilidad.
- Código y pruebas: no existe render personalizado de errores; se añade uno
  compatible con el manejo actual de Laravel. La clave de UI amplía Base, que ya
  distribuye `system.admin.dashboard`.

## 5. Requisitos y bloques técnicos aplicables

### Configuración y dominio

- `CMS_ERROR_DETAILS` es booleana y por defecto `false`. Un valor válido se
  interpreta como booleano; ausente equivale a desactivada.
- `ErrorDetails::enabled()` es la única fuente de verdad y devuelve `true` solo
  cuando la configuración está activa y el entorno actual no es `production`.
- En `production`, con la variable activa, la aplicación no muestra detalle y
  registra una advertencia como máximo una vez por hora para no inundar los logs.
- La detección de `production` no distingue mayúsculas y minúsculas: una variante
  como `Production` o `PRODUCTION` también desactiva el modo.

### Render de errores

- Solo se renderiza la vista de diagnóstico para respuestas con estado `>= 500`.
  Un 4XX conserva el comportamiento por defecto.
- La vista muestra: estado HTTP, clase de la excepción, mensaje, `archivo:línea`
  y traza. Para cada excepción previa encadenada se muestra su clase y mensaje.
- La traza se construye desde `getTrace()` sin incluir argumentos de llamada.
- La vista no renderiza variables de entorno, `APP_KEY`, cookies, cabeceras,
  cuerpo de la petición ni credenciales.
- Un fallo al construir o renderizar la propia vista de diagnóstico no debe
  convertirse en un bucle; se propaga al manejador por defecto.

### Panel de administración

- Cuando `ErrorDetails::enabled()` es `true`, el dashboard muestra un aviso
  persistente con la clave `base::admin.diagnostics.warning`, sin enlaces ni
  acciones adicionales.
- Cuando está desactivado, el aviso no aparece.
- El aviso no se muestra en `production` porque allí el modo está desactivado.

### Datos, API, Vue, PageBuilder, asíncronía e integraciones

No aplicable: no se crean tablas, migraciones ni contratos de API. No se
introduce Vue, PageBuilder, colas ni integraciones externas.

### Seguridad y validación

- La superficie de detalle nunca se activa en `production` en este incremento.
- No se exponen secretos ni datos de la petición; la traza omite argumentos.
- El texto del aviso y de la vista es estático o proviene de la excepción, y se
  escapa con el escape por defecto de Blade.
- No se versionan credenciales ni valores reales; `.env.example` solo documenta el
  nombre y un valor ficticio seguro.

### Entrega y compatibilidad

- La variable ausente mantiene el comportamiento actual: error genérico.
- No cambia el estado de instalaciones existentes; no requiere migración.
- La documentación de instalación y despliegue incorpora la re-sincronización de
  templates como paso operativo tras modificar un manifiesto.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Probar que con `CMS_ERROR_DETAILS` activa y entorno no productivo un 5XX muestra
  la clase, el mensaje y la traza.
- Probar que con la variable desactivada o en `production` el 5XX es genérico y no
  expone el mensaje de la excepción.
- Probar que la vista de diagnóstico no contiene `APP_KEY` ni valores de entorno.
- Probar que el dashboard muestra el aviso cuando el modo está activo y no lo
  muestra cuando está desactivado.
- Probar la advertencia única por hora en `production` y que la detección de
  `production` ignora mayúsculas y minúsculas.
- Ejecutar las pruebas enfocadas antes de la suite afectada y el quality gate
  habitual (Pint, Pest, build de Vite y validación de manifiestos).

### Riesgos aceptados

- Mientras el modo está activo en un entorno de prueba, cualquier visitante puede
  ver mensajes y trazas. Se acepta porque el guard impide activarlo en
  `production` y el operador controla el entorno. Un mensaje de excepción podría
  contener datos de la operación; por ello no se activa en producción y la traza
  omite argumentos.

### Deuda técnica

No aplica. La habilitación en producción queda fuera de alcance con condición de
revisión explícita, no como comportamiento incompleto.

## 7. Criterios de aceptación

- **CA-01:** Con `CMS_ERROR_DETAILS=true` y un entorno distinto de `production`,
  una respuesta 5XX muestra estado, clase, mensaje, `archivo:línea` y traza de la
  excepción, incluida la cadena de excepciones previas.
- **CA-02:** Con `CMS_ERROR_DETAILS=false`, o con `production`, una respuesta 5XX
  es genérica y no contiene el mensaje ni la traza de la excepción.
- **CA-03:** La página de diagnóstico no expone `APP_KEY`, variables de entorno,
  cookies, cabeceras ni cuerpo de la petición, y la traza no incluye argumentos.
- **CA-04:** El dashboard muestra el aviso `base::admin.diagnostics.warning`
  cuando el modo está activo y no lo muestra cuando está desactivado.
- **CA-05:** En `production`, con la variable activa, se registra una advertencia
  como máximo una vez por hora y no se muestra el detalle.
- **CA-06:** La documentación de instalación y despliegue incluye la variable
  `CMS_ERROR_DETAILS` y la re-sincronización `php artisan cms:template:sync` tras
  modificar un manifiesto de template.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Imposibilidad de diagnosticar fallos | HTTP/integración | 5XX con detalle en entorno no productivo | Guía de diagnóstico |
| CA-02 | Fuga de detalle en producción | HTTP | 5XX genérico sin mensaje de excepción | Guía de despliegue seguro |
| CA-03 | Exposición de secretos o datos de petición | HTTP/seguridad | Sin `APP_KEY`, entorno, cookies ni argumentos | Guía de diagnóstico |
| CA-04 | Recordatorio ausente o indebido | HTTP/componente | Aviso presente solo con el modo activo | Guía de acceso al backoffice |
| CA-05 | Ruido en logs o exposición en producción | Integración | Advertencia única por hora e ignorado | Guía de despliegue seguro |
| CA-06 | Error operativo por manifiesto sin re-sincronizar | No aplica (documental) | Pasos de instalación y despliegue actualizados | Instalación y despliegue |

## 9. Plan de implementación

1. Crear pruebas rojas de la configuración, el render de detalle 5XX y la
   ausencia de exposición de secretos; implementar `config/diagnostics.php`,
   `ErrorDetails` y el render de excepciones hasta CA-01, CA-02 y CA-03.
2. Crear pruebas rojas del aviso en el panel; añadir la clave de UI, el paso de la
   variable a las vistas y el recordatorio hasta CA-04.
3. Crear pruebas rojas de la advertencia única en `production`; implementar el
   proveedor de diagnóstico hasta CA-05.
4. Actualizar la documentación de diagnóstico, instalación y despliegue hasta
   CA-06 y ejecutar el quality gate antes de completar la Spec.

## 10. Decisiones abiertas

No aplica. La habilitación en producción queda diferida con condición de revisión
explícita.
