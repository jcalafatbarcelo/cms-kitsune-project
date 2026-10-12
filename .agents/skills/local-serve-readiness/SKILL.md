---
name: local-serve-readiness
description: "Dejar un entorno local o de pruebas listo para servir el CMS sin errores 503, aplicando las migraciones pendientes, la sincronización de templates y las comprobaciones de instalación vigentes; no aplica a producción, no modifica código, comportamiento, Specs, ADR ni configuración de producto, y no sustituye los quality gates. Usar cuando el usuario pida preparar, arrancar o dejar listo el proyecto para servirlo y probarlo en local o testing, o solicite aplicar migraciones, sincronizar templates y ejecutar las comprobaciones previas al serve; no usar para despliegue en producción ni para cambios funcionales."
---

# Preparar el entorno local para servir

## Objetivo

Dejar un entorno `local` o `testing` listo para servir y probar el CMS sin
errores 503, recorriendo de forma idempotente las migraciones, la sincronización
de templates y las comprobaciones de instalación vigentes.

## Contexto del proyecto

- Aplica solo a `APP_ENV` `local` o `testing`; nunca a producción.
- Releer antes de actuar las fuentes de verdad aplicables:
  - `docs/getting-started/installation.md`;
  - `docs/administration/secure-deployment.md`;
  - `docs/administration/first-superadministrator.md`;
  - `composer.json` (scripts `setup` y `dev`) y `.env.example`.
- El `503` del sitio proviene de la resolución de presentaciones: si el template
  Base no está sincronizado con su manifiesto o falta su registro/hash, la web
  responde `503`. `php artisan cms:template:sync` lo corrige y es idempotente.

## Flujo de trabajo

1. Confirmar que la raíz es el proyecto del CMS y que `APP_ENV` es `local` o
   `testing`. Si falta `.env`, crearlo desde `.env.example`.
2. Si `APP_KEY` está vacío, ejecutar `php artisan key:generate`.
3. Crear `database/database.sqlite` si falta cuando `DB_CONNECTION=sqlite`.
4. Limpiar la caché de configuración si se cacheó antes:
   `php artisan config:clear` (evita migrar con una configuración obsoleta).
5. Aplicar migraciones pendientes: `php artisan migrate --force` (incluye tablas
   nuevas de cualquier módulo, como administración y auditoría).
6. Sincronizar el template Base: `php artisan cms:template:sync`.
7. Comprobar instalación: `php artisan cms:language:list`,
   `php artisan cms:language:validate` y `php artisan cms:template:list`.
8. Superadministrador: comprobarlo con
   `php artisan tinker --execute="echo \App\Models\AdministrationAccess::sole()->super_admin_user_id ?? 'null';"`.
   Si devuelve `null`, ejecutar `php artisan cms:admin:create` (interactivo); si
   ya hay identificador, omitirlo.
9. Assets: si no se usará `vite` en modo desarrollo y falta `public/build`,
   instalar dependencias con `npm install` (si falta `node_modules`) y compilar
   con `npm run build`.
10. Verificación final: arrancar `php artisan serve` y comprobar que `GET /` y
    `GET /admin/login` responden `200` y no `503`.

## Detección y mantenimiento

- Los pasos 5, 6, 8 y 9 se auto-adaptan: las migraciones aplican lo que falte,
  `cms:template:sync` re-hashea siempre y el alta de administrador se omite si ya
  existe.
- Un paso obligatorio nuevo no inferible (p. ej. un futuro comando de
  sincronización o semilla) no se adivina: detectarlo mediante la verificación
  final (`503` o error) y actualizar esta skill con `skill-creator`. Esa
  actualización corresponde a la entrega que introduce el paso.
- Reconciliar siempre con las fuentes de verdad citadas; si la guía de
  instalación añade un paso, aplicarlo aunque no figure literalmente aquí.

## Límites

- No usar en producción ni con `APP_ENV` distinto de `local`/`testing`.
- No modificar código, pruebas, Specs, ADR, `CHANGELOG.md` ni configuración de
  producto.
- No introducir secretos ni valores reales de `.env`.
- No crear un superadministrador duplicado; omitir si ya existe.
- No sustituye los quality gates ni el flujo SDD/TDD.

## Invocación

El usuario puede pedir "prepara el entorno local", "déjalo listo para servir" o
solicitar explícitamente las migraciones, la sincronización de template y las
comprobaciones previas al `serve`.
