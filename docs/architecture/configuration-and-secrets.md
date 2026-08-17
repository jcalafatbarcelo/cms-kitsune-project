# Configuración y gestión de secretos

## Objetivo

Este documento establece cómo reproducir la configuración del CMS sin almacenar
información sensible en Git. Se aplica al desarrollo local, integración
continua, despliegues y producción.

## Principios

- Git conserva código, plantillas seguras y documentación, nunca valores
  secretos.
- Cada entorno obtiene sus secretos mediante un gestor de secretos o un
  mecanismo externo al repositorio.
- Los permisos siguen el principio de mínimo privilegio y las credenciales se
  separan por entorno y servicio.
- La documentación describe cómo obtener y configurar un valor sin copiarlo.

## Archivos y datos que no se versionan

No se incorporan al repositorio:

- archivos `.env` reales ni variantes que contengan credenciales;
- API keys, tokens, contraseñas, certificados o claves privadas;
- archivos de autenticación de proveedores cloud;
- volcados de base de datos, backups o datos de producción;
- logs que puedan contener sesiones, cabeceras o datos personales;
- cachés, dependencias, builds y artefactos generados localmente.

El `.gitignore` debe actualizarse al incorporar herramientas que generen estos
artefactos. Ignorar un archivo reduce el riesgo de añadirlo por accidente, pero
no sustituye la revisión del diff ni revoca un secreto ya expuesto.

## Plantillas y documentación de variables

Cuando se configure Laravel, `.env.example` será la plantilla versionable. Solo
incluirá nombres de variables, valores públicos o ejemplos ficticios seguros.
Para cada variable no evidente, la documentación correspondiente indicará:

| Dato | Descripción |
| :--- | :--- |
| Nombre | Identificador exacto de la variable. |
| Finalidad | Componente o integración que la consume. |
| Obligatoriedad | Entornos en los que es necesaria. |
| Formato | Tipo o estructura esperada, sin revelar un valor real. |
| Origen | Servicio o procedimiento externo para obtenerla. |
| Rotación | Responsable o mecanismo de renovación cuando aplique. |

Los ejemplos deben ser inequívocamente ficticios. No se copian secretos en
Specs, ADR, documentación, fixtures, mensajes de commit, pull requests ni
salidas de pruebas.

## Desarrollo, CI y producción

En desarrollo, cada persona o agente crea su configuración local a partir de la
plantilla y obtiene los valores por el canal autorizado. En CI y producción, la
plataforma inyecta los valores desde su gestor de secretos; no se generan
archivos con credenciales para versionarlos o compartirlos.

Las credenciales deben ser distintas entre entornos. Si un servicio permite
limitar permisos, origen, caducidad o cuota, se configura con el alcance mínimo
necesario.

## Comprobaciones antes de un commit

1. Revisar `git status` para detectar archivos nuevos inesperados.
2. Revisar el diff completo, incluidos archivos de configuración y fixtures.
3. Confirmar que las plantillas usan valores ficticios seguros.
4. Ejecutar el detector de secretos configurado en el proyecto, cuando exista.
5. Excluir dependencias, builds, logs, cachés y otros artefactos locales.

Una comprobación automatizada complementa, pero no reemplaza, esta revisión.

## Respuesta ante una exposición

Si una credencial alcanza Git, una salida pública o un canal no autorizado:

1. detener la entrega y evitar volver a mostrar su valor;
2. revocar o rotar la credencial inmediatamente;
3. revisar accesos y actividad potencialmente afectados;
4. retirar el dato del historial cuando proceda, coordinando la reescritura;
5. comunicar el incidente y las acciones realizadas sin reproducir el secreto;
6. corregir ignores, plantillas o controles para evitar la repetición.

Eliminar el archivo en un commit posterior no invalida una credencial ya
expuesta.
