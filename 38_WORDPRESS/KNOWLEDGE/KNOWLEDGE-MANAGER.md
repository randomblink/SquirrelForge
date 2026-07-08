Status: Stable

---
# SquirrelForge WordPress Knowledge Manager

## Purpose

The Knowledge Manager acts as the central authority for selecting, loading, and prioritizing WordPress knowledge documents for any given development task. It ensures that all agents and components within the WordPress Layer operate from the same set of authoritative references, turning the knowledge base from a passive library into an active, curated resource.

## Core Principle

An agent's decision is only as good as the knowledge it consults. The Knowledge Manager guarantees that the *right* knowledge is consulted for every task, ensuring consistency, accuracy, and traceability in the agent's reasoning and output.

---

## Responsibilities

-   **Analyze Task**: Deconstruct a development request into its core components (e.g., "build a settings page" involves settings, security, and standards).
-   **Map Knowledge**: Determine the precise set of knowledge documents required for the task based on a predefined mapping.
-   **Load Context**: Load the content of only the relevant documents into the active context for the planning and execution agents.
-   **Resolve Conflicts**: Apply the master knowledge priority rules (e.g., Security > Performance) when documents present conflicting guidance.
-   **Record References**: Maintain a log of which knowledge documents were consulted for a specific plan or decision, creating an audit trail.

---

## Workflow

1.  **Receive Task**: The Knowledge Manager is given a specific development task from the `WORDPRESS-MANAGER`.
2.  **Analyze & Map**: It analyzes the task and maps it to a set of knowledge documents.
3.  **Load Context**: It retrieves and loads the content of the selected documents.
4.  **Provide Context**: It provides this curated knowledge context to the `PLUGIN-ARCHITECT`, `CODE-GENERATOR`, or other relevant components.
5.  **Record References**: It logs the list of documents used for the task's traceability report.

---

## Knowledge Mapping Examples

| Task | Knowledge Documents to Consult |
| :--- | :--- |
| **Build a new plugin** | `PLUGIN-HANDBOOK.md`, `CODING-STANDARDS.md`, `SECURITY.md`, `38_WORDPRESS/KNOWLEDGE/DATABASE.md` |
| **Build a block theme** | `38_WORDPRESS/KNOWLEDGE/THEME-HANDBOOK.md`, `BLOCK-EDITOR.md`, `CODING-STANDARDS.md`, `38_WORDPRESS/KNOWLEDGE/ACCESSIBILITY.md` |
| **Create a REST endpoint** | `38_WORDPRESS/KNOWLEDGE/REST-API.md`, `SECURITY.md`, `PERFORMANCE.md`, `CODING-STANDARDS.md` |
| **Create a settings page** | `SETTINGS-API.md`, `SECURITY.md`, `CODING-STANDARDS.md`, `38_WORDPRESS/KNOWLEDGE/ACCESSIBILITY.md` |
| **Create a WooCommerce extension** | `WOOCOMMERCE.md`, `PLUGIN-HANDBOOK.md`, `38_WORDPRESS/KNOWLEDGE/REST-API.md`, `SECURITY.md`, `38_WORDPRESS/KNOWLEDGE/DATABASE.md` |
| **Add a custom taxonomy** | `38_WORDPRESS/KNOWLEDGE/TAXONOMIES.md`, `PLUGIN-HANDBOOK.md`, `CODING-STANDARDS.md` |
| **Create a shortcode** | `SHORTCODES.md`, `SECURITY.md`, `CODING-STANDARDS.md` |

---

## Conflict Resolution

The Knowledge Manager must enforce the priority order defined in `38_WORDPRESS/KNOWLEDGE/README.md`:

1.  Security rules
2.  WordPress official behavior
3.  Project-specific requirements
4.  Performance rules
5.  Convenience

If `PERFORMANCE.md` suggests a caching strategy that `SECURITY.md` identifies as risky, the security rule must take precedence.

---

## Traceability

For every significant output (e.g., a generated file, an architectural plan), the system must be able to answer the question: "What knowledge was used to make this decision?" The Knowledge Manager is responsible for providing this information.

**Example Log Entry:**
`Task: generate_settings_field. References: [SETTINGS-API.md, SECURITY.md, CODING-STANDARDS.md].`

---

## Agent Rules

1.  **Centralized Authority**: All WordPress agents and components **must** request knowledge through the Knowledge Manager. They must not select knowledge documents themselves.
2.  **Mandatory Consultation**: No architectural plan may be created and no code may be generated without first consulting the knowledge provided by the Knowledge Manager for that task.
3.  **Context is King**: The agent must operate only on the knowledge provided for the current task. It should not assume knowledge from a previous, unrelated task is still relevant.

## Rule

Knowledge selection must prioritize required, specific, and current WordPress references before task execution.
