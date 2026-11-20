# Young-dogs AI SEO for TYPO3

TYPO3 backend extension that adds AI-assisted SEO generation for:

- **Content → Status → Pagetree Overview** (AI SEO view for pages)
- **Content → Status → News SEO** (module for `tx_news_domain_model_news`)

Editors can generate SEO metadata, review old vs new values, select fields, and apply changes directly.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.2 – 8.5 |
| TYPO3 | 14.3 |
| EXT:news (`georgringer/news`) | 14.1+ |

---

## Installation

```bash
composer require young-dogs/typo3-ai-seo
```

Then activate via TYPO3 Extension Manager or CLI:

```bash
./vendor/bin/typo3 extension:setup --extension=youngdogs_ai_seo
```

---

## Configuration

> 🚀 **Need an API key?** Get started with the Young-dogs AI SEO service at **[young-dogs.com](https://www.young-dogs.com)**. Sign up to grab your personal endpoint URL and API key—no complex setup, just plug them in and start your SEO magic.

Go to **Admin Tools → Settings → Extension Configuration → `youngdogs_ai_seo`**.

| Setting | Description |
|---|---|
| **API URL** | Full OpenAI-compatible chat completions endpoint URL |
| **API Key** | Bearer token used for API authorization |
| **Model** | Model sent to the API request |
| **Timeout** | Request timeout in seconds |
| **Max content length** | Maximum harvested content characters sent to AI |

Field-level SEO configuration is done via Page TSconfig:

- `mod.ai_seo_status.managedFields` for **pages**
- `mod.ai_seo_news.managedFields` for **news records**

Default examples are included in [`Configuration/page.tsconfig`](Configuration/page.tsconfig).

---

## Technical documentation

- [Documentation/TechnicalOverview.md](Documentation/TechnicalOverview.md)
- [Documentation/ApiContract.md](Documentation/ApiContract.md)

---

## License

MIT — see [LICENSE](LICENSE).

---

### 🌐 Connect with us

* **Website:** [young-dogs.com](https://www.young-dogs.com)
* **Network:** [LinkedIn](https://www.linkedin.com/company/young-dogs-ecommerce/)
* **HQ:** Young-dogs España SL, Asturias, Spain 🇪🇸

---
<sub>© 2026 Young-dogs Spain. Built with passion for innovation and operational excellence.</sub>