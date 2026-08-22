<?php

return [
    'title' => 'REST API Manual', 'subtitle' => 'Connect to live competition data and administer EasyKids through JSON API v1.',
    'intro' => 'The API uses URLs under /api and sends consistent JSON request and response bodies.', 'quick_start' => 'Quick start',
    'quick_start_steps' => ['Read live competitions immediately without a token.', 'Create a bearer token from API access to read every status or modify data.', 'Send Accept: application/json and Content-Type: application/json for requests with a body.', 'Add ?lang=en or send Accept-Language: en to receive English messages and validation.'],
    'base_url' => 'Base URL', 'authentication' => 'Authentication and access',
    'authentication_help' => 'GET requests without a token see LIVE resources only. An administrator token reads every status and authorizes create, update, and delete requests.',
    'security_note' => 'Keep tokens server-side. Never put one in public JavaScript, a URL, or Git.', 'response_format' => 'Response format',
    'success_response' => 'Successful responses place the resource in data. Paginated lists also include current_page, per_page, total, and links.',
    'error_response' => 'Errors set success to false and explain the problem in error.message. Validation errors add error.fields keyed by field name.',
    'public_resources' => 'Read-only REST resources', 'public_scope' => 'No token: LIVE only · Admin token: every status',
    'admin_resources' => 'Administrator REST resources', 'compatibility_actions' => 'Lifecycle endpoints (backward compatibility)',
    'compatibility_help' => 'New integrations should prefer PATCH /status. The /start, /complete, and /archive endpoints remain available for compatibility.',
    'method' => 'Method', 'endpoint' => 'Endpoint', 'description' => 'Description', 'authentication_required' => 'Access',
    'public_or_admin' => 'Public / admin', 'admin_only' => 'Admin', 'request_examples' => 'Request examples',
    'list_example' => 'Search and filter live competitions', 'create_example' => 'Create a competition',
    'status_example' => 'Transition through the REST status resource', 'score_example' => 'Record a match result idempotently with PUT',
    'pagination_filters' => 'Filters and pagination', 'pagination_help' => 'GET /tournaments supports status (admin only), format, search, and per_page from 1–100.',
    'status_codes' => 'HTTP status codes',
    'status_code_rows' => [['200', 'Read or update succeeded'], ['201', 'Resource created'], ['401', 'Token missing or invalid'], ['403', 'Account is not an administrator'], ['404', 'Resource not found or not visible to viewers'], ['422', 'Validation failed or lifecycle state is invalid'], ['429', 'Rate limit exceeded']],
    'read_endpoints' => [
        ['GET', '/api/health', 'Service health'], ['GET', '/api/tournaments', 'Paginated and filterable competition list'], ['GET', '/api/tournaments/{id}', 'Competition with stages, teams, matches, and standings'],
        ['GET', '/api/tournaments/{id}/participants', 'All participants'], ['GET', '/api/tournaments/{id}/participants/{participant}', 'One participant with members, standing, and attempts'],
        ['GET', '/api/tournaments/{id}/matches', 'All matches'], ['GET', '/api/tournaments/{id}/matches/{match}', 'One match'], ['GET', '/api/tournaments/{id}/standings', 'Complete standings'], ['GET', '/api/tournaments/{id}/standings/{participant}', 'One participant standing'],
    ],
    'write_endpoints' => [
        ['POST', '/api/tournaments', 'Create competition'], ['PUT / PATCH', '/api/tournaments/{id}', 'Replace / update competition'], ['DELETE', '/api/tournaments/{id}', 'Delete competition and related data'],
        ['POST', '/api/tournaments/{id}/participants', 'Create participant'], ['PUT / PATCH', '/api/tournaments/{id}/participants/{participant}', 'Replace / update participant'], ['DELETE', '/api/tournaments/{id}/participants/{participant}', 'Delete participant'],
        ['POST', '/api/tournaments/{id}/participants/import', 'Import multipart csv_file'], ['PATCH', '/api/tournaments/{id}/status', 'Transition to LIVE, COMPLETED, or ARCHIVED'], ['PUT', '/api/tournaments/{id}/matches/{match}/result', 'Put score_a and score_b'],
        ['POST', '/api/tournaments/{id}/participants/{participant}/attempts', 'Create an attempt with attempt_number'], ['PUT', '/api/tournaments/{id}/participants/{participant}/attempts/{number}', 'Put an attempt at the URL number'],
    ],
    'action_endpoints' => [['POST', '/api/tournaments/{id}/start', 'Start and generate bracket'], ['POST', '/api/tournaments/{id}/complete', 'Complete when results are ready'], ['POST', '/api/tournaments/{id}/archive', 'Archive a completed competition'], ['POST', '/api/tournaments/{id}/matches/{match}/result', 'Legacy result submission']],
];
