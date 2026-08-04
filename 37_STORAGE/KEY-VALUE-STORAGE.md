# SquirrelForge Key-Value Storage

## Purpose

Key-Value Storage provides durable, authoritative storage and retrieval of simple key-value records used across SquirrelForge: configuration values, feature flags, lookup entries, small state records, and other data addressed directly by a namespaced key rather than by query or search.

Key-Value Storage is distinct from `37_STORAGE/CACHE-MANAGER.md`: its records are durable and authoritative, never expire on their own, and are never silently evicted. It is distinct from `37_STORAGE/DOCUMENT-STORAGE.md`: its records are simple values addressed by key, not structured or semi-structured documents addressed by query.

Key-Value Storage does not define the business meaning of stored values. It stores and retrieves key-value data while preserving integrity, namespace isolation, permissions, versioning, and auditability.

---

# Responsibilities

- Store key-value records.
- Retrieve key-value records by key.
- Update existing key-value records.
- Delete key-value records.
- Enforce key namespace isolation.
- List keys within a namespace.
- Version key-value records on update.
- Record key-value operations.
- Maintain key-value operation history.

---

# Inputs

Key-Value Storage receives:

- Store requests
- Retrieve requests
- Update requests
- Delete requests
- List requests
- Namespace identifiers
- Permission context
- Governance policies

---

# Outputs

Key-Value Storage produces:

- Stored key-value confirmations
- Retrieved values
- Update confirmations
- Delete confirmations
- Key listings
- Key-value operation records

---

# Key-Value Workflow

1. Receive key-value request.
2. Validate namespace and key.
3. Verify permissions and governance rules.
4. Validate value structure.
5. Perform the requested operation (store, retrieve, update, delete, or list).
6. Record the previous version reference when an existing record is updated.
7. Record audit information.
8. Publish key-value status.
9. Return the operation response.

---

# Supported Operations

Key-Value Storage supports:

- Store (create a new record; rejected if the key already exists)
- Retrieve (read by key)
- Update (replace an existing record's value; rejected if the key does not exist)
- Delete (remove a record permanently)
- List (enumerate keys within a namespace, optionally by prefix)

Store and Update are deliberately separate operations. Key-Value Storage never silently overwrites an existing record on a Store request, and never creates a new record on an Update request.

---

# Key-Value Record

Every key-value record includes:

- Namespace
- Key
- Value
- Version
- Created timestamp
- Updated timestamp
- Permission status
- Governance status
- Final outcome

---

# Safety Rules

Key-Value Storage must never:

- Overwrite an existing record on a Store request instead of rejecting it.
- Update a record that does not exist instead of rejecting it.
- Return a deleted record.
- Leak keys or values from one namespace into another.
- Store unauthorized data.
- Retrieve data for unauthorized users or services.
- Bypass governance requirements.
- Corrupt stored values.

---

# Failure Handling

If a key-value operation fails:

- Preserve the original request.
- Record the failure.
- Retry when appropriate.
- Escalate persistent failures.
- Maintain audit continuity.
- Prevent partial writes from leaving a record in an inconsistent state.

---

# Audit Requirements

Every key-value operation records:

- Key-value operation ID
- Timestamp
- Namespace
- Key
- Operation type
- Permission status
- Governance status
- Final outcome

---

# Success Criteria

Key-Value Storage succeeds when:

- Records are stored and retrieved reliably by key.
- Namespace isolation is enforced.
- Updates are versioned and never confused with new records.
- Deletions are permanent and recorded.
- Governance and permission requirements are enforced.
- Audit history is complete.
