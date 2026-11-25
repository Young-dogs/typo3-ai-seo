# API Contract

The extension calls an Young-dogs AI SEO API endpoint over HTTP POST.

## Request

```json
{
  "model": "gpt-4o-mini",
  "messages": [
    {
      "role": "system",
      "content": "Prompt with field rules, language, and JSON output constraints"
    },
    {
      "role": "user",
      "content": "Page/news title, slug/path segment, and harvested content"
    }
  ],
  "temperature": 0.4,
  "response_format": {
    "type": "json_object"
  }
}
```

## Expected response shape

The endpoint must return a chat-completions response where:

- `choices[0].message.content` is a JSON object string
- keys match configured managed field names
- values are strings

Example generated JSON content:

```json
{
  "seo_title": "Compact and descriptive title",
  "description": "Concise summary for search snippets",
  "og_title": "Social sharing title",
  "og_description": "Social sharing description"
}
```

## Headers

Sent with every request:

```
Content-Type: application/json
Accept: application/json
X-API-Key: <your api key>
```

## Error handling

- Any non-200 status is treated as failure.
- Invalid or non-JSON `choices[0].message.content` is treated as failure.
- User-facing errors are returned by AJAX endpoints with `success: false` and an error message.

