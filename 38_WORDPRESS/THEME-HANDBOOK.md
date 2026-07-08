Status: Stable

---
# SquirrelForge WordPress Theme Handbook

## Purpose

This document defines the core concepts, best practices, and architectural rules SquirrelForge must follow when designing and building WordPress themes. It provides the practical knowledge needed to create themes that are secure, performant, and compliant with WordPress standards.

## Core Principle

**Themes control presentation.** A theme's primary responsibility is to control the look, feel, and layout of the site's content. It should not contain critical functionality that the site depends on to operate.

---

## Theme Types

SquirrelForge must identify and work within the context of the correct theme type.

1.  **Classic Themes:** The traditional model. Uses PHP-based template files (e.g., `index.php`, `single.php`, `page.php`) and the Template Hierarchy to render content.
2.  **Block Themes:** The modern, Full Site Editing (FSE) model. Uses HTML-based block templates (`templates/` and `parts/`) and a central `theme.json` file to control all aspects of the site's design.
3.  **Child Themes:** Inherit the functionality and styling of another theme (the "parent theme"). Used to safely customize a parent theme without modifying its core files. A child theme requires a `style.css` with a `Template:` header pointing to the parent theme's directory.
4.  **Hybrid Themes:** A classic theme that adopts some block theme features, like `theme.json` or block-based template parts.

---

## Core Theme Files

### For All Themes

- **`style.css`**: **Required.** Contains the theme header comment block, which tells WordPress the theme's name, author, version, etc.
- **`functions.php`**: The theme's main logic file. Used for theme setup, defining functions, and enqueuing assets.
- **`README.md`**: **Required.** Developer-facing documentation for the theme.

### For Classic Themes

- **`index.php`**: **Required.** The default fallback template if a more specific template cannot be found in the hierarchy.

### For Block Themes

- **`theme.json`**: **Required.** The central configuration file for global styles, settings, and block presets.
- **`templates/index.html`**: **Required.** The main template for the site.

---

## Key Development Rules

### 1. Use `functions.php` for Setup
- **Rule:** All theme setup logic must be hooked to `after_setup_theme`.
- **Common Tasks:**
    - `add_theme_support()`: To enable features like post thumbnails, title tags, and HTML5 markup.
    - `register_nav_menus()`: To register navigation menu locations.
    - `load_theme_textdomain()`: To enable translation.

### 2. Enqueue Assets Correctly
- **Rule:** All CSS and JavaScript files must be loaded using `wp_enqueue_style()` and `wp_enqueue_script()`, hooked to `wp_enqueue_scripts` for the frontend and `admin_enqueue_scripts` for the admin area.
- **Forbidden:** Do not hardcode `<link>` or `<script>` tags in `header.php` or `footer.php`.

### 3. Understand the Template Hierarchy
- **Rule:** When creating classic themes, name template files according to the WordPress Template Hierarchy to ensure they are used for the correct content types (e.g., `single-book.php` for a CPT named "book", `category-news.php` for the "news" category archive).

### 4. The Loop
- **Rule:** Use the standard WordPress Loop (`if ( have_posts() ) : while ( have_posts() ) : the_post(); ... endwhile; endif;`) to display posts in templates.

### 5. Security is Still Essential
- **Rule:** Themes are just as responsible for security as plugins.
    - **Sanitize** any user input (e.g., from a customizer option or contact form).
    - **Escape** all data before outputting it in a template file. This is especially important for data coming from options or post meta.

### 6. Separation of Concerns
- **Rule:** Do not register Custom Post Types or Custom Taxonomies in a theme's `functions.php`. This is plugin territory. If a user switches themes, they will lose access to all their content.
- **Guideline:** Keep `functions.php` organized. For complex themes, break out functionality into separate files within an `inc/` directory and `require` them in `functions.php`.

### 7. `theme.json` is King for Block Themes
- **Rule:** In block themes, all design tokens (colors, typography, spacing), block style variations, and layout settings **must** be defined in `theme.json`. Avoid writing custom CSS for things that can be controlled via `theme.json`.

---

## Forbidden Patterns

SquirrelForge must reject or refactor theme code that:

- Registers a Custom Post Type or Taxonomy.
- Contains hardcoded business logic that should be in a plugin.
- Uses `query_posts()`. This function modifies the main query and is highly discouraged. Use `WP_Query` for secondary loops instead.
- Directly accesses the database with `$wpdb` for content that could be fetched with `WP_Query`.
- Hardcodes API keys or other secrets.

## Rule

WordPress theme work must follow approved theme architecture, template, styling, accessibility, and compatibility requirements.
