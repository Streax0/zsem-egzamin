## 2026-06-22 - [Lazy Loading Avatars]
**Learning:** Found multiple instances where avatars were missing lazy loading and async decoding, contributing to poor frontend performance on pages with many users (social, rankings, search). Also realized backend optimization was restricted in this task constraints.
**Action:** Always verify if optimization violates negative constraints like database editing, and prioritize safe frontend changes.
## 2024-06-24 - APCu Caching for dictionary file read

APCu caching was implemented for synchronous JSON file reading in `flashcards.php`.
Using `filemtime` for cache keys effectively invalidates cache upon file change while relying on fast `stat` caching under the hood, significantly outperforming synchronous IO and `json_decode`.
The performance impact showed a ~71% improvement in parsing operations.
