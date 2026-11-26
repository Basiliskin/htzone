---
name: devops
description: DevOps role for a home assignment — owns the production Dockerfile, CI workflow, secrets/env handling, deploying to the chosen free host, keep-awake ping and verifying the live site end to end. Use for S0 tooling review, after the last /developer step, or whenever build, CI or deploy breaks.
---

# /devops

Follow `.claude/skills/_shared/PROTOCOL.md` throughout.

## Goal
A live public URL that anyone can test without running anything locally, rebuilt automatically from the GitHub repo, with checks proving it works.

## Steps
1. Ground yourself (protocol §1): the hosting, DB and stack in `docs/decisions.md` and `docs/stack.md`. Look at the current Dockerfile, compose file and CI workflow before changing anything.
2. **Image**:
   - multi-stage build
   - pinned base image and dependency versions; no dev dependencies in the final stage
   - runs as a non-root user
   - data/DB built during the image build from committed sources (if that's the settled design), file permissions read-only
   - `HEALTHCHECK` or host health path set to `/health`
   - small image; `.dockerignore` excludes `.git`, tests, `node_modules`, `docs/ai-log` etc.
   Build it locally, run it, `curl` `/health` and a search. Paste real output.
3. **CI** (GitHub Actions):
   - on push and PR: install with cache → format check → static analysis → unit + integration tests → Docker build
   - E2E against the built container, if settled
   - fail fast, with readable job names
4. **Secrets and config**:
   - `.env.example` lists every variable; nothing secret is committed (grep the repo and git history)
   - host env vars set through the host's dashboard or CLI
5. **Deploy** to the settled host. Walk the user through any step that needs their account (signing up, connecting GitHub): tell them exactly what to click or run, and suggest `! <command>` for interactive logins. Never ask for their passwords or tokens in chat.
6. **Verify live** and paste results:
   - `/health` returns 200
   - each example query from `docs/requirements.md` returns sensible results, with response time
   - security headers present (`curl -I`)
   - HTTPS, RTL page renders, images load
   - cold-start time measured
7. **Keep-awake** (if the host sleeps): explain how to set up a free uptime ping to `/health` (every 10 min), and note the cold-start trade-off in the README.
8. Update the README *Run & load catalog* section (local and live) and *Known limitations*. Log in SESSION-LOG.

## Hand off
➡️ Next step: `/qa`
Prompt to paste: `/qa verify the live site and the repo against docs/requirements.md, audit security and architecture, and check publish readiness`
