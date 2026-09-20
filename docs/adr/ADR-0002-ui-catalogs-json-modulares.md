# ADR-0002: UI catalogs JSON modulares

- **Fecha:** 2026-09-19 23:23 UTC
- **Última actualización:** 2026-09-19 23:23 UTC
- **Estado:** Aceptado
- **Autores:** Responsable del proyecto y OpenCode (asistencia de redacción)
- **Reemplaza a:** No aplica
- **Reemplazado por:** No aplica

## Ámbito e impacto transversal

Componentes afectados: Core, módulos, templates futuros, localización de
frontend y backoffice, overrides, paquetes de idioma, validación y herramientas
de extensibilidad.

Restricción transversal: todos los textos estáticos del CMS se definen mediante
`UI catalogs` JSON no ejecutables, con claves técnicas estables y propietario
explícito. Core, módulos y templates aplican el mismo contrato aunque sus rutas y
ciclos de vida se concreten en Specs diferentes.

Fuera de alcance: contenido editorial, `PageTranslation`, valores por instancia
del PageBuilder, persistencia de overrides, administración web, rutas localizadas
y diseño definitivo del instalador dinámico de paquetes.

## Contexto

El CMS necesita traducir login, validaciones, alertas, backoffice y otros textos
de interfaz que no forman parte del contenido editorial. El monolito modular
permite que Core, módulos custom y templates incorporen textos propios. También
se prevé distribuir idiomas como paquetes en el futuro, aunque esa instalación
queda fuera del MVP y del TFM.

Los archivos PHP de Laravel son idiomáticos para contenido versionado, pero no
son adecuados como artefacto futuro de instalación dinámica porque ejecutarían
código. YAML requeriría una dependencia y un parser adicionales. El JSON nativo
de Laravel es no ejecutable, editable, validable y admite búsqueda por clave
exacta, placeholders y pluralización.

La decisión fue aceptada durante la planificación por el responsable del
proyecto al aprobar JSON como formato común, `en` como base completa, los modos
de fallback `base` y `key`, y el mismo modelo conceptual para Core, módulos y
templates.

## Restricciones

- Técnicas: Laravel 13, Eloquent, `nwidart/laravel-modules` 13 y JSON UTF-8. No se
  parsea Blade ni se ejecutan `UI catalogs` como PHP, plantillas o JavaScript.
- Funcionales: `en` es el `UI catalog` base obligatorio; una clave no declarada por
  el `UI catalog` base de su propietario no existe para el sistema.
- Temporales: el MVP admite alta manual; descarga, subida e instalación dinámica
  se aplazan fuera del TFM.
- Económicas: no se requiere servicio de traducción, repositorio de paquetes ni
  infraestructura externa.
- Equipo: el formato debe ser legible y mantenible manualmente, validable en CI y
  reutilizable por un instalador futuro sin migración de contenido.

## Decisión

1. Denominar `UI catalog` al diccionario clave-valor de textos estáticos de
   interfaz. En documentación española se puede aclarar como “catálogo de
   interfaz”, pero el término normativo es `UI catalog`.
2. Representar cada `UI catalog` como un objeto JSON plano cuyos valores son
   strings. No admitir código ejecutable ni HTML en el alcance inicial.
3. Usar claves técnicas independientes del texto inglés con formato
   `<owner>::<group>.<item>`, por ejemplo `core::auth.login.submit`.
4. Asignar cada clave a un propietario normalizado. Un propietario puede ser
   Core, un módulo o un template. Compartir contrato no exige una abstracción ni
   un ciclo de vida común antes de que exista esa necesidad.
5. Exigir que todo propietario aporte un `UI catalog` `en` con todas sus claves. Una
   clave presente solo en otro locale se ignora en runtime y la validación la
   trata como error.
6. Exigir completitud respecto de `en` cuando un propietario declare un `UI catalog`
   para otro locale. Un módulo o template puede omitir por completo locales
   distintos de `en`.
7. Permitir que los futuros overrides traduzcan claves válidas para locales no
   distribuidos por el propietario. Un override no puede crear una clave que no
   exista en su `UI catalog` `en`.
8. Mantener `en` siempre instalado como idioma base. La resolución admite dos
   modos configurables: `base`, que consulta `en` antes de devolver la clave, y
   `key`, que omite ese fallback.
9. Usar por defecto `base` en producción y `key` en desarrollo, testing y E2E,
   permitiendo configuración explícita en cualquier entorno. Una clave
   definitivamente ausente se devuelve de forma literal en lugar de interrumpir
   el renderizado.
10. Diseñar el manifiesto y el JSON del alta manual para que un futuro paquete
    dinámico reutilice el mismo contrato de datos. El instalador futuro no podrá
    ejecutar archivos aportados por el paquete.
11. Mantener los `UI catalogs` junto a su propietario cuando su arquitectura esté
    disponible. Para módulos se utilizará la convención `Resources/lang`; para
    templates se conservará `Resources/lang` bajo la raíz que defina su futura
    Spec y se registrarán en el traductor de forma equivalente a los módulos, sin
    una arquitectura de traducción separada ni prefijos anticipados como
    `template_` o `tmp_`.

## Criterios de decisión

1. Evitar ejecución de código en artefactos que puedan instalarse en el futuro.
2. Conservar propiedad y fronteras entre Core, módulos y templates.
3. Detectar claves faltantes o huérfanas de forma automatizable.
4. Mantener edición manual sencilla durante el MVP.
5. Reutilizar capacidades de Laravel sin impedir distribución independiente.
6. Permitir degradación segura en producción y diagnóstico visible en pruebas.

## Consecuencias positivas

- El formato versionado y el futuro formato instalable comparten la misma
  representación no ejecutable.
- Las claves estables no cambian cuando se corrige o sustituye el texto inglés.
- La propiedad explícita reduce colisiones entre extensiones.
- Los módulos y templates pueden distribuir solo `en` sin bloquear otros idiomas
  instalados en el CMS.
- El modo `key` hace visibles las ausencias que el fallback inglés ocultaría.
- La completitud por propietario permite validar soporte declarado sin exigir a
  todas las extensiones todos los idiomas del sitio.

## Consecuencias negativas

- El formato plano es más repetitivo que arrays PHP anidados; se mitiga con una
  convención de claves y validación automática.
- Laravel documenta JSON principalmente para usar el texto base como clave. El
  proyecto utilizará claves técnicas exactas, comportamiento soportado por el
  traductor pero que debe protegerse con pruebas de integración al actualizar
  Laravel.
- Los `UI catalogs` de extensiones ausentes producirán inglés o claves visibles según
  el modo elegido. Los overrides permitirán traducciones locales sin modificar
  la extensión.
- Un instalador futuro deberá registrar ubicaciones externas, validar manifiestos
  y resolver conflictos de propietarios. Esa complejidad se aplaza hasta que
  exista distribución dinámica real.
- Los templates aún no tienen raíz ni ciclo de vida definidos. Su Spec deberá
  integrar el contrato sin cambiar el formato ni anticipar una abstracción vacía.

## Alternativas consideradas

### Arrays PHP nativos de Laravel

Descripción: usar archivos PHP agrupados y namespaced para todos los `UI catalogs`.

Ventajas: integración idiomática, arrays anidados y soporte directo de Laravel y
nWidart.

Desventajas: un paquete dinámico que aporte PHP introduce ejecución de código;
mantener después un formato instalable distinto exigiría conversión o dos fuentes
de verdad.

Motivo de descarte: no satisface el objetivo de reutilizar de forma segura el
mismo contrato en paquetes futuros.

### YAML

Descripción: definir `UI catalogs` y manifiestos en YAML.

Ventajas: legibilidad para estructuras jerárquicas y soporte de comentarios.

Desventajas: Laravel no lo usa como formato de traducción nativo, requiere parser
y configuración adicionales y amplía la superficie de validación.

Motivo de descarte: JSON cubre la necesidad con menor infraestructura y mejor
integración.

### Textos ingleses como claves JSON

Descripción: seguir la convención habitual de Laravel donde el texto base es la
clave del JSON.

Ventajas: vistas legibles y uso directo del estilo documentado por Laravel.

Desventajas: corregir el texto inglés cambia la identidad de la clave, complica
overrides y acopla contenido visible con contratos de extensiones.

Motivo de descarte: las claves técnicas son más estables para un CMS modular y
para datos persistidos en overrides.

### UI catalogs completos obligatorios en todas las extensiones

Descripción: exigir a cada módulo y template todos los idiomas instalados.

Ventajas: ninguna pantalla mostraría fallback por ausencia conocida.

Desventajas: acopla extensiones al estado de cada instalación, impide distribución
independiente y traslada una carga ilimitada a sus autores.

Motivo de descarte: se exige `en` y completitud solo para locales que el
propietario declare; los overrides cubren necesidades locales adicionales.

## Revisión futura

Fecha de revisión o condición observable: al implementar el primer módulo custom,
el primer template o la instalación dinámica de paquetes de idioma, lo que ocurra
antes.

Evidencia a evaluar: integración real del loader JSON con Laravel, colisiones de
claves, coste de validación, tamaño y rendimiento de `UI catalogs`, necesidades de
pluralización, seguridad de paquetes, conflictos entre `UI catalogs` y overrides, y
suficiencia del contrato uniforme para módulos y templates.
