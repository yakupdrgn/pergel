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
| Blocks | Her zaman, anahtarsız (pattern'ler bu bloklarla kurulur); blok CSS/JS'i yalnızca blok sayfadaysa |
| Brand | Her zaman (hafif: `init` + editör script'i yalnızca editörde) |
| Seo | Her zaman (meta kaydı admin/REST için; `Head` yalnızca ön yüzde, eklenti varsa yalnızca entegrasyon filtreleri) |
| Ai | İsteğe bağlı |
| Content | Yalnızca açılan içerik türleri |
| Woo | WooCommerce aktifse |
| Performance | Ön yüz (`is_admin()` dışı; her parça kendi ayar anahtarıyla) |
| Import / Cli | Yalnızca WP-CLI (`Cli` şu an var; bkz. "WP-CLI") |

## Ayar modeli (F2)

Tek option (`pergel_settings`), autoload ≤ 20 KB, REST şemasıyla doğrulanır. Tasarım ayarları bu
option'da değil Global Styles'tadır (K5).

- `Settings\Schema` — gruplar ve alanlar (`modules`, `brand`, `seo`, `performance`). Varsayılanlar, temizleme ve REST JSON Schema bu tek tanımdan üretilir.
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

## Performans modülü (F2)

Yalnızca ön yüzde yüklenir; dört anahtarın hepsi varsayılan açıktır (Ayarlar → Performance).

- **Emoji:** çekirdeğin emoji algılama script'i, stilleri ve `s.w.org` DNS prefetch'i sayfadan ve
  embed'lerden kalkar. Akışlar ve e-postalar çekirdeğin görsel yedeğini korur.
- **oEmbed keşif bağlantıları:** `<head>`'deki iki `alternate` bağlantısı kalkar. Başka siteler
  sayfa adresinden embed kartı üretemez; site içi embed'ler çalışır (çekirdek onları HTTP'siz çözer).
- **Dashicons:** giriş yapmamış ziyaretçide kuyruktan çıkar (`wp_enqueue_scripts` 100 ve `wp_footer` 1).
  Dashicons'a bağımlı bir stil onu yine getirir.
- **Font önyükleme:** `Performance\Fonts` (saf PHP, birim testli) birleşik Global Styles'tan kök ve
  başlık (`heading`, `h1`–`h6`) font preset'lerini bulur; bu fontların düz (italik olmayan),
  temel Latin'i kapsayan woff2 dosyaları `wp_preload_resources` ile önyüklenir. URL'ler çekirdeğin
  `@font-face` adresiyle birebir aynıdır (`file:./` → `get_theme_file_uri()`). Sistem fontlu temel
  temada hiçbir şey önyüklenmez. `pergel/performance/font_preloads` filtresi listeyi değiştirir.

Bütçe bunları denetler: `emoji = 0`, `fontPreloads ≤ 2`, `unusedPreloads = 0` (önyüklenip hiçbir
`@font-face` kuralınca kullanılmayan dosya), `jsonld ≤ 1`.

## Bloklar (F3)

Pergel blokları sunucuda çizilir ve `engine/blocks/<slug>/` altında durur:

- `block.json`: ad `pergel/<slug>`, `apiVersion` 3, `textdomain` `pergel-engine`, kategori
  (`pergel` ekleyicide çekirdek kategorilerin ardından gelir), `render: file:./render.php`.
- `render.php`: blok çıktısı; `$attributes`, `$content`, `$block` değişkenleriyle çağrılır.
- `style.css` (isteğe bağlı): çekirdek yalnızca blok sayfada çizildiğinde ekler ve küçükse
  satır içine alır; ayrı istek oluşmaz.
- Editör betiği olmayan blok `supports.autoRegister: true` taşır. WP 7.0'dan beri editör böyle
  bir bloğu sunucu önizlemesiyle kendisi kaydeder ve ayar alanlarını `attributes`'tan üretir
  (breadcrumb böyledir).
- İçerik tutan bloklar derlemesiz bir `index.js` (`editorScript: file:./index.js`) ve bağımlılıkları
  sayan `index.asset.php` taşır. Ortak yardımcı `engine/assets/blocks-editor.js`
  (`pergel-blocks-editor`) `window.pergelBlocks.container()` ile bloğu çekirdek bloklardan oluşan
  bir InnerBlocks şablonu, isteğe bağlı sınıf ve Inspector kontrolleriyle kaydeder; `save` yalnızca
  iç blokları yazar, sarmalayıcıyı `render.php` basar. Böylece metin, görsel ve bağlantılar çekirdek
  blokların kendi araçlarıyla düzenlenir, contentOnly pattern'lerde de açık kalır ve çevrilebilir.
- `render.php` ve `block.json`'un gösterdiği her dosya (`style`, `editorScript`,
  `viewScriptModule`...) var olmalıdır; `tools/blocks.php` bunu da denetler.

`php tools/blocks.php` bu kuralları denetler ve tüm `block.json`'ları `engine/blocks/manifest.php`
dosyasına yazar. `Blocks\Module` blokları bu dosyadan bir metadata koleksiyonu olarak kaydeder;
istek başına JSON okunmaz. CI `--check` ile manifest'in güncel olduğunu, `npm run validate:json`
ile `block.json`'ların WP 7.1 şemasına uyduğunu denetler.

### Header seçenekleri

`Blocks\Header`, header alanını gösteren Template Part bloğuna iki öznitelik ekler:
`pergelSticky` (`""`, `always`, `smart`: aşağı kaydırırken gizlenir, yukarıda geri gelir) ve
`pergelOverlay` (header sayfanın ilk bölümünün üstüne biner, sayfa kayana kadar arka planı
yoktur). Seçenekler bloğun Ayarlar sekmesindeki "Header behaviour" panelindedir
(`engine/assets/header-controls.js`, derlemesiz). Öznitelik şablondaki blok örneğinde durduğu için
her şablon kendi davranışını seçer; overlay için olağan yer `front-page` şablonudur.

Ön yüzde `render_block_core/template-part` süzgeci sarmalayıcıya `pergel-header` ve durum
sınıflarını ekler, `header.css`'i (satır içi) ve `pergel/header` script modülünü yalnızca seçenek
açıkken yükler. Script; gizleme, `is-scrolled` ve çapa bağlantıları için `--pergel-header-height`
değişkenini yönetir. Odak header'a girerse ya da mobil menü açıksa header gizlenmez. Gizlenmemiş
header'da `transform` bulunmaz; aksi halde `position: fixed` mobil menü header'ın içine hapsolurdu.

### Logo boyutu

Header ve footer pattern'lerindeki Site Logo bloklarında sabit genişlik yoktur. Genişliği
verilmemiş logo (`is-default-size`) temanın `assets/css/blocks/site-logo.css` stiliyle
yüksekliğe göre boyutlanır: `logo.height` (40px) ve `logo.max-width` (240px) token'ları
`--wp--custom--logo--*` olur. Yatay yazı logosu da kare işaret de okunur kalır. Editörde genişlik
verilirse sınıf kalkar ve o genişlik geçerlidir. Kural theme.json `css` alanında değildir:
WordPress o alanı `:where()` ile sardığı için çekirdeğin 120px kuralını geçemez.

### Breadcrumb

`pergel/breadcrumb` sayfanın yolunu `<nav><ol>` olarak basar; son adım bağlantısız ve
`aria-current="page"` taşır, iki adımdan kısa yolda hiçbir şey basmaz. Yolu `Seo\Trail` verir:

- Rank Math, Yoast, AIOSEO ve TSF aktifse yol eklentinin kendi BreadcrumbList'ini ürettiği listeden
  okunur (Rank Math `Breadcrumbs::get()->get_crumbs()`, Yoast `YoastSEO()->meta->for_current_page()`,
  AIOSEO `breadcrumbs->frontend->getBreadcrumbs()`, TSF `Meta\Breadcrumbs::get_breadcrumb_list()`).
- Eklenti yoksa yol Pergel'in yedek grafiğindeki yoldur (`Head::page()['crumbs']`).
- SEOPress (ücretsiz) yol üretmez: Pergel'in yolu kullanılır ve blok BreadcrumbList'i kendisi
  ekler (`pergel/breadcrumb/schema` filtresiyle kapatılabilir).

Her kaynak `Trail::clean()`'den geçer: adı ve bağlantısı olan, schema'dan gizlenmemiş adımlar
kalır. Eklentiler de BreadcrumbList'e tam bu adımları koyduğu için görünen yol ile schema aynıdır;
SEO matrisi (`tools/seo/check.mjs`) bunu altı senaryoda denetler. Rank Math yolu yalnızca destek
bildiren temalarda kurduğundan Pergel `rank-math-breadcrumbs` desteğini ekler; blog sayfası gibi
bağlantısız bırakılan son adıma sayfanın adresini verir. Editörde blok örnek bir yol gösterir.
Blok `single`, `page`, `archive` ve `home` şablonlarında başlığın üstündedir.

### İçerik blokları (F3b)

Hepsi sunucuda çizilir; CSS'leri yalnızca blok sayfadayken yüklenir. Ön yüz script'leri küçük
script modülleridir (`viewScriptModule`), jQuery ve Interactivity kullanmaz.

- **Sayaç** (`pergel/counter`): sayı ve etiket paragrafı. Sunucu son sayıyı basar; script blok
  görünür olunca sıfırdan sayar (binlik ayırıcı, ondalık ve çevresindeki metin korunur: `250+`,
  `%98`, `1.200`). `prefers-reduced-motion`'da ve süre 0 iken sayı hiç değişmez. Süre 0–10000 ms.
- **Zaman çizelgesi** (`pergel/timeline` > `pergel/timeline-item`): `<ol>`/`<li>`; dar ekranda tek
  sütun, 48rem'den geniş ekranda çizginin iki yanında sırayla.
- **Logo şeridi** (`pergel/logo-marquee`): Image blokları iki kez basılır, ikinci kopya
  `aria-hidden` ve `inert`'tir ve id'leri silinir. Kayma saf CSS'tir (süre 5–120 sn), üzerine
  gelince, logolara odaklanınca ve "Duraklat" düğmesiyle durur (WCAG 2.2.2); düğmeyi script
  gösterir. Hareket azaltılmışsa logolar sabit, sarılan bir satırdır. Editörde de sabit satırdır.
- **Fiyat tablosu** (`pergel/pricing-table` > `pergel/pricing-plan`): sığdığı kadar sütun, butonlar
  altta hizalı; öne çıkan plan `is-featured` alır.
- **Karşılaştırma tablosu:** yeni blok değil, `core/table` için `pergel-comparison` blok stili
  (`Blocks\Styles`). İlk sütun yapışkan, tablo en az 32rem; dar ekranda tablo kendi içinde yatay
  kayar ve çerçevesi klavyeyle kaydırılabilsin diye `tabindex="0"` alır.
- **İçindekiler** (`pergel/table-of-contents`): yazının H2–H3 (ayarla H4'e kadar) başlıklarını
  iç içe `<ol>` olarak listeler; iki başlıktan azsa hiçbir şey basmaz. Başlık listesi
  `parse_blocks` ile içerikten çıkarılır. `Blocks\Toc` tekil sayfalarda içeriğin her başlığına
  aynı id'yi verir: HTML çapası olan kendi id'sini korur, diğerleri metinden üretilir ve tekrar
  edenler `-2`, `-3` alır. Eşleşme düzey+metin ve sıraya göredir; sıra her `the_content`
  geçişinde baştan başlar (meta açıklaması için üretilen özet başlıkları önce bir kez çizer).
  Editör önizlemesi kayıtlı içerikten gelir.
- **Harita** (`pergel/map`): adres/yer ve yakınlık. Önce statik görsel (ya da adresli bir yer
  tutucu) ve Google Maps'e giden bir bağlantı basılır; iframe yalnızca "Show the map"e basınca
  oluşur. Sayfa yüklenirken üçüncü taraf isteği yoktur; API anahtarı gerekmez.

### Etkileşimli bloklar (F3b)

Bu üç blok çekirdeğin Interactivity API'sini kullanır (`supports.interactivity`, `data-wp-*`
yönergeleri, `viewScriptModule`); jQuery yoktur. Sunucu tam HTML'i basar, script yalnızca durum
ekler; JavaScript olmadan da içerik görünür ve kullanılabilir.

- **Carousel** (`pergel/carousel` > `pergel/carousel-slide`): WAI-ARIA carousel deseni
  (etiketli `region`, `aria-roledescription`, slayt başına "2 of 5" etiketi). Görünümde 1–4 slayt
  (`perView`; dar ekranda bir sonraki slayt kenardan görünür), önceki/sonraki düğmeleri ve isteğe
  bağlı noktalar. Kaydırma CSS scroll-snap'tir; script düğmeleri ve noktaları kaydırma konumuyla
  eşler. Otomatik oynatma yoktur. Script'siz carousel dokunma, trackpad ya da odaktayken ok
  tuşlarıyla kaydırılan bir şerittir.
- **Mega menü** (`pergel/mega-menu`): Navigation bloğunun bir öğesidir (`Blocks\MegaMenu` blok
  türünü `core/navigation`'ın izinli blokları arasına ekler). Paneli `pergel-mega-menu` alanındaki
  bir template part'tır (tema varsayılanı `parts/mega-menu.html`, metinler
  `patterns/hidden-mega-menu.php`'de); editörde blok listeden bir parça seçer ve parça Site
  Editor'de header gibi bloklarla düzenlenir. Tıklama açar; Esc, dışarı tıklama ve odağın
  dışarı çıkması kapatır (Esc mobil menüyü kapatmaz, yalnızca paneli). Header'da panel header
  boyunca açılır, footer'da yukarı açılır, mobil menüde öğesinin altında yerinde açılır. Script'siz
  panel üzerine gelince ve odakta açılır. İçeriği boş (metin ve medya yok) bir parça hiçbir şey
  basmaz, boş kutu açılmaz.
- **Form** (`pergel/form`): iletişim veya teklif formu; ayrıntılar aşağıda.

### Formlar (F3b)

`Forms` modülü (`modules.forms` ile kapatılır; kapalıyken Form bloğu hiçbir şey basmaz) Form
bloğunun mesajlarını alır.

- **Çizim** (`Forms\Form`): ad, e-posta ve mesaj zorunludur; telefon ve şirket bloğun ayarıyla
  eklenir. Teklif formunda mesaj alanı "What do you need?" olur ve e-posta konusu değişir.
  İsteğe bağlı onay kutusu gizlilik sayfasına bağlanır.
  Formda imzalı bir belirteç (`Form::sign()`, HMAC: blok ayarları, sayfa, oluşturma zamanı) ve
  `website` adlı bir tuzak alanı vardır. Belirteç nonce değildir: önbellekli sayfada da geçerli
  kalır ve bloğun ayarlarını sunucuya taşır.
- **Gönderim** (`Forms\Submit`): script `POST /wp-json/pergel/v1/form` kullanır; script'siz form
  `admin-post.php` (`action=pergel_form`) üzerinden gider ve sonuç koduyla sayfaya geri döner.
  İkisi de aynı sırayla denetler: dolu tuzak sessizce "gönderildi" alır; belirteç imzalı olmalı;
  yüklemeden 3 saniye içinde gelen form geri çevrilir; bağlantı başına 10 dakikada 5 mesaj (IP
  yalnızca özetlenerek sayaç anahtarında kullanılır); Turnstile anahtarları varsa doğrulanır;
  sonra alanlar sunucuda doğrulanır. Geçen mesaj önce saklanır, sonra e-postayla gönderilir.
- **Saklama** (`Forms\Inbox`): gizli `pergel_submission` yazı türü, yönetimde Pergel →
  "Form messages" (ad, e-posta, form, sayfa, e-posta gönderildi mi, tarih). Yalnızca yöneticiler görür;
  mesaj elle oluşturulamaz, okunur ve silinir. Kişisel veri dışa aktarma/silme araçlarına bağlıdır ve gizlilik
  politikasına önerilen metin ekler. Yazı türü yalnızca yönetimde, WP-CLI'da ve mesaj
  saklanmadan hemen önce kaydedilir.
- **E-posta:** alıcı `forms.recipient`, boşsa site yönetici e-postası; `Reply-To` gönderendir.
- **Turnstile:** `PERGEL_TURNSTILE_SITE_KEY` ve `PERGEL_TURNSTILE_SECRET_KEY` `wp-config.php`'de
  tanımlıysa (ya da `pergel/forms/turnstile` süzgeciyle) kullanılır; Cloudflare script'i yalnızca
  formlu sayfalarda yüklenir.

### Ön yüzde yalın yükleme

Sayfa görüntülemesinde Pergel bloklarının editör betik ve stilleri kaydedilmez:
`Blocks\Module` kayıt sırasında `block_type_metadata` süzgeciyle `pergel/*` bloklarının
`editorScript`/`editorStyle` alanlarını düşürür (yönetim, WP-CLI ve REST istekleri hariç).
Böylece her blok için `index.asset.php` okunmaz. Form sınıfları da yalnızca Form bloğu çizildiğinde
ya da bir mesaj geldiğinde yüklenir. Bütçe ölçümü (`pergel_files ≤ 45`) bunu denetler.

## WP-CLI (F2)

`Cli` modülü yalnızca WP-CLI altında yüklenir; web isteklerine kod eklemez.

| Komut | İş |
| --- | --- |
| `wp pergel info` | Motor, tema, WordPress ve PHP sürümleri, yüklenen modüller |
| `wp pergel tokens build [--check]` | Tasarım token'larından `theme.json` ve variation'ları üretir; `--check` yalnızca farkı denetler |
| `wp pergel settings get [<yol>]` | Tüm ayarlar, bir grup (`brand`) veya bir alan (`brand.email`); metin düz, gerisi JSON |
| `wp pergel settings set <grup.alan> <değer> [--format=json]` | Tek alan; açık/kapalı `true/false`, `1/0`, `yes/no` alır, listeler satır satır veya JSON dizi |
| `wp pergel settings export [<dosya>]` | Tüm ayarlar JSON olarak (dosyaya veya çıktıya) |
| `wp pergel settings import <dosya\|-> [--dry-run]` | JSON'dan okur; dosyada olmayan alanlar korunur, değişen alanlar listelenir |
| `wp pergel modules list` | Modül, anahtar (`on`/`off`/`always`), bu komutta yüklendi mi |
| `wp pergel modules enable\|disable <id>...` | Anahtarı olan modülleri açar/kapatır |

Ayar komutları ekranla aynı şemadan geçer: geçersiz veya bilinmeyen bir alan varsa hiçbir şey
kaydedilmez ve komut 1 ile çıkar (ekran ise geçerli kısmı kaydeder). Görsel alanları ek olarak
bu sitede görsel olan bir ekin kimliğini ister: `set` başka bir değeri reddeder, `import` başka
siteden gelen kimlikleri uyarıyla sıfırlar. `tools/ci/cli-smoke.sh` tüm komutları CI'da gerçek
WP-CLI ile dener ve ayarları sonunda geri yükler.

## Ölçüm

`tools/budget/perf-probe.php` yalnızca `X-Pergel-Perf` başlığı taşıyan isteklerde çalışır ve
istek sonunda bir JSON satırı yazar: süre, sorgu sayısı ve süresi, DB yazmaları, yüklenen dosya
sayısı (toplam ve Pergel'e ait), bellek. `measure.mjs` her sayfayı 3 kez sıcak ölçer (medyan),
Playwright ile tarayıcı tarafını (HTML KB, istek sayısı, JS KB, gövdedeki CSS bağlantıları, DOM,
H1, JSON-LD, emoji yükleyicisi, font önyüklemeleri ve kullanılmayanları, LCP, CLS) toplar ve `tests/budget/budget.json` ile karşılaştırır.

## Geliştirme ortamı

Yerelde site `C:\xampp\htdocs\pergel-tema`, repo `D:\dev\pergel`; `tools/dev/link.ps1` tema ve
eklentiyi junction ile bağlar. Site Apache + PHP 8.4 FastCGI + MariaDB 11.8 (3308) üzerinde
çalışır; canlı ortam (Hostinger: PHP 8.4, MariaDB 11.8, nginx/LiteSpeed, LSCache, Cloudflare Free)
ile aynı sürümler. Pergel hiçbir davranışı `.htaccess`'e bağlamaz.
