---
name: pergel-package
description: Bir firmanın brief'inden Pergel proje paketi (package.yaml, sitemap.yaml, content/*.md, media.yaml) hazırlar, `wp pergel package validate --strict` ile temizler ve bir geliştirme sitesine iki kez içe aktararak sınar. Yeni bir site kurulurken, paketteki bir sayfa veya bölüm değişirken ya da panelde düzenlenmiş bir site pakete geri alınırken kullan.
---

# Pergel proje paketi

Paket bir sitenin tamamını tarif eden klasördür; `wp pergel import` siteyi ondan kurar. Formatın
tek kaynağı `docs/PROJECT-PACKAGE.md`'dir; bu skill yalnızca iş sırasını ve iyi paketin ölçütünü
verir. Format hakkında bir şeyden emin değilsen o belgeye bak, tahmin etme.

## Sınırlar

- İçe aktarma ve dışa aktarma yalnızca geliştirme veya prova sitesinde çalışır. Canlı siteye ve
  staging'e paket kullanıcı açıkça istemeden aktarılmaz.
- Paket sır içermez: şifre, API anahtarı, kişisel veri yok. Paket bir git deposuna girebilir.
- Görsel web'den indirilip pakete konmaz. Görsel kullanıcıdan gelir; kaynağı ve lisansı
  `media.yaml`'a yazılır. Lisansı bilinmiyorsa sor.
- Firmayla ilgili bilgi uydurulmaz (adres, telefon, sertifika, rakam, müşteri adı, yorum).
  Brief'te yoksa kullanıcıya sor ya da o bölümü pakete alma.

## İş sırası

1. **Brief'i oku.** Paketteki `brief.md` ve `research/` serbest notlardır (okuyucu yok sayar).
   Eksikse sor: firma adı ve kısa adı (paket `name`), dil (`tr_TR` gibi), sayfalar, hedef
   kitle ve ton, iletişim bilgileri, elindeki görseller.
2. **`package.yaml`:** `format: 1` (ürün dosyası varsa `format: 2`), `name` (küçük harf, rakam, tire), `title`, `tagline`,
   `locale`. `settings` ayar ekranının gruplarıdır; anahtarları
   `wp pergel settings get` gösterir. Yalnızca varsayılandan farklı olanları yaz.
3. **`sitemap.yaml`:** Sayfa ağacı. Ön sayfa `slug: ""`. Adreslerde Türkçe karakter yok
   (`hakkimizda`, `iletisim`). Blog için `type: posts`, mağaza için `shop`, `cart`, `checkout`,
   `account` (WooCommerce gerekir). Menüyü ağaç iyi veriyorsa `menu` yazma.
4. **Bölümleri seç.** Her sayfa için pattern'leri seç:

   ```sh
   wp pergel pattern list                         # ad, başlık, kategori, alanlar
   wp pergel pattern list --category=banner       # banner, about, services, testimonials,
                                                  # pergel-faq, pergel-pricing, pergel-stats,
                                                  # pergel-process, pergel-product,
                                                  # call-to-action, contact ...
   wp pergel pattern fields hero-split            # content dosyasına kopyalanacak bölüm
   ```

   Sitede yapay zekâ erişimi açıksa (Pergel → Ayarlar → Modüller → Yapay zekâ erişimi) aynı
   bilgiyi `pergel/list-patterns` ve `pergel/get-pattern-fields` yetenekleri verir.

   Bir sayfada aynı pattern iki kez kullanılmaz; sayfa bir akış izler: giriş (banner) → değer
   veya hizmetler → kanıt (rakamlar, yorumlar, logolar) → süreç veya SSS → çağrı (call-to-action).
5. **`content/<sayfa>.md`:** `pattern fields` çıktısını `sections` altına kopyala ve her alanı
   doldur. Kurallar:
   - Örnek metin bırakma: yazılmayan alan pattern'in örnek metnini korur ve not olur.
     İstemediğin bloğu `null` ile kaldır.
   - `items` listesini gerçek sayıda öğeyle yaz; fazlası ilk öğenin kopyası olur, eksiği kalkar.
   - Görsel alanına paketteki bir yolu (`media/x.jpg`) ya da `null` yaz; görsel yoksa pattern'in
     örnek görseli kalmasın.
   - Metinde yalnızca satır içi Markdown (`**kalın**`, `*italik*`, `[bağlantı](/adres/)`).
   - `seo.title` en çok 60, `seo.description` 120–155 karakter; ikisi de sayfaya özgü.
   - Gövde Markdown'ında başlıklar `##` ile başlar (`#` sayfa başlığıdır ve reddedilir).
   - Site içi bağlantılar köke göre (`/iletisim/`); sitemap'teki adreslerle aynı olmalı.
   **Ürünler (`products/<SKU>.md`, format 2):** Paket ürün yapmaz; mağazada o SKU'lu ürün
   olmalı. Yalnız değişecek alanları yaz (`title`, `excerpt`, `template`, `price`, `seo`,
   `sections` ve gövde); yazılmayan alan mağazadaki gibi kalır. Fiyatı kullanıcı açıkça
   istemedikçe yazma. Bölümlü ürün sayfası için `template: single-product-sections` ve
   "Ürün" kategorisindeki pattern'ler (`wp pergel pattern list --category=pergel-product`).
   Yalnız ürün içeren pakette `sitemap.yaml` gerekmez.
6. **`media/` ve `media.yaml`:** Her görsel için `alt` (görselde ne var, bağlamıyla), `source`,
   `license`, varsa `author`. Süs görseli `alt: ""`. Dosya adları küçük harf ve tireli.
7. **Marka:** Renk, font veya köşe yuvarlaklığı değişecekse `brand/tokens.json` için
   `pergel-brand` skill'ini kullan.
8. **Denetle:** Notlar dahil temiz olana kadar düzelt.

   ```sh
   wp pergel package validate ./proje --strict
   ```

   Her bildirim dosya ve yer verir (`content/home.md: sections[2].items[1].text: ...`).
   Bütün listeyi bir seferde düzelt, sonra yeniden çalıştır.
9. **Geliştirme sitesinde sına:**

   ```sh
   wp pergel import ./proje --dry-run   # create/update/set satırlarını oku; beklenmeyen var mı
   wp pergel import ./proje
   wp pergel import ./proje             # ikinci kez: her satır "keep" olmalı
   ```

   İkinci içe aktarmada `keep` dışında bir satır varsa paket tekrar üretilebilir değildir;
   sebebini bul (genellikle her koşuda değişen bir değer). Sonra sayfaları tarayıcıda aç; ön
   kontrol bir adresteki yabancı sayfa yüzünden durursa o sayfayı silme, kullanıcıya sor.

## Var olan bir paketi değiştirmek

- Panelde düzenleme yapılmış olabilir. Önce siteyi pakete geri al ve farkı incele:

  ```sh
  wp pergel export ./proje --dry-run
  wp pergel export ./proje
  git diff ./proje                      # paket bir depodaysa
  ```

- Sonra paketi değiştir, `validate --strict`, `import --dry-run` ve `import`.
- `skip` satırı panelde düzenlenmiş bir kayıttır; `--force` yalnızca kullanıcı panel
  değişikliğinin ezilmesini onaylarsa kullanılır.
- Paketten çıkarılan sayfa `stale` olarak yerinde kalır. `--prune` (çöpe taşır, görselleri
  siler) yalnızca kullanıcı isterse.

## Bitti ölçütü

- `package validate --strict` sorunsuz ve notsuz.
- Geliştirme sitesine ikinci içe aktarma yalnızca `keep`.
- Her sayfada gerçek, firmaya özgü metin; pattern'in örnek metni veya örnek görseli yok.
- Her görselin alt metni, kaynağı ve lisansı `media.yaml`'da.
