# Theme Development Workflow

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose
This workflow defines the standard, end-to-end process SquirrelForge follows to create a new, modern, block-based WordPress theme.

### Phase 1 — Planning & Scaffolding

1.  **Define Core Concept:**
    -   What is the theme's primary aesthetic and purpose (e.g., "Minimalist blog theme," "Portfolio theme for photographers")?
2.  **Establish Identity:**
    -   Determine the Theme Name.
    -   Determine the Theme Slug / Text Domain.
3.  **Scaffold the Project:**
    -   Use a standard block theme template to generate the initial file structure (`theme.json`, `style.css`, `functions.php`, `templates/`, `parts/`).

**Deliverable:** A complete, empty block theme shell.

### Phase 2 — Global Styles & Settings (`theme.json`)

1.  **Define Palette & Typography:** Set up the color palette, font sizes, and font families in `theme.json`.
2.  **Configure Layout:** Define global layout settings like content width and spacing presets.
3.  **Style Core Blocks:** Add default styles for core WordPress blocks (headings, paragraphs, buttons, etc.) within `theme.json`.

**Deliverable:** A fully configured `theme.json` file that defines the theme's design system.

### Phase 3 — Template & Pattern Creation

1.  **Build Template Parts:** Create the reusable HTML parts of the theme (e.g., `header.html`, `footer.html`, `sidebar.html`).
2.  **Assemble Templates:** Construct the main templates (e.g., `index.html`, `single.html`, `page.html`, `archive.html`) using the template parts and core blocks.
3.  **Create Block Patterns:** Build custom block patterns for complex layouts that users can easily insert (e.g., a "Call to Action" section, a "Team Members" layout).

**Deliverable:** A full set of templates and patterns that constitute the theme's structure.

### Phase 4 — Testing & Quality Assurance

1.  **Theme Check:** Run the official Theme Check plugin and resolve any critical errors or warnings.
2.  **Responsive Testing:** Confirm the theme displays correctly on desktop, tablet, and mobile screen sizes.
3.  **Accessibility Review:** Perform a review against the `ACCESSIBILITY-REVIEW-WORKFLOW.md`.

**Deliverable:** A stable, responsive, and accessible theme.

### Phase 5 — Documentation & Packaging

1.  **Finalize `style.css`:** Ensure the theme header in `style.css` is complete and accurate.
2.  **Create `readme.txt`:** Write a description of the theme and its features.
3.  **Prepare for Distribution:** Create the final `.zip` file.

**Deliverable:** A distributable `.zip` file.

### Phase 6 — Final Report

Summarize the completed theme, list the deliverables, and suggest next steps like "Install on a test site" or "Submit to the WordPress.org theme directory."

**Deliverable:** A comprehensive project completion report.