# Acceso al backoffice

## Alcance disponible

El backoffice ofrece únicamente el acceso autenticado y un panel inicial. No
incluye todavía edición de contenido, idiomas, overrides, Pages, Navigation,
medios ni PageBuilder. El panel no enlaza a recursos que aún no existen.

## Iniciar sesión

1. Abrir `/admin/login` en el navegador.
2. Introducir el email y la contraseña del superadministrador.
3. Enviar el formulario. Un acceso correcto abre `/admin`.

El acceso está reservado al superadministrador. Un usuario autenticado sin esa
condición no puede entrar y recibe una respuesta de acceso denegado; no se
revela si una cuenta existe ni si tiene privilegios.

## Cerrar sesión

En el panel, usar la acción de cierre de sesión. La sesión se invalida por
completo y se vuelve al formulario de acceso. El cierre requiere una petición
protegida y no se puede provocar enlaces externos.

## Límite de intentos

Los intentos fallidos se limitan a cinco por minuto por combinación de email e IP
de origen. Al superarlo, el formulario indica que se intente más tarde y no
autentica. El mensaje es el mismo para credenciales inválidas, cuentas
inexistentes o usuarios sin privilegios, para no facilitar la enumeración.

## Avisos del panel

Si el administrador ve el aviso **Modo diagnóstico activo**, significa que el
detalle de errores 5XX está habilitado en ese entorno. Es una ayuda de
diagnóstico para pruebas, no un estado normal; debe desactivarse antes de
publicar. Véase
[Detalle de errores de diagnóstico](../administration/diagnostic-error-details.md).

## Problemas frecuentes

- **No puedo acceder**: comprobar que existe el superadministrador y que las
  credenciales son las creadas durante el aprovisionamiento. La contraseña no se
  puede restablecer desde la aplicación.
- **Se rechazan mis credenciales correctas**: esperar un minuto si se superó el
  límite de intentos.
- **El navegador fuerza HTTPS**: es el comportamiento esperado en entornos
  publicados. Solo `local` y `testing` admiten HTTP.
- **No veo menús de gestión**: es lo previsto en este incremento. El panel es un
  shell sin operaciones mutables.
