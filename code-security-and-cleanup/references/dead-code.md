# Dead Code

## Contents
1. Proof standard
2. Finding candidates
3. Search protocol
4. Alive by convention
5. Removal order and commits
6. Dependencies

The "never REMOVE" list in `SKILL.md` applies to everything here.

## 1. Proof standard

- **REMOVE:** you ran the search protocol (section 3), nothing loads or calls the item, and no convention in section 4 or rule in `SKILL.md` keeps it alive. Record the evidence: the commands you ran and what they returned, for example "searched the whole repository for the name and for it as a string: only the definition".
- **REVIEW:** you suspect it is unused, but some route cannot be ruled out from the repository. Say which route, and what would settle it: access logs, the owner's confirmation, a database query.
- **KEEP:** it is alive. When it looked dead, say what keeps it alive, so nobody proposes it again.

Some dead code is provable from one file: statements after an unconditional `return`, `throw` or exit; a local variable never read, unless the language can read it by name (see `SKILL.md`); an import whose name is never used; a private member never used in its class; a block of commented-out code. Everything else (functions, exports, files, assets, dependencies) needs the whole-repository search.

## 2. Finding candidates

- **Use the project's own tools first,** where they exist: its linter, type checker or dead-code tool. Their output is a candidate list, not proof; check each item against section 4.
- Then look for:
  - files nothing imports, requires, includes or enqueues;
  - exported names with no importer, and functions with no caller;
  - assets (scripts, styles, images) that no code or markup references;
  - commented-out code and unreachable statements;
  - manifest dependencies that no file uses (section 6).
- **Leave third-party and generated code out:** vendored libraries, installed packages, build output, minified files.

## 3. Search protocol

For each candidate name:
1. Search the whole repository for the full name, including templates, markup, config, build files, package scripts, CI files and documentation.
2. Search for fragments, in case the name is built at runtime from a prefix and a variable.
3. Search for it as a string literal: callbacks registered by name, dynamic dispatch, reflection.
4. Check each convention in section 4.
5. Match the language's rules: some languages treat function and class names case-insensitively.

For a file, search for its name with and without the extension and its path, and look for loaders that include every file in a folder.

A reference only from tests or maintenance scripts does not make code dead, but say so: the owner may want it.

## 4. Alive by convention

Code can be alive with no direct reference. Check for each candidate:
- **Loaded by name or place:** framework route and page files, templates picked by file name, migrations, config files, files a loader includes by scanning a folder.
- **Called by a name in a string:** hook and event callbacks, route tables, dependency-injection containers, plugin loaders that read names from config.
- **Called from markup:** inline event handlers in HTML or templates, and data attributes or class names that scripts look up.
- **Called from outside the repository:** the public API of a library, plugin or theme, documented functions, command-line commands, scheduled jobs, webhooks.
- **Run for side effects:** imports and includes that bind no name, and modules that register something when loaded.
- **Read by tools or the runtime:** comments carrying block markup, translator notes, linter or bundler directives, licence headers.

If any of these might apply, the verdict is REVIEW, not REMOVE.

## 5. Removal order and commits

Safest first, one commit per kind of change:
1. commented-out code and unreachable statements;
2. unused imports and local variables;
3. unused private members;
4. unused functions and exports;
5. unused files and assets;
6. unused dependencies.

With each removal:
- remove the item's doc comment, and any import that your removal left unused; touch nothing else;
- delete files with `git rm`, and check build configs and manifests that list files;
- after the commit, search again for every removed name across the repository, then run the checks listed in `SKILL.md`.

## 6. Dependencies

- **A dependency is unused** only when no source file, build or bundler config, package script, CI file or tool config names it. Config files often load plugins by a string name, and some packages are used only from the command line.
- **Edit the manifest by hand.** Do not run the package manager's remove or update command: it usually reinstalls the remaining packages and may use the network.
- **The lockfile:** edit it by hand only when the package's entries are easy to find and nothing else depends on the package. Otherwise leave it, and say in the outcome report that the lockfile must be regenerated and that a clean install in CI may fail until it is.
- **Never install, update or add packages** while doing this.
