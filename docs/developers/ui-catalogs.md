# Desarrollo de UI catalogs

## Contrato actual

Un `UI catalog` es un objeto JSON plano de claves técnicas y valores string. Los
propietarios actuales son Core y los CMS Templates registrados en `cms_templates`.
Core almacena sus archivos en:

```text
Modules/Core/Resources/lang/<locale>.json
```

Cada template registrado conserva los suyos bajo su propio directorio:

```text
Templates/<Directory>/Resources/lang/<locale>.json
```

Cada clave sigue `<owner>::<group>.<item>`. El propietario `core` está reservado
para Core; un template usa su `identifier` registrado en `cms_templates`.

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
- Claves y valores sin caracteres de control Unicode (`\p{Cc}`). Se rechazan los
  controles bidireccionales de embedding/override `U+202A`–`U+202E`. Se permiten
  marcas direccionales legítimas `U+061C`, `U+200E`, `U+200F`, los isolates
  `U+2066`–`U+2069` y otros formatos `Cf` como ZWJ (`U+200D`).
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

`Modules\Core\Localization\Services\UiCatalogResolver` expone resolución explícita
para claves Core:

```php
$resolver = app(Modules\Core\Localization\Services\UiCatalogResolver::class);

$label = $resolver->get('core::language.installed', 'en', [
    'locale' => 'es_ES',
]);
```

`Modules\Core\Template\Services\TemplateUiCatalogs` resuelve claves de templates,
incluidas las de Base, con el prefijo del propietario efectivo.

La precedencia efectiva es:

```text
override(locale), si el interruptor global está activo y el override es válido
-> UI catalog(locale)
-> UI catalog en, solo en modo base y cuando locale no sea en
-> clave literal
```

El modo `key` no consulta `en`. Ambos caminos consultan la capa de overrides antes
de leer el archivo del propietario. Un override de `base::…` no sustituye una clave
propia de otro template. La ausencia del catálogo localizado no impide aplicar un
override válido.

Para pluralización se utiliza `choice($key, $number, $locale, $replace)`. El
selector de Laravel se aplica sobre la línea efectiva, incluida la procedente de
un override.

Cada carga lee una sola instantánea de bytes y calcula su SHA-256 antes de
decodificar, validar y devolver líneas. La caché se identifica por los hashes de
`en` y del locale solicitado; cambiar cualquiera fuerza revalidación. Un archivo
que se vuelva inválido nunca se carga parcialmente.

La capa de overrides se apoya en `UiCatalogOverrides`,
`UiCatalogOverrideStore`, `UiCatalogOverrideValidator`, `UiCatalogOverrideManager`
y `UiCatalogOwnerResolver`. El estado global (`enabled` y `version`) vive en
`ui_catalog_override_state`; las líneas validadas se cachean por locale, versión y
fingerprint del `en` de los propietarios afectados. El resolver reutiliza el
snapshot del propietario mientras sus metadatos de archivo no cambien y lo
reinicia con `forget()` o en un proceso nuevo. Si el catálogo del propietario
cambia, desaparece una clave o se alteran placeholders o pluralización, el
override se ignora en la siguiente resolución sin esperar a un cambio de versión.
Una entrada de caché malformada se trata como miss. Si faltan las tablas o falla
el almacén o la caché, la resolución continúa con los catálogos y registra un
aviso acotado y deduplicado.

El snapshot del propietario se reutiliza mientras `mtime` y tamaño del `en.json`
no cambien; la caché de directorios de template también vive en la instancia. El
estado global, la versión y las filas por locale se memoizan igualmente en la
instancia. En procesos de vida larga (Octane, workers), un cambio del mismo tamaño
dentro de la misma granularidad temporal, un template desregistrado, una mutación
de overrides o un cambio de interruptor en otro proceso puede requerir reiniciar
el worker o ejecutar `forget()`/limpieza equivalente. PHP-FPM crea una instancia
por petición y no necesita este paso.

Cualquier superficie web futura debe autorizar antes de invocar el manager; el
actor `system` no sustituye una comprobación de autorización web.

## Añadir o modificar claves

1. Añadir la clave a `en.json` con nombre estable y el propietario correcto.
2. Actualizar en el mismo cambio todos los locales versionados de ese propietario.
3. Mantener placeholders y pluralización compatibles.
4. Ejecutar:

```shell
php artisan cms:language:validate
php artisan cms:ui-catalog:override:validate
php artisan test tests/Unit/Core/UiCatalogRepositoryTest.php
php artisan test tests/Feature/Core/UiCatalogOverrideResolutionTest.php
```

Los overrides no crean claves ni modifican el inventario `en`. Su operación se
documenta en [Administración de overrides de UI catalogs](../administration/ui-catalog-overrides.md).

No se debe registrar la ruta con el loader JSON genérico de Laravel: ese loader
volvería a leer archivos sin aplicar este contrato. Los módulos custom todavía no
son propietarios de overrides; cuando se incorporen reutilizarán la misma
identidad `<owner>::<group>.<item>` y el mismo almacenamiento, sin migración de
filas ni cambio de comandos.
