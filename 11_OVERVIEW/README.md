# SquirrelForge Overview Layer

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `README.md`, `ARCHITECTURE.md`
Used By: All layers
Last Updated: 2026-07-04

## Purpose

The Overview Layer provides the entry point for SquirrelForge architecture, vocabulary, and lifecycle concepts.

It gives downstream layers a shared orientation without replacing the root repository map or the top-level architecture document.

---

## Layer Boundary

`11_OVERVIEW` owns:

- architecture orientation,
- canonical vocabulary,
- lifecycle overview,
- and system-wide conceptual alignment.

`11_OVERVIEW` does not own:

- mandatory operating rules,
- agent identity,
- workflow implementation,
- execution dispatch,
- testing infrastructure,
- governance policy,
- security policy,
- or domain-specific engineering knowledge.

Those responsibilities remain in their respective layers.

---

## Components

| Component | Responsibility |
|---|---|
| `SYSTEM-ARCHITECTURE.md` | Summarizes the current numbered layer architecture and control flow. |
| `GLOSSARY.md` | Defines canonical terminology. |
| `LIFECYCLE.md` | Describes the request-to-archive lifecycle. |

The component roster must match files that actually exist in this directory.

---

## Reading Order

```text
Root README
   ↓
ARCHITECTURE.md
   ↓
11_OVERVIEW/SYSTEM-ARCHITECTURE.md
   ↓
11_OVERVIEW/GLOSSARY.md
   ↓
11_OVERVIEW/LIFECYCLE.md
   ↓
01_RULES
   ↓
12_AGENT / 14_ENGINE
```

---

## Dependency Rule

The Overview Layer depends on the root repository map and the top-level architecture file.

Downstream documents may refine overview concepts, but they must not contradict them without an explicit architecture cleanup.

---

## Maintenance Rule

When the numbered layer structure changes, update these files in the same cleanup pass:

- `README.md`
- `ARCHITECTURE.md`
- `11_OVERVIEW/README.md`
- `11_OVERVIEW/SYSTEM-ARCHITECTURE.md`
- `12_AGENT/COLLECTION-MANIFEST.md`
- affected layer READMEs

---

## Rule

> Overview documents define shared system concepts. Layer documents may refine those concepts, but must not silently contradict them.
