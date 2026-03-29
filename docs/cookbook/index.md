# Recipes

Practical patterns for common workflow scenarios.

## Examples

| Recipe | Use Case |
|--------|----------|
| [Order Workflow](./order-workflow) | E-commerce order lifecycle |
| [Approval Flow](./approval-flow) | Document/request approval process |

## Development

| Recipe | Purpose |
|--------|---------|
| [Scaffolding](./scaffolding) | Generate workflow code quickly |
| [Testing Workflows](./testing) | Test strategies and examples |

## When to Use Each Pattern

### Order Workflow

Best for:
- E-commerce orders
- Fulfillment pipelines
- Any linear progression with payments

### Approval Flow

Best for:
- Document approvals
- Request workflows
- Sequential review processes

::: info
Both patterns use single-state workflows. For parallel approvals (e.g., "finance AND legal must approve"), you'd need a different architecture.
:::
