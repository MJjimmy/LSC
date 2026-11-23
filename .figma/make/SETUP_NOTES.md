# Initial import setup notes

## Figma production deploy

The application is server-rendered PHP and requires a PostgreSQL-backed runtime.
Its supported production path is the existing `Dockerfile` and `render.yaml`.

Figma's deploy verifier requires a deployable static output directory for this
repository. The application has no static production build, and publishing only
the public assets would omit PHP routing, authentication, and collaboration
features. For that reason, no `.figma/make/deploy` script was created.

`figma make verify-deploy` currently reports that `.figma/make/deploy` is
missing. Supporting Figma production publishing would require a separately
approved application migration to a supported server runtime.

## Data-backed preview features

The public landing page runs without database configuration. Authentication and
collaboration features require `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, and
`PGPASSWORD` to point to a provisioned PostgreSQL database with the application
schema.
