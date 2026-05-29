#!/bin/sh
#
# Generate the frontend TypeScript types from the code-first OpenAPI document.
#
# Source-of-truth pipeline (Phase 4+):
#   PHP impl  --(swagger-php)-->  backend/openapi.json
#                              --(openapi-typescript)-->  frontend/src/api/schema.ts
#                              consumed at runtime by openapi-fetch
#
# Pinned tooling (frontend package.json, exact versions — bump deliberately):
#   - openapi-typescript 7.13.0  (devDependency, run here)
#   - openapi-fetch      0.17.0  (dependency, used by src/api/client.openapi.ts)
#
# schema.ts is a generated-but-tracked artifact, exactly like backend/openapi.json.
# CI does NOT run this script; regenerate by hand after the OpenAPI doc changes
# (the `composer openapi` script in backend/ regenerates openapi.json first).
#
# This replaces the old api_defs/gen_ts.sh (openapi-generator typescript-fetch
# class client). That file was retired in Phase 5 with the rest of api_defs/ —
# this is now the only TS generator.
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/frontend"

yarn openapi-typescript ../backend/openapi.json -o src/api/schema.ts
