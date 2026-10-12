# SPEC: Protección frente a abuso en el acceso al backoffice

- **Estado:** Rechazada
- **Perfil:** feature
- **Origen de la planificación:** Hallazgo L2 de la auditoría de seguridad del
  incremento de backoffice; endurecimiento acordado (opción D, defensa en
  profundidad).
- **Spec relacionada:** [SPEC-minimal-admin-panel](SPEC-minimal-admin-panel.md)
  (completada).
- **Motivo de rechazo:** La defensa multicapa con tablas técnicas, reservas
  transaccionales, cron, señales operativas, guards de workers y controles de
  proxy supera el riesgo y la capacidad operativa del MVP en hosting compartido.
  Se sustituye por [SPEC-admin-login-throttling](SPEC-admin-login-throttling.md).

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
- Añadir un único límite por IP de origen como red de seguridad frente a la
  rotación de emails: usa `REMOTE_ADDR` en conexiones directas y la IP efectiva
  tras un proxy confiable y saneado, sin acumular ambas cuotas.
- Contabilizar únicamente los intentos fallidos, no los accesos correctos.
- Validar sintácticamente email, contraseña y CSRF antes de reservar una cuota.
- Aplicar respuestas `429` genéricas, sin distinguir cuenta, privilegio ni causa.
- Ventanas cortas y sin bloqueo permanente: la cuenta vuelve a admitir intentos
  al expirar la ventana.
- Usar una tabla técnica dedicada en la base de datos para los contadores y
  reservas del login.
- Purgar los buckets vencidos cada 15 minutos.
- Bloquear tráfico HTTP y workers en producción si falta la migración técnica,
  sin bloquear Artisan de migración ni mantenimiento.
- Garantizar la reserva y actualización conjunta de las cuotas aplicables con una
  transacción de base de datos.
- Validar al arrancar en producción la configuración local del almacén y de
  `TRUSTED_PROXIES`; documentar el saneamiento real del proxy como requisito de
  despliegue verificable fuera de la aplicación.

### Fuera de alcance

- CAPTCHA, MFA, verificación por correo o notificaciones de producto. La señal
  operativa de purga y su alerta externa de despliegue forman parte de este
  incremento.
- Auditoría durable de intentos denegados (corresponde a LOC-03).
- Bloqueo permanente o desactivación de cuentas.
- Un backend Redis para rate limiting.
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
- ADR-0003: añade una migración técnica de rate limiting y verifica la
  transacción en SQLite, MySQL y MariaDB. No hay conflicto.
- ADR-0001/0006: no afecta a Blade/Vue ni a la resolución de presentaciones.
- Roadmap de calidad/localización: no adelanta auditoría durable ni LOC-04. No
  hay conflicto.

## 5. Requisitos y bloques técnicos aplicables

### Dominio, configuración y límites

- El límite por email+IP usa por defecto 5 intentos fallidos en 60 segundos; el
  límite por cuenta usa 10 en 900 segundos; y el límite por IP de origen usa 30
  en 300 segundos.
- Los valores se configuran mediante `LOGIN_RATE_LIMIT_EMAIL_IP_MAX_ATTEMPTS`,
  `LOGIN_RATE_LIMIT_EMAIL_IP_DECAY_SECONDS`,
  `LOGIN_RATE_LIMIT_ACCOUNT_MAX_ATTEMPTS`,
  `LOGIN_RATE_LIMIT_ACCOUNT_DECAY_SECONDS`,
  `LOGIN_RATE_LIMIT_ORIGIN_IP_MAX_ATTEMPTS` y
  `LOGIN_RATE_LIMIT_ORIGIN_IP_DECAY_SECONDS`.
- Cada máximo admite enteros entre 1 y 100 y cada ventana enteros entre 1 y 3600
  segundos. Los valores omitidos usan sus defaults; en producción, un valor
  presente fuera de rango impide el arranque antes de aceptar tráfico.
- Solo consume presupuesto una autenticación procesada que falla: cuenta
  inexistente, contraseña errónea o usuario sin privilegio de superadministrador.
  Errores de formato y CSRF no consumen cuota.
- Tras validar sintaxis y antes de derivar buckets, la identidad de cuota se
  resuelve con la misma collation que el proveedor de autenticación: usa el email
  canónico de la cuenta coincidente o el email normalizado de entrada si no
  existe. Así, las variantes equivalentes para el motor no pueden rotar la cuota
  por cuenta.
- Un acceso correcto no consume presupuesto. Una cuota agotada por email+IP o IP
  de origen rechaza cualquier nuevo intento, incluido uno con credenciales
  correctas, hasta que permita la admisión de nuevo.
- Si solo la cuota por cuenta está agotada, pero las cuotas por email+IP e IP de
  origen admiten la solicitud, se verifica la credencial: un acceso correcto se
  permite y libera sus reservas; un fallo responde `429` genérico sin ampliar la
  cuota de cuenta agotada. Esta excepción evita que terceros mantengan bloqueado
  indefinidamente al superadministrador por conocer su email.
- Las ventanas empiezan con el primer fallo y expiran sin bloqueo permanente.

### Almacenamiento y atomicidad

- El rate limiting usa exclusivamente la conexión de base de datos configurada
  por Laravel; no depende de `CACHE_STORE`, `cache.limiter` ni Redis. En
  producción, una conexión de base de datos inválida o una migración pendiente
  impiden el arranque antes de aceptar tráfico.
- Crear la tabla técnica `login_rate_limit_buckets` con `bucket_hash` de 64
  caracteres como clave primaria, `attempts` entero sin signo, `window_id` UUID
  y `expires_at` indexado. No tiene claves foráneas ni guarda email o IP en claro.
  La clave se deriva con HMAC-SHA-256 y `APP_KEY` a partir del tipo de cuota y su
  identificador normalizado. Rotar `APP_KEY` invalida únicamente las cuotas con
  vida restante, cuyo máximo es una hora.
- La admisión reserva atómicamente una plaza antes de verificar las credenciales:
  para las filas aplicables de cuenta, email+IP e IP de origen, incrementa
  `attempts` y devuelve sus `window_id`. Un fallo de autenticación conserva la
  reserva como intento fallido; un acceso correcto libera una plaza solo cuando
  el `window_id` sigue coincidiendo. Si el proceso falla antes de liberar, la
  reserva expira con su ventana y aplica fail-safe.
- Si solo la cuota por cuenta está agotada, la admisión reserva únicamente
  email+IP e IP de origen y conserva intacto el bucket de cuenta. Tras verificar
  una credencial correcta libera solo esas reservas; tras un fallo devuelve `429`
  sin mutar ningún bucket. Las cuotas por email+IP o IP de origen agotadas no
  admiten esta excepción.
- La operación de reserva o liberación usa una única transacción. Inserta de
  forma idempotente las filas ausentes, bloquea las filas aplicables ordenadas por
  `bucket_hash`, reinicia las filas expiradas con un nuevo `window_id`, y confirma
  todas las mutaciones o revierte todas ante cualquier error.
- La transacción toma el instante actual del motor de base de datos para comparar
  y asignar `expires_at`. Una liberación que deja `attempts` en cero elimina el
  bucket; así, el primer fallo posterior crea una ventana y `window_id` nuevos.
- SQLite usa una transacción `IMMEDIATE`; MySQL y MariaDB insertan de forma
  idempotente, vuelven a leer con bloqueo y reintentan de forma acotada los
  conflictos de clave, deadlock o serialización. Agotados los reintentos, la
  solicitud recibe `429` fail-closed sin incremento parcial.
- Local y testing usan una base de datos explícita. La concurrencia se prueba con
  conexiones independientes y un archivo SQLite compartido o con los motores
  reales; SQLite en memoria no demuestra esta garantía.
- Un trabajo programado cada 15 minutos elimina filas con `expires_at` vencido.
  La purga usa el índice de expiración, no modifica ventanas activas y se prueba
  frente a reservas concurrentes. Emite una señal de éxito sin identificadores;
  registra el evento estructurado `admin.rate_limit_purge.completed` con el
  instante y el número de buckets eliminados. La operación alerta externamente
  si no observa ese evento en 30 minutos.
- Un timeout, error de conexión, transacción o contador durante una solicitud no
  permite la autenticación: responde `429` genérico con `Retry-After: 60` y no
  registra secretos, credenciales, hashes ni identificadores de sesión.
- La tabla técnica no representa un agregado de dominio ni se expone al
  backoffice; solo soporta rate limiting transaccional.

### Seguridad y validación

- Todas las respuestas de throttling son `429` con mensaje y cabecera
  `Retry-After` genéricos, sin revelar cuenta, privilegio ni existencia. Cuando
  hay cuotas agotadas, la cabecera devuelve el mayor tiempo restante de ellas.
- No se registran credenciales, hashes ni identificadores de sesión.
- El límite por cuenta no permite un bloqueo indefinido explotable por terceros.
- Los proxies solo aportan IP efectiva si están declarados en `TRUSTED_PROXIES`.
  Cuando `REMOTE_ADDR` no pertenece a un proxy confiable, la IP de origen es
  `REMOTE_ADDR`. Cuando pertenece a un proxy confiable cuyo saneamiento se haya
  verificado en el despliegue, la IP de origen es la IP efectiva. La admisión
  aplica exactamente una cuota IP-only, nunca una cuota adicional compartida por
  el balanceador.
- La IP de par es siempre `REMOTE_ADDR` y no cambia con `X-Forwarded-For`; una
  entrada directa no confiable tampoco puede alterar la IP de origen mediante
  esa cabecera.
- La aplicación valida al bootstrap la sintaxis y las redes explícitas de
  `TRUSTED_PROXIES` y rechaza los CIDR universales `0.0.0.0/0` y `::/0`, sus
  comodines y equivalentes. El proxy debe sanear realmente las cabeceras en la
  infraestructura. Esta última garantía se comprueba en el despliegue, no desde
  Laravel.
- El despliegue enumera en `TRUSTED_PROXIES` todos los saltos que pueden llegar a
  PHP, bloquea el acceso directo al origen y exige que el proxy perimetral elimine
  las cabeceras `X-Forwarded-*` recibidas del cliente antes de reconstruir la
  cadena permitida. La comprobación operativa cubre conexión directa denegada,
  proxy válido y cabecera maliciosa.
- En producción, un guard de readiness impide servir HTTP o ejecutar workers si
  falta la migración `login_rate_limit_buckets`. Artisan de migración y
  mantenimiento queda excluido para permitir reparar el estado antes de reabrir
  tráfico.
- El middleware HTTP devuelve `503` genérico sin detalles de esquema. Al iniciar
  un worker, el guard termina con código distinto de cero si falta la migración
  para que su supervisor lo reintente. Se excluyen `migrate`, `migrate:status`,
  `migrate:rollback`, `down`, `up` y los comandos de mantenimiento declarados.

### API, Vue, PageBuilder, workers e integraciones

No aplicable: no se añaden rutas, contratos de API, Vue, PageBuilder ni
integraciones de producto. El lifecycle de workers aplica exclusivamente al guard
de readiness: cubre los procesos `queue:work` y `queue:listen`; excluye los
comandos Artisan de migración, estado, rollback y mantenimiento definidos.

### Entrega y compatibilidad

- La migración técnica `login_rate_limit_buckets` se ejecuta antes de activar el
  rate limiting; no hay backfill ni datos de negocio que migrar.
- La plantilla de entorno, instalación, guía de acceso y despliegue seguro
  documentan los seis límites, la migración y conexión de base de datos, y el
  requisito de saneamiento de proxies, incluida la diferencia entre conexión
  directa y tráfico tras balanceador.
- El despliegue configura cron para ejecutar `php artisan schedule:run` cada
  minuto; la guía de operación incluye una comprobación de que la purga registrada
  cada 15 minutos se ejecuta realmente y una alerta externa a los 30 minutos sin
  el evento `admin.rate_limit_purge.completed`.
- La documentación declara que los `bucket_hash` son datos pseudonimizados,
  deriva su HMAC de `APP_KEY` y explica que rotar esa clave invalida cuotas activas
  de hasta una hora.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Probar que rotar IP (o `X-Forwarded-For` desde un proxy confiable) no permite
  superar el límite por cuenta.
- Probar que rotar email desde un origen directo queda acotado por el límite de
  IP de origen.
- Probar que varios clientes tras el mismo proxy confiable no comparten una
  cuota de balanceador y que cada IP efectiva queda acotada por su cuota IP-only.
- Probar que los accesos correctos no consumen el presupuesto de intentos fallidos.
- Probar que solo las autenticaciones procesadas fallidas consumen cuota.
- Probar que variantes de email equivalentes para la collation de autenticación
  comparten la cuota por cuenta en SQLite, MySQL y MariaDB cuando el motor las
  admita.
- Probar que los errores de formato y CSRF no reservan cuota ni alcanzan el
  verificador de credenciales.
- Probar que una credencial correcta supera una cuota de cuenta agotada sin
  liberar ni modificar los fallos que la agotaron.
- Probar que esa excepción reserva y libera solo email+IP e IP de origen, y que
  una cuota agotada de email+IP o IP de origen sigue rechazando todo intento.
- Probar respuestas genéricas e indistinguibles y `Retry-After` igual al máximo
  tiempo restante de las cuotas agotadas.
- Probar la atomicidad con solicitudes concurrentes sobre el backend `database`
  en SQLite, MySQL y MariaDB, y la ausencia de incrementos parciales incluso si
  falla una actualización intermedia; salvo la excepción explícita de cuota de
  cuenta agotada, la reserva previa no permite que más intentos alcancen el
  verificador de credenciales que el máximo de cuota.
- Probar el rechazo de arranque en producción para conexión o límites inválidos,
  y la respuesta fail-closed de 60 segundos ante fallo transitorio de runtime.
- Probar que `REMOTE_ADDR` no cambia al alterar `X-Forwarded-For`, que la IP
  efectiva solo procede de proxies configurados y que se selecciona exactamente
  una IP de origen por solicitud.
- Probar que los CIDR universales y equivalentes no pueden declararse en
  `TRUSTED_PROXIES`.
- Probar que una liberación tardía no decrementa una ventana con `window_id`
  nuevo, que rotar `APP_KEY` no reutiliza buckets previos y que email/IP no se
  persisten en claro.
- Probar que el primer fallo posterior a un éxito inicia una ventana nueva con el
  reloj del motor de base de datos.
- Probar la purga de buckets vencidos y el guard de readiness para HTTP/workers,
  incluida la exclusión de Artisan de migración y mantenimiento.
- Probar el registro de la purga con `everyFifteenMinutes()` y documentar la
  comprobación operativa del cron `schedule:run` por minuto y alerta a los 30
  minutos sin el evento de éxito.
- Probar el evento de éxito de purga sin identificadores y el guard de inicio de
  `queue:work` y `queue:listen`, además de los comandos Artisan excluidos.

### Riesgos aceptados

- El límite por cuenta puede provocar un bloqueo temporal de la cuenta ante un
  ataque dirigido; se limita al permitir una credencial correcta cuando solo esa
  cuota está agotada. Un atacante distribuido puede seguir consumir coste de
  verificación hasta los límites por email+IP e IP de origen.

### Deuda técnica

No aplica.

## 7. Criterios de aceptación

- **CA-01:** Rotar la IP o una variante de email equivalente para la collation
  del motor no permite superar un límite por cuenta configurable y comprobable.
- **CA-02:** Un mismo origen queda acotado aunque varíe el email; clientes
  distintos tras un proxy confiable saneado no comparten un bucket de
  balanceador y cada IP efectiva conserva su cuota IP-only.
- **CA-03:** Las respuestas de throttling no revelan cuenta, privilegio ni
  existencia.
- **CA-04:** Un acceso correcto no consume el presupuesto de intentos fallidos y
  la cuenta se recupera al expirar la ventana; una credencial correcta puede
  superar una cuota de cuenta agotada sin mutar los fallos existentes.
- **CA-05:** Sin reserva transaccional no se permite la evasión; el comportamiento
  es fail-safe y verificable, tanto al arrancar producción como ante fallo de
  runtime. La reserva actualiza las cuotas aplicables todas o ninguna.
- **CA-06:** La IP de origen no puede ser manipulada mediante cabeceras: usa la
  IP de par en conexión directa o la IP efectiva tras proxy confiable saneado,
  sin acumularse ni compartir un bucket de balanceador.
- **CA-07:** La configuración, los límites, la migración y los requisitos de
  despliegue quedan documentados.
- **CA-08:** Los buckets vencidos se purgan y el guard de producción impide
  tráfico HTTP y workers con la migración pendiente sin bloquear su reparación;
  la ausencia de purga se alerta en 30 minutos.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Evasión por rotación de IP o alias de collation | Integración/concurrencia | Límite por cuenta respetado con emails equivalentes | Guía de acceso al backoffice |
| CA-02 | Ataque distribuido o denegación compartida tras balanceador | Integración | Límite por IP de origen y ausencia de bucket compartido tras proxy | Guía de seguridad |
| CA-03 | Enumeración/causa expuesta | HTTP | `429` genérico | Guía de acceso al backoffice |
| CA-04 | Bloqueo de cuenta legítima | HTTP | Acceso correcto no consume presupuesto | Guía de acceso al backoffice |
| CA-05 | Evasión, indisponibilidad o incremento parcial | Integración/configuración/concurrencia | Startup estricto, `429` de 60 segundos y reserva transaccional atómica | Instalación y despliegue seguro |
| CA-06 | IP de cliente o de par falseada | HTTP/integración | IP de origen única, proxy confiable y cuota condicional | Despliegue seguro |
| CA-07 | Despliegue incorrecto | Documentación | Variables y requisitos documentados | Instalación, acceso y despliegue |
| CA-08 | Retención indefinida o tráfico sin esquema | Integración/scheduler | Purga, alerta y guard de readiness verificables | Operación y despliegue |

## 9. Plan de implementación

1. Crear pruebas rojas de los tres límites, validación previa, autenticación
   procesada, éxito sin consumo, excepción de cuenta agotada, `Retry-After` y
   configuración validada; implementar la admisión y reserva hasta CA-01, CA-03
   y CA-04.
2. Crear pruebas rojas de reserva transaccional, concurrencia con barrera, fallo
   de conexión, arranque estricto, conexión directa y clientes distintos tras
   proxy hasta CA-02, CA-05 y CA-06.
3. Añadir la migración, purga programada y guard de readiness; actualizar
   plantillas y guías de instalación, acceso y despliegue, incluida la
   comprobación de cadena de proxies y cron; ejecutar el quality gate y la matriz
   SQLite/MySQL/MariaDB hasta CA-07 y CA-08.

## 10. Decisiones abiertas

No aplica.
