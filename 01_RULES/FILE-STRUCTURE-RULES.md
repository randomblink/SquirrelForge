# SquirrelForge WordPress File Structure Rules

## Purpose

The File Structure Rules define the required folder and file patterns for WordPress plugins, themes, child themes, blocks, and supporting assets created or reviewed by SquirrelForge.

## Responsibilities

- Define required WordPress plugin files.
- Define required WordPress theme files.
- Define optional but recommended folders.
- Prevent disorganized or unsafe file layouts.
- Ensure generated code has predictable locations.
- Support inspection, testing, and future maintenance.

---

## Standard Plugin Structure

A SquirrelForge WordPress plugin should use this structure unless the project requires a smaller layout:

```text
plugin-name/
├── plugin-name.php
├── README.md
├── readme.txt
├── uninstall.php
├── includes/
│   ├── class-plugin-name.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   └── class-loader.php
├── admin/
│   ├── class-admin.php
│   ├── views/
│   ├── css/
│   └── js/
├── public/
│   ├── class-public.php
│   ├── css/
│   └── js/
├── languages/
├── assets/
└── tests/
```

### Required Plugin Files

| File | Required | Purpose |
|---|---|---|
| `plugin-name.php` | Yes | Main plugin bootstrap file. |
| `README.md` | Yes | Developer-facing project documentation. |
| `readme.txt` | Recommended | WordPress.org-style plugin metadata. |
| `uninstall.php` | Recommended | Cleanup when plugin is deleted. |
| `includes/class-plugin-name.php` | Recommended | Main plugin controller class. |
| `includes/class-loader.php` | Recommended | Registers actions and filters. |
| `includes/class-activator.php` | Recommended | Activation setup. |
| `includes/class-deactivator.php` | Recommended | Deactivation cleanup. |

---

## Standard Theme Structure

A SquirrelForge WordPress theme should use this structure unless the project requires a block theme layout:

```text
theme-name/
├── style.css
├── functions.php
├── index.php
├── README.md
├── screenshot.png
├── header.php
├── footer.php
├── sidebar.php
├── single.php
├── page.php
├── archive.php
├── search.php
├── 404.php
├── template-parts/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── inc/
└── languages/
```

### Required Theme Files

| File | Required | Purpose |
|---|---|---|
| `style.css` | Yes | Theme stylesheet and theme header metadata. |
| `functions.php` | Yes | Theme setup, assets, supports, hooks. |
| `index.php` | Yes | Fallback template. |
| `README.md` | Yes | Developer-facing documentation. |
| `screenshot.png` | Recommended | WordPress admin theme preview. |

---

## Block Theme Structure

A block theme should use this structure:

```text
theme-name/
├── style.css
├── functions.php
├── theme.json
├── index.php
├── templates/
│   ├── index.html
│   ├── single.html
│   ├── page.html
│   └── archive.html
├── parts/
│   ├── header.html
│   └── footer.html
├── patterns/
├── assets/
└── README.md
```

### Required Block Theme Files

| File | Required | Purpose |
|---|---|---|
| `style.