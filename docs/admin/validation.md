# Validation and Orphans

## Validation

The workflow analyzer can detect issues such as:

- missing initial state
- multiple initial states
- unreachable states
- dead ends
- invalid transition targets
- duplicate transitions
- broken happy paths

Use:

```bash
bin/cake workflow validate
```

or the admin validation screen.

## Orphans

An orphan is an entity whose current state does not exist in the definition anymore.

Typical causes:

- state rename
- state removal
- bad direct writes
- incomplete data migration

The orphan view helps you find those records before they cause hidden runtime errors.

