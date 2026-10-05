# SPEC: Protección frente a abuso en el acceso al backoffice

- **Estado:** Propuesta
- **Perfil:** feature
- **Origen de la planificación:** Hallazgo L2 de la auditoría de seguridad del
  incremento de backoffice; endurecimiento acordado (opción D, defensa en
  profundidad).
- **Spec relacionada:** [SPEC-minimal-admin-panel](SPEC-minimal-admin-panel.md)
  (completada).

## 1. Objetivo o problema

Endurecer el límite de intentos de acceso al backoffice con controles
independientes de la IP del cliente, de modo que un proxy mal saneado, cabeceras
`X-Forwarded-*` falseadas o un almacén de caché sin bloqueos no permitan evadir el
límite. No se introducen bloqueos permanentes ni se revela la existencia de
cuentas.

## 2. Contexto y evidencia

Hoy el límite se calcula por email normalizado e IP efectiva
(`LoginRateLimit`). La IP efectiva depende de que el proxy sanea
`X-Forwarded-For` y de un `CACHE_STORE` con locks atómicos entre procesos. El
limitador actual consume cuota antes de autenticar, por lo que también penaliza
accesos correctos. La auditoría lo señaló como `needs_validation`.

## 3. Alcance

- Mantener el límite actual por email+IP como primer control.
- Añadir un límite por cuenta (email normalizado, sin IP) para acotar ataques
  distribuidos que rotan IP.
- Añadir un límite por IP de par (`REMOTE_ADDR`, la conexión directa) como red de
  seguridad adicional frente a la rotación de emails desde un mismo origen.
- Contabilizar únicamente los intentos fallidos, no los accesos correctos.
- Aplicar respuestas `429` genéricas, sin distinguir cuenta, privilegio ni causa.
- Ventanas cortas y sin bloqueo permanente: la cuenta vuelve a admitir intentos
  al expirar la ventana.
- Usar un almacén dedicado y único para los contadores y locks del login.
- Validar al arrancar en producción la configuración local del almacén y de
  `TRUSTED_PROXIES`; documentar el saneamiento real del proxy como requisito de
  despliegue verificable fuera de la aplicación.

### Fuera de alcance

- CAPTCHA, MFA, verificación por correo, notificaciones o alertas.
- Auditoría durable de intentos denegados (corresponde a LOC-03).
- Bloqueo permanente o desactivación de cuentas.
- Cambios en autenticación, autorización, rutas o payload del backoffice. La
  semántica de throttling y su configuración cambian conforme a esta Spec.

### Alcance diferido

- Correlación con reputación de IP, listas de bloqueo externas o limitación por
  dispositivo se retomarán solo con necesidad demostrada y Spec propia.
- La observabilidad de intentos denegados se coordina con el roadmap de calidad.

## 4. *Clash check*

- `SPEC-minimal-admin-panel` (completada): refuerza CA-03 sin cambiar mensajes,
  rutas, sesión ni autorización; las respuestas `429` y la política de cuota se
  concretan en este incremento. No hay conflicto.
- ADR-0003: no añade migraciones de dominio; el store `database` reutiliza las
  tablas de caché existentes y la atomicidad se prueba con SQLite, MySQL y
  MariaDB. No hay conflicto.
- ADR-0001/0006: no afecta a Blade/Vue ni a la resolución de presentaciones.
- Roadmap de calidad/localización: no adelanta auditoría durable ni LOC-04. No
  hay conflicto.

## 5. Requisitos y bloques técnicos aplicables

### Dominio, configuración y límites

- El límite por email+IP usa por defecto 5 intentos fallidos en 60 segundos; el
  límite por cuenta usa 10 en 900 segundos; y el límite por IP de par usa 30 en
  300 segundos.
- Los valores se configuran mediante `LOGIN_RATE_LIMIT_EMAIL_IP_MAX_ATTEMPTS`,
  `LOGIN_RATE_LIMIT_EMAIL_IP_DECAY_SECONDS`,
  `LOGIN_RATE_LIMIT_ACCOUNT_MAX_ATTEMPTS`,
  `LOGIN_RATE_LIMIT_ACCOUNT_DECAY_SECONDS`,
  `LOGIN_RATE_LIMIT_PEER_IP_MAX_ATTEMPTS` y
  `LOGIN_RATE_LIMIT_PEER_IP_DECAY_SECONDS`.
- Cada máximo admite enteros entre 1 y 100 y cada ventana enteros entre 1 y 3600
  segundos. Los valores omitidos usan sus defaults; en producción, un valor
  presente fuera de rango impide el arranque antes de aceptar tráfico.
- Solo consume presupuesto una autenticación procesada que falla: cuenta
  inexistente, contraseña errónea o usuario sin privilegio de superadministrador.
  Errores de formato y CSRF no consumen cuota.
- Un acceso correcto no consume presupuesto. Una cuota agotada rechaza cualquier
  nuevo intento, incluido uno con credenciales correctas, hasta que permita la
  admisión de nuevo.
- Las ventanas empiezan con el primer fallo y expiran sin bloqueo permanente.

### Almacenamiento y atomicidad

- `LOGIN_RATE_LIMIT_STORE` selecciona el único store de los contadores y locks;
  su default es `database` y no depende de `CACHE_STORE` ni de `cache.limiter`.
- En producción solo se admiten stores `database` o `redis`. Una configuración
  inválida, inexistente o sin locks atómicos impide el arranque. `array`, `file`,
  `null`, `session`, `storage`, `octane` y `failover` no son admisibles.
- El despliegue debe conectar esos stores a una infraestructura compartida entre
  procesos; Laravel no puede demostrar esa topología solo desde su configuración
  local y se comprueba como requisito operativo.
- Local y testing pueden usar una configuración explícita adecuada a su prueba;
  una prueba de concurrencia no puede demostrar atomicidad distribuida con un
  store `array`.
- Antes de autenticar, la admisión adquiere los locks de cuenta, email+IP e IP de
  par en ese orden canónico y rechaza con `429` si cualquiera está agotado.
- Tras un fallo de autenticación, y bajo los mismos locks, se vuelven a comprobar
  las tres cuotas; se incrementan las tres o ninguna. Si una se agota durante la
  operación, la respuesta es `429` y no hay incremento parcial.
- Un timeout, error de lock, conexión, contador o store durante una solicitud no
  permite la autenticación: responde `429` genérico con `Retry-After: 60` y no
  registra secretos, credenciales, hashes ni identificadores de sesión.
- No se crean tablas ni migraciones de dominio; el store `database` reutiliza las
  tablas de caché y locks existentes.

### Seguridad y validación

- Todas las respuestas de throttling son `429` con mensaje y cabecera
  `Retry-After` genéricos, sin revelar cuenta, privilegio ni existencia. Cuando
  hay cuotas agotadas, la cabecera devuelve el mayor tiempo restante de ellas.
- No se registran credenciales, hashes ni identificadores de sesión.
- El límite por cuenta no permite un bloqueo indefinido explotable por terceros.
- Los proxies solo aportan IP efectiva si están declarados en `TRUSTED_PROXIES`.
  La IP de par es siempre `REMOTE_ADDR` y no cambia con `X-Forwarded-For`, incluso
  desde un proxy confiable; se acepta la limitación temporal compartida detrás de
  un balanceador.
- La aplicación valida al bootstrap la sintaxis y las redes explícitas de
  `TRUSTED_PROXIES`, pero el proxy debe sanear realmente las cabeceras en la
  infraestructura. Esta última garantía se comprueba en el despliegue, no desde
  Laravel.

### API, Vue, PageBuilder e integraciones

No aplicable: no se añaden rutas, contratos de API, Vue, PageBuilder, colas ni
integraciones externas.

### Entrega y compatibilidad

- Sin migraciones de dominio ni cambios de datos de negocio.
- La plantilla de entorno, instalación, guía de acceso y despliegue seguro
  documentan `LOGIN_RATE_LIMIT_STORE`, los seis límites, los stores admitidos y
  el requisito de saneamiento de proxies.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Probar que rotar IP (o `X-Forwarded-For` desde un proxy confiable) no permite
  superar el límite por cuenta.
- Probar que rotar email desde un mismo origen queda acotado por el límite de IP
  de par.
- Probar que los accesos correctos no consumen el presupuesto de intentos fallidos.
- Probar que solo las autenticaciones procesadas fallidas consumen cuota.
- Probar respuestas genéricas e indistinguibles y `Retry-After` igual al máximo
  tiempo restante de las cuotas agotadas.
- Probar la atomicidad con solicitudes concurrentes sobre cache `database` en
  SQLite, MySQL y MariaDB, y la ausencia de incrementos parciales.
- Probar el rechazo de arranque en producción para store o límites inválidos, y
  la respuesta fail-closed de 60 segundos ante fallo transitorio de runtime.
- Probar que `REMOTE_ADDR` no cambia al alterar `X-Forwarded-For` y que la IP
  efectiva solo procede de proxies configurados.

### Riesgos aceptados

- El límite por cuenta puede provocar un bloqueo temporal de la cuenta ante un
  ataque dirigido; se acepta con ventana corta y sin bloqueo permanente.

### Deuda técnica

No aplica.

## 7. Criterios de aceptación

- **CA-01:** Rotar la IP del cliente no permite superar un límite por cuenta
  configurable y comprobable.
- **CA-02:** Un mismo origen directo queda acotado aunque varíe el email.
- **CA-03:** Las respuestas de throttling no revelan cuenta, privilegio ni
  existencia.
- **CA-04:** Un acceso correcto no consume el presupuesto de intentos fallidos y
  la cuenta se recupera al expirar la ventana.
- **CA-05:** Sin locks atómicos no se permite la evasión; el comportamiento es
  fail-safe y verificable, tanto al arrancar producción como ante fallo de
  runtime.
- **CA-06:** La IP de par no puede ser manipulada mediante cabeceras y la IP
  efectiva exige un proxy configurado.
- **CA-07:** La configuración, los límites, los stores admitidos y los requisitos
  de despliegue quedan documentados.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Evasión por rotación de IP | Integración/concurrencia | Límite por cuenta respetado | Guía de acceso al backoffice |
| CA-02 | Ataque distribuido desde un origen | Integración | Límite por IP de par respetado | Guía de seguridad |
| CA-03 | Enumeración/causa expuesta | HTTP | `429` genérico | Guía de acceso al backoffice |
| CA-04 | Bloqueo de cuenta legítima | HTTP | Acceso correcto no consume presupuesto | Guía de acceso al backoffice |
| CA-05 | Evasión, indisponibilidad o incremento parcial | Integración/configuración/concurrencia | Startup estricto, `429` de 60 segundos y contadores atómicos | Instalación y despliegue seguro |
| CA-06 | IP de cliente o de par falseada | HTTP/integración | `REMOTE_ADDR` estable y proxy confiable requerido | Despliegue seguro |
| CA-07 | Despliegue incorrecto | Documentación | Variables y requisitos documentados | Instalación, acceso y despliegue |

## 9. Plan de implementación

1. Crear pruebas rojas de los tres límites, autenticación procesada, éxito sin
   consumo, `Retry-After` y configuración validada; implementar la configuración
   y la admisión bajo locks hasta CA-01, CA-03 y CA-04.
2. Crear pruebas rojas de concurrencia con cache `database`, fallo de store,
   arranque estricto y cabeceras de proxy hasta CA-02, CA-05 y CA-06.
3. Actualizar plantillas y guías de instalación, acceso y despliegue; ejecutar el
   quality gate y la matriz aplicable hasta CA-07.

## 10. Decisiones abiertas

No aplica.
