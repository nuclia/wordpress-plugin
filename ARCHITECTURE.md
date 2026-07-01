# Progress Agentic RAG Plugin Architecture

## Templates

- Runtime HTML lives in plain PHP files under `templates/`.
- Classes prepare data, enforce permissions, validate requests, and load templates with `require_once`.
- Do not add a template engine or abstraction layer.
- Keep escaping in the template file, as close to output as possible.
- Admin templates live under `templates/admin/`; frontend templates live under `templates/frontend/`.
