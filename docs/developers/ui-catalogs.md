# Desarrollo de UI catalogs

## Contrato actual

Un `UI catalog` es un objeto JSON plano de claves técnicas y valores string. Core
es el único propietario disponible en este incremento y almacena sus archivos en:

```text
Modules/Core/Resources/lang/<locale>.json
```

Cada clave sigue `<owner>::<group>.<item>`:

```json
{
  "core::language.installed": "Language :locale installed."
}
```

`en.json` es el inventario completo de claves válidas. Cualquier otro locale
debe contener exactamente las mismas claves. Una clave localizada ausente de
`en` se rechaza y nunca se registra ni resuelve.

## Reglas de validación

- Archivo regular dentro de `Modules/Core/Resources/lang`; no se admiten enlaces
  simbólicos ni rutas externas.
- JSON UTF-8 no ejecutable, representado como un objeto plano.
- Entre 1 y 10.000 claves y un máximo de 2 MiB por archivo.
- Claves de hasta 191 bytes y valores string no vacíos de hasta 16.384 bytes.
- Claves y valores sin caracteres de control.
- Sin HTML como contrato de contenido; Blade debe escapar el resultado.
- Mismo conjunto de placeholders que `en`, comparado sin distinguir la
  capitalización admitida por Laravel.
- `|` queda reservado para pluralización. Un valor plural debe incluir `:count`
  y todos los locales de esa clave deben ser también plurales.

Ejemplo plural:

```json
{
  "core::items.count": "{0} No items|{1} One item|[2,*] :count items"
}
```

## Resolución

`Modules\Core\Localization\Services\UiCatalogResolver` expone resolución explícita:

```php
$resolver = app(Modules\Core\Localization\Services\UiCatalogResolver::class);

$label = $resolver->get('core::language.installed', 'en', [
    'locale' => 'es_ES',
]);
```

Para pluralización se utiliza `choice($key, $number, $locale, $replace)`. El modo
`base` consulta el locale solicitado y después `en`; el modo `key` no consulta
`en`. Ambos devuelven la clave literal si no existe una línea válida.

Cada carga lee una sola instantánea de bytes y calcula su SHA-256 antes de
decodificar, validar y devolver líneas. La caché se identifica por los hashes de
`en` y del locale solicitado; cambiar cualquiera fuerza revalidación. Un archivo
que se vuelva inválido nunca se carga parcialmente.

## Añadir o modificar claves

1. Añadir la clave a `en.json` con nombre estable y propietario `core`.
2. Actualizar en el mismo cambio todos los locales Core versionados.
3. Mantener placeholders y pluralización compatibles.
4. Ejecutar:

```shell
php artisan cms:language:validate
php artisan test tests/Unit/Core/UiCatalogRepositoryTest.php
```

No se debe registrar la ruta con el loader JSON genérico de Laravel: ese loader
volvería a leer archivos sin aplicar este contrato. Otros módulos y templates
deberán usar el mismo modelo de propietario y completitud cuando sus Specs los
incorporen; no están implementados en este incremento.
