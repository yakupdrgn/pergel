---
name: pergel-brand
description: Bir markanın renklerini, fontlarını ve köşe yuvarlaklığını Pergel proje paketinin `brand/tokens.json` dosyasına çevirir; WCAG AA kontrastını tutturur ve temanın sunduğu fontlardan seçer. Bir pakete marka kimliği eklenirken veya değişirken kullan.
---

# Pergel marka token'ları

`brand/tokens.json`, temanın `tokens/default.json` dosyasının üzerine yazılan kısmi bir W3C
Design Tokens dosyasıdır. İçe aktarma onunla paket adında bir alt tema yazar ve etkinleştirir.
Format: `docs/TOKENS.md`; paketteki yeri: `docs/PROJECT-PACKAGE.md` → `brand/tokens.json`.

## Kurallar

- **Yalnızca değişeni yaz.** Ana temanın token'ları zaten geçerli ve erişilebilir; marka için
  genellikle `color`, `font-family` ve `radius` yeter. Boyut ve boşluk ölçeği ancak marka
  rehberi açıkça başka diyorsa değişir.
- **Zorunlu renkler** (silinemez, yeniden adlandırılamaz): `base` (sayfa zemini), `surface`
  (kart ve footer zemini), `contrast` (metin), `muted` (ikincil metin), `accent` (bağlantı,
  düğme), `on-accent` (düğme metni). Ayraç rengi `line`'dır; `text`, `link`, `border` adları
  yasaktır.
- **Kontrast 4.5:1 (WCAG AA):** contrast/base, contrast/surface, muted/base, muted/surface,
  accent/base, accent/surface, on-accent/accent. Markanın ana rengi bu çiftleri tutmuyorsa
  rengi sessizce değiştirme: koyulaştırılmış (veya koyu temada açılmış) bir ton öner, iki
  değerin oranını da göster ve kullanıcıya sor. Açık marka rengi çoğunlukla `accent` değil,
  süs (ör. `line` veya ek bir renk slug'ı) olarak kullanılabilir.
- **Fontlar yalnızca temanın içinden:** Paket format 1 font dosyası taşımaz, uzak font (Google
  Fonts) kabul edilmez. Ana temada olan aileler: Figtree, Geist, Lexend Exa, Manrope, Space
  Grotesk (`theme/assets/fonts/`). Markanın fontu bunlardan biri değilse en yakını öner ve
  sor; ya da sistem fontunda kal (varsayılan). Bir aileyi seçerken token'ı `$extensions`'taki
  `fontFace` listesiyle birlikte, aileyi kullanan style variation'dan aynen kopyala:

  | Aile | Kaynak |
  | --- | --- |
  | Geist, Space Grotesk | `theme/tokens/graphite.json` |
  | Manrope | `theme/tokens/clinical-air.json` |
  | Figtree, Lexend Exa | `theme/tokens/lab-precision.json` |

- Token'la anlatılamayan kurallar (büyük harfli düğme, menü yazısı) `$extensions."com.ariumlab.pergel".styles`
  altında theme.json `styles` biçiminde yazılır; style variation'lardaki gibi. Bir style
  variation'ı markaya çevirirken onları da taşı.
- **Logo** token değildir: `package.yaml`'da `logo:` (format 3, SVG olabilir; Safe SVG ve
  `--user` gerekir). Logo adı da içeriyorsa `settings.brand.logo_has_name: true` başlıktaki site
  adını gizler.
- Köşe yuvarlaklığı `radius` grubundadır: `small`, `medium`, `large` ölçek; `button` ve
  `card` ölçeğe başvurur (`"{radius.large}"`). Keskin köşeli marka için ölçeği küçült,
  başvuruları değiştir.

## İş sırası

1. Marka rehberinden veya kullanıcının verdiği değerlerden renkleri (hex), fontları ve köşe
   biçimini çıkar. Değer ölçülmüşse (ör. canlı siteden), kaynağını söyle.
2. Her renk için hangi slug'a gideceğini bir tabloyla göster; kontrast oranlarını hesapla.
3. `brand/tokens.json`'u yaz. Örnek:

   ```json
   {
   	"color": {
   		"accent": { "$value": "#0f5c4a" },
   		"surface": { "$value": "#f3f6f4" }
   	},
   	"radius": {
   		"button": { "$value": "{radius.large}" }
   	}
   }
   ```

4. Denetle; kontrast, başvuru ve font dosyası sorunları burada çıkar:

   ```sh
   wp pergel package validate ./proje --strict
   ```

   Mesaj oranı da verir (`contrast of color.accent on color.base is 2.14:1, needs 4.5:1`).
5. Geliştirme sitesine içe aktar ve birkaç sayfayı (ön sayfa, bir form, footer) açık ve
   koyu zeminli bölümleriyle tarayıcıda gör. Alt tema `create`, tekrar içe aktarmada `keep`
   olmalı.

## Dikkat

- İçe aktarma başka bir alt tema etkinken durur; o sitede önce Pergel teması etkinleşmelidir.
  Etkin alt temayı kendiliğinden değiştirme, kullanıcıya sor.
- `brand/tokens.json` paketten silinirse tekrar içe aktarmada ana tema yeniden etkinleşir.
