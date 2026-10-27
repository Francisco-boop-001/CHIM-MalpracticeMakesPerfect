# Working rules

- A downloadable CHIM plugin must open through the ordinary server/mod workflow. Do not add a separate passphrase, CLI provisioning, or login screen unless the user asks for it. Verify fresh access with no plugin credentials, while retaining origin/CSRF checks and the existing PRISMA connection boundary.

- Optional Playthrough Saves must not become a prerequisite for ordinary CHIM users. Test the real owner factory and HTTP bootstrap with PAS off and no managed identity, while preserving configured managed behavior and explicitly stating that ordinary public data is shared.
