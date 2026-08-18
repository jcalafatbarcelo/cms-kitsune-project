# Estrategia de documentación

## Objetivo

Esta estrategia prepara la documentación del CMS para dos etapas: acompañar el
desarrollo y la defensa del TFM, y permitir después que usuarios, administradores,
desarrolladores de extensiones y contributors adopten el proyecto como software
open source.

Durante la fase inicial no se selecciona ni despliega un portal o una wiki. La
documentación canónica se mantiene como Markdown versionado junto al producto;
las herramientas de publicación y consulta se evaluarán cuando exista contenido
real y representativo.

## Audiencias

| Audiencia | Necesidades principales |
| :--- | :--- |
| Usuario o editor | Backoffice, páginas, traducciones, PageBuilder, assets, menús y publicación. |
| Administrador | Requisitos, instalación, configuración, despliegue, actualización, backups, seguridad y resolución de problemas. |
| Desarrollador de extensiones | Módulos, bloques, eventos, permisos, contratos públicos, compatibilidad y pruebas. |
| Contributor del core | Arquitectura, Specs, ADR, convenciones, calidad, contribución, seguridad y releases. |

Un documento puede atender a varias audiencias, pero debe evitar mezclar tareas
operativas, uso funcional y razonamiento arquitectónico sin una navegación clara.

## Fuentes y clases de documentación

| Clase | Ejemplos | Tratamiento |
| :--- | :--- | :--- |
| Funcional | Guías de idiomas, páginas, menús y PageBuilder | Canónica y revisada. |
| Operativa | Instalación, despliegue, actualización y backups | Canónica y versionada. |
| Extensibilidad | Módulos, bloques, eventos y contratos | Canónica y versionada. |
| Arquitectónica | Arquitectura, límites de módulos y ADR | Canónica según la finalidad de cada artefacto. |
| Contratos generados | OpenAPI, schemas, comandos y opciones de configuración | Derivada de fuentes deterministas. |
| Consulta asistida | Resúmenes, mapas y respuestas sobre el repositorio | Derivada, trazable y no normativa. |

Las Specs aprobadas prescriben el comportamiento deseado y los ADR aceptados
restringen las decisiones arquitectónicas. El código y las pruebas describen el
estado ejecutable. La documentación editorial explica ese producto a su
audiencia; una wiki, un índice o una respuesta generada no reemplaza ninguna de
esas fuentes.

## Principios de Documentation as Code

- Mantener la fuente canónica en `docs/` y revisarla mediante Git y pull requests.
- Documentar el comportamiento en el mismo cambio que lo implementa, salvo que
  una Spec aprobada delimite expresamente otro incremento.
- Distinguir de forma visible funcionalidad prevista, en desarrollo, disponible,
  deprecada y sin soporte.
- Usar Markdown portable, enlaces relativos y sintaxis independiente del portal
  cuando no exista una necesidad justificada.
- Evitar duplicación: enlazar a la fuente responsable en lugar de copiar reglas o
  contratos.
- Mantener ejemplos mínimos, seguros y verificables. No almacenar credenciales,
  datos personales ni valores reales de configuración.
- Crear secciones y directorios cuando exista contenido real; no anticipar una
  estructura mediante archivos vacíos.
- Tratar diagramas y capturas como apoyo. Las instrucciones deben seguir siendo
  comprensibles sin depender exclusivamente de una imagen.

## Organización prevista

La estructura podrá crecer de forma incremental:

```text
docs/
├── getting-started/
├── user-guide/
├── administration/
├── developers/
├── architecture/
├── modules/
├── api/
├── specs/
├── adr/
└── changelog/
```

`docs/specs/`, `docs/adr/` y `docs/changelog/` conservan sus flujos específicos.
La documentación ordinaria del producto se mantiene mediante la skill
`documentation-maintainer` cuando figure en `skills/INDEX.md`.

## Versionado y publicación futura

Cuando existan releases públicas, el portal deberá diferenciar como mínimo la
documentación de la rama en desarrollo, la versión estable y las versiones aún
soportadas. Las versiones fuera de soporte se identificarán de forma visible.
Los contratos, ejemplos y guías indicarán su versión cuando puedan diferir.

La plataforma de publicación se seleccionará cerca de la primera beta mediante
una comparación basada en contenido real. Deberá ofrecer:

- publicación reproducible desde el Markdown del repositorio;
- navegación accesible, búsqueda y enlaces estables;
- soporte de versiones y contribuciones mediante Git;
- portabilidad y una ruta de migración razonable;
- superficie operativa reducida y telemetría controlable;
- acceso a la documentación esencial sin depender de un proveedor de IA.

La elección concreta de un portal podrá requerir un ADR si introduce una
restricción arquitectónica duradera o transversal.

## Wikis y asistencia mediante IA

OpenWiki u otra herramienta equivalente podrá evaluarse como capa externa para:

- explorar semánticamente el repositorio;
- facilitar el onboarding de contributors;
- resumir módulos y localizar documentación;
- explicar relaciones entre componentes con referencias a sus fuentes;
- apoyar demostraciones y presentaciones.

No se utilizará como fuente única, para sustituir Specs o ADR, para publicar
instrucciones normativas sin revisión ni como dependencia necesaria del build o
runtime del CMS. Cualquier integración será desacoplada, reemplazable y de solo
lectura respecto al repositorio.

Antes de adoptar una solución se verificarán al menos:

1. licencia, actividad y mantenimiento;
2. compatibilidad con la licencia futura del CMS;
3. privacidad, telemetría y tratamiento del código por proveedores externos;
4. autenticación, autorización y aislamiento entre repositorios o versiones;
5. exclusión verificable de secretos y archivos sensibles;
6. trazabilidad de cada respuesta a archivos, versión y commit;
7. actualización reproducible y eliminación completa de índices;
8. coste, cuotas, latencia y degradación ante indisponibilidad;
9. precisión medida contra un conjunto de preguntas verificadas;
10. posibilidad de retirar la herramienta sin afectar al CMS ni a su
    documentación canónica.

## Hitos de adopción

1. **Desarrollo inicial:** mantener Markdown y actualizarlo con cada incremento;
   no seleccionar plataforma.
2. **Antes de la primera beta:** evaluar un portal convencional cuando la
   instalación, los módulos principales y los puntos de extensión sean estables.
3. **Tras consolidar la documentación canónica:** probar una wiki o capa de
   consulta inteligente contra preguntas verificadas y decidir con evidencia.

## Mantenimiento

Cada Spec funcional debe declarar su impacto documental. Una entrega comprobará
si afecta guías de usuario, administración, extensibilidad, contratos, migración,
material visual o versionado. Cuando no exista impacto, se justificará sin crear
documentación artificial.
