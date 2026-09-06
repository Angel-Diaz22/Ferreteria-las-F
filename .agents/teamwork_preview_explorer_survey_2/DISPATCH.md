## 2026-09-06T03:34:31Z

You are teamwork_preview_explorer_survey_2.
Your working directory is: /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_survey_2

MANDATORY FIRST STEP:
Read /Users/angeldiaz/Documents/FerreterialasF/.agents/ORIGINAL_REQUEST.md before starting work.

OBJECTIVE:
Perform a comprehensive survey for dead code, unused routes, deprecated Livewire components, orphaned classes, and redundant Blade views across the codebase.
Inspect:
1. Routes in `routes/web.php`, `routes/api.php`, and any other route files. Check if routes point to non-existent controllers or obsolete endpoints.
2. Controllers in `app/Http/Controllers/`: identify any orphaned controllers not referenced in routes, Filament, or tests.
3. Livewire components in `app/Livewire/` or `app/Http/Livewire/`: identify components that are no longer used or rendered.
4. Views in `resources/views/`: identify redundant or unreferenced blade templates.
5. Verify whether candidate dead code is dynamically called or event-driven before marking it for safe removal.

SCOPE BOUNDARIES:
- Read-only exploration and analysis. DO NOT delete or modify application files.
- Write your findings to your working directory.

INPUT INFORMATION:
- /Users/angeldiaz/Documents/FerreterialasF/.agents/ORIGINAL_REQUEST.md
- /Users/angeldiaz/Documents/FerreterialasF/routes/
- /Users/angeldiaz/Documents/FerreterialasF/app/Http/Controllers/
- /Users/angeldiaz/Documents/FerreterialasF/app/Livewire/
- /Users/angeldiaz/Documents/FerreterialasF/resources/views/

OUTPUT REQUIREMENTS:
- Write your detailed analysis to /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_survey_2/survey_report.md
- Write /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_survey_2/handoff.md containing Observation, Logic Chain, Caveats, Conclusion, and Verification Method.
- Send a completion message back to parent when done.

COMPLETION CRITERIA:
- Concrete inventory of verified dead code vs active code, with exact file paths and safe removal plans preserving all active business logic.
