# ADR-0003: Baseline moderna de bases de datos

- **Fecha:** 2026-09-20 00:52 UTC
- **Última actualización:** 2026-09-20 00:52 UTC
- **Estado:** Aceptado
- **Autores:** Responsable del proyecto y OpenCode (asistencia de redacción)
- **Reemplaza a:** No aplica
- **Reemplazado por:** No aplica

## Ámbito e impacto transversal

Componentes afectados: persistencia Eloquent, migraciones, pruebas de
integración, CI, instalación y despliegue del CMS.

Restricción transversal: el proyecto solo soporta series modernas y mantenidas
de MySQL y MariaDB en producción. SQLite se conserva como motor de desarrollo y
pruebas con una versión mínima moderna, pero no se ofrece como persistencia de
producción.

Fuera de alcance: seleccionar un proveedor de hosting o base de datos gestionada,
definir topologías de alta disponibilidad y cambiar Eloquent como capa de
persistencia.

## Contexto

El SDD inicial prevé MySQL o MariaDB para despliegues LAMP/LEMP y la configuración
de desarrollo usa SQLite. Laravel admite versiones anteriores de esos motores,
pero ese rango del framework no constituye por sí mismo el contrato de soporte
de Kitsune.

Mantener compatibilidad con motores antiguos ampliaría las variantes de SQL,
migraciones y pruebas, y podría impedir usar garantías de integridad disponibles
en versiones actuales. El proyecto no necesita adaptarse a hostings compartidos
que hayan retirado esas versiones. El responsable del proyecto ha decidido
priorizar versiones modernas y mantenidas.

MySQL documenta `8.4` como serie LTS con cinco años de soporte principal y tres
de soporte extendido. MariaDB publica `11.4` como serie de largo plazo. SQLite no
mantiene series LTS equivalentes y su versión efectiva depende de la biblioteca
con la que PHP haya enlazado `pdo_sqlite`.

## Restricciones

- Técnicas: Laravel 13, PHP 8.3 o posterior, Eloquent ORM y SQL portable entre
  los motores soportados. Las versiones se verifican contra el servidor o la
  biblioteca realmente usados, no contra la versión del cliente PDO.
- Funcionales: las invariantes persistentes deben ofrecer la misma garantía en
  todos los motores declarados por la Spec que las introduzca.
- Temporales: la matriz multi-motor se incorporará cuando LOC-01 introduzca las
  primeras tablas de dominio y pruebas de portabilidad reales.
- Económicas: no se mantiene infraestructura para versiones legacy ni para
  motores adicionales sin una necesidad aprobada.
- Equipo: cada actualización de baseline debe acompañarse de pruebas, notas de
  migración cuando procedan y revisión de compatibilidad de Laravel y drivers.

## Decisión

1. Soportar MySQL `8.4.x LTS` como baseline de producción y ejecutar siempre un
   patch que siga recibiendo correcciones del proveedor.
2. Soportar MariaDB `11.4.x LTS` como baseline de producción y ejecutar siempre
   un patch que siga recibiendo correcciones del proveedor.
3. Exigir SQLite `3.45.0` o posterior para desarrollo y pruebas. La distribución
   o runtime que lo suministre debe seguir aplicando actualizaciones de seguridad.
4. No soportar SQLite como persistencia de producción, MySQL anterior a `8.4`,
   MariaDB anterior a `11.4`, ni ramas Innovation o rolling por defecto.
5. Tratar el número de serie como mínimo funcional, no como permiso para operar
   un patch vulnerable u obsoleto. Instalaciones y CI deben usar el último patch
   mantenido disponible de la serie elegida. CI fijará la versión exacta y, si
   usa una imagen, su digest inmutable; las actualizaciones se revisarán de forma
   controlada en lugar de depender de una etiqueta flotante.
6. Verificar en CI la versión efectiva de cada motor antes de ejecutar sus
   pruebas. Una versión fuera de la baseline debe fallar de forma explícita.
7. Ejecutar las pruebas de migraciones e integridad dependientes del motor en
   SQLite, MySQL y MariaDB. Una prueba que solo pase en SQLite no demuestra
   compatibilidad de producción.
8. Revisar esta baseline antes de cada release mayor o menor de Kitsune y antes
   de que una serie seleccionada deje de recibir soporte. Cambiar una serie
   requiere actualizar este ADR o reemplazarlo, adaptar CI y documentar la ruta
   de actualización.

## Criterios de decisión

1. Mantener soporte activo y correcciones de seguridad del proveedor.
2. Reducir diferencias de comportamiento entre desarrollo, CI y producción.
3. Demostrar portabilidad con ejecución real, no mediante compatibilidad teórica
   de Laravel.
4. Evitar complejidad y deuda destinadas únicamente a infraestructura legacy.
5. Preferir series estables de largo plazo frente a cadencias de innovación.

## Consecuencias positivas

- Las migraciones pueden depender de garantías actuales y verificables de
  integridad sin conservar alternativas para motores legacy.
- La matriz de CI detectará diferencias reales entre SQLite, MySQL y MariaDB.
- Las series LTS reducen cambios de comportamiento durante su ciclo de soporte.
- El contrato de instalación es más estrecho y explícito que el rango general
  admitido por Laravel.

## Consecuencias negativas

- Un hosting que solo ofrezca MySQL o MariaDB anteriores queda fuera de soporte;
  la mitigación es usar un plan, contenedor o servicio con una serie admitida.
- La matriz multi-motor aumentará duración y mantenimiento de CI; se limitará a
  pruebas de persistencia e integración que aporten evidencia específica.
- El último patch disponible puede cambiar sin modificar la serie; CI deberá
  actualizar su referencia fijada y registrar la versión efectiva para que cada
  ejecución sea reproducible y auditable.
- SQLite no reproduce exactamente el comportamiento de producción, por lo que
  no puede ser el único motor del quality gate cuando existan migraciones de
  dominio.

## Alternativas consideradas

### Adoptar todos los mínimos admitidos por Laravel

Descripción: soportar las versiones mínimas de SQLite, MySQL y MariaDB que
Laravel pueda utilizar.

Ventajas: mayor compatibilidad con hostings existentes y menor barrera inicial
de instalación.

Desventajas: amplía la matriz, conserva limitaciones de motores antiguos y hace
que el contrato dependa de una política de framework con objetivos distintos.

Motivo de descarte: Kitsune no necesita soporte legacy y su coste reduciría la
calidad de las garantías persistentes sin beneficio actual.

### Soportar solo un motor de producción

Descripción: seleccionar MySQL o MariaDB y retirar el otro.

Ventajas: menor matriz de pruebas y menos diferencias de dialecto.

Desventajas: contradice el objetivo vigente de desplegar sobre ambos ecosistemas
y reduce opciones modernas de operación sin evidencia que lo justifique.

Motivo de descarte: ambos motores son objetivos explícitos del proyecto y pueden
probarse con un coste proporcional cuando aparezca persistencia real.

### Seguir ramas de innovación o rolling

Descripción: adoptar siempre la rama con funcionalidades más recientes.

Ventajas: acceso temprano a mejoras del motor.

Desventajas: ciclos de soporte más cortos, cambios de comportamiento más
frecuentes y mantenimiento continuo de compatibilidad.

Motivo de descarte: una serie LTS moderna ofrece las garantías necesarias con
menor volatilidad para el CMS.

## Revisión futura

Fecha de revisión o condición observable: antes de cada release mayor o menor de
Kitsune, al anunciarse el fin de soporte de MySQL `8.4` o MariaDB `11.4`, o si la
baseline de SQLite deja de estar disponible en los runners soportados, lo que
ocurra antes.

Evidencia a evaluar: calendarios oficiales de soporte, advisories de seguridad,
versiones de Laravel y PDO, resultados de la matriz multi-motor, disponibilidad
en proveedores objetivo y coste de migración a la siguiente serie LTS.

Referencias oficiales:

- [Modelo de releases LTS de MySQL](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html).
- [Política de mantenimiento de MariaDB](https://mariadb.org/about/#maintenance-policy).
- [Historial de releases de SQLite](https://www.sqlite.org/chronology.html).
