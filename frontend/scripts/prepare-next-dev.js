const fs = require("fs");
const path = require("path");

const projectRoot = process.cwd();
const timestamp = new Date().toISOString().replace(/[:.]/g, "-");

const candidates = [
  {
    label: ".next/dev",
    root: path.join(projectRoot, ".next", "dev"),
    checks: ["", "lock"],
  },
  {
    label: ".next-dev",
    root: path.join(projectRoot, ".next-dev"),
    checks: ["", path.join("dev"), path.join("dev", "lock")],
  },
];

function hasWriteAccess(target) {
  const stat = fs.statSync(target);
  const mode =
    stat.isDirectory()
      ? fs.constants.R_OK | fs.constants.W_OK | fs.constants.X_OK
      : fs.constants.R_OK | fs.constants.W_OK;

  fs.accessSync(target, mode);
}

function getStalePath(target) {
  const parent = path.dirname(target);
  const base = path.basename(target);
  let attempt = 0;

  while (true) {
    const suffix = attempt === 0 ? "" : `-${attempt}`;
    const nextPath = path.join(parent, `${base}-stale-${timestamp}${suffix}`);

    if (!fs.existsSync(nextPath)) {
      return nextPath;
    }

    attempt += 1;
  }
}

for (const candidate of candidates) {
  if (!fs.existsSync(candidate.root)) {
    continue;
  }

  const blockedPath = candidate.checks
    .map((check) => path.join(candidate.root, check))
    .find((checkPath) => {
      if (!fs.existsSync(checkPath)) {
        return false;
      }

      try {
        hasWriteAccess(checkPath);
        return false;
      } catch {
        return true;
      }
    });

  if (!blockedPath) {
    continue;
  }

  const stalePath = getStalePath(candidate.root);

  try {
    fs.renameSync(candidate.root, stalePath);
    console.warn(
      `[prepare-next-dev] Rotated unwritable ${candidate.label} cache at ${path.relative(projectRoot, blockedPath)} -> ${path.relative(projectRoot, stalePath)}`
    );
  } catch (error) {
    const reason = error instanceof Error ? error.message : String(error);
    console.error(
      `[prepare-next-dev] Failed to rotate ${candidate.label} cache: ${reason}`
    );
    process.exit(1);
  }
}
