# Technical Overview

## Scope

`youngdogs_ai_seo` provides AI-assisted SEO metadata generation for:

1. TYPO3 Info module page overview (`pages` records)
2. Custom News SEO backend module (`tx_news_domain_model_news` records)

## Architecture

### Entry points

- **Page overview integration**
  - Event listener: `YoungDogs\AiSeo\EventListener\InjectAiSeoView`
  - Adds JS and AJAX config when AI SEO view is active.
- **News SEO module**
  - Backend module: `youngdogs_ai_seo_news`
  - Controller: `YoungDogs\AiSeo\Controller\NewsOverviewController`
  - Fluid template: `Resources/Private/Templates/NewsOverview.html`

### AJAX controllers

- `GenerateController` (pages):
  - `rowsAction`: load existing managed field values
  - `generateAction`: harvest content, build prompt, call AI, normalize output
  - `applyAction`: persist selected values via DataHandler
- `NewsAjaxController` (news):
  - Same flow for `tx_news_domain_model_news`

### Core services

- `ExtensionSettings`: reads extension configuration (`apiUrl`, `apiKey`, `model`, `timeout`, `maxContentLength`)
- `FieldConfiguration` / `NewsFieldConfiguration`: resolve TSconfig-managed fields and permissions
- `ContentHarvester` / `NewsContentHarvester`: collect plain-text source content
- `PromptBuilder`: builds system/user prompts and output rules
- `AiClient`: calls Young-dogs AI SEO API endpoint and expects JSON object output
- `PageRecordService` / `NewsRecordService`: record resolution, permission checks, persistence

## Data flow

1. Frontend JS requests field definitions and existing values (`rows` route)
2. User triggers generate action for one record
3. Backend harvests source content and calls AI endpoint
4. Returned values are normalized (trim/strip tags/whitespace, max-length truncation)
5. User reviews existing vs generated values in modal and selects fields
6. Selected fields are persisted through DataHandler (`apply` route)

## Configuration model

### Extension configuration

Defined in `ext_conf_template.txt`:

- `apiUrl`
- `apiKey`
- `model` (default: `gpt-4o-mini`)
- `timeout` (default: `60`)
- `maxContentLength` (default: `12000`, minimum enforced: `500`)

### TSconfig-managed fields

Page-level TSconfig keys:

- `mod.ai_seo_status.managedFields.<field>.`
- `mod.ai_seo_news.managedFields.<field>.`

Each field supports:

- `label` (optional)
- `maxLength` (optional)
- `type` (`string` or `text`)
- `instruction` (optional prompt hint)
- `disabled` (optional)

## Persistence and permissions

- Write operations use TYPO3 `DataHandler` to preserve workspace/history behavior.
- Only fields existing in TCA and allowed for current backend user are returned.
- Module/route access is inherited from TYPO3 modules:
  - `web_info_overview` (page overview routes)
  - `youngdogs_ai_seo_news` (news routes)

## JavaScript modules

- `Resources/Public/JavaScript/ai-seo-status-preview-v5.js`
- `Resources/Public/JavaScript/ai-seo-news.js`

Both modules:

- inject SEO columns dynamically
- add generate buttons per row
- show preview modal with per-field apply checkboxes
- apply selected values via AJAX and show TYPO3 notifications

