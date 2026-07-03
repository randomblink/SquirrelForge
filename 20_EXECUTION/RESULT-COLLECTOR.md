# SquirrelForge Result Collector

## Purpose

The Result Collector gathers outputs from completed actions, verifies their completeness, consolidates execution results, and returns them to the Workflow Executor for the next workflow stage.

---

## Responsibilities

- Receive completed action results.
- Verify result completeness.
- Confirm required outputs are present.
- Aggregate execution results.
- Detect missing or duplicate results.
- Record collection history.
- Return consolidated results to the Workflow Executor.

---

## Collection Process

1. Receive completed action output.
2. Validate output integrity.
3. Confirm required artifacts exist.
4. Merge results into the workflow record.
5. Detect missing or duplicate outputs.
6. Record collection status.
7. Return consolidated execution results.

---

## Result Status

| Status | Meaning |
|---|---|
| Pending | Awaiting action completion |
| Received | Output received |
| Validated | Output passed verification |
| Missing | Required output not received |
| Duplicate | Duplicate output detected |
| Rejected | Output failed validation |
| Complete | Ready for the next workflow stage |

---

## Result Record

| Field | Description |
|---|---|
| Result ID | Unique identifier |
| Workflow | Parent workflow |
| Action ID | Source action |
| Output Type | Artifact or result category |
| Validation | Pass / Fail |
| Timestamp | Collection time |
| Status | Current collection status |

---

## Validation Checklist

- Required outputs received.
- Output integrity verified.
- No duplicate artifacts detected.
- Validation completed successfully.
- Workflow record updated.

---

## Rule

A workflow stage is not complete until every required execution result has been collected, validated, and recorded.
