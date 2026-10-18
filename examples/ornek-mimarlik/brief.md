# Örnek Mimarlık: prova paketi

Pergel'in örnek proje paketi (F5f). Örnek Mimarlık uydurma bir firmadır; adresi, telefonu ve
e-postası gerçek değildir (`.test` alan adı, `555` hattı). Görseller Pergel deposu için
üretilmiş soyut çizimlerdir, GPL-2.0-or-later.

- Sayfalar: ana sayfa, hakkımızda, hizmetler, projeler, blog, iletişim.
- Marka: taş rengi zemin (`#f5f2ec`), koyu yeşil vurgu (`#2f5d50`), Manrope.
- Dil: Türkçe (`tr_TR`).

`tools/ci/rehearsal.sh` bu paketi CI'da her değişiklikte boş bir siteye kurar, ikinci içe
aktarmanın yalnızca `keep` verdiğini ve dışa aktarmanın paketi dosya dosya geri verdiğini
sınar (docs/PROJECT-PACKAGE.md, "Örnek paket ve prova sitesi"). Paketteki dosyalar
`wp pergel export`'un çıktısıdır: değiştirirken siteye içe aktarıp panelde düzenleyin ve geri
dışa aktarın ya da elle yazdığınızı bir içe/dışa aktarma turuyla biçime getirin.

Bu dosyayı paket okuyucusu yok sayar.
