import fs from "fs";
import path from "path";

const root = process.cwd();

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, "utf8"));
}

function updateFile(filePath, updater) {
  const current = fs.readFileSync(filePath, "utf8");
  const next = updater(current);
  if (next !== current) {
    fs.writeFileSync(filePath, next, "utf8");
  }
}

const pkgPath = path.join(root, "package.json");
const pkg = readJson(pkgPath);
const version = String(pkg.version || "").trim();

if (!/^\d+\.\d+\.\d+(-[0-9A-Za-z-.]+)?$/.test(version)) {
  throw new Error(`Invalid SemVer version in package.json: "${version}"`);
}

const pluginFile = path.join(root, "cart2chat.php");
const readmeFile = path.join(root, "readme.txt");

let pluginBefore = fs.readFileSync(pluginFile, "utf8");
if (!/(\*\s*Version:\s*)([^\r\n]+)/.test(pluginBefore)) {
  throw new Error("Could not find plugin header Version in cart2chat.php");
}
if (!/(define\('PV_PLUGIN_VERSION',\s*')([^']+)('\);)/.test(pluginBefore)) {
  throw new Error("Could not find PV_PLUGIN_VERSION constant in cart2chat.php");
}
let pluginAfter = pluginBefore
  .replace(/(\*\s*Version:\s*)([^\r\n]+)/, `$1${version}`)
  .replace(/(define\('PV_PLUGIN_VERSION',\s*')([^']+)('\);)/, `$1${version}$3`);
fs.writeFileSync(pluginFile, pluginAfter, "utf8");

let readmeBefore = fs.readFileSync(readmeFile, "utf8");
if (!/(Stable tag:\s*)([^\r\n]+)/.test(readmeBefore)) {
  throw new Error("Could not find Stable tag in readme.txt");
}
let readmeAfter = readmeBefore.replace(/(Stable tag:\s*)([^\r\n]+)/, `$1${version}`);
fs.writeFileSync(readmeFile, readmeAfter, "utf8");

console.log(`Synced plugin files to version ${version}`);
