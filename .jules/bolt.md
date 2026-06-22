## 2026-06-22 - [Lazy Loading Avatars]
**Learning:** Found multiple instances where avatars were missing lazy loading and async decoding, contributing to poor frontend performance on pages with many users (social, rankings, search). Also realized backend optimization was restricted in this task constraints.
**Action:** Always verify if optimization violates negative constraints like database editing, and prioritize safe frontend changes.
## 2024-10-24 - AI Visibility Optimization
Added `llms.txt` to provide explicit context to AI models about platform authors, and updated `robots.txt` to explicitly allow AI crawlers (GPTBot, ClaudeBot, etc.) to access it, ensuring they can index this standard AI context file while respecting other site restrictions.
