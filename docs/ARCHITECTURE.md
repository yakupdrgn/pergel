# Mimari

## Parçalar

```
Proje paketi (brief · tokens · sitemap · içerik · ürün CSV)
        │  wp pergel import (F5)
        ▼
Child tema (ör. ariumlab) ──► pergel (blok tema)        ──► WordPress 7.x çekirdeği
                          └─► pergel-engine (eklenti)   ──► WooCommerce (isteğe bağlı)
                                                        └─► SEO eklentisi (varsa devreder)
```

| Parça | Yer | Sorumluluk |
| --- | --- | --- |
| `pergel` | `theme/` → `wp-content/themes/pergel` | Yalnızca görünüş: `theme.json`, şablonlar, şablon parçaları, pattern'ler, style variation'lar, fontlar |
| `pergel-engine` | `engine/` → `wp-content/plugins/pergel-engine` | Tüm işlev: bloklar, SEO/AI, WooCommerce özellikleri, içerik türleri, performans, WP-CLI |
| Child tema | Proje reposu | Projeye özel token'lar, pattern'ler, şablon değişiklikleri |

Tema eklentisiz de çalışır (çekirdek bloklarla); eklenti de başka bir blok temayla çalışır. Tema
değiştiğinde içerik, SEO verisi ve özel içerik türleri kaybolmaz.

## Repo düzeni

```
pergel/
├─ theme/                 blok tema (dağıtılır)
├─ engine/                eklenti (dağıtılır)
│  ├─ pergel-engine.php   başlık, sabitler, autoloader, boot
│  ├─ src/<Modül>/        PSR-4: Pergel\<Modül>\...
│  ├─ blocks/<blok>/      block.json + render.php (sunucuda çizilen bloklar)
│  ├─ build/              derlenmiş editör JS'i (repoya girer, K6)
│  └─ languages/
├─ starters/              kurumsal / e-ticaret / editoryal starter paketleri (F3–F4)
├─ tools/
│  ├─ budget/             perf-probe.php (mu-plugin) + measure.mjs (Playwright)
│  ├─ ci/                 CI'da WordPress kurulumu ve php -S router'ı
│  ├─ dev/link.ps1        yerel junction'lar
│  ├─ fixtures/seed.php   sabit test içeriği (idempotent)
│  ├─ stubs/              statik analiz için WP-CLI stub'ları
│  └─ validate-json.mjs   theme.json / block.json şema doğrulaması
├─ tests/php/             birim testleri (WordPress olmadan)
├─ tests/integration/     WordPress gerektiren testler (PERGEL_WP_DIR)
├─ tests/budget/          performans bütçesi (K11)
└─ docs/
```

## Engine çalışma modeli

1. `pergel-engine.php` yalnızca sabitleri tanımlar, autoloader'ı kaydeder ve `plugins_loaded`
   (öncelik 5) üzerine `Core\Plugin::boot` bağlar. Başka kod çalıştırmaz.
2. **Autoloader:** `Pergel\X\Y` → `src/X/Y.php`. Composer'a ve `vendor/`'a gerek yoktur; yalnızca
   kullanılan sınıf dosyası yüklenir. (Plandaki "Composer classmap"ten bilinçli sapma, bkz.
   DECISIONS.md.)
3. **Modüller:** Her modül `Core\Module` arayüzünü uygular:
   - `id()` — sabit makine adı, ayar anahtarı olarak da kullanılır.
   - `should_load()` — bu istek için gerekli mi? Ucuz olmalı: autoload'lu option'lar dışında
     sorgu yok. Yanlış dönerse modülün diğer sınıfları hiç yüklenmez.
   - `register()` — hook'ları ekler.
4. `pergel/modules` filtresi child temaların ve proje eklentilerinin kendi modüllerini eklemesine
   izin verir. Tüm modüller kaydedildikten sonra `pergel/loaded` çalışır.

Planlanan modüller ve yüklenme koşulları:

| Modül | Yüklenme |
| --- | --- |
| Core | Her zaman |
| Settings | Her zaman (hafif: hook'lar; ekran yalnızca admin) |
| Blocks | Blok sayfadaysa CSS/JS |
| Brand | Her zaman (hafif: `init` + editör script'i yalnızca editörde) |
| Seo | Her zaman (meta kaydı admin/REST için; `Head` yalnızca ön yüzde, eklenti varsa yalnızca entegrasyon filtreleri) |
| Ai | İsteğe bağlı |
| Content | Yalnızca açılan içerik türleri |
| Woo | WooCommerce aktifse |
| Performance | Ön yüz |
| Import / Cli | Yalnızca WP-CLI (`Cli` şu an var: `wp pergel info`) |

## Ayar modeli (F2)

Tek option (`pergel_settings`), autoload ≤ 20 KB, REST şemasıyla doğrulanır. Tasarım ayarları bu
option'da değil Global Styles'tadır (K5).

- `Settings\Schema` — gruplar ve alanlar (`modules`, `brand`, `seo`; performans grubu kendi adımında
  eklenir). Varsayılanlar, temizleme ve REST JSON Schema bu tek tanımdan üretilir.
- `Settings\Settings` — okuma: `all()`, `get( 'brand.email' )`, `module_enabled( 'seo' )`,
  yazma: `update( $değişiklikler )`. Değerler istek başına bir kez şemaya göre normalize edilir;
  önbellek option her değiştiğinde düşer.
- `Settings\Module` — her zaman yüklenir; `sanitize_option_pergel_settings` filtresi, önbellek
  hook'ları ve `admin_init` / `rest_api_init` üzerinde `register_setting` (`/wp/v2/settings`,
  yalnızca `manage_options`).
- `Core\Plugin::boot` bir modülü yalnızca `modules.<id>` anahtarı açıksa (veya hiç yoksa) ve
  `should_load()` doğruysa yükler.
- Eklenti etkinleştirilirken varsayılanlar autoload ile yazılır; böylece okuma sorgu yapmaz.
- `Settings\Screen` — yalnızca admin'de yüklenir. Üst düzey "Pergel" menüsü, her şema grubu
  için bir sekme. Form `options.php`'ye gider ve yalnızca açık sekmenin alanlarını gönderir;
  diğer gruplar birleştirerek yazma sayesinde korunur. Hatalı alanlar eski değerini korur,
  hata hem üst bildirimde hem alanın altında (`aria-invalid`, `aria-describedby`) görünür.
- `Settings\Labels` — sekme, bölüm, alan etiketleri ve hata metinleri; şemanın saf PHP
  kalması için ayrı tutulur. Labels'ta yer almayan alan "Other" bölümünde yine gösterilir.
- `engine/assets/settings.js` — build'siz; görsel alanları için çekirdek medya seçicisi.

## Marka ve Block Bindings (F2)

Şirket bilgileri bir kez ayarlarda girilir; bloklar ve yapılandırılmış veri aynı kaynaktan okur.

- `Brand\Brand` — `brand` grubunu okur ve boşlukları doldurur: ad yoksa site başlığı, adres
  için ana sayfa, ana logo için Site Logo. `value( $anahtar )` boş değerde `null` döner.
- `Brand\Format` — saf PHP: `tel:` / `mailto:` bağlantısı, tek satır adres, profil URL'sinden
  sosyal ağ, schema.org `Organization`. Birim testleriyle sınanır.
- `Brand\Module` — `pergel/brand` bağlama kaynağı. Argümanlar: `{"key": "name"}`; sosyal için
  `{"key": "social", "network": "linkedin"}`. Anahtarlar: tüm metin alanları ile `url`,
  `address`, `email_url`, `phone_url`, `copyright`, `logo_inverse` (görselde `id`, `url`, `alt`).
  Boş değer bloğun kayıtlı metnini korur. Zengin metin özelliklerinde (`content`, `text`,
  `caption`) değer burada kaçışlanır; diğer özellikleri çekirdek yazarken kaçışlar.
- `engine/assets/brand-bindings.js` — build'siz, yalnızca blok ve site editöründe: güncel
  değerler ve bloğun "Özellikler" panelindeki alan listesi (`getFieldsList`). Değerler editörde
  salt okunur.
- `Brand::organization()` SEO modülünün `Organization` düğümünü besler.

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pergel/brand","args":{"key":"phone"}}}}} -->
<p>+90 000 000 00 00</p>
<!-- /wp:paragraph -->
```

## SEO (F2)

K8 sözleşmesi: bir SEO eklentisi aktifse Pergel hiçbir SEO etiketi basmaz, yalnızca eklentinin
`Organization` düğümünü marka bilgisiyle tamamlar. Eklenti yoksa çekirdek API'lerle bir yedek
katman çalışır.

- `Seo\Detect` — Rank Math, Yoast SEO, AIOSEO, SEOPress, TSF'yi sürüm sabitinden tanır (ilk
  eşleşen kazanır); `pergel/seo/plugin` filtresiyle değiştirilebilir.
- `Seo\Integrations` — eklenti başına tek filtre: `rank_math/json_ld` (100),
  `wpseo_schema_organization`, `aioseo_schema_output`, `seopress_schemas_organization_html`
  (JSON dizesi), `the_seo_framework_schema_graph_data`. Yalnızca boş özellikler doldurulur,
  `sameAs` birleştirilir; `Person` düğümlerine dokunulmaz.
- `Seo\Graph` — saf PHP: `Organization`, `WebSite` (+ `SearchAction`), `WebPage` türü,
  yazıda `BlogPosting` / `Article`, en az iki halkada `BreadcrumbList`. Birim testli.
- `Seo\Head` — yedek katman: belge başlığı, `wp_robots`, açıklama, Open Graph, Twitter
  kartı, liste sayfalarında sayfa numaralı canonical (tekil sayfanın canonical'ı çekirdekte),
  tek bir JSON-LD `@graph` (`pergel/seo/graph` filtresi). 404'te hiçbir şey basılmaz.
  Açıklama sırası: SEO paneli → özet → içeriğin başı; ön sayfada site sloganı.
- `Seo\Meta` — `_pergel_seo_title`, `_pergel_seo_description`, `_pergel_seo_noindex`
  (REST'te, `edit_post` yetkisiyle) ve editörde build'siz "SEO" paneli
  (`engine/assets/seo-panel.js`).
- `Seo\Sitemap` — çekirdek site haritası: gizli yazılar çıkarılır, `lastmod` eklenir, yazar
  sayfaları ayarla açılmadıkça listelenmez.
- Ayarlardaki SEO sekmesi (paylaşım görseli, X kullanıcı adı, yazar site haritası) eklenti
  aktifken bunu söyleyen bir bildirim gösterir.

**SEO matrisi** (`tools/seo/`): `matrix.sh` Pergel'i önce tek başına, sonra her ücretsiz SEO
eklentisiyle sırayla çalıştırır; `check.mjs` her senaryoda yedi sayfanın (ön sayfa, blog, yazı,
sayfa, kategori, arama, 404) `<head>`'ini ve site haritalarını okur. Kurallar: tek `<title>`,
en fazla bir açıklama / robots / Open Graph ve Twitter etiketi, dizine girecek sayfalarda tek
canonical, sayfa başına en fazla bir `Organization` ve bir `WebSite`, eklenti varken Pergel
JSON-LD'si yok (`class="pergel-schema"`), her `Organization` düğümünde marka telefonu ve sosyal
profili, tek XML site haritası (robots.txt'teki de aynısı). CI'da `seo-matrix` işi eklentilerin
wordpress.org'daki son sürümlerini kurar (`INSTALL=1`). Yerelde:

```bash
WP_DIR=/c/xampp/htdocs/pergel-tema SITE_URL=http://localhost/pergel-tema bash tools/seo/matrix.sh
```

## Ölçüm

`tools/budget/perf-probe.php` yalnızca `X-Pergel-Perf` başlığı taşıyan isteklerde çalışır ve
istek sonunda bir JSON satırı yazar: süre, sorgu sayısı ve süresi, DB yazmaları, yüklenen dosya
sayısı (toplam ve Pergel'e ait), bellek. `measure.mjs` her sayfayı 3 kez sıcak ölçer (medyan),
Playwright ile tarayıcı tarafını (HTML KB, istek sayısı, JS KB, gövdedeki CSS bağlantıları, DOM,
H1, JSON-LD, LCP, CLS) toplar ve `tests/budget/budget.json` ile karşılaştırır.

## Geliştirme ortamı

Yerelde site `C:\xampp\htdocs\pergel-tema`, repo `D:\dev\pergel`; `tools/dev/link.ps1` tema ve
eklentiyi junction ile bağlar. Site Apache + PHP 8.4 FastCGI + MariaDB 11.8 (3308) üzerinde
çalışır; canlı ortam (Hostinger: PHP 8.4, MariaDB 11.8, nginx/LiteSpeed, LSCache, Cloudflare Free)
ile aynı sürümler. Pergel hiçbir davranışı `.htaccess`'e bağlamaz.
