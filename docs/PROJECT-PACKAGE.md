# Proje paketi formatı (K12)

> Durum: format 1. Okuyucu ve denetim F5a'da (`wp pergel package validate`), içe aktarma
> F5b'de (`wp pergel import`), dışa aktarma F5c'de (`wp pergel export`) tamamlandı. Paketi
> Claude Code ile hazırlamak için depoda iki skill var (F5e): `pergel-package` ve
> `pergel-brand` (`.claude/skills/`).

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
  pattern veya alan, geçersiz ayar, derlenmeyen veya düşük kontrastlı token, temada olmayan
  font dosyası, paket dışından görsel.
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

Paket font dosyası taşımaz; `font-family` token'ının `fontFace` dosyaları ana temadan gelir
(Figtree, Geist, Lexend Exa, Manrope, Space Grotesk). Ana temada olmayan dosya denetimde sorun
olur; içe aktarma yarıda kalmaz.

## İçe aktarma

```sh
wp pergel import ./proje --dry-run   # ne olacağını gösterir, hiçbir şey kaydetmez
wp pergel import ./proje             # siteyi paketten kurar
wp pergel import ./proje             # tekrar: değişmeyen her şey "keep"
wp pergel import ./proje --prune     # paketten çıkan sayfaları çöpe, görselleri siler
```

Sıra: önce paket denetlenir (`package validate` ile aynı; `--strict` notları da sorun sayar),
sonra site. Sorun varsa hiçbir şey kaydedilmez. Ardından site başlığı, slogan ve dil; kullanılan
görseller; Pergel ayarları; sayfalar; okuma ayarları (ön sayfa, yazılar sayfası, WooCommerce
sayfaları); menü (`wp_navigation`); son olarak `brand/tokens.json` için alt tema. Her adım bir
satır bildirir: `create`, `update`, `keep`, `set`, `adopt`, `skip`, `stale`, `remove`.

**Paketin yaptığını tanıma.** İçe aktarmanın yaptığı her sayfa, görsel ve menü
`_pergel_package` (paket adı) ve `_pergel_package_key` (sayfa yolu, görsel yolu veya `menu`)
meta'sını taşır. Tekrar içe aktarmada bunlar bulunur, adres değişse de aynı kayıt güncellenir.
Paketin yapmadığı kayda dokunulmaz.

**Tekrar üretilebilirlik.** Her kayıtta iki özet tutulur: paketten üretilen verinin özeti
(`_pergel_package_source`) ve kaydedildiği andaki yazının özeti (`_pergel_package_saved`;
başlık, içerik, adres, üst sayfa, sıra, durum, şablon, SEO). Paket değişmediyse kayıt `keep`.
Paket değiştiyse `update`. Yazı panelde düzenlendiyse (özet tutmuyorsa) `skip` ve not:
panel değişikliği ezilmez; `--force` ezer. Görseller dosya özetiyle (sha1) karşılaştırılır:
aynı dosya `keep` (alt metin, açıklama veya kaynak değiştiyse `update`), başka dosya
`update` (eski ek silinir, yenisi yüklenir).

**Paketten çıkanlar.** Önceki içe aktarmanın yapıp paketin artık içermediği sayfa, görsel ve
menü `stale` olarak listelenir ve yerinde kalır. `--prune` sayfaları ve menüyü çöpe taşır,
görselleri siler.

**Ön kontrol.** Paketin bir adresinde paketin yapmadığı bir sayfa varsa içe aktarma başlamaz
(`/blog/ is page 7, which this package did not make; move or delete it first`); siteyi ezen
bir varsayılan yoktur. İstisna WooCommerce sayfalarıdır: `type: cart` gibi bir sayfanın adresinde
WooCommerce'in o türdeki sayfası varsa pakete alınır (`adopt`); içeriği ve bloğu korunur, başlık
ve adres paketten gelir. WooCommerce sayfası hiç yoksa önce WooCommerce'in kendi kurulumuyla
oluşturulur. Mağaza türlü sayfa WooCommerce etkin değilken sorundur.

**Dil.** `locale` yalnızca site dili yüklüyse (`en_US` her zaman) ayarlanır; değilse not:
içe aktarma dil paketi indirmez.

**Alt tema.** `brand/tokens.json` varsa tema dizinine paket adıyla bir alt tema yazılır
(`style.css` başlığında `Pergel package: <ad>`; `tokens/default.json` birleşik token'lar;
`theme.json` derlenir) ve etkinleştirilir. Bu imza olmayan aynı adlı bir dizin veya paketin
yazmadığı etkin bir alt tema sorundur. Paketten `brand/tokens.json` çıkarılırsa ana tema
yeniden etkinleşir.

## Dışa aktarma

```sh
wp pergel export ./proje --dry-run         # ne yazılacağını gösterir, hiçbir şey yazmaz
wp pergel export ./proje                   # paketin yaptığı sayfaları pakete yazar
wp pergel export ./proje --strict          # not varsa hata verir
wp pergel export ./site --all --name=site  # paketle kurulmamış bir sitenin tüm sayfaları
```

İçe aktarmanın tersidir: site başlığı, slogan ve dil, varsayılandan farklı Pergel ayarları,
sayfalar ve SEO alanları, menü, kullanılan görseller ve etkin alt temanın token'ları. Paket adı
verilmezse sayfaları yapan paketin adı kullanılır (birden çok paket varsa `--name` gerekir);
`--all` ile ve paket yoksa site başlığından türetilir.

**Sayfalar.** Varsayılan olarak yalnızca paketin yaptığı yayımlanmış sayfalar, `--all` ile tüm
yayımlanmış sayfalar yazılır. Ön sayfa `slug: ""` ve `content/home.md` olur; yazılar sayfası ve
WooCommerce sayfaları türleriyle yazılır, içerikleri yazılmaz. Paketin tutamayacağı yapılar not
olur: ön sayfanın alt sayfaları ve üst sayfası dışa aktarılmayan sayfalar en üste taşınır,
pakete uymayan adres (`ürünler`) dönüştürülür (`urunler`).

**İçerik.** Kök bloğu `metadata.patternName` taşıyan (blok ekleyicinin, içe aktarmanın ve
starter'ların eklediği) bir bölüm, pattern adı ve alanlarıyla `sections` altına yazılır; diğer
bloklar Markdown gövdesine. Gövde bölümlerin arasındaysa yerinde `- markdown: true` durur.
Markdown'a yazılamayan blok (grup, sütunlar, kısa kod) dışarıda kalır ve not olur.

**Denetim.** Her bölüm pattern'e geri doldurulur, her Markdown parçası bloklara geri çevrilir
ve sayfadakiyle karşılaştırılır (varlıklar, boşluklar ve blok meta verisi hariç). Farklıysa not:
bölüm alanlarının ötesinde düzenlenmişse (blok eklenmiş, stil değişmiş) içe aktarma pattern'i bu
alanlarla geri verir. Yazılan paket sonunda `package validate` gibi denetlenir; sorun hatadır.

**Görseller.** Blok, ayar veya Markdown'daki görsel ortam kütüphanesinde aranır (ek kimliği,
adresi veya boyutlu kopyasının adresi). Paketin yaptığı görsel kendi yolunu korur, diğerleri
`media/<dosya adı>` olur; özgün dosya kopyalanır. `media.yaml`'a alt metin, açıklama, kaynak,
lisans ve sahibi yazılır. Bölümdeki alt metin görselinkinden farklıysa alan `{ src, alt }`
olur. Kütüphane dışındaki görsel not olur.

**Menü.** Paketin menüsü, yoksa en yeni `wp_navigation`. Sayfaya giden bağlantı sayfa yolu,
diğerleri `{ label, url }` olur; site içi adresler köke göre yazılır. Menü sayfa ağacının
varsayılanıyla aynıysa `menu` yazılmaz.

**Alt tema.** Etkin alt temanın `tokens/default.json` dosyası `brand/tokens.json` olur. Alt
temayı bu paket yazmadıysa not: o sitede içe aktarmadan önce Pergel teması etkin olmalıdır.

**Klasör.** Hedef boş, yeni veya aynı paketin klasörü olmalıdır. Tekrar dışa aktarma yalnızca
değişen dosyaları yazar (`create`, `update`, `keep`) ve sitede artık olmayan içerik ve görsel
dosyalarını siler (`remove`). Dışa aktarılan paket aynı siteye içe aktarılırsa her kayıt `keep`
olur: içeriği paketin yazacağıyla zaten aynı olan sayfa, panelde düzenlenmiş olsa da değişmiş
sayılmaz.

## Kurallar

- İçerikte yalnızca çekirdek bloklar ve kayıtlı Pergel blokları oluşur; "Özel HTML" bloğu yok.
- Paket sır içermez (API anahtarı, şifre). Ortam bilgileri ayrı ve git dışında tutulur.
- Panelde yapılan değişiklikler `wp pergel export` ile pakete geri yazılır.

## Claude skill'leri (F5e)

Depodaki `.claude/skills/` altında, Claude Code'un bu depoda açıldığında kullandığı iki skill
vardır. Format bu belgede kalır; skill'ler iş sırasını ve iyi paketin ölçütünü verir.

| Skill | Ne yapar |
| --- | --- |
| `pergel-package` | Brief'ten paketi hazırlar: sayfa ağacı, pattern seçimi (`pattern list` / `fields` veya yapay zekâ erişimi), içerik, görseller; `validate --strict` temizlenene kadar düzeltir, geliştirme sitesine iki kez içe aktararak ikincinin yalnızca `keep` verdiğini doğrular. Var olan paketi değiştirmeden önce `export` ile panel değişikliklerini geri alır. |
| `pergel-brand` | Marka renklerini, fontlarını ve köşe yuvarlaklığını `brand/tokens.json`'a çevirir; zorunlu renk slug'larını ve 4.5:1 kontrastı korur, fontu ana temadakilerden seçer. Tutmayan marka rengini sessizce değiştirmez, öneri sunar. |

İkisi de canlı siteye içe aktarmaz, firma bilgisi uydurmaz, web'den görsel indirmez; bilgi
eksikse kullanıcıya sorar.
