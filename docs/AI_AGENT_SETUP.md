# AI Agent Workspace Setup

The AI Agent Workspace uses Google's Gemini API. It is optional and disabled by default. No AI request is sent until an administrator enables the feature and configures an API key.

## Configure

1. Create a Gemini API key in Google AI Studio: https://aistudio.google.com/app/apikey
2. Add the following values to the server's `.env` file:

```dotenv
AI_AGENTS_ENABLED=true
AI_AGENT_PROVIDER=gemini
GEMINI_API_KEY=your-server-side-api-key
GEMINI_MODEL=gemini-2.5-flash
GEMINI_API_ENDPOINT=https://generativelanguage.googleapis.com/v1beta
AI_AGENT_TIMEOUT=45
AI_AGENT_MAX_PROMPT_LENGTH=4000
```

3. Clear cached configuration:

```bash
php artisan config:clear
php artisan config:cache
```

Keep the API key on the server. Never place it in Blade, JavaScript, public assets, source control or a browser request.

## Agent roles

- Student Agent: vocabulary, grammar, English practice and exam preparation.
- Teacher Agent: lesson planning and learning material drafts.
- Admin Agent: general LMS operations support (no direct account, finance or settings changes).
- Support Agent: platform help and troubleshooting.
- Learning Agent: study plans, revision and learning recommendations.

Available agents are restricted by the signed-in user's role.

## Human approval and audit

The model cannot directly execute LMS actions. The current supported proposal is `create_assignment_draft` from the Teacher Agent:

1. Gemini may suggest a draft assignment.
2. The proposal is stored with status `pending`.
3. A teacher who owns the course or an administrator must explicitly approve it.
4. Approval creates an **unpublished** assignment draft. The teacher must review it before publishing.
5. Rejected proposals do not create LMS records.

Unsupported action types and proposals for courses the teacher does not own are ignored. Approval and rejection are recorded with the reviewer and timestamps.

## Privacy and operations

- Prompts and generated responses are stored in the AI run audit table so users can review their recent activity.
- Do not submit passwords, payment PINs, OTPs, health details or other sensitive personal information.
- The Gemini API key is sent in the `x-goog-api-key` request header and is never stored in the database.
- Requests are made server-side with a timeout. Provider errors are not retried automatically.
- If the provider is unavailable or the key is missing, the run is marked failed and no action is executed.
- Disable the feature by setting `AI_AGENTS_ENABLED=false` and clearing the configuration cache.

See Google's official [Gemini generateContent API documentation](https://ai.google.dev/api/generate-content) for request format and model availability. The configured model can be changed with `GEMINI_MODEL` without changing application code.
