# Proje paketi formatı (K12)

> Durum: format 1. Okuyucu ve denetim F5a'da tamamlandı (`wp pergel package validate`);
> içe aktarma (`wp pergel import`) F5b'de, dışa aktarma F5c'de gelir.

Her Pergel sitesi, platformdan bağımsız dosyalardan oluşan bir proje paketinden üretilir. Aynı
paket tekrar uygulandığında aynı site çıkar (idempotent). Paket, AI ile site kurmanın ve gerekirse
WordPress'ten çıkışın temelidir.

```
proje/
├─ package.yaml           # zorunlu: format, ad, site başlığı, dil, Pergel ayarları
├─ sitemap.yaml           # zorunlu: sayfa ağacı, şablon ve sayfa türü, menü
├─ content/*.md           # sayfa başına: frontmatter'da SEO ve bölümler, gövdede Markdown
├─ media/                 # paketteki görseller (jpg, jpeg, png, webp, avif, gif)
├─ media.yaml             # görsel başına alt metin, açıklama, kaynak, lisans, yazar
├─ brand/tokens.json      # W3C Design Tokens; temanın tokens/default.json'unun üzerine yazılır
├─ brief.md               # serbest: firma, hedef kitle, ton (okuyucu yok sayar)
└─ research/              # serbest: rakip analizi, anahtar kelimeler (okuyucu yok sayar)
```

Format 1'de henüz olmayanlar: ürünler (`products/`), logo ve font dosyaları, birden çok dil.
Bunlar formatın sonraki sürümleriyle eklenir; `format` numarası artar ve eski numarayı okuyan
motor yeni paketi reddeder.

## Denetim

```sh
wp pergel package validate ./proje            # sorunlar ve notlar
wp pergel package validate ./proje --strict   # notlar da sorun sayılır
wp pergel pattern list                        # pattern'ler ve alanları
wp pergel pattern fields hero-split           # content dosyasına kopyalanacak örnek bölüm
```

Okuyucu bütün paketi okur ve her şeyi bir seferde, dosyası ve yeriyle bildirir
(`sitemap.yaml: pages[3]: slug: "hakkimizda" is used twice at this level`). İki tür bildirim
var:

- **Sorun** (problem) içe aktarmayı durdurur: bilinmeyen anahtar, eksik dosya, tanınmayan
  pattern veya alan, geçersiz ayar, derlenmeyen veya düşük kontrastlı token, paket dışından
  görsel.
- **Not** (note) durdurmaz: pattern'in örnek metnini koruyan alan, alt metni olmayan görsel,
  hiçbir sayfanın kullanmadığı content dosyası. `--strict` notları da sorun sayar; teslim
  edilecek paketler `--strict` ile temiz olmalıdır.

Denetim siteyi değiştirmez; pattern'leri ve ayarları etkin temaya göre doldurup kontrol eder.

## `package.yaml`

```yaml
format: 1
name: ornek-firma          # küçük harf, rakam ve tire
title: Örnek Firma         # site başlığı
tagline: Topraktan sofraya # isteğe bağlı
locale: tr_TR              # isteğe bağlı; tr_TR, en_US gibi
settings:                  # isteğe bağlı; Pergel ayar ekranının alanları
  brand:
    name: Örnek Firma A.Ş.
    email: info@ornek.test
    logo_inverse: media/logo-beyaz.png
  seo:
    og_image: media/paylasim.jpg
```

`settings` grupları ve anahtarları ayar ekranıyla aynıdır (`wp pergel settings get`); değerler
ayar ekranının kurallarıyla denetlenir. Görsel alanları paketteki bir dosyanın yolunu alır.

## `sitemap.yaml`

```yaml
pages:
  - slug: ""                 # ön sayfa: boş slug, en üst düzeyde tek
    title: Ana sayfa
    content: content/home.md
  - slug: hakkimizda
    title: Hakkımızda
    content: content/about.md
    children:
      - slug: ekip           # adres: /hakkimizda/ekip/
        title: Ekibimiz
        template: page-landing
  - slug: blog
    title: Blog
    type: posts              # yazılar sayfası
  - slug: sss
    title: SSS
    content: content/sss.md
    menu: false              # varsayılan menüde gösterme
menu:                        # isteğe bağlı
  - hakkimizda
  - { page: hakkimizda/ekip, label: Ekip }
  - { label: Instagram, url: "https://instagram.com/ornek" }
```

- Sayfa anahtarları: `slug`, `title` (zorunlu), `content`, `template`, `type`, `menu`,
  `children`. Bir düzeyde aynı slug iki kez kullanılamaz.
- `template`: temanın sayfa şablonlarından biri (`theme.json` → `customTemplates`, bugün
  `page-landing`). Yazılmazsa varsayılan sayfa şablonu.
- `type`: `page` (varsayılan), `posts`, `shop`, `cart`, `checkout`, `account`; `page`
  dışındakiler birer kez kullanılır.
- `content` yoksa sayfa boş açılır (`posts`, `shop` gibi sayfalar içeriğini kendisi gösterir).
- **Menü:** `menu` listesi yazılmazsa sayfa ağacından üretilir; ön sayfa, sepet, ödeme ve hesap
  sayfaları ile `menu: false` olanlar dışarıda kalır. Liste yazılırsa yalnızca o liste kullanılır.
  Öge bir sayfa yolu (`hakkimizda/ekip`), `{ page, label, children }` veya `{ label, url }`
  olabilir. Adreslerde yalnızca `http`, `https`, `mailto` ve `tel` geçer.

## `content/*.md`

```markdown
---
seo:
  title: Örnek Firma | Organik ürünler
  description: Sertifikalı organik ürünler, üreticiden kapınıza.
  noindex: false
sections:
  - pattern: hero-split
    eyebrow: Doğal ve yerel
    heading: Topraktan **sofraya**
    text: Sertifikalı organik ürünler.
    buttons:
      - { label: Ürünleri gör, url: /urunler/ }
    image: media/tarla.jpg
  - markdown: true             # gövde buraya gelir
  - pattern: faq-accordion
    heading: Sık sorulanlar
    eyebrow: null              # bloğu kaldırır
    items:
      - summary: Kargo ne kadar sürer?
        text: İki iş günü.
      - summary: İade var mı?
        text: 14 gün içinde.
---

## Neden biz?

Üreticiden **doğrudan** gelir.
```

- Frontmatter'da yalnızca `seo` (`title`, `description`, `noindex`) ve `sections` olur. Sayfa
  başlığı `sitemap.yaml`'dadır.
- `sections` sırayla sayfayı kurar. `pattern` Pergel pattern adıdır (`hero-split` veya
  `pergel/hero-split`). `{ markdown: true }` gövdenin yerini belirler; yoksa gövde en sona
  eklenir. Frontmatter'ı olmayan dosya yalnızca gövdedir.
- Bilinmeyen pattern veya alan içe aktarmayı durdurur; sessizce atlanmaz.

### Alan modeli

Pattern'lerin alanları elle tutulan bir listeden değil, pattern'in bloklarından çıkarılır
(`engine/src/Package/Fields.php`); yeni bir pattern ek iş gerektirmeden paketten
kullanılabilir. `wp pergel pattern fields <ad>` alanları örnek değerleriyle gösterir.

| Alan | Blok | Değer |
| --- | --- | --- |
| `heading` | başlık | metin |
| `eyebrow` | kapsamdaki ilk başlıktan önceki paragraf | metin |
| `text` | diğer paragraflar | metin |
| `buttons` | düğme grubu | `{ label, url }` listesi |
| `image` | görsel, medya ve metin görseli | yol veya `{ src, alt }` |
| `background` | kapak (cover) görseli | yol veya `{ src, alt }` |
| `list` | liste | metin listesi |
| `summary` | açılır (details) bloğun başlığı | metin |
| `quote`, `cite` | alıntı paragrafları ve kaynağı | metin |
| `items` | aynı türden, alanları eşleşen kardeş bloklar | alan haritaları listesi |

- Aynı kapsamda ikinci bir alan `text_2`, `heading_2` diye adlandırılır.
- `items` öğeleri aynı alanlara sahiptir; biri diğerinde olmayan alanlar taşıyabilir (öne
  çıkan fiyat planı gibi). Fazla öğe ilkinin kopyası olarak eklenir, eksik öğe kaldırılır;
  öğeler arasındaki ayraç ve boşluk blokları öğelerle birlikte gelir ve gider. Öğeler öğe
  içerebilir.
- Metinler satır içi Markdown alır (`**kalın**`, `*italik*`, `` `kod` ``, `[bağlantı](url)`).
- `null` bloğu kaldırır. Yazılmayan alan pattern'in örnek metnini korur ve not olarak bildirilir.
- Site verisi gösteren bloklar (sorgular, gezinme, formlar, harita, mağaza blokları, iç içe
  pattern'ler) alan değildir.

### Markdown alt kümesi

Paragraflar; `##`–`######` başlıklar (`#` reddedilir: H1 sayfa başlığıdır); `-`, `*`, `+` ve
`1.` listeler (girintiyle iç içe); `>` alıntılar; ```` ``` ```` kod; `---` ayraç; tek başına
bir satırda `![alt](media/x.jpg "açıklama")` görsel; `|---|` satırlı tablolar. HTML metin olarak
gösterilir, çalıştırılmaz; pakette "Özel HTML" bloğu yoktur. Hatalar satır numarasıyla
bildirilir.

### YAML alt kümesi

Paket kendi YAML okuyucusuyla okunur (eklenti veya kütüphane gerekmez). Okunanlar: blok
haritalar ve listeler, `{ }` ve `[ ]` (birden çok satıra yayılabilir), düz, `'tek'` ve
`"çift"` tırnaklı değerler, `|` ve `>` blokları, `#` yorumlar ve baştaki tek `---`. Değerler
YAML 1.2'deki gibidir: `yes`, `no`, `on`, `off` metindir. Satır numarasıyla reddedilenler:
girintide sekme, anchor ve alias, tag, birden çok belge, bir sonraki satıra taşan değer,
tekrarlanan anahtar, yerinde olmayan girinti.

## Görseller ve `media.yaml`

Görseller yalnızca paketin içinden gelir: `media/hero.jpg` gibi, paket köküne göre bir yol.
Adres (`https://…`), mutlak yol veya `..` reddedilir; böylece paket kendi başına tamdır ve her
görselin kaynağı ile lisansı kayıtlıdır.

```yaml
media/tarla.jpg:
  alt: Sabah ışığında buğday tarlası
  source: Firma arşivi
  license: Firmaya ait
media/logo-beyaz.png:
  alt: ""                 # süs görseli
```

Anahtarlar: `alt`, `caption`, `source`, `license`, `author`. Bölüm alanında `{ src, alt }` ile
verilen alt metin önce gelir, sonra `media.yaml`. Alt metni olmayan görsel not olarak bildirilir;
`alt: ""` görselin süs olduğunu söyler. Markdown görselinin alt metni Markdown'daki metindir.

## `brand/tokens.json`

Temanın `tokens/default.json` dosyasının üzerine yazılan kısmi bir W3C Design Tokens
dosyasıdır; yalnızca değişen token'lar yazılır. Birleşen ağaç `wp pergel tokens build` ile aynı
kurallarla derlenir: çözülmeyen başvuru veya düşük kontrastlı renk çifti sorundur.

```json
{ "radius": { "card": { "$value": "{radius.large}" } } }
```

## Kurallar

- İçerikte yalnızca çekirdek bloklar ve kayıtlı Pergel blokları oluşur; "Özel HTML" bloğu yok.
- Paket sır içermez (API anahtarı, şifre). Ortam bilgileri ayrı ve git dışında tutulur.
- Panelde yapılan değişiklikler `wp pergel export` ile pakete geri yazılabilecek (F5c).
