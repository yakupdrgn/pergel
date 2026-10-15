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
| Settings | Yalnızca admin |
| Blocks | Blok sayfadaysa CSS/JS |
| Brand | Her zaman (hafif) |
| Seo | Ön yüz |
| Ai | İsteğe bağlı |
| Content | Yalnızca açılan içerik türleri |
| Woo | WooCommerce aktifse |
| Performance | Ön yüz |
| Import / Cli | Yalnızca WP-CLI (`Cli` şu an var: `wp pergel info`) |

## Ayar modeli (F2)

Tek option (`pergel_settings`), autoload ≤ 20 KB, REST şemasıyla doğrulanır. Tasarım ayarları bu
option'da değil Global Styles'tadır (K5).

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
