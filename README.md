# Pergel

Hızlı, temiz kodlu ve tamamen WordPress yönetim panelinden tasarlanabilen bir WordPress sistemi:

- **`theme/`** — `pergel` blok teması (theme.json v3, Site Editor)
- **`engine/`** — `pergel-engine` eklentisi (bloklar, SEO/AI katmanı, WooCommerce özellikleri,
  içerik türleri, WP-CLI)

Gereksinimler: WordPress 7.1+, PHP 8.1+. Kurumsal siteler ve WooCommerce mağazaları için.

## Geliştirme

```bash
composer install && npm ci
composer check            # PHPCS + PHPStan (seviye 8)
npm run validate:json     # theme.json / block.json şema doğrulaması
npm run budget -- --base <site-url> --log <wp-content>/pergel-perf.jsonl
```

Yerel kurulum, sınırlar ve çalışma kuralları için `CLAUDE.md`; mimari ve kararlar için `docs/`.

## Lisans

GPL-2.0-or-later.
