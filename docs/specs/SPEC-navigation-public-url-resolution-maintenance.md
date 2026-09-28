# SPEC: Mantenimiento de resolución pública por lote para Navigation

- **Estado:** Completada
- **Perfil:** maintenance
- **Origen de la planificación:** Revisión previa a Pull Request de la fundación
  de Navigation.
- **Spec relacionada:** [SPEC-navigation-foundation](SPEC-navigation-foundation.md),
  completada.

## 1. Objetivo o problema

Restaurar un coste de resolución proporcional a la profundidad de las Pages y no
al número de ítems de menú, preservando exactamente las URLs canónicas y el
filtrado de disponibilidad pública definidos para Navigation.

## 2. Contexto y evidencia

**Fuente del comportamiento esperado:**
[ADR-0005](../adr/ADR-0005-contrato-url-publica-pages-navigation.md),
`SPEC-navigation-foundation` y `SPEC-public-localized-routes`.

**Pasos de reproducción:** crear un menú con varios ítems localizados que apunten
a Pages distintas y resolverlo públicamente.

**Resultado actual:** `PublicMenuResolver` invoca `forPage()` por cada ítem.
Cada invocación vuelve a consultar idioma, configuración, home, traducción y
ancestros, multiplicando consultas al crecer el menú.

**Resultado esperado:** Navigation solicita las URLs de todos los `page_id`
únicos del menú en una operación de Pages. La resolución comparte idioma,
configuración, homes, traducciones y ancestros, sin cambiar ítems visibles, URLs
ni respuestas HTTP.

## 3. Alcance

- Añadir una operación de lectura por lote al contrato de URLs públicas de Pages.
- Mantener `forPage()` como envoltorio compatible de la operación por lote.
- Hacer que Navigation resuelva los destinos únicos de un menú con una única
  operación de contrato antes de formar el árbol filtrado.
- Añadir regresiones que demuestren que añadir ítems de la misma profundidad no
  multiplica las consultas de Pages por ítem.
- Actualizar ADR-0005, documentación y changelog para reflejar el contrato real.

### Fuera de alcance

- Cambiar las reglas de disponibilidad, publicación, canonicidad, home, prefijos,
  idioma efectivo, estructura de menú o presentación Blade.
- Añadir caché persistente, caché compartida, invalidación, colas, APIs, nuevos
  destinos de menú o cambios de esquema.

### Alcance diferido

No aplica: una necesidad medida de caché o de metadatos adicionales requerirá un
incremento posterior.

## 4. *Clash check*

- ADR-0005: amplía el mismo servicio de lectura sin transferir a Navigation la
  autoridad sobre URLs o disponibilidad. No hay conflicto.
- SPEC-navigation-foundation: conserva Page como único destino, los árboles por
  idioma y el filtrado de subárboles. No hay conflicto.
- SPEC-public-localized-routes: conserva la URL canónica, la home con barra final
  y la ausencia de fallback de contenido. No hay conflicto.
- Código y pruebas actuales: el lote sustituye consultas repetidas por datos
  equivalentes dentro de Pages; no hay migración ni contrato HTTP nuevo. No hay
  conflicto.

## 5. Requisitos y bloques técnicos aplicables

### Contrato y lógica crítica

No cambian reglas de negocio, modelo de datos ni contrato HTTP público: se
optimiza el contrato interno entre Pages y Navigation.

- Pages expone `forPages(array $pageIds, string $locale): array<int, string>`.
  El resultado contiene solo pares `page_id => URL canónica` de Pages públicas
  para el locale solicitado; IDs no disponibles se omiten.
- `forPage(int $pageId, string $locale): ?string` conserva su resultado y delega
  en la operación por lote.
- La operación carga una vez por lote el idioma activo, la configuración de
  frontend, la home y los datos necesarios de traducciones y ancestros.
- Navigation deduplica los `page_id` antes de invocar el lote y no consulta
  modelos internos de Pages.
- El lote no lee sesión, headers ni decide respuestas HTTP.

### Datos, API, Vue, PageBuilder e integración externa

No aplicable: no hay migraciones, API HTTP, Vue, PageBuilder, colas ni servicios
externos.

### Seguridad y validación

- El lote no devuelve URLs de traducciones ausentes, inactivas o no publicables.
- Los IDs se tratan como referencias internas; no se usan para componer paths,
  vistas, clases ni consultas SQL sin parámetros.
- No se registra el menú completo, labels ni datos editoriales durante la
  resolución.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Pruebas de integración para equivalencia entre `forPage()` y `forPages()`.
- Pruebas de Navigation para destinos repetidos, destinos no disponibles y
  subárboles filtrados con resolución por lote.
- Prueba de consultas que compare uno y varios ítems de igual profundidad y
  demuestre que el coste de resolución no crece linealmente por ítem.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build y matriz SQLite, MySQL
  8.4 y MariaDB 11.4.

### Riesgos aceptados

No aplica.

### Deuda técnica

No aplica. La caché persistente queda fuera de alcance hasta disponer de una
medición que la justifique.

## 7. Criterios de aceptación

- **CA-01:** `forPages()` devuelve exclusivamente las mismas URLs canónicas que
  `forPage()` para Pages públicas y omite destinos no disponibles.
- **CA-02:** Resolver un menú deduplica destinos e invoca una única resolución
  por lote, preservando el árbol, el orden y el filtrado de subárboles.
- **CA-03:** Añadir ítems de la misma profundidad no multiplica consultas de
  idioma, configuración, home, traducción ni ancestros por cada ítem.
- **CA-04:** La optimización no expone contenido no público, no altera idioma,
  URLs, sesión, cabeceras ni el contrato HTTP.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | URL o disponibilidad divergente | Integración | Resultados individuales y por lote equivalentes | Contrato de Pages |
| CA-02 | N+1 o árbol alterado | Integración | Una llamada por lote y árbol equivalente | Navigation |
| CA-03 | Degradación lineal de consultas | Integración/BD | Conteo acotado al crecer ítems | Rendimiento interno |
| CA-04 | Exposición o regresión HTTP | Integración/HTTP | Destinos no públicos omitidos y contrato intacto | ADR-0005 |

## 9. Plan de implementación

1. Crear pruebas rojas de equivalencia y conteo de consultas; implementar el lote
   de Pages hasta CA-01 y CA-03.
2. Crear pruebas rojas de Navigation; consumir el lote y deduplicar destinos hasta
   CA-02 y CA-04.
3. Ejecutar matriz, quality gates, documentación y actualización de ADR antes de
   completar la Spec.

## 10. Decisiones abiertas

No aplica. El lote en memoria sustituye cualquier caché persistente o contrato de
metadatos hasta que exista una necesidad medida.
