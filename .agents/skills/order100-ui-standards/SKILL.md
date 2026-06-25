---
name: Order100 UI Standards
description: Use this skill whenever building, modifying, or fixing Frontend UI, Admin Dashboards, Forms, Tables, or Buttons in Order100.
---

# Order100 UI Standards

When building or modifying UI components for the Order100 project, you must strictly adhere to these standards to ensure a cohesive, professional "FluentCRM-like" aesthetic and prevent CSS conflicts with WordPress core.

## 1. General Principles
- **Aesthetics First**: Use modern Tailwind CSS utility classes. Prioritize clean spacing, subtle borders (`border-slate-300`), muted backgrounds for containers (`bg-white` or `bg-slate-50`), and primary branding colors (like `indigo-600`).
- **No Raw HTML Elements**: Never use unstyled raw HTML elements (like bare `<input>`, `<select>`, `<button>`).
- **WordPress Admin CSS Immunity**: WordPress admin CSS forcefully applies specific margins and border-radiuses to `input` and `select` tags. For composite components (like input addons), **you must use inline styles to override them**.

## 2. Component Templates

Always refer to the precise HTML templates in the `examples/` directory for standard components:

- **[Inputs & Addons](file:///Users/kevinqi/development/antigravity/order100/.agents/skills/order100-ui-standards/examples/inputs.html)**: Standard text inputs, combined inputs with right-side unit dropdowns, or static text addons.
- **[Buttons](file:///Users/kevinqi/development/antigravity/order100/.agents/skills/order100-ui-standards/examples/buttons.html)**: Primary Save Settings buttons (`rounded-xl`), Secondary, Danger, and Outline buttons.
- **[Tables](file:///Users/kevinqi/development/antigravity/order100/.agents/skills/order100-ui-standards/examples/tables.html)**: FluentCRM style full-width list tables.
- **[Cards / Data Lists](file:///Users/kevinqi/development/antigravity/order100/.agents/skills/order100-ui-standards/examples/cards.html)**: Standard rounded cards (`rounded-xl`) with header badges for listing campaigns or settings blocks.
- **[Modals & Alerts](file:///Users/kevinqi/development/antigravity/order100/.agents/skills/order100-ui-standards/examples/modals.html)**: SweetAlert2 configurations using injected Tailwind classes for buttons.
- **[Selects & Dropdowns](file:///Users/kevinqi/development/antigravity/order100/.agents/skills/order100-ui-standards/examples/selects.html)**: Rules against native `<select multiple>` and styling.
- **[Settings Panels](file:///Users/kevinqi/development/antigravity/order100/.agents/skills/order100-ui-standards/examples/settings_panel.html)**: The standard layout for admin settings cards (headers, labels, descriptions).

## 3. Strict Rules for Specific Elements
- **Combined Inputs (Input + Unit Addon)**: 
  - Must use `flex items-stretch rounded-md shadow-sm`.
  - Must apply inline styles `style="margin:0; border-top-right-radius:0; border-bottom-right-radius:0; border-right:0;"` on the left element.
  - Must apply inline styles `style="margin:0; border-top-left-radius:0; border-bottom-left-radius:0;"` on the right element.
  - Both elements must explicitly use `font-size:14px;` via inline styles to ensure alignment.
- **Toggles (Checkboxes)**: Use Tailwind-styled checkboxes or toggle switches, never native browser checkboxes.
- **Selects**: Standard selects must have padding adjusted for custom dropdown arrows (`pr-8`).
