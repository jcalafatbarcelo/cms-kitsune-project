# Primer superadministrador

## Alcance

El backoffice dispone de un único superadministrador. Se crea de forma
controlada mediante un comando interactivo; no existe registro público,
invitaciones ni gestión web de usuarios. Cualquier alta o cambio posterior de
usuarios requiere una capacidad y una Spec propias. El uso diario del acceso se
describe en [Acceso al backoffice](../user-guide/backoffice-access.md).

## Requisitos

- Migraciones aplicadas: la tabla `administration_access` debe existir y contener
  su registro singleton.
- Terminal interactiva. El comando no admite credenciales por argumentos,
  opciones, variables de entorno ni entrada no interactiva.
- Contraseña que cumpla la política: entre 15 caracteres y 72 bytes UTF-8, con
  mayúscula, minúscula, número y símbolo.

## Crear el superadministrador

```shell
php artisan cms:admin:create
```

El comando solicita nombre, email, contraseña y confirmación. La contraseña se
introduce de forma silenciosa y se almacena con el hash `bcrypt` de Laravel. El
email se normaliza a minúsculas y sin espacios antes de validarse y guardarse.

Al finalizar correctamente muestra `Superadministrator created.` y el código de
salida es `0`.

## Garantías

- Solo puede existir un superadministrador. Una segunda ejecución falla sin
  modificar datos y devuelve un código distinto de `0`.
- La creación es atómica: si falla la validación, el email ya existe o hay
  concurrencia, no queda un usuario parcial ni una elevación parcial.
- El registro singleton no puede borrarse, duplicarse ni cambiar de
  identificador; la base de datos rechaza esas operaciones.
- No se registran la contraseña, sus argumentos ni valores derivados en la salida
  ni en los logs.

## Cuidado y recuperación

La contraseña del superadministrador no se puede recuperar desde la aplicación:
no hay restablecimiento ni correo de recuperación en este incremento. Perderla
requiere intervención operativa externa con acceso autorizado a la base de datos
y a la consola. Por ello:

- guardar la credencial en el gestor de secretos del entorno, nunca en Git;
- no reutilizar la contraseña de otros servicios;
- crear el superadministrador antes de exponer `/admin` en un entorno publicado.

## Diagnóstico

Los errores de validación, un email duplicado o un superadministrador ya
existente no crean ni elevan usuarios. Si el comando indica que falta la terminal
interactiva, ejecutarlo directamente en una consola y no mediante una tubería o
un proceso sin TTY.
