# AI Agent Workspace Setup

The AI Agent Workspace uses Google's Gemini API. It is optional and disabled by default. No AI request is sent until an administrator enables the feature and configures an API key.

## Configure

1. Create a Gemini API key: https://aistudio.google.com/app/apikey
2. Add these values to the server's `.env`:

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

Keep the API key on the server. Never place it in Blade, JavaScript, public assets, source control or browser requests.

## Role-specific agents and actions

| Signed-in role | Agent access | Action proposals |
|---|---|---|
| Student | Student, Learning, Support | `create_complaint_draft` |
| Teacher / Instructor | Teacher, Learning, Support | `create_assignment_draft` for courses they own |
| Guardian | Support, Learning | `create_complaint_draft` |
| Branch Manager | Support, Learning | `create_announcement_draft` for their managed branch |
| Admin | All agents | `create_assignment_draft`, `create_announcement_draft` |

The authenticated role is passed into the server-side Gemini system instruction. The server independently enforces the action allowlist; model output cannot grant itself extra permissions.

## Supported action workflow

Every action is a proposal, never an automatic mutation:

1. Gemini returns a JSON response with a message and optional `actions` array.
2. The server validates the action type against the signed-in role and validates every payload field.
3. The proposal is stored as `pending` in `ai_action_proposals`.
4. The proposal owner reviews and approves or rejects it. Admins may also review proposals where authorized.
5. Approval locks the proposal in a database transaction, rechecks authorization and status, executes once, and records reviewer, timestamps and result.
6. A rejected proposal creates no LMS record. Duplicate approval returns HTTP 409.

### Action details

- `create_assignment_draft`: Teacher can propose assignments only for their own courses; Admin can propose for any course. Approval creates an unpublished assignment. It is never auto-published.
- `create_complaint_draft`: Student or Guardian can propose a complaint. Approval submits it under that user's own account and branch and creates a complaint audit event / tracking code.
- `create_announcement_draft`: Admin can target a branch or all branches. Branch Manager's branch is derived from the server-side manager-to-branch relationship; model-supplied branch IDs are ignored. Approval creates an unpublished announcement draft; an authorized administrator must review and publish it through the announcement manager.

Unsupported actions, invalid payloads, unauthorized course IDs, cross-branch announcement targeting and cross-user proposal access are rejected or ignored. AI cannot publish announcements or assignments, send messages, change payments, grant access, edit accounts, delete data or change system settings.

## Privacy and operations

- Prompts and generated responses are stored in the AI run audit table so users can review their recent activity.
- Do not submit passwords, payment PINs, OTPs, health details or other sensitive personal information.
- The Gemini API key is sent in the `x-goog-api-key` request header and is never stored in the database.
- Requests are made server-side with a timeout. Provider errors are not retried automatically.
- If the provider is unavailable or the key is missing, the run is marked failed and no action is executed.
- Disable the feature with `AI_AGENTS_ENABLED=false`, then clear the configuration cache.

See Google's official [Gemini generateContent API documentation](https://ai.google.dev/api/generate-content) for request format and model availability. Change the configured model with `GEMINI_MODEL` without changing application code.
