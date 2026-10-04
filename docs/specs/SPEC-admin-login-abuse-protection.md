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
`X-Forwarded-For` y de un `CACHE_STORE` con locks atómicos entre procesos. La
auditoría lo señaló como `needs_validation`.

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
- Exigir que el `CACHE_STORE` ofrezca locks atómicos y que el proxy sane las
  cabeceras; validarlo en el arranque cuando el modo estricto esté activo y
  documentarlo como requisito de despliegue.

### Fuera de alcance

- CAPTCHA, MFA, verificación por correo, notificaciones o alertas.
- Auditoría durable de intentos denegados (corresponde a LOC-03).
- Bloqueo permanente o desactivación de cuentas.
- Cambios en autenticación, autorización, rutas o contrato público del backoffice.

### Alcance diferido

- Correlación con reputación de IP, listas de bloqueo externas o limitación por
  dispositivo se retomarán solo con necesidad demostrada y Spec propia.
- La observabilidad de intentos denegados se coordina con el roadmap de calidad.

## 4. *Clash check*

- `SPEC-minimal-admin-panel` (completada): refuerza CA-03 sin cambiar mensajes,
  rutas, sesión ni autorización. No hay conflicto.
- ADR-0003: no añade migraciones ni depende de particularidades de motor; la
  persistencia del contador usa el almacén de caché configurado.
- ADR-0001/0006: no afecta a Blade/Vue ni a la resolución de presentaciones.
- Roadmap de calidad/localización: no adelanta auditoría durable ni LOC-04.

## 5. Requisitos y bloques técnicos aplicables

### Dominio y límites

- Clave por email+IP: 5 intentos fallidos por minuto.
- Clave por cuenta (sin IP): 10 intentos fallidos por 15 minutos.
- Clave por IP de par (`REMOTE_ADDR`): 30 intentos fallidos por 5 minutos.
- Un intento que no falla no consume presupuesto.
- La ventana se reinicia al expirar; no hay bloqueo permanente.

### Almacenamiento y atomicidad

- La admisión se decide bajo lock atómico del almacén de caché, de modo que
  solicitudes concurrentes no superen el límite.
- Un almacén que no ofrezca locks atómicos no debe permitir la evasión: la
  aplicación falla de forma segura (rechazo temporal) o no arranca en modo
  estricto, según se decida antes de aprobar.
- No se crean tablas ni migraciones nuevas; se reutiliza el almacén existente.

### Seguridad y validación

- Todas las respuestas de throttling son `429` con mensaje y cabecera
  `Retry-After` genéricos, sin revelar cuenta, privilegio ni existencia.
- No se registran credenciales, hashes ni identificadores de sesión.
- El límite por cuenta no permite un bloqueo indefinido explotable por terceros.
- Los proxies solo aportan IP efectiva si están declarados en `TRUSTED_PROXIES`.

### API, Vue, PageBuilder e integraciones

No aplicable: no se añaden rutas, contratos de API, Vue, PageBuilder, colas ni
integraciones externas.

### Entrega y compatibilidad

- Sin migraciones ni cambios de datos.
- Requiere `CACHE_STORE` con locks atómicos (database o redis) en producción y
  proxies que sanen cabeceras. Documentado en la guía de despliegue seguro.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Probar que rotar IP (o `X-Forwarded-For` desde un proxy confiable) no permite
  superar el límite por cuenta.
- Probar que rotar email desde un mismo origen queda acotado por el límite de IP
  de par.
- Probar que los accesos correctos no consumen el presupuesto de intentos fallidos.
- Probar respuestas genéricas e indistinguibles.
- Probar la atomicidad con solicitudes concurrentes y el fail-safe sin locks.

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
  fail-safe y verificable.
- **CA-06:** La configuración y los requisitos de despliegue quedan documentados.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Evasión por rotación de IP | Integración/concurrencia | Límite por cuenta respetado | Guía de acceso al backoffice |
| CA-02 | Ataque distribuido desde un origen | Integración | Límite por IP de par respetado | Guía de seguridad |
| CA-03 | Enumeración/causa expuesta | HTTP | `429` genérico | Guía de acceso al backoffice |
| CA-04 | Bloqueo de cuenta legítima | HTTP | Acceso correcto no consume presupuesto | Guía de acceso al backoffice |
| CA-05 | Evasión por caché sin locks | Integración/configuración | Fail-safe verificado | Guía de despliegue seguro |
| CA-06 | Despliegue incorrecto | No aplica (documental) | Requisitos documentados | Instalación y despliegue |

## 9. Plan de implementación

1. Pruebas rojas de los tres límites y de la contabilidad solo de fallos;
   implementar el contador por cuenta y por IP de par.
2. Pruebas rojas de atomicidad/concurrencia y fail-safe sin locks.
3. Ajustar documentación de despliegue y de acceso, y ejecutar el quality gate.

## 10. Decisiones abiertas

- Umbrales exactos de los tres límites y comportamiento por defecto cuando el
  almacén no ofrece locks atómicos (fail-safe frente a fallo de arranque).
