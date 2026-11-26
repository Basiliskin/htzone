#!/usr/bin/env node
// PostToolUse hook (Edit/Write/MultiEdit): for an edited .php file, runs PHP CS Fixer (auto-fix)
// and PHPStan on that file inside the running `app` compose service.
// Silently skips when: not a .php file, Docker/compose unavailable, `app` not running, or vendor/ missing.
// On PHPStan errors: exits 2 with the errors on stderr so Claude sees and fixes them.
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join, relative } from 'node:path';

const projectDir = process.env.CLAUDE_PROJECT_DIR ?? process.cwd();
const SERVICE = 'app';

const run = (args, opts = {}) =>
  execFileSync('docker', args, { cwd: projectDir, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 60_000, ...opts });

let raw = '';
process.stdin.on('data', (chunk) => (raw += chunk));
process.stdin.on('end', () => {
  let filePath;
  try {
    filePath = JSON.parse(raw || '{}')?.tool_input?.file_path;
  } catch {
    process.exit(0);
  }
  if (!filePath || !filePath.endsWith('.php')) process.exit(0);

  const rel = relative(projectDir, filePath);
  if (rel.startsWith('..') || rel.startsWith('vendor/')) process.exit(0);
  if (!existsSync(join(projectDir, 'vendor', 'bin', 'phpstan'))) process.exit(0);

  try {
    const running = run(['compose', 'ps', '--status', 'running', '--services']);
    if (!running.split('\n').includes(SERVICE)) process.exit(0);
  } catch {
    process.exit(0); // docker not available
  }

  // 1) auto-format (best effort, never blocks)
  try {
    run(['compose', 'exec', '-T', SERVICE, 'vendor/bin/php-cs-fixer', 'fix', rel, '--quiet']);
  } catch {
    /* ignore formatter failures */
  }

  // 2) static analysis — feed errors back to Claude
  try {
    run(['compose', 'exec', '-T', SERVICE, 'vendor/bin/phpstan', 'analyse', rel, '--no-progress', '--error-format=raw', '--memory-limit=512M']);
  } catch (err) {
    const out = `${err.stdout ?? ''}${err.stderr ?? ''}`.trim();
    if (err.status === 1 && out) {
      process.stderr.write(`PHPStan found problems in ${rel}:\n${out}\n`);
      process.exit(2);
    }
  }
  process.exit(0);
});
