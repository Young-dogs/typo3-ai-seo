# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Public README for GitHub
- Technical documentation in `Documentation/TechnicalOverview.md`
- AI endpoint specification in `Documentation/ApiContract.md`
- This changelog

### Changed
- `composer.json` enriched with package metadata (`version`, `keywords`, `authors`)
- EXT:news is optional; the News SEO module and AJAX routes are registered only when it is installed

---

## [1.0.0] - 2026-11-20

### Added
- Initial release of the Young-dogs AI SEO extension for TYPO3 14
- AI SEO integration for Content → Status → Pagetree Overview
- News SEO backend module for `tx_news_domain_model_news`
- Managed-field generation workflow (generate → review → apply)
- Configurable managed fields via Page TSconfig
- AI API integration for metadata generation

[Unreleased]: https://github.com/young-dogs/typo3-ai-seo/compare/1.0.0...HEAD
[1.0.0]: https://github.com/young-dogs/typo3-ai-seo/releases/tag/1.0.0

