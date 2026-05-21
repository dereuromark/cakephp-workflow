# Approval Flow

Approval flows are a good fit as long as only one current state is active at a time.

Example states:

- `draft`
- `submitted`
- `approved`
- `rejected`

## Diagram

A single reviewer moves a record `draft → submitted → approved`; `reject` is the alternative terminal outcome.

```mermaid
flowchart TD
    draft([Draft])
    submitted([Submitted])
    approved([Approved])
    rejected([Rejected])
    draft -->|submit| submitted
    submitted -->|approve| approved
    submitted -->|reject| rejected
    classDef initial fill:#f5f5f5,stroke:#9e9e9e,stroke-width:2px
    classDef final fill:#e8f5e9,stroke:#4caf50,stroke-width:2px
    classDef failed fill:#ffebee,stroke:#f44336,stroke-width:2px
    class draft initial
    class approved final
    class rejected failed
    linkStyle 0,1 stroke:#2e7d32,stroke-width:2px
```

## When This Model Works Well

- one approver at a time
- serial review
- clear terminal outcomes

## When It Stops Fitting

If your process requires:

- multiple parallel approvals
- “finance and legal must both approve”
- partial approvals active at the same time

then you are moving beyond a single-state model.

That is the point where a true multi-place workflow engine becomes relevant.

