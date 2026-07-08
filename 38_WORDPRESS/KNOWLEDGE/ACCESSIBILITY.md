# WordPress Accessibility Knowledge

## Purpose

This file defines the accessibility knowledge that the SquirrelForge WordPress Agent must apply when developing or reviewing WordPress plugins, themes, blocks, patterns, admin interfaces, and frontend output.

Accessibility is a compatibility requirement, not an optional enhancement.

## Core Principles

The WordPress Agent must preserve:

- Keyboard accessibility.
- Logical focus order.
- Visible focus indicators.
- Semantic HTML structure.
- Correct heading hierarchy.
- Accessible form labels and instructions.
- Sufficient text and interface contrast.
- Meaningful alternative text behavior.
- Screen reader compatibility.
- Reduced reliance on color alone.
- Predictable navigation and interaction behavior.
- Compatibility with browser zoom and text resizing.

## Semantic HTML

Use native HTML elements whenever they provide the required behavior.

Prefer:

- `button` for actions.
- `a` for navigation.
- Proper heading elements for document structure.
- `label` elements associated with form controls.
- Lists for actual list content.
- Tables only for tabular data.
- Native form controls before custom replacements.

ARIA must supplement semantic HTML, not replace correct native structure.

## Keyboard Interaction

Interactive functionality must be usable without a mouse.

The Agent must verify:

- Interactive elements are keyboard reachable.
- Focus order follows the visual and logical workflow.
- Focus is not trapped unintentionally.
- Custom controls implement appropriate keyboard behavior.
- Modal and dialog focus is managed correctly.
- Focus returns to an appropriate location after temporary interfaces close.

## Forms

WordPress forms and settings interfaces must:

- Provide programmatically associated labels.
- Identify required fields clearly.
- Provide understandable validation messages.
- Associate errors with the affected fields.
- Preserve entered data when validation fails where practical.
- Avoid placeholder text as the only label.
- Provide instructions before users need them.

## Images and Media

The Agent must distinguish between informative and decorative images.

Informative images require meaningful alternative text appropriate to context.

Decorative images should not create unnecessary screen reader output.

Audio and video features must consider captions, transcripts, controls, autoplay behavior, and keyboard operation.

## WordPress Admin Interfaces

Custom WordPress admin interfaces must follow established WordPress admin interaction patterns where practical.

The Agent must consider:

- Existing WordPress admin markup conventions.
- Accessible notices.
- Screen reader text where visual labels are insufficient.
- Accessible tables and row actions.
- Form field descriptions.
- Keyboard-accessible controls.
- Focus behavior after asynchronous actions.

## Block Editor Compatibility

Blocks and patterns must remain accessible in both the editor and rendered frontend output.

The Agent must verify:

- Editor controls have understandable labels.
- Block settings remain keyboard operable.
- Generated markup preserves semantic structure.
- Dynamic rendering does not remove accessibility attributes.
- Custom interactions work with keyboard and assistive technology.
- Heading levels are not chosen only for visual appearance.

## Dynamic Interfaces

For AJAX, REST-driven, or JavaScript-updated interfaces, consider:

- Focus management.
- Loading state communication.
- Error communication.
- Success notices.
- Live region behavior when appropriate.
- Keyboard access to newly inserted controls.
- Prevention of unexpected context changes.

## Validation

Accessibility validation should combine automated checks with manual review.

Relevant checks include:

1. Navigate the interface using only the keyboard.
2. Confirm visible focus indicators.
3. Inspect heading structure.
4. Verify form labels and error relationships.
5. Check image alternative text behavior.
6. Test zoom and text resizing.
7. Review dynamic updates and focus management.
8. Use automated accessibility tools when available.
9. Perform screen reader testing for significant custom interfaces when practical.

## Rule

The WordPress Agent must not mark interface work complete solely because it renders correctly visually.

Accessibility requirements must be considered during implementation and verified during validation.
