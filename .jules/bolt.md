## 2026-06-22 - [Lazy Loading Avatars]
**Learning:** Found multiple instances where avatars were missing lazy loading and async decoding, contributing to poor frontend performance on pages with many users (social, rankings, search). Also realized backend optimization was restricted in this task constraints.
**Action:** Always verify if optimization violates negative constraints like database editing, and prioritize safe frontend changes.
## 2024-06-25 - [Fix Empty Catch Block]
Removed an empty `catch` block in `duels/results.php` which was silently suppressing errors from a fetch polling operation. Replaced it with a `catch (error) { console.error('...', error); }` to improve maintainability and debuggability. The error handling ensures errors are not swallowed and are appropriately visible in the browser console.
