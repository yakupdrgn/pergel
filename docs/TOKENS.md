# Tasarım token'ları (format 1)

Bir temanın renkleri, fontları, boyutları ve boşlukları `tokens/` klasöründeki JSON dosyalarında
tutulur. `theme.json` içindeki preset'ler ve `styles/` altındaki style variation'lar bu dosyalardan
**üretilir**; elle düzenlenmez.

```bash
wp pergel tokens build                 # etkin temayı derle
wp pergel tokens build --theme=ariumlab --check   # yazmadan karşılaştır (CI)
php tools/tokens.php [--check]         # WordPress olmadan, bu repodaki theme/ için
```

## Dosyalar

| Dosya | Çıktı |
| --- | --- |
| `tokens/default.json` | `theme.json` içindeki preset'ler ve `settings.custom` |
| `tokens/<slug>.json` | `styles/<slug>.json` style variation'ı. Yalnızca değişen token'lar yazılır, gerisi `default.json`'dan gelir. Kökte `$extensions."com.ariumlab.pergel".title` zorunlu. |

**Child temalar** (ör. `ariumlab`): Child temanın `tokens/default.json` dosyası ana temanınkinin
üzerine birleştirilir. Bu yüzden child temada yalnızca değişen token'lar yazılır, örneğin marka
rengi ve fontlar.

`theme.json`'da token'ların **sahip olduğu** alanlar her derlemede yeniden yazılır:

- `settings.color.palette`
- `settings.typography.fontFamilies` ve `fontSizes`
- `settings.spacing.spacingSizes`
- `settings.shadow.presets`
- `settings.layout.contentSize` ve `wideSize`
- `settings.custom` altında token'da bulunan gruplar

Geri kalan her şey (`styles`, `templateParts`, ayar bayrakları) elle yazılır ve korunur. `styles`
bölümü sabit değer değil, token değişkeni kullanır. Örneğin `var(--wp--custom--radius--button)`.
Böylece bir variation yalnızca token'ları değiştirerek bütün görünümü değiştirebilir.

## Format

[W3C Design Tokens (DTCG) 2025.10](https://www.designtokens.org/tr/2025.10/format/) formatının bir
alt kümesi kullanılır:

- `$value` içeren bir nesne **token**'dır, `$value` içermeyen nesne ise **grup**.
- Gruba yazılan `$type` alt token'lara geçer.
- `$description` serbest açıklamadır.
- Alias `"{grup.token}"` biçiminde yazılır. Hedefin `$type`'ı aynı olmalıdır; döngü hata verir.
- Adlarda yalnızca `a-z`, `0-9` ve `-` kullanılır.
- Pergel'e özgü alanlar `$extensions."com.ariumlab.pergel"` altına yazılır:

| Alan | Nerede | Anlamı |
| --- | --- | --- |
| `format` | kök | Format sürümü, şu an `1` |
| `title` | kök (variation) | Site Editor'de görünen variation adı |
| `name` | token | Editörde görünen preset adı. Yazılmazsa slug'dan türetilir. |
| `fluid.min` | `font-size`, `spacing` | Küçük ekrandaki değer; `$value` büyük ekrandaki değerdir |
| `fontFace` | `font-family` | Kendi sunucumuzdan sunulan woff2 dosyaları (aşağıda) |
| `styles` | kök (variation) | Token'la ifade edilemeyen stil kuralları (ör. büyük harf buton). `theme.json` `styles` biçiminde yazılır ve variation'a olduğu gibi kopyalanır. Renk, font ve boyut için token kullanılır; `styles` yalnızca kalanlar içindir. |

### Desteklenen `$type`'lar

| `$type` | `$value` | CSS çıktısı |
| --- | --- | --- |
| `color` | `"#rrggbb"`, `"#rrggbbaa"` veya `{"colorSpace":"srgb","components":[r,g,b],"alpha":1}` | `#rrggbb` / `#rrggbbaa` |
| `dimension` | `{"value":1.5,"unit":"rem"}` veya `"1.5rem"`. Birim px, rem, em veya %. | `1.5rem` |
| `fontFamily` | `"Inter"` veya `["Inter", "system-ui", "sans-serif"]` | Boşluk içeren adlar tırnaklanır |
| `fontWeight` | 1–1000 arası tam sayı | sayı |
| `number` | sayı (ör. satır yüksekliği) | sayı |
| `shadow` | `{color, offsetX, offsetY, blur, spread, inset}` veya bunların listesi | `box-shadow` değeri |

### Gruplar

| Grup | `$type` | Hedef | Zorunlu slug'lar |
| --- | --- | --- | --- |
| `color` | color | `settings.color.palette` | base, surface, contrast, muted, accent, on-accent |
| `font-family` | fontFamily | `typography.fontFamilies` | body, heading, mono |
| `font-size` | dimension | `typography.fontSizes` | small, medium, large, x-large, xx-large |
| `spacing` | dimension | `spacing.spacingSizes` | 20, 30, 40, 50, 60, 70, 80 |
| `shadow` | shadow | `shadow.presets` (çekirdeğin varsayılan gölgeleri kapanır) | – |
| `layout` | dimension | `layout.contentSize` / `wideSize` | content, wide |
| diğer her grup | herhangi | `settings.custom.<grup>.<token>` → `--wp--custom--<grup>--<token>` | – |

Zorunlu slug'lar Pergel şablonlarının ve pattern'lerinin güvendiği sözleşmedir. Bir proje daha
fazla slug ekleyebilir ama zorunlu slug'ları silemez.

Pergel'in kullandığı custom gruplar şunlardır. Şablonlar bunlara başvurur:

- `radius`: small, medium, large, button, card
- `font-weight`: body, heading, button
- `line-height`: body, heading

### Akışkan değerler

- **`font-size`:** `fluid.min` verilirse theme.json'ın kendi akışkan tipografisine
  `{min, max}` olarak geçer. Verilmezse `fluid: false` yazılır ve boyut sabit kalır.
- **`spacing`:** Derleyici bir `clamp()` üretir. Değer, 360 px genişlikteki ekranda `fluid.min`'e,
  `layout.wide` genişliğinde `$value`'ya eşit olur. Arada doğrusal büyür. Değerler px veya rem
  olmalıdır.

### Fontlar

```json
"body": {
	"$value": [ "Figtree", "system-ui", "sans-serif" ],
	"$extensions": { "com.ariumlab.pergel": { "fontFace": [
		{ "src": "file:./assets/fonts/figtree/figtree-latin-wght-normal.woff2", "fontWeight": "300 900", "fontStyle": "normal", "unicodeRange": "U+0000-00FF, U+0131, …" },
		{ "src": "file:./assets/fonts/figtree/figtree-latin-ext-wght-normal.woff2", "fontWeight": "300 900", "fontStyle": "normal", "unicodeRange": "U+0100-02BA, …" }
	] } }
}
```

- `src` yalnızca temanın (child temada ana temanın da) içindeki bir woff2 dosyası olabilir. Uzak
  font (Google Fonts vb.) ve `..` içeren yol kabul edilmez. Dosya yoksa derleme hata verir.
- `fontFamily` alanı listedeki ilk addan, `fontDisplay: swap` ise varsayılan olarak doldurulur.
  Diğer alanlar (`fontWeight`, `unicodeRange` vb.) olduğu gibi geçer.
- Her aile iki alt kümeyle gelir: **latin** ve **latin-ext**. Tarayıcı `unicodeRange` sayesinde
  latin-ext dosyasını yalnızca sayfada ğ, ş, İ gibi karakterler varsa indirir. Türkçe sitede
  ikisi de iner. Dosyalar [Fontsource](https://fontsource.org) `@fontsource-variable/*`
  paketlerindeki `wght` alt kümeleridir. Aralıklar paketin `index.css` dosyasından alınır.
- Font klasörü `assets/fonts/<aile>/` biçimindedir. Lisans metni yanında `OFL.txt` olarak durur
  ve `readme.txt` dosyasının Copyright bölümüne yazılır.

## Denetimler

Derleme aşağıdakilerden biri bulunduğunda hiçbir dosya yazmaz. Bulunan sorunların hepsini birlikte
listeler.

- Zorunlu slug eksik, `$type` yanlış veya preset grubu iç içe yazılmış.
- Alias hedefi yok, türü uyuşmuyor ya da alias'lar döngü oluşturuyor.
- Değer okunamıyor (renk, boyut, gölge biçimi).
- `fontFace` dosyası temada yok.
- **Kontrast (WCAG AA, 4.5:1)** sağlanmıyor. Her token setinde şu çiftler denetlenir:

| Metin | Zemin |
| --- | --- |
| contrast | base |
| contrast | surface |
| muted | base |
| muted | surface (footer, kartlar) |
| accent | base (bağlantılar) |
| accent | surface |
| on-accent | accent (butonlar) |

CI iki şeyi doğrular. `php tools/tokens.php --check` çıktının token'larla aynı olduğunu
denetler. `composer test` ise derleyicinin birim testlerini çalıştırır.
