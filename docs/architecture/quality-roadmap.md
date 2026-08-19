# Roadmap de calidad, seguridad y observabilidad

## Propósito y estado

Este documento registra iniciativas técnicas candidatas que pueden reforzar el
control de calidad, la seguridad y la observabilidad del CMS. Está dirigido a
contributors del core y responsables de despliegue.

> [!IMPORTANT]
> Todas las iniciativas descritas están **previstas o pendientes de evaluación**.
> El repositorio todavía no contiene una aplicación Laravel ejecutable y ninguna
> de estas herramientas está instalada, configurada ni disponible.

Este roadmap no sustituye una Spec, un ADR ni el backlog de una implementación.
Cuando una iniciativa vaya a modificar el build, el runtime, un contrato o un
comportamiento observable, deberá delimitarse mediante una Spec concreta y
aprobada antes de implementarse. Solo requerirá un ADR si la decisión resultante
es arquitectónica, transversal y duradera.

## Principios de adopción

- Adoptar una herramienta para resolver un riesgo demostrado, no únicamente por
  popularidad o como previsión abstracta.
- Preferir las capacidades idiomáticas de Laravel y Vue cuando proporcionen una
  solución suficiente y mantenible.
- Mantener las comprobaciones obligatorias en integración continua (CI). Los
  hooks locales mejoran el feedback, pero pueden omitirse y no son una barrera
  autoritativa.
- Tratar la validación del navegador como defensa adicional. Laravel seguirá
  siendo la autoridad para validar solicitudes, autorización y reglas de negocio.
- Introducir telemetría con minimización de datos, filtrado de información
  sensible, configuración por entorno y degradación segura.
- Versionar únicamente nombres de variables y ejemplos ficticios; nunca
  credenciales, tokens, DSN privados ni archivos de entorno reales. Véase
  [Configuración y secretos](configuration-and-secrets.md).
- Definir para cada adopción una señal de entrada, una comprobación automatizada
  y una condición explícita para considerarla terminada.

## Registro de iniciativas

| Iniciativa | Objetivo | Prioridad orientativa | Hito de evaluación | Estado |
| :--- | :--- | :--- | :--- | :--- |
| Quality gates en CI | Hacer obligatorios formato, análisis estático, tests y controles de seguridad reproducibles | Alta | Bootstrap de la aplicación | Candidata |
| Hooks locales con Husky | Adelantar feedback sobre archivos preparados para commit o push | Media | Cuando existan scripts frontend estables | Candidata |
| Cabeceras HTTP y Content Security Policy (CSP) para Laravel | Reducir exposición a ejecución, framing, filtrado de información y transporte inseguro | Alta | Primer endpoint HTTP; endurecimiento antes de staging | Candidata |
| Observabilidad con Sentry | Detectar y diagnosticar errores de Laravel y Vue por entorno y release | Media/Alta | Integración básica tras el bootstrap; completar antes de staging | Candidata |
| Validación runtime con Zod | Validar datos no confiables en las fronteras de Vue y del PageBuilder | Media | Primer contrato frontend o schema de bloque complejo | Candidata |

Las prioridades son relativas a estas iniciativas y no alteran el alcance MoSCoW
del producto definido en el SDD inicial.

## Secuencia recomendada

### 1. Bootstrap y baseline reproducible

Cuando se cree la aplicación Laravel y Vue:

1. definir comandos canónicos de formato, lint, análisis estático y tests;
2. ejecutar esos comandos en CI sobre un entorno limpio;
3. establecer una configuración segura de sesiones, cookies, errores y secretos;
4. añadir un baseline de cabeceras HTTP en Laravel o en la infraestructura que
   sea responsable de cada cabecera;
5. documentar cómo reproducir localmente el mismo quality gate.

No conviene instalar hooks antes de que los comandos que ejecutarán sean rápidos,
estables y reproducibles.

### 2. Primer incremento vertical de backoffice y API

Al aparecer el primer flujo Vue que intercambie datos con Laravel:

- evaluar Zod en la frontera del frontend;
- mantener la validación autoritativa en Laravel;
- decidir cómo se evita la divergencia entre tipos TypeScript, validadores Zod y
  contratos del backend;
- integrar captura básica de errores por entorno, sin enviar contenido editorial
  ni información sensible;
- añadir hooks locales solo como complemento de CI.

### 3. PageBuilder y módulos principales

Al definir los schemas JSON del PageBuilder:

- seleccionar una fuente de verdad para los contratos y su estrategia de
  compatibilidad;
- validar entradas persistidas antes de que Vue las consuma;
- probar schemas válidos, versiones incompatibles y datos corruptos;
- revisar CSP frente a Vite, editores, assets, imágenes y cualquier origen
  externo realmente necesario;
- instrumentar errores accionables sin registrar el contenido de las páginas.

La elección de una estrategia transversal de generación o sincronización de
contratos podría superar el umbral de ADR; la simple instalación de una librería
no lo supera por sí sola.

### 4. Staging y preparación de release

Antes de exponer el CMS a usuarios reales:

- verificar automáticamente las cabeceras efectivas en el entorno desplegado;
- introducir CSP primero en modo de reporte si el riesgo de incompatibilidad lo
  justifica y aplicar después la política exigible;
- configurar releases, sourcemaps, alertas, muestreo y filtrado de datos de la
  plataforma de observabilidad;
- comprobar que una indisponibilidad del proveedor de observabilidad no impide
  operar el CMS;
- mantener CI como control obligatorio, aunque los hooks locales estén activos;
- revisar dependencias, secretos, logs y artefactos de build antes del release.

## Evaluación por iniciativa

### Husky

Husky es compatible con el workspace de Vue y puede ejecutar scripts de calidad
mediante hooks de Git. Su adopción tiene sentido cuando exista un `package.json`
con comandos estables.

Uso previsto:

- `pre-commit`: formato y lint enfocados, preferentemente solo sobre archivos
  preparados;
- `pre-push`: comprobaciones enfocadas cuyo tiempo sea razonable;
- CI: suite y controles completos, siempre autoritativos.

No se adoptará si obliga a duplicar lógica entre hooks y CI: ambos deben invocar
los mismos scripts versionados. Tampoco se presentará como medida de seguridad
del runtime.

### Cabeceras HTTP y CSP

Helmet pertenece al ecosistema de servidores Node/Express y no corresponde al
runtime previsto, que sirve la aplicación con Laravel y Blade. Se conserva el
objetivo de seguridad, pero se evaluará una solución idiomática mediante
middleware Laravel y/o configuración de la infraestructura responsable.

La evaluación debe cubrir, según el despliegue real:

- Content Security Policy, incluida una política de `frame-ancestors`;
- protección frente a interpretación incorrecta del tipo de contenido;
- política de referrer y permisos del navegador;
- transporte HTTPS y HSTS, únicamente cuando el dominio opere correctamente
  bajo HTTPS;
- compatibilidad con Vite, imágenes, editores, Sentry y fuentes externas
  explícitamente aprobadas.

Las cabeceras se comprobarán sobre respuestas reales. No se duplicará una misma
responsabilidad entre Laravel, proxy y servidor web sin definir precedencia.

### Sentry

Sentry es candidato para observar por separado Laravel y Vue, correlacionando
errores con entorno y release cuando sea posible. La adopción deberá definir:

- entornos habilitados y comportamiento local por defecto;
- filtrado de cookies, cabeceras, payloads, datos personales y contenido del CMS;
- almacenamiento externo de DSN y tokens;
- muestreo, retención, cuotas y alertas accionables;
- publicación protegida de sourcemaps;
- tratamiento de jobs, comandos y errores del navegador;
- comportamiento degradado cuando el servicio no esté disponible;
- mecanismo verificable para generar un evento de prueba sin datos reales.

Antes de habilitar telemetría en staging o producción se revisarán privacidad,
región de tratamiento, coste y política de conservación del proveedor elegido.

### Zod

Zod es candidato para validar datos runtime que entran en Vue, especialmente
respuestas HTTP, formularios complejos y schemas del PageBuilder. Su valor será
mayor si el frontend adopta TypeScript.

La evaluación deberá comparar al menos:

- contratos PHP y Zod mantenidos de forma explícita y cubiertos por pruebas;
- JSON Schema como formato interoperable;
- OpenAPI o generación de clientes cuando existan contratos HTTP estables;
- validadores específicos para el modelo de bloques del PageBuilder.

No se duplicarán reglas de negocio completas en el navegador. Zod podrá mejorar
los mensajes y detectar contratos incompatibles, pero Laravel deberá rechazar
cualquier solicitud inválida independientemente del cliente utilizado.

## Criterios para promover una iniciativa

Una iniciativa puede pasar de `Candidata` a `Planificada` cuando:

1. existe una necesidad presente y un propietario claro;
2. se conoce el incremento funcional o técnico que la necesita;
3. se han comparado la capacidad nativa, la dependencia propuesta y la opción de
   posponerla;
4. se han identificado mantenimiento, rendimiento, privacidad, seguridad y coste;
5. pueden definirse pruebas y criterios de aceptación verificables;
6. se ha determinado si requiere Spec y, excepcionalmente, ADR;
7. existe un plan de retirada o sustitución razonable.

Al adoptarla se actualizará este registro con un enlace a la Spec, ADR o
documentación operativa responsable. Si se descarta, se conservará brevemente el
motivo y la condición que permitiría reevaluarla.

## Fuera de alcance actual

- Seleccionar proveedores, planes comerciales o regiones concretas.
- Instalar paquetes o modificar configuración ejecutable.
- Definir contratos API o schemas del PageBuilder todavía inexistentes.
- Establecer comandos de CI, hooks o despliegue antes de disponer del proyecto
  ejecutable.
- Afirmar que alguna de estas medidas está activa en el CMS.
