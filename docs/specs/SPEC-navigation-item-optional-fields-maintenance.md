# SPEC: Mantenimiento de campos opcionales de ítems de menú

- **Estado:** Completada
- **Perfil:** maintenance
- **Origen de la planificación:** Error reproducido por el responsable del
  proyecto al crear un ítem de menú desde `/admin/menus`.
- **Spec relacionada:** [SPEC-pages-backoffice](SPEC-pages-backoffice.md),
  completada.

## 1. Objetivo o problema

Restaurar la creación y el movimiento de ítems de menú cuando las entradas HTTP
opcionales `parent_id` y `position` no se envían. La gestión de menús definida
por la Spec relacionada debe aceptar ítems raíz y posiciones asignadas por el
dominio sin producir errores 500.

## 2. Contexto y evidencia

**Fuente del comportamiento esperado:**
[SPEC-pages-backoffice](SPEC-pages-backoffice.md), que incluye gestionar menús e
ítems desde el backoffice y exige errores de validación controlados.

**Pasos de reproducción:** autenticarse como superadministrador y enviar
`POST /admin/menus/items` con `menu`, `locale`, `page_id` y `label`, omitiendo
`parent_id` y `position`.

**Resultado actual:** `MenuController::storeItem()` accede a índices inexistentes
en los datos validados y responde 500. `moveItem()` presenta el mismo riesgo para
un `parent_id` omitido.

**Resultado esperado:** los campos opcionales ausentes se interpretan como
`null`, se delegan a `MenuManager` conforme a su contrato existente y la petición
redirige al listado de menús.

## 3. Alcance

- Normalizar a `null` los campos opcionales ausentes antes de invocar
  `MenuManager::createItem()` y `MenuManager::moveItem()`.
- Añadir regresiones HTTP para crear un ítem sin padre ni posición y mover un ítem
  sin padre.

### Fuera de alcance

- Cambiar la jerarquía de menús, el algoritmo de posiciones, la validación de los
  campos presentes, los permisos, las rutas o los contratos de `MenuManager`.
- Añadir migraciones, persistencia nueva, APIs públicas, Vue, PageBuilder,
  asincronía o integraciones externas.

### Alcance diferido

No aplica: la corrección restaura el contrato existente de gestión de ítems.

## 4. *Clash check*

- **SDD inicial:** conserva los menús como árboles visuales independientes; no
  altera URLs, publicación ni contenido. No hay conflicto.
- **SPEC-pages-backoffice:** restaura la gestión de ítems y el manejo controlado
  de entradas opcionales sin ampliar su alcance. No hay conflicto.
- **Código y pruebas:** la validación Laravel puede omitir claves `nullable` no
  enviadas; el acceso directo contradice ese comportamiento. No hay conflicto
  irresoluble.
- **Documentación:** la guía ya describe la gestión de menús, sin prometer que
  los campos opcionales deban enviarse. No requiere cambio.

## 5. Requisitos y bloques técnicos aplicables

### HTTP y dominio

- `parent_id` y `position` conservan su validación `nullable` cuando se reciben.
- Una clave opcional ausente equivale a `null`; no se convierte a `0` ni provoca
  acceso a un índice inexistente.
- La creación delega los valores normalizados a `MenuManager::createItem()`.
- El movimiento delega el padre normalizado a `MenuManager::moveItem()`.
- No cambian los datos, contratos públicos, reglas de jerarquía ni posiciones.

### Seguridad y validación

- Las rutas mantienen autenticación de superadministrador y protección CSRF.
- Los valores presentes continúan sujetos a validación entera; la normalización no
  permite ignorar entradas inválidas.

### Datos, API, Vue 3, PageBuilder, asincronía e integraciones

No aplicable: esta reparación es local al controlador HTTP y no modifica esos
componentes.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Pruebas HTTP autenticadas que reproduzcan ambos campos omitidos y verifiquen la
  redirección correcta y el estado persistido esperado.
- Ejecutar primero las pruebas enfocadas y después la suite afectada y Pint.

### Riesgos aceptados

No aplica.

### Deuda técnica

No aplica.

## 7. Criterios de aceptación

- **CA-01:** Un superadministrador crea un ítem de menú omitiendo `parent_id` y
  `position`; la petición no responde 500, redirige al listado y persiste un ítem
  raíz con la posición asignada por el dominio.
- **CA-02:** Un superadministrador mueve un ítem omitiendo `parent_id`; la
  petición no responde 500, redirige al listado y el ítem queda como raíz en la
  posición solicitada.
- **CA-03:** Los campos opcionales presentes e inválidos siguen recibiendo la
  respuesta de validación de Laravel; no se relajan las reglas de entrada.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Error 500 al crear un ítem raíz | HTTP | Redirección y valores persistidos | No aplica: restaura la guía existente |
| CA-02 | Error 500 al mover un ítem a raíz | HTTP | Redirección y padre nulo persistido | No aplica: restaura la guía existente |
| CA-03 | Bypass de validación por normalización | HTTP | Entrada inválida rechazada | No aplica: no cambia contrato |

## 9. Plan de implementación

1. Añadir pruebas HTTP rojas para la creación y el movimiento con campos
   opcionales omitidos.
2. Normalizar las claves opcionales ausentes en el controlador hasta satisfacer
   CA-01 a CA-03.
3. Ejecutar pruebas enfocadas, suite afectada, Pint y revisión del diff.

## 10. Decisiones abiertas

No aplica.
