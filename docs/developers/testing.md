# Pruebas

## Vistas con Vite

Las pruebas `Feature` aíslan Vite globalmente desde `tests/Pest.php` con
`withoutVite()`. Los trabajos de CI de esas pruebas no construyen el manifest de
Vite; el aislamiento evita que una prueba de servidor dependa de un asset
compilado, también en Login y en la web pública. El build frontend se valida por
separado con `npm run build`.

Las pruebas de carga selectiva de assets pueden sustituir temporalmente la
instancia de Vite por un `hot` file propio y comprobar la etiqueta renderizada,
como hace `tests/Feature/AssetIsolationTest.php`; no dependen de un manifest
generado durante la suite.

Los módulos JavaScript nativos se prueban con `npm run test:js`, basado en el
runner incorporado de Node. No incorpora un navegador real; los flujos de foco,
teclado y controles nativos requieren una comprobación manual adicional.
