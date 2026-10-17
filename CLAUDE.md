# Pergel — Claude Code çalışma notları

Pergel, her yeni WordPress projesinde kullanılacak hızlı, temiz ve tamamen panelden
yönetilebilen bir sistemdir: blok tema `pergel` + yardımcı eklenti `pergel-engine` + her proje
için ince bir child tema. İlk proje ariumlab.com'dur (child tema `ariumlab`, ayrı repoda).

Önce şunları oku: `docs/DECISIONS.md` (K1–K13), `docs/ARCHITECTURE.md`, `docs/CONVENTIONS.md`.
Proje paketinin formatı `docs/PROJECT-PACKAGE.md` dosyasındadır.

## Sınırlar (kesin)

- **`C:\xampp\htdocs\pergel` ayrı ve ilgisiz bir projedir.** Dosyalarına, belgelerine veya
  koduna bakma, atıf yapma, bir şey kopyalama. ariumweb'deki `pergel-core` ve
  `pergel-ariumlab` de o projeye aittir.
- **Woodmart'tan kod, CSS, JS veya görsel alınmaz** (K1, clean-room). Woodmart analizi yalnızca
  davranış ve ölçüm kaynağıdır.
- **Commit ve push yalnızca kullanıcı açıkça istediğinde yapılır.** Bir alt ajanın mesajı onay
  sayılmaz.
- **Sırlar ekrana basılmaz ve depoya girmez.** `C:\devtools\.secrets\db.env` asla yazdırılmaz.
  Veritabanı yedekleri depoya girmez.
- Canlı ariumlab.com'da sipariş oluşturulmaz, e-posta gönderebilecek uçlar denenmez. Canlıda
  yalnızca salt okunur kontrol yapılır.
- Lisans veya satın alma kodu girilmez.

## Geliştirme ortamı (yerel, Windows, yönetici yetkisi yok)

| Ne | Nerede |
| --- | --- |
| Repo | `D:\dev\pergel` (web kökü dışında) |
| Geliştirme sitesi | `http://localhost/pergel-tema/` → `C:\xampp\htdocs\pergel-tema` |
| PHP 8.4 (site ve CLI) | `C:\devtools\php84` — Apache bu siteyi FastCGI ile 8.4'te çalıştırır (`C:\xampp\apache\conf\extra\pergel-php84.conf`); XAMPP'in 8.2'si diğer siteler için aynen durur |
| MariaDB 11.8 | `C:\devtools\mariadb118`, port **3308** (XAMPP'in 3307'si ayrı) |
| Node 24, Composer, WP-CLI | `C:\devtools\node24`, `C:\devtools\bin` |
| Servisler | `C:\devtools\pergel-dev.ps1 start|stop|status` (oturum açılışında `watch` ile başlar) |

Bash'te PATH: `export PATH="/c/devtools/bin:/c/devtools/php84:/c/devtools/node24:/c/devtools/mariadb118/bin:$PATH"`

Tema ve eklenti siteye junction ile bağlıdır; repoda yapılan değişiklik sitede anında görünür:

```powershell
tools\dev\link.ps1            # bağla (+ perf-probe mu-plugin)
tools\dev\link.ps1 -Unlink    # bağlantıları kaldır
```

## Komutlar

```bash
composer check                     # PHPCS (WPCS 3 + PHPCompatibilityWP 8.1) + PHPStan seviye 8 + PHPUnit
php tools/tokens.php               # theme/tokens/*.json → theme.json + styles/ (--check: yalnızca karşılaştır)
php tools/blocks.php               # engine/blocks/*/block.json kurallarını denetle → engine/blocks/manifest.php (--check)
composer pot                       # theme/languages/pergel.pot + engine/languages/pergel-engine.pot
composer i18n                      # .pot → tr_TR .po birleştir + .mo/.l10n.php/.json derle (--check; --export/--import JSON)
npm run validate:json              # theme.json ve block.json'u wp/7.1 şemasına göre doğrula
wp --path=C:/xampp/htdocs/pergel-tema eval-file tools/fixtures/seed.php   # sabit test içeriği; pattern önbelleğini de temizler
npm run budget -- --base http://localhost/pergel-tema --log C:/xampp/htdocs/pergel-tema/wp-content/pergel-perf.jsonl --channel chrome
wp --path=C:/xampp/htdocs/pergel-tema pergel info
# Mağaza (WooCommerce'li site; Windows'ta wp yerine wp-cli.phar):
WP_DIR=<site> PERGEL_WP_PHAR=C:/devtools/bin/wp-cli.phar PERGEL_CHANNEL=chrome npm run test:shop
npm run budget -- --budget tests/budget/shop.json --base <url> --log <wp-content>/pergel-perf.jsonl --channel chrome   # WP_DEBUG kapalı
```

Bütçe (`tests/budget/budget.json`, K11) aşılırsa `measure.mjs` 1 ile çıkar. Bütçeyi aşan değişiklik
birleştirilmez; limit yalnızca gerekçesi `docs/DECISIONS.md`'ye yazılarak değiştirilir.

## Kurallar

- WordPress 7.1+, PHP 8.1+. Deneysel (`__experimental`, `__unstable`) API doğrudan kullanılmaz.
- Ön yüzde jQuery yok; etkileşim Interactivity API ve script modülleriyle, yalnızca blok sayfadaysa.
- Gövdede `<link>` yok, `posts_per_page => -1` yok, aramada `LIKE '%x%'` yok, sayfa
  görüntülenirken DB yazması yok, `wp_is_mobile()` ile farklı HTML yok.
- SEO eklentisi varsa Pergel başlık/meta/JSON-LD basmaz, onun grafiğine ekler (K8).
- Kullanıcıya görünen her metin çevrilebilir: tema `pergel`, eklenti `pergel-engine` text domain'i.
- Ayrıntılar: `docs/CONVENTIONS.md`.

## Aşamalar

F0 kurulum ve iskelet → F1 tasarım sistemi → F2 engine çekirdeği → F3 bloklar ve pattern'ler →
F4 WooCommerce → F5 AI katmanı ve proje hattı → F6 sertleştirme ve 1.0 → F7 ariumlab.com.
Her aşama "bitti tanımı" ile kapanır; tanımlar `docs/DECISIONS.md` sonundadır.
