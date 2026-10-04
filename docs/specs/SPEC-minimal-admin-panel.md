# SPEC: Panel de administración mínimo

- **Estado:** Completada
- **Aprobación:** El responsable solicitó validar e implementar este contrato y
  confirmó expresamente HTTPS global, incluidas las rutas públicas.
- **Perfil:** feature
- **Origen de la planificación:** Petición del responsable del proyecto para
  establecer el prerrequisito administrativo de PageBuilder, concretada el
  2026-09-29.
- **Spec relacionada:** [SPEC-template-presentation-resolution-foundation](SPEC-template-presentation-resolution-foundation.md) (completada).

## 1. Objetivo o problema

Entregar el acceso mínimo y seguro a un panel administrativo Blade que sirva de
base al futuro PageBuilder. El incremento permite crear de forma controlada al
primer superadministrador desde Artisan, autenticarlo por sesión y mostrar un
shell administrativo sin operaciones de gestión ni edición.

## 2. Contexto y evidencia

El proyecto dispone del guard `web` basado en sesión, proveedor Eloquent y modelo
`User`, pero no tiene rutas web de administración, controladores, vistas de
autenticación, autorización ni comandos para aprovisionar usuarios. La tabla
`users` contiene identidad, email único, hash de contraseña y token de sesión;
no existe una relación persistente y única de superadministración.

PageBuilder debe operar desde un entorno administrativo eficiente y protegido;
por ello el roadmap de contenido lo condiciona a este incremento. Blade es la
base prescrita para páginas de backoffice. El contrato de presentaciones admite
vistas de sistema cuando una Spec concrete sus rutas, datos y autorización.

## 3. Alcance

- Reutilizar `users` como identidad persistente y añadir una tabla singleton
  `administration_access` que referencia al único superadministrador. No se
  crea gestión web de usuarios ni se registran otros tipos de usuario en este
  incremento.
- Incorporar el comando interactivo `cms:admin:create`. Solicita nombre, email y
  contraseña con confirmación; no acepta ni muestra contraseñas como argumentos,
  opciones, salida ni logs. Crea el superadministrador solo si aún no existe uno y
  falla sin modificar datos si ya existe.
- Incorporar autenticación por email y contraseña con el guard `web`: formulario
  Blade, inicio de sesión, cierre de sesión y regeneración de sesión apropiada.
- Publicar `GET` y `POST /admin/login`, y `POST /admin/logout`. La ruta de login
  solo está disponible para invitados; el formulario POST está protegido por
  CSRF.
- Publicar `GET /admin` como dashboard vacío funcionalmente, protegido por
  autenticación y por la relación de superadministrador. Tras un login correcto se
  redirige a esa ruta; el cierre de sesión redirige a `/admin/login`.
- Renderizar login y dashboard mediante las claves de presentación cerradas
  `system.auth.login` y `system.admin.dashboard`. Base es el template efectivo y
  propietario de ambas presentaciones en este incremento; no se introduce aún
  selección ni tematización de vistas de sistema por templates Custom.
- Proporcionar en el `UI catalog` Base `en` las claves
  `base::admin.login.title`, `base::admin.login.email`,
  `base::admin.login.password`, `base::admin.login.submit`,
  `base::admin.login.failed`, `base::admin.login.throttled`,
  `base::admin.dashboard.title` y `base::admin.dashboard.logout`. El panel usa
  el idioma predeterminado de backoffice ya definido por Core, sin selector de
  idioma en esta entrega.
- Permitir HTTP solo en `local` y `testing`. En cualquier otro entorno, la
  aplicación redirige `GET` y `HEAD` HTTP con `308` a la URL HTTPS equivalente y
  rechaza con `400` cualquier otro método HTTP antes de procesar su cuerpo o
  credenciales. Genera URLs HTTPS y configura cookies de sesión `Secure`,
  `HttpOnly` y `SameSite=Lax` como mínimo.

### Fuera de alcance

- Registro público, invitaciones, creación o administración web de usuarios,
  cambio o recuperación de contraseña y verificación de email.
- Roles, permisos granulares, delegación de administración, usuarios sin acceso
  administrativo o más de un superadministrador.
- Cualquier CRUD, edición, publicación o configuración desde el backoffice,
  incluidos idiomas, overrides, Pages, Navigation, CMS Templates, Media y
  PageBuilder.
- Auditoría administrativa durable, consulta de eventos, alertas, retención o
  registro de intentos de login denegados.
- Vue, SPA, Inertia, API JSON, colas, integraciones externas, assets específicos
  y schemas o almacenamiento de PageBuilder.
- Selección, instalación o tematización por templates Custom de las vistas de
  sistema.

### Alcance diferido

- La auditoría administrativa durable se especificará antes del primer flujo web
  mutable, conforme al roadmap de calidad. La creación del primer superadministrador
  queda limitada al comando de uso único y no habilita mutaciones web.
- La administración de idiomas y overrides requiere completar LOC-02 y LOC-03
  antes de LOC-04.
- La edición con PageBuilder se retomará tras completar este backoffice mínimo y
  aprobar su propia Spec de schema, persistencia, validación, traducción y
  renderizado.
- Recuperación de contraseña, ciclos de vida de usuarios y autorización con roles
  o permisos se retomarán únicamente cuando exista una necesidad funcional
  concreta y una Spec aprobada.
- La tematización de login y dashboard por CMS Templates se retomará al definir
  la selección de template de sistema y sus reglas de compatibilidad.
- El ciclo de vida de usuarios (alta, edición, desactivación, recuperación de
  contraseña y eliminación) se retomará solo con auditoría administrativa
  durable, requisitos de identidad y una Spec aprobada.

## 4. *Clash check*

- SDD inicial: conserva Laravel, Eloquent y Blade como base del backoffice; Vue
  queda reservado para zonas interactivas como el futuro PageBuilder. No hay
  conflicto.
- ADR-0001: el panel usa Blade, no adopta SPA ni Inertia y no instala Vue. No hay
  conflicto.
- ADR-0003: la migración de `users` y su restricción de superadministrador debe ser
  portable y verificarse en SQLite, MySQL y MariaDB. No hay conflicto.
- ADR-0006 y `SPEC-template-presentation-resolution-foundation`, completada:
  las vistas de sistema estaban explícitamente diferidas a una Spec de
  backoffice. Esta Spec extiende el catálogo cerrado con dos claves de sistema y
  Base como propietario, sin permitir que HTTP o datos editables elijan Blades o
  rutas. Los fallos de resolución conservarán el `503` controlado. No hay
  conflicto.
- Roadmap de localización: LOC-03 sigue siendo obligatorio antes de un
  backoffice mutable y LOC-04 no se adelanta. Este shell no muta idiomas ni
  overrides. No hay conflicto.
- Roadmap de calidad: no se crea una tabla genérica de auditoría antes de conocer
  el primer flujo mutable. Se difiere de forma explícita y segura. No hay
  conflicto.
- Código y pruebas: `User`, `users` y el guard de sesión existentes permiten el
  incremento, pero no contienen autorización, rutas ni flujos de login. El enum
  de presentaciones y la validación de Base tienen dos casos públicos actuales;
  esta Spec define su extensión y las pruebas de regresión asociadas. No hay
  migraciones o contratos públicos existentes que requieran compatibilidad.

## 5. Requisitos y bloques técnicos aplicables

### Dominio, datos y migración

- El único actor autorizado es el superadministrador. Un usuario autenticado sin
  esa relación no puede acceder a `/admin`.
- `administration_access` contiene exactamente un registro sembrado por la
  migración: `singleton` entero sin signo con valor fijo `1` y clave primaria, y
  `super_admin_user_id` anulable, único y clave foránea a `users.id` con borrado
  restringido. Una restricción `CHECK (singleton = 1)` impide registros de otro
  singleton en los motores soportados. El valor nulo representa que el bootstrap
  todavía no se ha realizado.
- La migración instala triggers equivalentes en SQLite, MySQL y MariaDB que
  rechazan insertar otro singleton, cambiar su identificador o borrar el registro
  sembrado. Ninguna ruta, comando posterior ni operación Eloquent puede eliminar
  o sustituir el singleton.
- `cms:admin:create` inicia una transacción de escritura y bloquea el registro
  singleton con el mecanismo portable correspondiente al motor; donde exista,
  usa bloqueo de fila y SQLite serializa la escritura. Después comprueba
  `super_admin_user_id`. Si es nulo, crea el `User` y asigna su identificador
  dentro de la misma transacción; si no lo es, falla sin modificar datos. Los
  conflictos de serialización se reintentan de forma acotada y, si persisten,
  fallan sin crear un usuario parcial.
- La relación singleton es la única fuente de autorización; no se usa un boolean
  editable en `users` ni se permite elevar usuarios existentes.
- La migración, el seed del singleton, los bloqueos y las restricciones se
  prueban en SQLite, MySQL y MariaDB conforme a ADR-0003. La prueba de SQLite
  usa un archivo compartido y conexiones independientes para demostrar la
  contención real; las de MySQL y MariaDB usan conexiones independientes contra
  el servicio del motor. Su `down()` elimina primero los triggers y revierte
  exclusivamente las tablas de este incremento cuando no existan dependencias
  posteriores.

### Rutas HTTP, presentación y frontend

- `GET /admin/login` devuelve el formulario de login a invitados. Un
  superadministrador ya autenticado se redirige a `/admin`.
- `POST /admin/login` acepta exclusivamente email y contraseña protegidos por
  CSRF. Unas credenciales válidas de superadministrador inician sesión y redirigen a
  `/admin`; cualquier otra combinación devuelve el mismo error de autenticación
  sin revelar si el email existe o si pertenece a un usuario sin relación de
  superadministrador.
- Antes de validar, consultar, persistir o limitar, el email se normaliza con
  `trim` y minúsculas. Los intentos de login se limitan a cinco por minuto por la
  combinación de un hash SHA-256 del email normalizado y la IP efectiva. Al
  exceder el límite, la respuesta no autentica y comunica el reintento sin
  distinguir la existencia o el rol de la cuenta.
- Al iniciar sesión se regenera el identificador de sesión. `POST /admin/logout`
  requiere una sesión autenticada y CSRF, invalida la sesión, regenera el token
  CSRF y redirige a `/admin/login`.
- Un invitado que solicita `/admin` se redirige a `/admin/login`. Un usuario
  autenticado que no es superadministrador recibe `403`, sin renderizar datos o
  navegación administrativa.
- Un usuario autenticado sin relación de superadministrador que solicita `GET` o
  `POST /admin/login` recibe `403`; no se redirige a login ni a `/admin`.
- `GET /admin` entrega una página Blade con el título del panel y una acción de
  cierre de sesión. No expone enlaces ni acciones a recursos aún no disponibles.
- Solo el código puede solicitar `system.auth.login` y
  `system.admin.dashboard`. Base declara e implementa ambas presentaciones y sus
  vistas reciben solo los datos estrictamente necesarios; ni parámetros HTTP,
  sesiones editables ni contenido persistido pueden elegir una clave, archivo,
  namespace o import.
- Base declara las dos claves en su manifiesto, `CmsPresentation` las representa
  como casos explícitos y `TemplateManifestValidator` exige las claves de `UI
  catalog` Base enumeradas en esta Spec. Un manifiesto, hash o Blade ausente, no
  regular o inseguro según el resolvedor, o la ausencia de Base, produce `503`
  sin rutas internas ni datos de sesión. Los errores de compilación o renderizado
  de Blade no pertenecen a esa validación y siguen el manejo de errores de
  Laravel sin mostrar detalles en entornos publicados.

### Módulos, API, Vue, PageBuilder, asincronía e integraciones

No aplicable: el incremento pertenece a la aplicación Laravel base y no
introduce módulos `nWidart`, API JSON, Vue, PageBuilder, colas ni integraciones
externas. No se definen contratos de API ni schemas JSON de PageBuilder.

### Seguridad y validación

- El comando valida nombre, email normalizado único y contraseña antes de
  persistir. La contraseña se solicita y confirma de forma silenciosa, tiene al
  menos 15 caracteres y como máximo 72 bytes UTF-8, contiene mayúscula,
  minúscula, número y símbolo, y se almacena con bcrypt configurado por Laravel.
  El login aplica los mismos límites de formato al email y contraseña sin revelar
  la causa del rechazo.
- Las rutas de administración usan middleware de invitado, autenticación y
  autorización según corresponda; la comprobación de superadministrador se aplica
  en el servidor, nunca solo en la vista.
- Las credenciales inválidas, el throttling y los accesos denegados no revelan
  atributos del usuario, secretos, hash de contraseña ni detalles internos.
- La verificación de credenciales realiza el mismo trabajo criptográfico para una
  cuenta existente y para una desconocida, de forma que el tiempo de respuesta no
  permita deducir si el email está registrado.
- Blade conserva el escape por defecto. El shell no procesa HTML editorial ni
  entrada enriquecida.
- No se versionan credenciales, usuarios iniciales, contraseñas ni datos de
  producción. La ejecución del comando y sus credenciales son responsabilidad
  del operador fuera de Git.
- La detección de entorno considera `local` y `testing` como excepciones de
  desarrollo. En cualquier otro entorno, `SESSION_SECURE_COOKIE=true`,
  `SESSION_HTTP_ONLY=true` y `SESSION_SAME_SITE=lax` son obligatorios. Laravel
  genera URLs HTTPS, redirige `GET` y `HEAD` HTTP con `308` y rechaza con `400`
  el resto de métodos HTTP antes de procesar sus cuerpos. Solo las IP o CIDR
  declaradas en `TRUSTED_PROXIES` pueden aportar cabeceras `X-Forwarded-*`; si no
  hay proxies declarados, esas cabeceras no modifican el esquema ni la IP efectiva.

### Entrega y compatibilidad

- La migración se ejecuta con el flujo normal de Laravel y no requiere backfill
  de usuarios existentes.
- El despliegue requiere ejecutar migraciones antes de `cms:admin:create`,
  configurar `TRUSTED_PROXIES`, HTTPS y cookies seguras antes de exponer `/admin`.
  La política de transporte cambia las respuestas HTTP de toda la web: `308`
  para GET/HEAD y `400` para otros métodos fuera de local/testing. Las rutas
  públicas localizadas conservan su comportamiento de dominio sobre HTTPS.
- El rollback revierte la migración solo si no existen dependencias posteriores
  de la relación de superadministrador; la guía de despliegue deberá advertir esta
  condición operativa cuando se implemente el incremento.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Aplicar TDD a la autorización administrativa, el comando de uso único, la
  autenticación y la invalidación de sesión.
- Probar el comando con entradas válidas, email duplicado, validación fallida,
  superadministrador ya existente y ejecución concurrente, verificando que las
  rutas de error no dejan un usuario o elevación parcial. Probar que no se puede
  insertar, modificar ni borrar el singleton fuera de su contrato.
- Probar login correcto, credenciales inválidas, usuario sin relación de
  superadministrador, límite de intentos, variantes de mayúsculas y espacios del
  email, regeneración de sesión, logout y CSRF.
- Probar que una cuenta desconocida y una contraseña incorrecta producen la misma
  redirección, el mismo error y el mismo estado, sin revelar la existencia de la
  cuenta.
- Probar las respuestas de invitado y `403`, además de que dashboard no ofrece
  acciones de gestión no implementadas.
- Probar que las dos claves de presentación de sistema son cerradas, Base las
  aporta, sus `UI catalogs` están completos y una entrada no confiable no puede
  seleccionar Blades o imports. Probar los `503` no reveladores ante Base,
  manifiesto, hash o Blade ausente, no regular o inseguro.
- Probar que en entornos distintos de `local` y `testing` HTTP `GET` y `HEAD`
  reciben `308` hacia HTTPS, mientras que `POST /admin/login`, logout y cualquier
  otro método HTTP se rechazan con `400` antes de procesar sus cuerpos. Comprobar
  que HTTPS se acepta, que sus cookies de sesión incluyen `Secure`, `HttpOnly` y
  `SameSite=Lax`, y que `local` y `testing` conservan HTTP para desarrollo y
  pruebas.
- Probar que las rutas `/admin/*` se registran antes de la ruta pública catch-all
  de Pages y que ninguna solicitud administrativa es resuelta por Pages.
- Ejecutar las pruebas enfocadas antes de la suite afectada, Pint, build de Vite
  si permanece configurado, validación de manifiestos y la matriz SQLite, MySQL
  y MariaDB para la migración y la invariancia de superadministrador.

### Riesgos aceptados

- La pérdida de la contraseña del único superadministrador requiere intervención
  operativa fuera de este incremento, porque no hay recuperación de contraseña
  ni gestión de usuarios. Se acepta para evitar introducir un flujo sensible sin
  requisitos de identidad, notificación y auditoría definidos.

### Deuda técnica

No aplica. Los flujos diferidos no son implementaciones incompletas del shell:
cada uno exige una necesidad concreta y una Spec aprobada antes de incorporarse.

## 7. Criterios de aceptación

- **CA-01:** Tras ejecutar migraciones, `administration_access` contiene su
  singleton vacío y `cms:admin:create` crea exactamente un superadministrador
  mediante interacción de consola validada y sin exponer la contraseña. Si ya
  existe uno, falla una validación o hay concurrencia, no crea ni eleva usuarios
  de forma parcial. No se puede insertar, sustituir ni borrar el singleton.
- **CA-02:** Un superadministrador puede iniciar sesión desde `/admin/login` con
  email y contraseña válidos y llega a `/admin`; la sesión se regenera durante
  el login y puede cerrarse mediante una petición POST protegida por CSRF.
- **CA-03:** Credenciales inválidas, un usuario sin relación de
  superadministrador y un exceso de
  intentos no autentican ni revelan si el usuario existe o tiene privilegios. Los
  intentos se limitan a cinco por minuto por email normalizado e IP efectiva.
- **CA-04:** Un invitado es redirigido desde `/admin` a `/admin/login`; un usuario
  autenticado sin relación de superadministrador recibe `403` tanto en `/admin`
  como en las rutas de login; solo el superadministrador recibe el dashboard
  Blade.
- **CA-05:** Login y dashboard se resuelven mediante las claves cerradas
  `system.auth.login` y `system.admin.dashboard` de Base, con las claves `UI
  catalog` Base especificadas. Una Base, manifiesto, hash o Blade ausente, no
  regular o inseguro responde `503` sin revelar detalles. Ninguna entrada HTTP o
  dato editable puede elegir una presentación, ruta, namespace, archivo o import
  distinto.
- **CA-06:** El dashboard no ofrece CRUD, edición, publicación, configuración ni
  administración de recursos. La aplicación no expone registro, recuperación de
  contraseña ni gestión web de usuarios en este incremento.
- **CA-07:** Fuera de `local` y `testing`, toda la web, incluido `/admin` y las
  rutas públicas localizadas, redirige solicitudes HTTP
  `GET` y `HEAD` con `308` a HTTPS y rechaza con `400` el resto de métodos antes
  de procesar sus cuerpos. HTTPS se acepta y sus cookies de sesión incluyen
  `Secure`, `HttpOnly` y `SameSite=Lax`. Solo los proxies declarados en
  `TRUSTED_PROXIES` determinan el esquema e IP efectiva. `local` y `testing`
  pueden operar por HTTP para desarrollo y pruebas.
- **CA-08:** Las rutas `/admin/*` se resuelven siempre por el backoffice y nunca
  por la ruta pública catch-all de Pages.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Escalado no autorizado, secreto expuesto, concurrencia, creación parcial o pérdida del singleton | Integración/consola/migración | Un único superadministrador, bloqueo, triggers, validación y contraseña no visible | Guía de operación del primer superadministrador |
| CA-02 | Secuestro o persistencia indebida de sesión | HTTP/integración | Login, regeneración, logout e invalidación verificadas | Guía de acceso al backoffice |
| CA-03 | Enumeración de usuarios, evasión de throttling o fuerza bruta | HTTP | Error genérico y throttling por email normalizado e IP efectiva | Guía de acceso al backoffice |
| CA-04 | Acceso administrativo sin autorización o bucle de login | HTTP | Redirección de invitado, `403` y dashboard restringido | Guía de acceso al backoffice |
| CA-05 | Selección de código o Blade desde una entrada no confiable, o fallo de template validable no controlado | Integración/HTTP | Claves cerradas, UI catalogs completos, `503` controlado y entradas rechazadas | Arquitectura de CMS Templates |
| CA-06 | Exposición prematura de mutaciones sin auditoría | HTTP | Shell sin rutas ni acciones de gestión | Roadmap de contenido y administración |
| CA-07 | Credenciales o sesión transmitidas por HTTP, redirección insegura o suplantación de proxy | HTTP/integración/configuración | `GET`/`HEAD` redirigidos, métodos con cuerpo rechazados y cookies seguras | Guía de despliegue seguro |
| CA-08 | Ruta administrativa absorbida por Pages | HTTP/integración | `/admin/*` alcanza exclusivamente controladores de backoffice | Arquitectura de rutas y backoffice |

## 9. Plan de implementación

1. Crear pruebas rojas de `administration_access`, sus triggers, el bloqueo
   singleton y el comando interactivo; implementar migración, modelo y comando
   hasta CA-01. Verificar concurrencia con conexiones independientes en los tres
   motores y un archivo SQLite compartido.
2. Crear pruebas HTTP rojas de login, normalización de email, CSRF, throttling,
   regeneración e
   invalidación de sesión; implementar rutas y acciones hasta CA-02 y CA-03.
3. Crear pruebas rojas de invitado, autorización, precedencia de rutas,
   presentación cerrada y fallos `503`; implementar middleware, claves de sistema
   Base y dashboard Blade hasta CA-04, CA-05 y CA-08.
4. Crear pruebas rojas de redirección HTTP `GET`/`HEAD`, rechazo de métodos con
   cuerpo, HTTPS, proxies confiables y atributos de cookie por entorno;
   implementar la configuración de proxies, HTTPS y sesión hasta CA-07.
5. Verificar que no existen rutas o acciones de gestión, actualizar la
   documentación operativa y ejecutar todos los quality gates antes de completar
   la Spec.

## 10. Decisiones abiertas

No aplica. El incremento se limita explícitamente a un único superadministrador,
bootstrap interactivo de uso único, autenticación por sesión y shell no mutable.
