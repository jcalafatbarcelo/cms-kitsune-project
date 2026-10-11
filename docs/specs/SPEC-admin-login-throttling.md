# SPEC: Mantenimiento simple del límite de inicio de sesión

- **Estado:** Revisión
- **Perfil:** feature
- **Origen de la planificación:** Simplificación aprobada de la protección de
  acceso administrativo para mantener compatibilidad con hosting compartido.
- **Spec relacionada:** [SPEC-minimal-admin-panel](SPEC-minimal-admin-panel.md),
  completada; sustituye el alcance rechazado de
  [SPEC-admin-login-abuse-protection](SPEC-admin-login-abuse-protection.md).

## 1. Objetivo o problema

Restaurar el contrato del panel mínimo: limitar cinco autenticaciones fallidas
por minuto para cada combinación de email normalizado e IP efectiva, sin
penalizar solicitudes con formato inválido ni accesos correctos.

## 2. Contexto y evidencia

`LoginRateLimit` incrementa hoy la cuota antes de validar las credenciales y
`SessionController` lo invoca antes de su validador. Por tanto, una solicitud
malformada o un acceso correcto consume el presupuesto que
`SPEC-minimal-admin-panel.md`, CA-03, reserva para intentos fallidos.

El incremento conserva el contrato HTTP del panel mínimo e incorpora un comando
Artisan operativo acotado para verificar el backend de locks durante el
despliegue. Por ello se clasifica como `feature`.

**Fuente del comportamiento esperado:** `SPEC-minimal-admin-panel.md`, CA-03,
la guía de acceso al backoffice y sus pruebas HTTP.

**Pasos de reproducción:** enviar una solicitud de login con formato inválido o
con credenciales correctas y repetir después cinco autenticaciones fallidas.

**Resultado actual:** las solicitudes iniciales consumen parte de la cuota y el
siguiente fallo puede recibir `429` antes de alcanzar cinco fallos procesados.

**Resultado esperado:** solo cinco autenticaciones fallidas procesadas consumen
la cuota; una sexta recibe `429` genérico con `Retry-After` y un acceso correcto
no consume ni restablece los fallos previos.

## 3. Alcance

- Conservar Laravel `RateLimiter` y el límite fijo de cinco fallos en 60 segundos.
- Comprobar formato de email y contraseña antes de consultar o incrementar la
  cuota.
- Conservar el lock de caché ya usado para que la comprobación de cuota, una
  autenticación procesada y el incremento por su fallo sean atómicos para una
  clave de cuota.
- Incrementar la cuota solo tras una autenticación procesada que falle, incluida
  cuenta inexistente o usuario sin acceso administrativo.
- Usar como clave email normalizado conforme a la identidad autenticable e IP
  efectiva proporcionada por Laravel.
- Devolver `429` genérico y `Retry-After` cuando la cuota ya esté agotada.
- Rechazar `0.0.0.0/0` y `::/0` en `TRUSTED_PROXIES`, que contradicen la guía
  operativa existente al confiar en cualquier cliente como proxy.
- Añadir una comprobación Artisan de despliegue para verificar que dos procesos
  compiten por el lock de caché del login configurado, sin autenticar usuarios ni
  conservar datos.

### Fuera de alcance

- MFA, TOTP, WebAuthn, correo, OAuth, identidad federada, JWT, CAPTCHA o MFA por
  email.
- Tablas, migraciones, cron, scheduler, workers, colas, alertas, Redis o servicios
  externos de rate limiting.
- Cuotas por cuenta o por IP independiente, bloqueos permanentes, reputación de
  IP o cambios en autorización, rutas, sesiones y payloads.

### Alcance diferido

- MFA opcional mediante WebAuthn o TOTP se evaluará cuando existan gestión de
  usuarios, recuperación de acceso y necesidades verificables de backoffice.
- La identidad federada se tratará como módulo externo y queda fuera del MVP.
- La entrega de correo se evaluará separadamente si el producto requiere
  verificación, recuperación o MFA por email.

## 4. *Clash check*

- SDD inicial y ADR-0001: conserva Laravel y Blade; no añade Vue, APIs ni capas
  de infraestructura. No hay conflicto.
- `SPEC-minimal-admin-panel`, CA-03: concreta su límite ya definido y restaura la
  contabilidad exclusiva de fallos. No hay conflicto.
- `TrustConfiguredProxies`: reutiliza la IP efectiva existente; no cambia la
  política de proxies ni presume infraestructura adicional. No hay conflicto.
- Documentación de acceso: ya comunica cinco fallos por minuto; se mantiene ese
  contrato. No hay conflicto.

## 5. Requisitos y bloques técnicos aplicables

### Autenticación y seguridad

- La validación sintáctica y CSRF se resuelven antes de consultar el limitador.
- La clave conserva la normalización y equivalencia de email usada por el
  proveedor de autenticación para evitar presupuestos distintos por alias de
  collation.
- El lock de caché existente cubre consulta de cuota, autenticación procesada e
  incremento del fallo; no se permite que peticiones paralelas superen cinco
  verificaciones de credenciales para una misma clave activa.
- Tras cada fallo autenticable se registra un intento mediante `RateLimiter`; un
  acceso correcto no incrementa ni limpia la cuota existente.
- Cuando ya existen cinco fallos activos, no se intenta autenticar y se responde
  con el estado `429`, la cabecera `Retry-After` de Laravel y el mensaje genérico
  de throttling existente.
- `TRUSTED_PROXIES` debe contener exclusivamente direcciones o rangos de proxies
  concretos; se rechazan los rangos universales IPv4 e IPv6 para que una conexión
  directa no pueda falsear la IP efectiva con cabeceras reenviadas.

### Datos, API, Vue, PageBuilder, colas e integraciones

No aplicable: no se añaden persistencia, rutas, APIs, Vue, PageBuilder, tareas
asíncronas, proveedores externos ni configuración nueva.

No se añaden reglas de negocio, modelo de datos, rutas ni payloads HTTP. Este
incremento corrige la contabilidad observable de la cuota para restaurar el
contrato de fallos ya prescrito por el panel mínimo y la documentación operativa.
El único contrato nuevo es el comando Artisan de verificación descrito en
compatibilidad y despliegue.

### Compatibilidad y despliegue

- No añade configuración de aplicación, base de datos, caché, proxy ni correo.
- En producción, el proceso PHP que atiende el login debe tener un
  `max_execution_time` finito; el lock de admisión se concede por ese límite más
  cinco segundos, con un mínimo de 60 segundos. La aplicación rechaza procesar
  el login si el límite es ilimitado, sin afectar a comandos Artisan.
- El límite usa el store de caché Laravel ya configurado. En producción este
  store debe compartir locks atómicos entre los procesos PHP que sirven el
  backoffice; `array`, `null` y `session` solo son admisibles en local o testing.
- La aplicación valida esa restricción de driver en producción. La topología de
  un único host o de almacenamiento compartido es un requisito de despliegue;
  este incremento no añade ni administra infraestructura distribuida.
- La comprobación Artisan admite un modo que retiene temporalmente un lock de
  verificación y otro que intenta adquirirlo. Durante el despliegue, dos procesos
  independientes deben producir respectivamente éxito de retención y rechazo de
  adquisición; cualquier otro resultado bloquea la puesta en producción.
- El contrato del comando es `admin:verify-login-rate-limit-lock` con
  `--hold=<segundos>` opcional entre 0 y 30. Sin espera, sale con código 0 si
  adquiere y libera el lock, y con código 1 si ya está retenido; con espera,
  informa que lo retiene y lo libera al terminar. El nombre de lock es fijo,
  temporal y no contiene email, IP ni credenciales.
- Esta comprobación solo acredita que el backend configurado comparte locks entre
  dos procesos. No sustituye la prueba de integración concurrente del flujo de
  login, que acredita el máximo de cinco verificaciones de credenciales.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Pruebas HTTP para formato inválido, cinco fallos, sexto intento `429`, acceso
  correcto y usuario sin privilegios.
- Prueba de regresión que demuestre que un acceso correcto no consume cuota y que
  una variante de email equivalente no crea otro presupuesto cuando el motor la
  considera equivalente.
- Prueba de configuración que rechace rangos universales en `TRUSTED_PROXIES` y
  prueba HTTP de cabeceras reenviadas según el contrato existente.
- Prueba de integración concurrente que demuestre que, para una clave activa, no
  se procesan más de cinco verificaciones de credenciales antes del `429`.
- Prueba de configuración que rechace en producción los drivers de caché no
  compartidos para locks y mantenga los admitidos en local y testing.
- Prueba de la comprobación Artisan con dos procesos independientes y guía de
  despliegue que describa su ejecución antes de abrir el backoffice.
- Ejecutar prueba enfocada, suite Admin, Pint y build configurados.

### Riesgos aceptados

- Usuarios que compartan email e IP efectiva comparten una cuota temporal. Se
  acepta por tratarse de un único superadministrador en el MVP y por no imponer
  infraestructura adicional.
- El límite simple no sustituye MFA ni defensa distribuida ante un atacante con
  muchos orígenes; esas capacidades quedan diferidas.

### Deuda técnica

No aplica: la limitación deliberada se registra como alcance diferido, no como
deuda de implementación.

## 7. Criterios de aceptación

- **CA-01:** Cinco autenticaciones fallidas procesadas para una combinación de
  email e IP efectiva son admitidas; la siguiente recibe `429` genérico con
  `Retry-After`.
- **CA-02:** Errores de formato y accesos correctos no consumen ni reinician la
  cuota de fallos.
- **CA-03:** Cuenta inexistente y usuario sin privilegios consumen la cuota como
  fallos autenticables, sin revelar su condición.
- **CA-04:** La clave de cuota conserva la equivalencia de email del autenticador
  y la política existente de proxies confiables; los rangos universales de
  `TRUSTED_PROXIES` se rechazan.
- **CA-05:** Las solicitudes concurrentes de una misma clave no procesan más de
  cinco verificaciones de credenciales antes de responder `429`, con un store de
  caché de producción admisible y correctamente desplegado. Antes de abrir el
  backoffice, la comprobación Artisan de dos procesos evidencia adicionalmente
  que el backend comparte locks.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Fuerza bruta básica | HTTP | Quinto fallo admitido y sexto `429` | Guía de acceso ya alineada |
| CA-02 | Denegación por accesos válidos o formato inválido | HTTP | Presupuesto intacto | No aplica: contrato ya documentado |
| CA-03 | Enumeración o evasión por tipo de cuenta | HTTP | Mismo resultado y consumo | Guía de acceso ya alineada |
| CA-04 | Evasión por alias o cabecera falseada | Integración/HTTP | Clave compartida y rango universal rechazado | Despliegue seguro existente |
| CA-05 | Superar la cuota mediante concurrencia | Integración/configuración/Artisan | Prueba de login con máximo de cinco verificaciones, drivers inseguros rechazados y preflight de lock en dos procesos | Despliegue seguro |

## 9. Plan de implementación

1. Crear pruebas rojas para CA-01 a CA-03; reordenar validación, autenticación y
   `RateLimiter` dentro del lock existente hasta que solo los fallos procesados
   consuman cuota.
2. Añadir regresiones de collation y proxy, incluida la prohibición de rangos
   universales, hasta CA-04.
3. Mantener o adaptar la prueba de concurrencia y validar los drivers de caché
   de producción para CA-05; implementar y documentar la comprobación Artisan
   de dos procesos. Ejecutar las validaciones configuradas y revisar que no se
   añada infraestructura.

## 10. Decisiones abiertas

No aplica.
