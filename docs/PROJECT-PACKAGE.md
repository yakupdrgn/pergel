# Proje paketi formatı (K12)

> Durum: taslak. Format F5'te `wp pergel import` ile birlikte kesinleşir ve sürümlenir
> (`package.yaml` içindeki `format: 1`).

Her Pergel sitesi, platformdan bağımsız dosyalardan oluşan bir proje paketinden üretilir. Aynı
paket tekrar uygulandığında aynı site çıkar (idempotent). Paket, AI ile site kurmanın ve gerekirse
WordPress'ten çıkışın temelidir.

```
proje/
├─ package.yaml           # format sürümü, proje adı, diller, hedef Pergel sürümü
├─ brief.md               # firma, hedef kitle, ton, hedefler, olmazsa olmazlar
├─ research/              # rakip analizi, ürün karşılaştırmaları, anahtar kelimeler
├─ brand/
│  ├─ tokens.json         # W3C Design Tokens (2025.10): renk, tipografi, boşluk, radius, gölge
│  ├─ logo.svg
│  └─ fonts/              # woff2, lisansıyla
├─ sitemap.yaml           # sayfa ağacı, URL'ler, şablon seçimi
├─ content/*.md           # sayfa başına: frontmatter'da bölüm listesi (pattern + alanlar), gövdede metin
├─ products/
│  ├─ products.csv        # WooCommerce CSV formatı; SKU'ya göre idempotent
│  └─ images/
├─ media/                 # görsel ve videolar + media.yaml (kaynak, lisans, alt metin)
└─ legal/                 # KVKK, mesafeli satış vb. (avukat veya müşteri onaylı)
```

## Örnek `sitemap.yaml`

```yaml
pages:
  - slug: ""              # anasayfa
    title: Ana sayfa
    template: front-page
    content: content/home.md
  - slug: hakkimizda
    title: Hakkımızda
    content: content/about.md
  - slug: urunler
    title: Ürünler
    type: shop            # WooCommerce mağaza sayfası
```

## Örnek `content/home.md`

```markdown
---
title: Ana sayfa
seo:
  title: Organik ürünler | A Şirketi
  description: Sertifikalı organik ürünler, üreticiden kapınıza.
sections:
  - pattern: pergel/hero-split
    heading: Topraktan sofraya
    text: Sertifikalı organik ürünler.
    image: media/hero.jpg
    cta: { label: Ürünleri gör, url: /urunler/ }
  - pattern: pergel/features-3
    items:
      - { title: Sertifikalı, text: "..." }
---

Serbest metin bölümleri (gövde) Markdown olarak yazılır ve çekirdek bloklara çevrilir.
```

## Kurallar

- Pattern adları ve alanları Pergel pattern kataloğundan gelir; bilinmeyen pattern içe aktarmayı
  durdurur (sessizce atlanmaz).
- Token'lar `wp pergel tokens build` ile child temanın `theme.json`'una derlenir; düşük kontrastlı
  renk çifti derlemeyi durdurur.
- İçerikte yalnızca çekirdek bloklar ve kayıtlı Pergel blokları oluşur; "Özel HTML" bloğu yok.
- Paket sır içermez (API anahtarı, şifre). Ortam bilgileri ayrı ve git dışında tutulur.
- Panelde yapılan değişiklikler `wp pergel export` ile pakete geri yazılabilir.
