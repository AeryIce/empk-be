import assert from "node:assert/strict";
import { spawn, spawnSync } from "node:child_process";
import { once } from "node:events";
import { existsSync, mkdtempSync, rmSync, rmdirSync } from "node:fs";
import { createConnection, createServer } from "node:net";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { setTimeout as pause } from "node:timers/promises";

const php = process.env.EMPK_SMOKE_PHP ?? "php";
const extensionDir = process.env.EMPK_SMOKE_PHP_EXT_DIR;
const phpOptions = extensionDir
  ? ["-n", "-d", "extension_dir=" + extensionDir, "-d", "extension=mbstring", "-d", "extension=intl",
    "-d", "extension=pdo_sqlite", "-d", "extension=openssl"]
  : [];

assert.ok(existsSync("server.php") && existsSync("vendor/autoload.php"), "Run from the checkout with Composer dependencies installed.");
for (const path of [".env", ".env.local", "bootstrap/cache/config.php"]) {
  assert.ok(!existsSync(path), "Use a clean test checkout without runtime credentials or cached config.");
}
const version = spawnSync(php, [...phpOptions, "-r", "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"], { encoding: "utf8" });
assert.equal(version.status, 0, "PHP failed to start");
assert.equal(version.stdout.trim(), "8.3", "Run this smoke test on PHP 8.3");

const temp = mkdtempSync(join(tmpdir(), "empk-recovery-http-"));
const database = join(temp, "fixtures.sqlite");
const portServer = createServer().listen(0, "127.0.0.1");
await once(portServer, "listening");
const { port } = portServer.address();
await new Promise((resolve, reject) => portServer.close((err) => err ? reject(err) : resolve()));
const origin = "http://127.0.0.1:" + port;
const fixturePassword = "Local fixture password";
const env = {
  PATH: process.env.PATH,
  SystemRoot: process.env.SystemRoot,
  TEMP: process.env.TEMP,
  TMP: process.env.TMP,
  APP_NAME: "eMPK Local Smoke",
  APP_ENV: "local",
  APP_DEBUG: "false",
  APP_URL: origin,
  APP_KEY: "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=",
  DB_CONNECTION: "sqlite",
  DB_DATABASE: database,
  DB_URL: "",
  SESSION_DRIVER: "file",
  SESSION_SECURE_COOKIE: "false",
  SESSION_COOKIE: "empk_recovery_test",
  CACHE_STORE: "array",
  QUEUE_CONNECTION: "sync",
  MAIL_MAILER: "array",
  LOG_CHANNEL: "stderr",
  EMPK_FIXTURE_PASSWORD: fixturePassword,
};

const setup = ' ' + [
  "$pdo = new PDO('sqlite:'.getenv('DB_DATABASE'));",
  "$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, role TEXT, panel_activated_at TEXT, email_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT)');",
  "$insert = $pdo->prepare('INSERT INTO users (name,email,password,role,panel_activated_at) VALUES (?,?,?,?,?)');",
  "foreach (['super_moderator','super_admin'] as $role) { $insert->execute([$role, $role.'@example.test', password_hash(getenv('EMPK_FIXTURE_PASSWORD'), PASSWORD_BCRYPT, ['cost'=>4]), $role, '2026-01-01']); }",
  "$pdo->exec(\"CREATE TABLE foundations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, kode TEXT, is_active BOOLEAN); INSERT INTO foundations VALUES (1, 'legacy-lpk', 'Legacy fixture LPK', 'sample', 1)\");",
  "$pdo->exec(\"CREATE TABLE schools (id INTEGER PRIMARY KEY, slug TEXT, nama TEXT, foundation_id INTEGER, jenjang TEXT, status_sekolah TEXT, is_published BOOLEAN); INSERT INTO schools VALUES (1, 'legacy-school', 'Legacy fixture school', 1, 'SD', 'aktif', 0)\");",
  "$pdo->exec(\"CREATE TABLE news_posts (id INTEGER PRIMARY KEY, slug TEXT, title TEXT, status TEXT, published_at TEXT); INSERT INTO news_posts VALUES (1, 'fixture-news', 'Fixture news', 'published', '2024-01-01 00:00:00')\");",
].join(" ");

const request = (method, path, headers = {}, body = "") => new Promise((resolve, reject) => {
  const data = [];
  const socket = createConnection({ host: "127.0.0.1", port }, () => {
    const fields = { Host: "127.0.0.1:" + port, Connection: "close", ...headers };
    if (body) fields["Content-Length"] = Buffer.byteLength(body);
    const text = Object.entries(fields).map(([name, value]) => name + ": " + value).join("\r\n");
    socket.write(method + " " + path + " HTTP/1.1\r\n" + text + "\r\n\r\n" + body);
  });

  socket.setTimeout(8000, () => socket.destroy(new Error("Loopback request timed out")));
  socket.on("error", reject);
  socket.on("data", (bytes) => data.push(bytes));
  socket.on("end", () => {
    const raw = Buffer.concat(data).toString("utf8");
    const boundary = raw.indexOf("\r\n\r\n");
    if (boundary === -1) return reject(new Error("No HTTP response for " + method + " " + path));
    const [statusLine, ...lines] = raw.slice(0, boundary).split("\r\n");
    const responseHeaders = {};
    for (const line of lines) {
      const at = line.indexOf(":");
      if (at < 1) continue;
      const name = line.slice(0, at).toLowerCase();
      (responseHeaders[name] ??= []).push(line.slice(at + 1).trim());
    }
    resolve({ status: Number(statusLine.split(" ")[1]), headers: responseHeaders, body: raw.slice(boundary + 4) });
  });
});

const requireStatus = (result, status, label) => assert.equal(result.status, status, label);
function assertBlocked(result, method, path) {
  requireStatus(result, 503, method + " " + path);
  assert.deepEqual(result.headers["cache-control"], ["no-store, private"]);
  assert.equal(result.headers.etag, undefined);
  assert.equal(result.headers["last-modified"], undefined);
  assert.equal(result.headers["set-cookie"], undefined);
  assert.equal(result.body, method === "HEAD" ? "" : '{"message":"Directory temporarily unavailable."}');
}

function collectCookies(result, jar) {
  for (const value of result.headers["set-cookie"] ?? []) {
    const pair = value.split(";", 1)[0];
    const position = pair.indexOf("=");
    jar.set(pair.slice(0, position), pair.slice(position + 1));
  }
}
const cookieHeader = (jar) => [...jar].map(([name, value]) => name + "=" + value).join("; ");
function getLoginSnapshot(html) {
  for (const [, encoded] of html.matchAll(/wire:snapshot="([^"]+)"/g)) {
    const decoded = encoded.replaceAll("&quot;", '"').replaceAll("&#039;", "'")
      .replaceAll("&lt;", "<").replaceAll("&gt;", ">").replaceAll("&amp;", "&");
    if (JSON.parse(decoded).memo.name === "app.filament.pages.auth.login") return decoded;
  }
  assert.fail("Filament login snapshot missing");
}

let server;
try {
  const prepared = spawnSync(php, [...phpOptions, "-r", setup], { env, encoding: "utf8" });
  assert.equal(prepared.status, 0, "Could not create isolated SQLite fixtures: " + prepared.stderr.slice(0, 300));
  server = spawn(php, [...phpOptions, "-S", "127.0.0.1:" + port, "-t", "public", "server.php"], {
    windowsHide: true, env, stdio: ["ignore", "ignore", "pipe"],
  });
  let spawnError = null;
  server.on("error", (error) => { spawnError = error; });
  let logs = "";
  server.stderr.on("data", (data) => { logs = (logs + data).slice(-4000); });

  let live = false;
  for (let attempt = 0; attempt < 40; attempt++) {
    if (spawnError || server.exitCode !== null) break;
    try { live = (await request("GET", "/up")).status === 200; } catch {}
    if (live) break;
    await pause(150);
  }
  assert.ok(live, "Local PHP HTTP server did not become ready\n" + logs);

  const conditional = {
    "If-None-Match": "*",
    "If-Modified-Since": "Fri, 01 Jan 2038 00:00:00 GMT",
  };
  const blocked = [
    ["GET", "/api/schools"], ["HEAD", "/api/schools", conditional],
    ["GET", "/api/schools/legacy-school", conditional], ["GET", "/api/schools/unknown"],
    ["GET", "/api/schools/?q=test"], ["GET", "/api/foundations"],
    ["HEAD", "/api/foundations/legacy-lpk", conditional], ["GET", "/api/foundations/unknown/"],
    ["GET", "/api/foundations/legacy-lpk", conditional],
    ["POST", "/api/schools/legacy-school"], ["PUT", "/api/foundations"],
    ["PATCH", "/api/schools"], ["DELETE", "/api/foundations/legacy-lpk"],
    ["OPTIONS", "/api/schools"],
    ["GET", "//api//schools/old"], ["GET", "/Api/Foundations/old"],
    ["GET", "/api/%73chools"], ["GET", "/%61pi%2Ffoundations%2Fold"],
    ["GET", "/api/%2573chools"], ["GET", "/api%5Cfoundations/old"],
    ["GET", "/./api/schools"], ["GET", "/other/%2e%2e/api/foundations"],
    ["GET", "/api/schools/.."], ["GET", "/api/foundations/%2e%2e"],
    ["GET", "/index.php/api/schools/old"], ["GET", "/api/foundations;legacy/old"],
    ["GET", origin + "/api/schools"],
  ];

  for (const [method, path, headers] of blocked) {
    assertBlocked(await request(method, path, headers), method, path);
  }

  const negative = await request("GET", "/api/schools-guide");
  requireStatus(negative, 404, "Unrelated route prefix");
  const staticFile = await request("GET", "/favicon.ico");
  requireStatus(staticFile, 200, "Static public asset");
  requireStatus(await request("GET", "/healthz"), 200, "Health endpoint");
  const apiNews = await request("GET", "/api/berita?next=/api/schools", { Accept: "application/json" });
  requireStatus(apiNews, 200, "Other API list");
  assert.equal(JSON.parse(apiNews.body).data[0].slug, "fixture-news");
  requireStatus(await request("GET", "/api/berita/fixture-news"), 200, "Other API detail");
  const activation = await request("GET", "/admin/activate");
  requireStatus(activation, 200, "Activation page");
  assert.match(activation.body, /name="activation_code"/);
  requireStatus(await request("GET", "/admin"), 302, "Filament unauthenticated redirect");

  for (const role of ["super_moderator", "super_admin"]) {
    const jar = new Map();
    const login = await request("GET", "/admin/login");
    requireStatus(login, 200, role + " login page");
    collectCookies(login, jar);
    const before = jar.get("empk_recovery_test");
    const xsrf = jar.get("XSRF-TOKEN");
    assert.ok(before && xsrf, "Login did not set session/CSRF cookies");

    const payload = JSON.stringify({ components: [{
      snapshot: getLoginSnapshot(login.body),
      updates: { "data.email": role + "@example.test", "data.password": fixturePassword },
      calls: [{ path: "", method: "authenticate", params: [] }],
    }] });
    const signedIn = await request("POST", "/livewire/update", {
      Cookie: cookieHeader(jar), "X-XSRF-TOKEN": decodeURIComponent(xsrf),
      "X-Livewire": "true", "Content-Type": "application/json", Accept: "application/json",
    }, payload);
    requireStatus(signedIn, 200, role + " Livewire login");
    assert.match(JSON.parse(signedIn.body).components[0].effects.redirect ?? "", /\/admin\/?$/);
    collectCookies(signedIn, jar);
    assert.notEqual(jar.get("empk_recovery_test"), before);
    const existingSession = { Cookie: cookieHeader(jar) };
    assertBlocked(await request("GET", "/api/schools", existingSession), "GET", "authenticated directory");
    requireStatus(await request("GET", "/admin", existingSession), 200, role + " existing session");
  }

  console.log("PASS: " + blocked.length + " directory HTTP cases, 8 route checks, and 2 real fixture login/session flows (PHP 8.3).");
} finally {
  if (server && server.exitCode === null && server.pid) {
    // Terminate only the child started by this test.
    const exited = once(server, "exit");
    server.kill();
    await exited;
  }
  rmSync(database, { force: true });
  rmdirSync(temp);
}
