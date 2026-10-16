# Kodlama kuralları

## Genel

- WordPress Coding Standards 3 (WordPress-Extra + WordPress-Docs) ve PHPCompatibilityWP 8.1+.
  Tek istisna: PSR-4 dosya adları (`Plugin.php`, `class-plugin.php` değil).
- PHPStan seviye 8, `treatPhpDocTypesAsCertain: false`. Yeni kod `composer check`'ten temiz
  geçmeden birleştirilmez. `phpcs:ignore` yalnızca gerekçesiyle ve satır bazında kullanılır.
- Her PHP dosyası `declare( strict_types=1 );` ile başlar (`wp eval-file` ile çalışan betikler
  hariç; bu durum dosyada belirtilir) ve `defined( 'ABSPATH' ) || exit;` içerir.
- Namespace: tema `Pergel\Theme`, eklenti `Pergel\<Modül>`, geliştirme araçları `Pergel\Dev`.
  Global önek `pergel_`, hook'lar `pergel/<ad>` (ör. `pergel/modules`).
- Text domain: tema `pergel`, eklenti `pergel-engine`. Kullanıcıya görünen her metin çevrilebilir.
- Satır sonu LF, girinti tab (YAML hariç), UTF-8 (`.editorconfig`).
- `theme.json` preset'leri ve `styles/*.json` elle düzenlenmez; `tokens/` değiştirilip
  `php tools/tokens.php` (veya `wp pergel tokens build`) çalıştırılır (`docs/TOKENS.md`).
- WordPress gerektirmeyen mantık `tests/php/` altında PHPUnit ile test edilir.
- HTML şablon ve template part'larda sabit metin olmaz; metin `patterns/hidden-*.php` içinde
  çeviri fonksiyonuyla yazılır ve şablona `wp:pattern` ile girer. Metin değişince `composer pot`,
  ardından `composer i18n` (`php tools/i18n.php`): yeni metinlerin Türkçesi `languages/*.po`'ya
  yazılır ve derlenmiş dosyalarla birlikte commit edilir. CI `--check` ile denetler.
- Her şablon tam olarak bir H1 üretir (bütçe denetler). Ön sayfada H1'i içeriğin ilk bölümü taşır.
- WordPress tema pattern dosyalarının listesini önbelleğe alır: yeni pattern dosyası ekleyince
  `tools/fixtures/seed.php`'yi çalıştırın (veya `wp eval 'wp_get_theme()->delete_pattern_cache();'`).

## Güvenlik

- Girdi: yetki kontrolü (`current_user_can`) + nonce + sanitize. REST uçlarında her zaman
  `permission_callback` ve şema.
- Çıktı: geç kaçış (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) çıktı anında.
- SQL: yalnızca `$wpdb->prepare` veya WP sorgu API'leri.
- Uzaktan kod indirip çalıştırma, imzasız güncelleme, `eval`, `extract()` yok.
- Herkese açık AJAX/REST uçları hız sınırlı ve sonuç sayısı sınırlı.

## Performans (K11 bütçesiyle denetlenir)

- `posts_per_page => -1` yok; aramada `LIKE '%x%'` yok.
- Sayfa görüntülenirken DB'ye yazılmaz (sayaç, log, görsel boyutlandırma vb.).
- Pahalı sonuçlar object cache / transient'te tutulur ve ilgili içerik değişince temizlenir.
- Her URL tek HTML üretir: `wp_is_mobile()` ile farklı çıktı yok; herkese açık sayfada nonce yok.
- Ön yüzde jQuery yok. Etkileşim Interactivity API + script modülleriyle, yalnızca blok sayfadaysa.
- Gövde içinde `<link rel="stylesheet">` yok; blok CSS'i çekirdeğin blok bazlı yükleyicisiyle.
- Admin kodu yalnızca admin'de, CLI kodu yalnızca WP-CLI'de yüklenir.

## Tema

- Tasarım değerleri `theme.json`'da; sabit renk veya piksel değeri şablonlara yazılmaz, preset
  kullanılır (`var(--wp--preset--...)`).
- Sayfa başına tek H1. Header'daki site başlığı H1 değildir (`"level":0`).
- Çevrilmesi gereken metin içeren şablon bölümleri PHP pattern olarak yazılır.
- Pattern'ler yalnızca çekirdek bloklar ve sunucuda kayıtlı Pergel bloklarını kullanır; "Özel
  HTML" bloğu yok. Her pattern editörde geçerli açılmalıdır.

## SEO

- Bir SEO eklentisi aktifse Pergel title/meta/OG/JSON-LD/site haritası basmaz (K8).
- Yapısal veri sayfada görünen içerikle aynıdır; gizli fiyat şemaya yazılmaz.

## Git

- `main` korunur; iş dallarda yapılır, CI yeşilse birleştirilir. Sürümler `vX.Y.Z` etiketiyle.
- Commit ve push yalnızca kullanıcı onayıyla.
- Commit mesajı: kısa özet satırı (emir kipi, İngilizce), boş satır, gerekirse açıklama.
