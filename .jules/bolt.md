## 2026-06-22 - [Lazy Loading Avatars]
**Learning:** Found multiple instances where avatars were missing lazy loading and async decoding, contributing to poor frontend performance on pages with many users (social, rankings, search). Also realized backend optimization was restricted in this task constraints.
**Action:** Always verify if optimization violates negative constraints like database editing, and prioritize safe frontend changes.
## 2026-06-24 - Refactor complex ensurePlatformEnhancements function
Extracted a ~474 line monolithic schema migration function into 8 logical domain-specific helper functions. This ensures better readability and maintainability without altering the order of schema upgrades. Reusable Python script utilizing robust `re.finditer` with position tracking proved extremely reliable at extracting exact blocks of code preserving internal formatting and avoiding bad regex injection issues.
