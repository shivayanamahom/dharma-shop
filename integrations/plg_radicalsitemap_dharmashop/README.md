# Dharma Shop integration for Radical Sitemap

Joomla plugin included in the Dharma Shop package and also built as a standalone installable ZIP.

The plugin contributes published, publicly accessible Dharma Shop categories, brands, badges, products, and landing pages. It skips broken landing pages and rows whose SEO robots metadata contains `noindex`. It requires `com_radicalsitemap`; landing pages are scanned only when `com_dharmashop_landing` is enabled.

Build a standalone ZIP with `./build.sh`; the main package builder includes the same plugin separately. Joomla installs new plugins disabled by default, so the sitemap integration must be enabled from **Настройки → Расширения** or the Joomla plugin manager.
