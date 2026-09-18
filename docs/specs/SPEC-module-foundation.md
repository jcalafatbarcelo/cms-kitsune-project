# SPEC: Fundación de módulos Laravel

- **Estado:** Completada
- **Perfil:** feature
- **Origen de la planificación:** Petición del responsable del proyecto.
- **Spec relacionada:** No aplica.

## 1. Objetivo o problema

Incorporar `nwidart/laravel-modules` como infraestructura de monolito modular
para Laravel 13. Dejar preparado el descubrimiento y autoload de módulos
futuros, sin crear ninguno.

## 2. Contexto y evidencia

El SDD, README y ADR-0001 prescriben `nwidart/laravel-modules`, pero
`composer.json` aún no lo requiere. El proyecto usa Laravel 13.31, PHP 8.5 y
Pest 5. La versión estable `v13.0.0` es compatible con Laravel 13 y PHP 8.3+.

## 3. Alcance

- Añadir `nwidart/laravel-modules:^13.0` como dependencia de runtime.
- Autorizar explícitamente `wikimedia/composer-merge-plugin`.
- Configurar Composer para incluir `Modules/*/composer.json`.
- Usar la configuración predeterminada: namespace `Modules`, raíz `Modules/` y
  activador por archivo.
- Verificar que Laravel descubre el paquete y registra `module:list`.
- Actualizar README y CHANGELOG al estado implementado.

### Fuera de alcance

- Crear módulos, entidades, migraciones, rutas, vistas, assets o endpoints.
- Publicar o personalizar `config/modules.php`.
- Definir dependencias entre módulos, contratos, permisos o reglas de dominio.
- Integrar Vue, Vite o assets modulares.
- Implementar instalación o activación de módulos desde el backoffice.

### Alcance diferido

- Primer módulo de dominio: requiere una Spec que defina responsabilidad, datos,
  contratos y pruebas.
- Personalización de scaffolding: solo si el primer módulo demuestra una
  necesidad no cubierta por la configuración predeterminada.

## 4. *Clash check*

- SDD inicial: concreta el MUST de monolito modular; sin conflicto.
- ADR-0001: preserva Blade, Vue bajo demanda y módulos con nWidart; sin
  conflicto.
- Specs vigentes: solo gobiernan agentes y ADRs; sin conflicto.
- Código y pruebas: no existen módulos ni configuración previa incompatible.
- README: declara que el paquete no está instalado; requiere actualización.

## 5. Requisitos y bloques técnicos aplicables

### Módulos Laravel

- Composer debe resolver una versión estable compatible con Laravel 13.
- La ejecución del plugin de Composer debe estar permitida explícitamente.
- Cada módulo futuro podrá aportar `Modules/<Nombre>/composer.json`; Composer
  deberá incluirlo al regenerar el autoload.
- No se creará `Modules/` manualmente ni un módulo vacío.

### Datos, API, Vue 3 y PageBuilder

No aplicable: el incremento no introduce persistencia, contratos HTTP,
componentes frontend ni schemas.

### Seguridad y entrega

- No incorporar secretos ni valores de entorno.
- Revisar la dependencia directa, el tránsito
  `wikimedia/composer-merge-plugin`, compatibilidad Laravel/PHP y notas de la
  versión instalada.
- Mantener `composer.lock` reproducible y válido.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- TDD: añadir primero una prueba de integración que requiera `module:list`; debe
  fallar antes de instalar la dependencia.
- La prueba debe verificar versión instalada, configuración de merge y ejecución
  correcta de `module:list`.
- Ejecutar validación, instalación reproducible, formato, suite, auditoría
  Composer y build frontend según la política de dependencias.

### Riesgos aceptados

- El plugin de Composer ejecuta código. Se acepta porque el paquete lo requiere,
  se autoriza explícitamente y se limita al paquete oficial revisado.

### Deuda técnica

- No aplica.

## 7. Criterios de aceptación

- **CA-01:** Composer instala una versión estable de
  `nwidart/laravel-modules` compatible con Laravel 13 y PHP 8.3+.
- **CA-02:** Composer permite el plugin requerido e incluye los manifiestos
  `Modules/*/composer.json` al regenerar el autoload.
- **CA-03:** Laravel registra `module:list` y el comando termina correctamente
  sin módulos de dominio creados.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Incompatibilidad runtime | Integración | Versión instalada compatible | README, CHANGELOG |
| CA-02 | Módulos futuros sin autoload | Integración | Configuración Composer verificada | No aplica: configuración autoexplicativa |
| CA-03 | Paquete no descubierto | Integración | `module:list` termina correctamente | README |

## 9. Plan de implementación

1. Añadir la prueba de integración para la fundación modular y confirmar rojo.
2. Instalar la dependencia y configurar el merge y plugin de Composer.
3. Regenerar autoload, confirmar verde y aplicar formato.
4. Actualizar README y CHANGELOG al finalizar.
5. Ejecutar las validaciones de dependencia y quality gate aplicables.

## 10. Decisiones abiertas

- No aplica.
