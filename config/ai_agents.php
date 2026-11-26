<?php

return [
    'enabled' => (bool) env('AI_AGENTS_ENABLED', false),
    'provider' => env('AI_AGENT_PROVIDER', 'gemini'),
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    'endpoint' => env('GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
    'timeout' => (int) env('AI_AGENT_TIMEOUT', 45),
    'max_prompt_length' => (int) env('AI_AGENT_MAX_PROMPT_LENGTH', 4000),
];
