# Perfiles de contenido técnico

Usar esta referencia para seleccionar contenido, no para añadir secciones
artificiales. El núcleo de la plantilla siempre se conserva; estos bloques se
incorporan dentro de `Requisitos y bloques técnicos aplicables` solo cuando el
alcance los active.

## Perfil `feature`

Aplicar a capacidades nuevas o cambios de comportamiento esperado. Revisar:

| Área | Contenido mínimo cuando aplique |
| :--- | :--- |
| Dominio | Actores, reglas, invariantes, estados, transiciones, precedencias, límites y errores. |
| Datos | Entidades, relaciones, cardinalidad, nulabilidad, unicidad, índices, eliminación, migración, backfill y rollback. |
| API | Método, ruta, autenticación, autorización, request, response, validación, códigos de error, idempotencia y compatibilidad. |
| Módulos Laravel | Módulo responsable, dependencias permitidas, contratos, eventos y efectos secundarios. |
| Vue 3 | Estado, Composition API, props/emits, carga, error, accesibilidad y contrato con backend. |
| PageBuilder | Schema JSON, versión, campos traducibles, validación, compatibilidad y renderizado Blade sin regex. |
| Seguridad | Actores, permisos, exposición de datos, entradas no confiables, abuso, auditoría y secretos. |
| Asincronía | Cola, reintentos, idempotencia, orden, fallos parciales y observabilidad. |
| Integraciones | Timeouts, errores, cuotas, credenciales, degradación, reemplazabilidad y datos compartidos. |
| Entrega | Compatibilidad hacia atrás, despliegue, migración, rollback y documentación de configuración. |

No fijar patrones, clases privadas o abstracciones si no son parte necesaria del
contrato o de una restricción arquitectónica vigente.

## Perfil `maintenance`

Usar únicamente si se restaura un comportamiento ya definido. Exigir:

1. fuente del comportamiento esperado: Spec, prueba, contrato, documentación o
   regla de negocio vigente;
2. reproducción mínima con precondiciones, acción, resultado actual y esperado;
3. alcance localizado y componentes afectados;
4. confirmación de que no cambian reglas, datos ni contratos públicos; si
   cambian, reclasificar como `feature`;
5. riesgo de regresión, compatibilidad y efectos secundarios;
6. criterio de aceptación por cada variante corregida;
7. prueba de regresión en el nivel más bajo que aporte confianza suficiente;
8. impacto documental, normalmente `No aplica` cuando se restaura lo ya
   documentado, con esa justificación expresa.

Una causa raíz puede declararse desconocida en borrador. No inventarla. Antes de
aprobar debe existir evidencia suficiente para acotar la reparación, aunque la
Spec no prescriba el detalle interno de la solución.

## Calidad y seguridad diferibles

Puede diferirse complejidad evolutiva como optimización avanzada, proveedores
secundarios, automatización adicional, extensibilidad especulativa o telemetría
no esencial. Registrar motivo, riesgo residual y condición para retomarla.

No diferir garantías necesarias para el comportamiento entregado: autorización,
integridad, validación, confidencialidad, manejo de errores previsibles,
migración segura, ausencia de secretos y pruebas de los criterios aplicables.
