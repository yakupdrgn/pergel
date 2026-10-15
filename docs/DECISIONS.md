# Karar defteri

Her kararın gerekçesi ve elenen alternatifi burada durur. Karar değişirse eski metin silinmez;
altına tarihli bir "Değişiklik" notu eklenir. Kaynak: Pergel Geliştirme Planı, 14 Ekim 2026
(onaylandı).

## K1 — Clean-room: Woodmart'tan kod alınmaz

Pergel sıfırdan yazılır. Woodmart'ın CSS, JS ve görselleri büyük ihtimalle Envato'nun split
lisansıyla korunuyor; türev bir temayı satmak riskli. Mimarisi de hedeflerle çelişiyor: aynı
eleman üç builder için üç kez yazılmış, her istekte 817 ayar alanı yükleniyor, script'lerin
146/161'i jQuery'ye bağlı. Düzeltmek yeniden yazmaktan pahalı.
**Elenen:** Woodmart fork'u, Woodmart child teması.

## K2 — Yerelde geliştirme, son kontrol staging'de

Yerelde her şey anında ve risksiz. ariumlab.com canlıda satış yapıyor. nginx/LiteSpeed, LSCache ve
Cloudflare katmanı yerelde birebir taklit edilemediği için yayından önce staging turu yapılır.
SSH (Hostinger) staging aşamasında, şifre yerine anahtarla kurulur.
**Elenen:** doğrudan canlıda geliştirme.

## K3 — Üç parça: `pergel` blok tema + `pergel-engine` eklenti + proje child teması

Tema yalnızca görünüş, işlev eklentide (Kadence, GeneratePress, Ollie aynı ayrımı yapıyor). Tema
değişse de içerik türleri, SEO verisi ve bloklar kaybolmaz. Child tema projeye özel tasarımı
dosyada, git'te tutar.
**Elenen:** her şeyi tek temada toplamak; hibrit Customizer teması.

## K4 — Blok tema (FSE), theme.json v3

WordPress'in yeni özellikleri blok tarafına geliyor (7.1 mobil/tablet stilleri, 7.0 navigation
overlay ve PHP-only bloklar, contentOnly pattern'ler). Blok işaretlemesi AI ile sayfa kurmaya en
uygun format. Yerel denemede TT25'ten hafif çıktı.
**Elenen:** klasik veya hibrit tema.

## K5 — Tasarım Site Editor'de, işlevler tek bir Pergel ayar ekranında

Renk, yazı tipi, boşluk ve şablon Global Styles'ta. Pergel ekranı yalnızca modüller, marka
bilgileri, SEO/AI ve performans ayarlarını içerir (yaklaşık 60 alan). Marka bilgileri Block
Bindings ile tek yerden bağlanır.
**Elenen:** Redux benzeri büyük ayar çerçevesi.

## K6 — Tema build'siz; yalnızca editör arayüzü gereken bloklar derlenir

Sunucuda Node gerekmez. Basit bloklar PHP-only. Karmaşık editör arayüzleri `@wordpress/scripts`
ile derlenir; derlenmiş dosyalar (`engine/build/`) repoya girer.
**Elenen:** tamamen build'siz yaklaşım; her şeyi derleyen ağır toolchain.

## K7 — Asgari WordPress 7.1, PHP 8.1; destek: son iki major WP sürümü

7.1'in mobil/tablet stilleri temel ihtiyaç. WooCommerce Şubat 2027'de PHP 8.1'i asgari yapıyor.
CI PHP 8.1 ve 8.4'te, ayrıca WP'nin bir sonraki betasında koşar.
**Elenen:** WP 6.x ve PHP 7.4 desteği.

## K8 — SEO sözleşmesi: eklenti varsa devret, yoksa yedek katman

Bir SEO eklentisi (Rank Math, Yoast, AIOSEO, SEOPress, TSF) aktifse Pergel başlık, meta, JSON-LD
ve site haritası basmaz; kendi verisini eklentinin grafiğine ekler, breadcrumb'ı hizalar. Eklenti
yoksa çekirdek API'lerle eksiksiz bir yedek katman çalışır. İlk entegrasyon Rank Math.
**Elenen:** kendi SEO eklentimiz; hiç SEO çıktısı vermemek.

## K9 — AI SEO kanıta göre sıralanır

Önce sunucuda çizilen içerik ve tutarlı yapısal veri (GPTBot ve ClaudeBot JS çalıştırmıyor).
llms.txt ve markdown görünümü gibi kanıtı zayıf katmanlar isteğe bağlı.
**Elenen:** ölçülmemiş "AI SEO" özellikleri.

## K10 — WooCommerce blok şablonları; PHP şablon override'ı yok

Klasik override'lar her WooCommerce güncellemesinde "eskimiş şablon" uyarısı üretir. Blok
şablonları theme.json ile stillenir. Satış modları (satış, teklif, katalog, girişli fiyat) veri
düzeyinde uygulanır (`woocommerce_is_purchasable`, fiyat HTML'e ve Store API'ye yazılmaz).
**Elenen:** `woocommerce/` klasöründe PHP override'ları; fiyatı CSS ile gizlemek.

## K11 — Performans bütçesi CI'da zorunlu

Bütçeyi aşan değişiklik birleştirilmez. Bütçe `tests/budget/budget.json` dosyasındadır; ölçüm
`tools/budget/measure.mjs` ve `tools/budget/perf-probe.php` ile yapılır.
**Elenen:** arada bir elle Lighthouse çalıştırmak.

## K12 — Her site bir "proje paketinden" üretilir

Brief, marka token'ları, site haritası, içerik ve ürün CSV'si platformdan bağımsız dosyalarda
durur; kurulum komutları idempotenttir. Format: `docs/PROJECT-PACKAGE.md`.
**Elenen:** içeriği yalnızca veritabanında tutmak.

## K13 — İsimler

Tema `pergel`, eklenti `pergel-engine`, ariumlab child teması `ariumlab`. PHP namespace `Pergel\`,
önek `pergel_`, hook adları `pergel/...`. ariumweb'deki `pergel-core` ve `pergel-ariumlab` ayrı
bir projeye ait; o adlar kullanılmaz.
**Elenen:** `pergel-core`, `pergel-ariumlab`.

---

## Uygulamada yapılan sapmalar (F0, 14 Ekim 2026)

- **Autoload:** Plan "Composer classmap" diyordu. Engine kendi küçük PSR-4 `spl_autoload_register`
  yükleyicisini kullanır; `vendor/` dağıtıma girmez. Sebep: çalışma zamanı bağımlılığı yok, ek
  dosya yok, yalnızca gereken sınıf yüklenir (bütçe: istek başına ≤ 60 Pergel dosyası). Üçüncü
  taraf bir çalışma zamanı kütüphanesi gerekirse classmap'e geçilir.
- **Yerel PHP:** Plan "XAMPP'in 8.2'si kalır, yalnızca CLI ve testler 8.4" diyordu. Uygulamada
  geliştirme sitesi de PHP 8.4'te çalışıyor: Apache yalnızca `pergel-tema` dizini için FastCGI ile
  `C:\devtools\php84`'e yönlendiriliyor. XAMPP'in global `php.ini`'si ve diğer siteler
  değişmedi; plandaki "php.ini'de 5 eklenti açılır" adımı bu yüzden gereksiz kaldı.
- **CI ortamı:** Plan wp-env (Docker) diyordu. F0'da CI, Ubuntu üzerinde PHP 8.4 + MariaDB 11.8
  servis konteyneri + WP-CLI + `php -S` ile kurulur (`tools/ci/`). Daha az hareketli parça,
  yerel kurulumla aynı adımlar. wp-env, Docker gerektiren testler (görsel regresyon, SEO matrisi)
  gelince yeniden değerlendirilir.
- **Repo konumu:** `D:\dev\pergel` (önce `C:\dev\pergel`, kullanıcı isteğiyle taşındı).

## F1 kararları (15 Ekim 2026)

- **Token formatı:** W3C DTCG 2025.10'un bir alt kümesi, `tokens/*.json` (ayrıntı:
  `docs/TOKENS.md`). Plandaki proje paketi `tokens.json` dosyası da bu formatı kullanır.
  **Elenen:** Style Dictionary gibi bir Node aracı (derleme WordPress olmadan da PHP ile çalışmalı,
  child temalar `wp pergel tokens build` ile derleniyor) ve doğrudan `theme.json` düzenlemek
  (kontrast denetimi ve variation'lar arası tutarlılık kaybolur).
- **Ayrı JSON Schema yok:** Doğrulayıcı derleyicinin kendisidir. Tüm sorunları tek seferde
  dosya ve token yoluyla listeler, hiçbir dosyayı yarım yazmaz. Şema ile derleyici arasında
  iki kaynaklı kural ikiliği oluşmaz. Editör otomatik tamamlaması gerekirse şema sonradan
  derleyicinin sabitlerinden üretilir.
- **Üretilen `theme.json`:** Token'ların sahip olduğu alanlar her derlemede yeniden yazılır, geri
  kalanı korunur. Biçim sabittir (tab girinti, sonda satır sonu), böylece CI'daki
  `php tools/tokens.php --check` farkı bayt bayt yakalar.
- **Kontrast derleme hatasıdır:** WCAG AA 4.5:1 sağlamayan zorunlu renk çifti uyarı değil
  hatadır. ariumlab.com'un bugünkü `#767676` / `#F5F7F8` (4.23) gibi çiftleri bu yüzden yeni
  temaya taşınamaz.
- **PHPUnit 10.5:** Saf PHP birimleri (token derleyicisi ve sonraki modüller) için geliştirme
  bağımlılığı olarak eklendi. `composer check` artık lint + analiz + test çalıştırır.
  WordPress gerektiren testler F2'de ayrıca değerlendirilir.
- **Style variation'lar ariumlab.com kimliğine göre:** Variation'lar genel örnekler değil,
  ariumlab.com kurumsal kimliğinden (mavi `#1E64E6`, koyu `#191919`/`#242424`, açık gri zemin,
  küçük radius) türetilen yönlerdir. Ticari fontlar (Satoshi, Preospe) kullanılmaz; yerine OFL
  lisanslı, Türkçe karakterleri kapsayan değişken fontlar kendi sunucumuzdan sunulur.
- **Varsayılan font sistem yığınıdır:** Ana tema hiç web font indirmez. Web fontları yalnızca
  variation'lar ve child temalar getirir.

- **Şablonlar ve tek H1:** `index`, `home`, `front-page`, `archive`, `search`, `single`, `page`,
  `404`. Her sayfada tam olarak bir H1 bulunur ve bütçe bunu `h1 = [1, 1]` aralığıyla denetler.
  Bütçede bir sayı üst sınırdır, `[min, max]` aralıktır.
- **`front-page.html` yalnızca statik ön sayfada:** Şablon sayfanın içeriğini başlıksız gösterir;
  H1'i içeriğin ilk bölümü (hero) taşır. Ön sayfa yazıları listeliyorsa `front-page`
  `frontpage_template_hierarchy` filtresiyle hiyerarşiden çıkarılır ve `home.html` kullanılır.
  Aynı filtre Site Editor'ün ana sayfa için açtığı şablonu da düzeltir.
  **Elenen:** `front-page.html` hiç olmaması (statik ön sayfada sayfa başlığı H1 olarak görünür;
  kurumsal sitelerde istenmez) ve koşulsuz `front-page.html` (yazı listesi olan ön sayfayı bozar).
- **Şablon metinleri PHP pattern'lerde:** HTML şablonlardaki metinler çevrilemediği için
  `patterns/hidden-*.php` dosyalarına taşındı (`Inserter: no`). Şablonlar bunları
  `wp:pattern` ile çağırır. Pattern'lerde koşullu blok yapısı yoktur, yalnızca çevrilen metin
  ve ayar değerleri vardır; çünkü Site Editor pattern'i bir kez çözüp şablona gömebilir.
- **`.pot` dosyaları depoda:** `composer pot` ile üretilir (`--no-location`, satır numarası
  değişince fark oluşmasın diye). CI, tarih satırı dışında farkı hata sayar.

### Değişiklik (15 Ekim 2026)

F5'teki "organik ürünler provası" plandaki bir örnekti; kullanıcı bu provaya gerek olmadığını
söyledi. F5'in prova hedefi henüz belirlenmedi. Bitti tanımı (paketten tek seferde ve tekrar
üretilebilen bir site) aynı kalır.

## Aşamalar ve bitti tanımları

| Aşama | İçerik | Bitti |
| --- | --- | --- |
| F0 | Ortam, repo, CI iskeleti, docs, junction'lar, boş tema + eklenti | Boş Pergel etkin, CI yeşil, bütçe ölçümü ilk raporu üretiyor |
| F1 | Token formatı ve theme.json üreticisi, temel şablonlar, 3 style variation, 6 header + 6 footer | Bütçe içinde, axe temiz, Site Editor'den tüm görünüm değiştirilebiliyor |
| F2 | Modül sistemi, ayar ekranı, marka + Block Bindings, SEO sözleşmesi, performans modülü, `wp pergel` | SEO matrisi 6 senaryoda temiz |
| F3 | Pergel blokları ve uzantıları, 120+ pattern, kurumsal starter, dönen video hero | Tüm pattern'ler geçerli, görsel testler oturdu, kurumsal demo kuruluyor |
| F4 | WooCommerce şablonları, hızlı arama, satış modları, P2 özellikleri, ölçek testi | Mağaza bütçesi tutuyor, ödeme akışı test modunda geçiyor |
| F5 | Proje paketi ve `wp pergel import`, Abilities/MCP, skill'ler, prova sitesi (hedef açık) | Prova sitesi paketten tek seferde ve tekrar üretilebiliyor |
| F6 | Tam test matrisi, güvenlik incelemesi, çeviriler, WP 7.2 uyumu | Tüm kontroller yeşil, v1.0.0 |
| F7 | ariumlab.com: içerik, staging, geçiş, 30 gün izleme | Yeni site yayında |

## Bilinen açık işler

- ~~Anasayfa (blog listesi) şu an H1 içermiyor~~ → F1'de çözüldü (`home.html` + bütçe `h1 = 1`).
- Çekirdeğin emoji script'i (`wp-emoji-release`) yükleniyor → F2 performans modülü kapatacak.
- ~~HTML şablonlardaki sabit metinler çevrilemiyor~~ → F1'de `patterns/hidden-*.php`'ye taşındı.
