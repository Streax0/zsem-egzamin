## 2026-06-22 - [Lazy Loading Avatars]
**Learning:** Found multiple instances where avatars were missing lazy loading and async decoding, contributing to poor frontend performance on pages with many users (social, rankings, search). Also realized backend optimization was restricted in this task constraints.
**Action:** Always verify if optimization violates negative constraints like database editing, and prioritize safe frontend changes.
## 2024-10-24 - AI Visibility Optimization
Added `llms.txt` to provide explicit context to AI models about platform authors, and updated `robots.txt` to explicitly allow AI crawlers (GPTBot, ClaudeBot, etc.) to access it, ensuring they can index this standard AI context file while respecting other site restrictions.
## 2024-06-24 - Custom Unauthorized Screen Implementation
When modifying core guard functions (like `requireLogin()` in `includes/auth.php`), direct inline rendering of blocking screens with proper HTTP response codes (e.g., 401 Unauthorized) and Bootstrap styling is preferable to immediate redirects. This provides a better user experience for unauthenticated guests trying to access protected paths while preserving the return URL context for when they do authenticate.
