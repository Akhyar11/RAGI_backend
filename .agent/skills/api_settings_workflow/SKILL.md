---
name: api-settings-workflow
description: "Use when creating or changing registered API integrations, API Settings credentials/authentication, connection tests, sync actions, or source-to-target imports in the Laravel backend."
---

# API Settings and Sync Workflow

Use this workflow for integrations configured through **Admin > Pengaturan Integrasi API**. This is an operational integration contract, not a generic CRUD recipe.

## Registry and Ownership

- Treat `iam_api_integrations` as a system-owned registry. Add or change fixed integration definitions in `Database\Seeders\IAM\IamApiIntegrationSeeder`; keep it idempotent and preserve administrator-managed connection settings on reseed.
- Do not add UI/API actions that create arbitrary integration definitions unless explicitly required. Administrators configure endpoint, authentication scheme, credential, and active status for registered integrations.
- Keep integration-specific import behavior in a domain service selected by the integration key. Do not infer target mappings from a response or build a generic mass-assignment importer.

## Credential and Authentication Rules

- `IamApiIntegration.token` is encrypted at rest and hidden from JSON serialization. Never return, log, audit, render, or include the raw token in exception messages.
- Expose only a boolean such as `token_configured` for UI state. Never serialize token length, a suffix, or a masked copy of the actual token.
- In edit forms, initialize the token input empty. Explain whether a token is already saved and that leaving the field empty preserves it.
- On update, omit the token or treat null/empty string as “keep existing credential”; replace it only when a non-empty new value is submitted. Add a regression test for empty token submissions.
- Authentication scheme is part of the upstream API contract: `bearer` sends `Authorization: Bearer <token>`; `header_key` sends `X-API-KEY: <token>`. Do not silently switch schemes or assume that a correct token implies a correct header.
- Connection diagnostics may show the configured scheme, HTTP status, and safe response metadata. They must not show or log request credentials. An upstream 401 should direct operators to verify the expected header/scheme, credential validity, and endpoint access.

## Test Connection vs. Sync

- Keep **Uji Koneksi** as a read-only health check. It must not write imported target data.
- Provide **Jalankan Sync** for manageable configured integrations. It must invoke a backend sync endpoint and a dedicated integration service; it must not be a frontend-only fetch or a renamed health check.
- Protect configuration changes, test, and sync with the existing `iam.api_integration.manage` Gate/permission. Record sync attempts/results with `AuditLogService`; audit failure must not change the import outcome.
- Use saved URL/authentication settings for sync. Reject missing URL, inactive integrations, unsupported integration keys/methods, unsuccessful HTTP responses, and malformed payloads with standard JSON errors and useful non-secret messages.

## Safe Import Behavior

- Follow the source pagination format with an explicit upper bound. Accept only documented response shapes and validate each row before writing.
- Map stable external identifiers to a dedicated target source-id column where possible; use a deterministic fallback key for legacy target rows. Re-running the same source must update the same target rows, not duplicate them.
- Validate dynamic reference values against the owning domain's reference service/model. Do not hardcode labels or query a legacy module table directly from IAM.
- Never guess missing target relationships. Keep optional unmapped values null where the target schema allows it; otherwise skip the row with an actionable reason.
- Never mass-delete target rows absent from a source response. Report source count, created, updated, skipped, and bounded skip details with reasons.
- Use a database transaction for target writes. Preserve existing target fields when a source omits them; do not overwrite faculty/ownership mappings with null by accident.

## Required Verification

- Feature-test authorization, credential-presence serialization without raw token exposure, empty-token preservation, successful sync, invalid/missing reference skips, idempotent repeat sync, and upstream failure status.
- Assert writes in the target database. Mock outbound HTTP and verify the expected auth header without printing its value in test output.
- Update `docs/api/IAM/IamApiIntegrationController.md` and `docs/README.md` whenever API routes or response contracts change. Document request headers, supported payload shapes, mapping/skip behavior, and concrete success/error examples.
- After edits, run the focused IAM integration feature tests, PHP syntax checks, route checks if routes changed, and the repository auditors with normal commit hooks.