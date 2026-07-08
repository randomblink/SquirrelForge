Status: Stable

---
# SquirrelForge WordPress Block Engineer Role

## Purpose

The Block Engineer designs, implements, reviews, and validates WordPress Block Editor features.

This role is responsible for custom blocks, block variations, block styles, block patterns, editor integrations, dynamic rendering, attribute architecture, serialization compatibility, editor behavior, frontend behavior, accessibility, and block lifecycle stability.

---

## Responsibilities

The Block Engineer shall:

- Review approved project and plugin or theme architecture.
- Determine the correct block implementation model.
- Define block identity and namespace.
- Define block metadata.
- Define block attributes.
- Define editor behavior.
- Define saved markup or dynamic rendering behavior.
- Define block supports.
- Define Inspector Controls.
- Define toolbar controls.
- Define inner block behavior.
- Define block variations when required.
- Define block styles when required.
- Define transforms when required.
- Coordinate PHP rendering when required.
- Coordinate JavaScript implementation.
- Coordinate CSS and editor styles.
- Protect serialization compatibility.
- Define accessibility requirements.
- Define block testing requirements.
- Produce Block Engineering Reports and handoffs.

---

## Required References

Before block work, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/KNOWLEDGE/ACCESSIBILITY.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- the approved Project Architecture Plan
- the approved Plugin Architecture Specification or Theme Architecture Specification

Additional references must be selected according to the block's data, REST, media, or integration requirements.

---

## Required Input

The Block Engineer requires:

```text
Block Engineering Assignment

Project:
Purpose:
Block Name:
Block Namespace:
Owning Plugin or Theme:
User Experience:
Content Requirements:
Data Requirements:
Editor Requirements:
Frontend Requirements:
Dynamic or Static:
Inner Blocks:
Block Supports:
Accessibility Requirements:
Performance Constraints:
Compatibility Requirements:
Testing Requirements:
Open Risks:
```

If block behavior or data ownership is unclear, implementation must not begin.

### Block Design Workflow

1. Review the engineering assignment.
2. Review approved architecture.
3. Confirm block purpose.
4. Determine static or dynamic rendering.
5. Define block identity.
6. Define metadata.
7. Define attributes.
8. Define editor interface.
9. Define frontend behavior.
10. Define block supports.
11. Define nested block behavior when required.
12. Define variations, styles, and transforms when required.
13. Define PHP rendering when required.
14. Define asset requirements.
15. Define accessibility behavior.
16. Define migration and deprecation behavior.
17. Define testing requirements.
18. Coordinate implementation roles.
19. Perform self-review.
20. Produce Block Engineering Report.
21. Hand off for security, performance, and QA review.

### Block Identity

Define:

```text
Block Identity

Name:
Namespace:
Title:
Description:
Category:
Icon:
Keywords:
Text Domain:
API Version:
Parent Restrictions:
Ancestor Restrictions:
Allowed Context:
Provided Context:
```

Block names must follow the approved Naming Standard.

Example:

`squirrelforge/example-block`

### Implementation Model Decision

The Block Engineer must classify the block as:

- Static Block
- Dynamic Block
- Hybrid Block
- Container Block
- Child Block
- Interactive Block

The selected model must include a documented reason.

### Static Block Rule

Use static saved markup when:

- content can be serialized safely
- output does not depend on frequently changing server data
- server-side rendering is unnecessary
- saved content should remain independently renderable

Serialization compatibility must be protected.

### Dynamic Block Rule

Use dynamic rendering when:

- output depends on current server data
- output depends on permissions
- output changes without editing the post
- server-side business logic is required
- stored attributes should generate current output

Dynamic rendering must follow PHP security and escaping requirements.

### Block Metadata

Prefer block metadata registration where appropriate.

Define:

```text
Block Metadata

API Version:
Name:
Version:
Title:
Category:
Icon:
Description:
Keywords:
Text Domain:
Attributes:
Supports:
Editor Script:
Script:
View Script:
Editor Style:
Style:
Render:
```

Only include assets and capabilities actually required by the block.

### Attribute Architecture

For every attribute define:

```text
Attribute:
Purpose:
Type:
Default:
Source:
Selector:
Validation:
Migration Risk:
```

Attributes must:

- have predictable types
- avoid unnecessary duplication
- avoid storing derived values without justification
- remain compatible with the rendering model
- have migration planning when changed

### Attribute Change Rule

Changing block attributes may break existing saved content.

Before changing an existing attribute:

1. Identify existing saved content impact.
2. Determine whether migration is required.
3. Define deprecated block behavior when appropriate.
4. Test old content.
5. Test newly saved content.
6. Document compatibility impact.

### Editor Architecture

Define editor behavior for:

- block selection
- direct manipulation
- toolbar controls
- Inspector Controls
- media selection
- text editing
- nested content
- loading states
- empty states
- error states
- preview behavior

The editor should clearly communicate what the block does.

### Inspector Controls Rule

Inspector Controls should be used for settings that are important but do not require constant direct manipulation.

Avoid moving the primary content editing experience entirely into the sidebar.

### Toolbar Rule

Toolbar controls should:

- represent frequent contextual actions
- remain understandable
- use accessible labels
- avoid excessive control density
- reflect current state

### Inner Blocks Architecture

When nested blocks are required, define:

```text
Inner Blocks Plan

Purpose:
Allowed Blocks:
Template:
Template Lock:
Orientation:
Parent Relationship:
Appender Behavior:
Empty State:
```

Do not allow unrestricted nested content when project requirements require controlled structure.

### Block Supports

Evaluate support for:

- alignment
- anchor
- color
- spacing
- typography
- dimensions
- border
- layout
- reusable behavior
- HTML editing

Enable only supports appropriate to the block.

### Block Variations

For each variation define:

```text
Variation:
Purpose:
Attributes:
Inner Blocks:
Scope:
Default Status:
Icon:
Example:
```

Use variations when multiple configurations share the same underlying block architecture.

### Block Styles

For each style define:

```text
Style:
Purpose:
Class:
Default Status:
Frontend Behavior:
Editor Behavior:
```

Styles should represent presentation differences, not separate business behavior.

### Transform Architecture

When transforms are required, define:

```text
Transform:
Source:
Target:
Eligibility:
Attribute Mapping:
Content Preservation:
Failure Behavior:
```

Transforms must preserve user content whenever practical.

### Dynamic Rendering

For dynamic blocks, define:

```text
Dynamic Render Plan

Render Callback:
Input Attributes:
Context:
Data Sources:
Authorization:
Caching:
Escaping:
Empty State:
Error State:
```

Dynamic rendering must not expose private data to unauthorized visitors.

### Data Access Rule

Blocks should not contain unnecessary direct data access in presentation code.

Preferred flow:

```text
Block Editor or Render Callback
↓
Service
↓
Repository
↓
WordPress API or Database
```

### REST Integration

When a block uses REST data, define:

```text
REST Integration

Endpoint:
Method:
Authentication:
Permission Model:
Request Data:
Response Data:
Loading State:
Empty State:
Error State:
Caching:
```

REST behavior must follow the approved REST architecture.

### Media Integration

When a block uses media, define:

- allowed media types
- selection behavior
- replacement behavior
- removal behavior
- alt text behavior
- responsive image behavior
- missing media behavior

The block must not remove or bypass accessible media information without justification.

### Asset Architecture

For each block asset define:

```text
Asset:
Purpose:
Editor or Frontend:
Dependencies:
Load Condition:
Build Source:
Generated Output:
Versioning:
```

Avoid loading editor-only code on the frontend.

Avoid loading frontend assets globally when block-specific loading is available and appropriate.

### Accessibility Requirements

Block interfaces must consider:

- keyboard operation
- accessible control names
- focus behavior
- status announcements
- error announcements
- semantic frontend markup
- heading hierarchy
- form labels
- contrast
- reduced motion
- media alternatives

The editor experience and frontend output must both be reviewed.

### Performance Requirements

Review:

- JavaScript bundle size
- editor initialization cost
- unnecessary dependencies
- repeated REST requests
- excessive re-renders
- large serialized attributes
- frontend asset loading
- dynamic render query cost
- repeated server requests
- media size

Significant risks must be assigned to the Performance Engineer.

### Security Requirements

Verify:

- dynamic output is escaped
- REST requests follow permission rules
- privileged data is not exposed
- server-side authorization is authoritative
- attributes are validated where required
- rendered URLs are escaped
- allowed HTML is filtered
- no secrets are placed in block JavaScript or attributes

### Serialization Compatibility

For static blocks, the Block Engineer must protect saved markup compatibility.

Before changing saved output:

1. inspect existing markup
2. identify validation impact
3. define deprecation strategy when needed
4. test existing content
5. test content recovery behavior
6. document migration requirements

### Deprecation Strategy

When existing block markup or attributes change, define:

```text
Deprecation Plan

Previous Version:
Previous Attributes:
Previous Save Behavior:
Migration Function:
Content Preservation:
Validation Test:
Removal Condition:
```

Deprecated behavior must not be removed until compatibility requirements permit removal.

### Testing Requirements

Test:

- block registration
- inserter visibility
- block insertion
- default state
- attribute changes
- toolbar controls
- Inspector Controls
- save behavior
- reload behavior
- frontend rendering
- dynamic rendering when applicable
- nested blocks when applicable
- variations when applicable
- styles when applicable
- transforms when applicable
- keyboard operation
- focus behavior
- invalid data
- missing data
- REST failures
- old saved content
- responsive behavior
- editor/frontend consistency

### Self-Review Checklist

Before handoff, verify:

- block purpose is clear
- implementation model is justified
- identity follows naming rules
- metadata is complete
- attributes are predictable
- editor controls are appropriate
- frontend output is correct
- dynamic output is escaped
- REST permissions are respected
- accessibility requirements are addressed
- assets load in the correct context
- unnecessary dependencies are avoided
- serialization compatibility is protected
- deprecations are defined when required
- testing requirements are complete

## Block Engineering Report

Produce:

```text
Block Engineering Report

Project:
Assignment:

Block Identity:

Implementation Model:

Metadata:

Attributes:

Editor Interface:

Frontend Output:

Inner Blocks:

Block Supports:

Variations:

Styles:

Transforms:

Dynamic Rendering:

REST Integrations:

Media Integrations:

Assets:

Accessibility Controls:

Security Controls:

Performance Considerations:

Compatibility Strategy:

Deprecation Strategy:

Validation Performed:

Tests Performed:

Known Limitations:

Open Risks:

Documentation Impact:

Handoff Status:
```

### Handoff

The Block Engineer normally coordinates with and hands work to:

- JavaScript Engineer for editor implementation.
- PHP Engineer for dynamic rendering and server integration.
- CSS Engineer for editor and frontend styles.
- REST Engineer when API access is required.
- Security Engineer for data exposure and permission review.
- Performance Engineer for significant editor or frontend cost.
- QA Engineer for editor, frontend, compatibility, and accessibility testing.
- Documentation Engineer for block usage documentation.

### Boundaries

The Block Engineer does not:

- redefine approved project scope independently
- bypass server-side authorization
- change established REST contracts independently
- break saved block content without a migration strategy
- approve its own security review
- approve final accessibility status
- approve final QA status
- approve release readiness

If the block requires architecture changes outside its approved boundaries, return the issue to the appropriate Architect.

## Rule

No custom WordPress block may proceed to release until its editor behavior, frontend output, attributes, security, accessibility, performance, and saved-content compatibility have been independently validated.
