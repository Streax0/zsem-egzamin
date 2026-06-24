## 2026-06-22 - [Lazy Loading Avatars]
**Learning:** Found multiple instances where avatars were missing lazy loading and async decoding, contributing to poor frontend performance on pages with many users (social, rankings, search). Also realized backend optimization was restricted in this task constraints.
**Action:** Always verify if optimization violates negative constraints like database editing, and prioritize safe frontend changes.
## 2024-10-24 - AI Visibility Optimization
Added `llms.txt` to provide explicit context to AI models about platform authors, and updated `robots.txt` to explicitly allow AI crawlers (GPTBot, ClaudeBot, etc.) to access it, ensuring they can index this standard AI context file while respecting other site restrictions.
## 2024-05-18 - Added worksheet options
- Added optimization to `teacher/pdf_generator.php`: Added shuffling of answers for closed questions (`worksheetShuffleOptions`) to make cheating more difficult during exams. The impact is better test integrity.
## 2024-05-18 - Added worksheet options (Fixed)
- Updated optimization to `teacher/pdf_generator.php`: Fixed the logic of `worksheetShuffleOptions` to handle questions with fewer than 4 answers appropriately. Also added UI inputs, variables usage and proper toggles in views to reflect user's configuration during PDF printing without breaking existing components.
## 2024-05-18 - Generator UI improvement
- Updated UI layout for `teacher/pdf_generator.php`: grouped configuration checkboxes into visually distinct and semantic cards (Mieszanie, Wygląd arkusza, Klucz odpowiedzi) matching the application's Bootstrap design system and handling dark mode styling.
