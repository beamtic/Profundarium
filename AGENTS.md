# Branch workflow

- Work on the `development` branch. Push branch commits only to
  `origin development`; never push directly to `main` or another branch.
- Do not create temporary feature branches for ordinary work. If development
  is not checked out, inspect the working tree before switching to it.
- Main is updated by a pull request from development, merged by the maintainer.
  Do not merge or push main unless the user explicitly overrides this policy.
- Before every push, verify the checked-out branch and use an explicit target:
  `git push origin development`. Never force-push.
- Release tags follow the separate release authorization rules below. A normal
  commit/push request does not authorize publishing a tag.

# Release versioning

Profundarium uses numbered container releases. `VERSION` contains the version
being prepared, without a `v` prefix. Git release tags use `vX.Y.Z`; GHCR image
tags use `X.Y.Z`.

Before preparing a release:

- Check existing remote tags. Never reuse, move, or overwrite a release tag.
- If VERSION has already been released, increment it once for the next release.
  Do not bump it for every commit while preparing that release.
- Use patch increments for fixes and minor increments for features. While below
  1.0, also increment the minor version for incompatible deployment changes.
- Update both Profundarium image defaults in compose.example.yml to match VERSION.
  The application and assets-init services must always use the same version.
- Keep deployment examples on numbered releases, not main or latest. Local test
  images can be selected through PROFUNDARIUM_IMAGE.
- Update DOCKER.md when release or deployment instructions change. Run
  `python3 docker/check-version.py` and the relevant application/container checks.

A request to commit or push changes does not by itself authorize a release tag.
The human maintainer decides when to release. Create and push a release tag only
when releasing is explicitly authorized; tag the tested commit intended for that
release. If asked to ship a release, perform the version checks as part of it.

A version in VERSION or the Compose example is not published until its release
tag workflow succeeds.
