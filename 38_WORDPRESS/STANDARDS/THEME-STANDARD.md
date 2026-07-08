Status: Stable

---
# SquirrelForge WordPress Theme Standard

## Purpose

This document defines the default theme architecture SquirrelForge should use when generating WordPress themes.

---

## Default Classic Theme Structure

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
Default Block Theme Structure
theme-name/
├── style.css
├── functions.php
├── theme.json
├── README.md
├── screenshot.png
├── templates/
│   ├── index.html
│   ├── front-page.html
│   ├── home.html
│   ├── single.html
│   ├── page.html
│   ├── archive.html
│   └── 404.html
├── parts/
│   ├── header.html
│   └── footer.html
├── patterns/
├── assets/
└── languages/
Required Theme Files
File	Purpose
style.css	Theme metadata and base stylesheet.
functions.php	Theme setup, supports, menus, assets, and hooks.
index.php or templates/index.html	Required fallback template.
README.md	Developer documentation.
screenshot.png	WordPress admin preview image.
Theme Responsibilities

Themes should control:

layout
templates
design tokens
typography
colors
spacing
menus
sidebars
block styles
frontend presentation

Themes should not contain core business logic that must survive a theme change.

Setup Requirements

A theme should register:

title tag support
post thumbnails
HTML5 markup support
custom logo support if needed
navigation menus
editor styles if needed
block styles if needed
translation support
Asset Requirements

Themes must enqueue assets using:

wp_enqueue_style()
wp_enqueue_script()

Themes must not hardcode stylesheet or script tags directly into templates unless explicitly justified.

Template Requirements

Classic themes should use template parts for repeated sections.

Block themes should use:

theme.json
templates/
parts/
patterns/
Accessibility Requirements

Themes must support:

semantic HTML
keyboard navigation
visible focus states
readable contrast
proper heading order
alt text support
skip links where appropriate
Security Requirements

Themes must:

escape output
sanitize customizer or settings input
avoid unsafe direct request handling
avoid storing secrets
avoid plugin-level business logic
Documentation Requirements

Each theme must document:

purpose
theme type
file structure
supported templates
registered menus
registered sidebars
asset locations
customization notes
testing steps
Testing Requirements

Before approval, verify:

theme activates without fatal errors
homepage renders
single posts render
pages render
archives render
search renders
404 renders
menus work
assets load
responsive layout works
keyboard navigation works
no WordPress core files are modified
Rule

SquirrelForge must generate themes using this standard unless a project-specific architecture is explicitly documented and approved.

## Rule

Theme work must satisfy WordPress theme structure, template, accessibility, performance, and compatibility requirements.
