#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import {
	lstat,
	mkdir,
	readFile,
	rm,
	writeFile,
} from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPT_PATH = fileURLToPath(import.meta.url);

export const ROOT_DIRECTORY = path.resolve(path.dirname(SCRIPT_PATH), '..');
export const PLUGIN_SLUG = 'aomark-listings';
export const PACKAGE_FILES = Object.freeze([
	'LICENSE',
	'aomark-listings.php',
	'aomark-real-estate.php',
	'assets/css/admin-listings.css',
	'assets/css/admin-menu.css',
	'assets/css/listings.css',
	'assets/img/aomark-icon-white.png',
	'assets/img/aomark-logo-white.png',
	'assets/js/admin-listings.js',
	'assets/js/listings.js',
	'assets/vendor/leaflet/LICENSE',
	'assets/vendor/leaflet/dist/images/layers-2x.png',
	'assets/vendor/leaflet/dist/images/layers.png',
	'assets/vendor/leaflet/dist/images/marker-icon-2x.png',
	'assets/vendor/leaflet/dist/images/marker-icon.png',
	'assets/vendor/leaflet/dist/images/marker-shadow.png',
	'assets/vendor/leaflet/dist/leaflet.css',
	'assets/vendor/leaflet/dist/leaflet.js',
	'includes/listings-admin.php',
	'includes/listings-assets.php',
	'includes/listings-elementor-controls.php',
	'includes/listings-models.php',
	'includes/listings-privacy.php',
	'includes/listings-query.php',
	'includes/listings-render.php',
	'readme.txt',
	'widgets/elementor-widgets.php',
	'widgets/listing-field.php',
	'widgets/listing-filter.php',
	'widgets/listing-gallery.php',
	'widgets/listing-map.php',
	'widgets/listing-meta.php',
	'widgets/listing-results.php',
]);

const BUILD_ROOT = path.join(ROOT_DIRECTORY, '.build');
const STAGING_DIRECTORY = path.join(BUILD_ROOT, PLUGIN_SLUG);
const DIST_DIRECTORY = path.join(ROOT_DIRECTORY, 'dist');
const ZIP_PATH = path.join(DIST_DIRECTORY, `${PLUGIN_SLUG}.zip`);
const CHECKSUM_PATH = `${ZIP_PATH}.sha256`;
const MANIFEST_PATH = path.join(DIST_DIRECTORY, `${PLUGIN_SLUG}.wporg-manifest.json`);

const FORBIDDEN_SEGMENTS = new Set([
	'.git',
	'.github',
	'.idea',
	'.vscode',
	'build',
	'coverage',
	'docs',
	'node_modules',
	'scripts',
	'tests',
]);
const FORBIDDEN_FILE_NAMES = new Set([
	'.ds_store',
	'thumbs.db',
	'wp-config.php',
]);
const FORBIDDEN_FILE_PATTERN = /(?:^|\/)\.env(?:\.|$)|\.(?:bak|log|map|sql|sqlite|swp|swo|tar|tmp|wpress|zip)(?:\.gz)?$/i;

function portable(filePath) {
	return filePath.split(path.sep).join('/');
}

function compareNames(left, right) {
	return left < right ? -1 : left > right ? 1 : 0;
}

function assertSafeRelativePath(relativePath) {
	const normalized = portable(relativePath);
	const segments = normalized.split('/');

	if (
		! normalized
		|| relativePath.includes('\\')
		|| path.posix.isAbsolute(normalized)
		|| /^[A-Za-z]:\//.test(normalized)
		|| normalized.includes('\0')
		|| segments[0].toLowerCase() === 'dist'
		|| segments.some(
			(segment) => (
				! segment
				|| segment === '.'
				|| segment === '..'
				|| segment.startsWith('.')
				|| FORBIDDEN_SEGMENTS.has(segment.toLowerCase())
			),
		)
		|| FORBIDDEN_FILE_NAMES.has(segments.at(-1).toLowerCase())
		|| FORBIDDEN_FILE_PATTERN.test(normalized)
	) {
		throw new Error(`Unsafe or development-only package path: ${normalized}`);
	}
}

/**
 * Validate a complete, file-by-file package definition.
 *
 * Exported so the artifact contract can prove that traversal and duplicate
 * entries are rejected by the same production code used by the builder.
 *
 * @param {string[]} relativePaths Package-relative source paths.
 * @returns {string[]} Validated paths in deterministic byte order.
 */
export function validatePackageDefinition(relativePaths) {
	if (! Array.isArray(relativePaths) || relativePaths.length === 0) {
		throw new Error('The release allowlist must contain at least one file.');
	}

	const exactPaths = new Set();
	const caseInsensitivePaths = new Set();

	for (const relativePath of relativePaths) {
		if (typeof relativePath !== 'string') {
			throw new TypeError('Every release allowlist entry must be a string.');
		}

		assertSafeRelativePath(relativePath);
		if (exactPaths.has(relativePath)) {
			throw new Error(`Duplicate package path: ${relativePath}`);
		}

		const foldedPath = relativePath.toLowerCase();
		if (caseInsensitivePaths.has(foldedPath)) {
			throw new Error(`Case-insensitive duplicate package path: ${relativePath}`);
		}

		exactPaths.add(relativePath);
		caseInsensitivePaths.add(foldedPath);
	}

	return [...relativePaths].sort(compareNames);
}

function assertGeneratedPath(targetPath) {
	const relativePath = path.relative(ROOT_DIRECTORY, targetPath);

	if (
		! relativePath
		|| path.isAbsolute(relativePath)
		|| relativePath === '..'
		|| relativePath.startsWith(`..${path.sep}`)
	) {
		throw new Error(`Generated path escapes the worktree: ${targetPath}`);
	}
}

async function assertRegularSourceFile(relativePath) {
	const segments = relativePath.split('/');
	let currentPath = ROOT_DIRECTORY;

	for (const segment of segments) {
		currentPath = path.join(currentPath, segment);
		const details = await lstat(currentPath);
		if (details.isSymbolicLink()) {
			throw new Error(`Symbolic links are not allowed in the release package: ${relativePath}`);
		}
	}

	const details = await lstat(path.join(ROOT_DIRECTORY, relativePath));
	if (! details.isFile()) {
		throw new Error(`Package entry must be a regular file: ${relativePath}`);
	}
}

function createCrc32Table() {
	const table = new Uint32Array(256);

	for (let index = 0; index < table.length; index += 1) {
		let value = index;
		for (let bit = 0; bit < 8; bit += 1) {
			value = (value & 1) ? (0xedb88320 ^ (value >>> 1)) : (value >>> 1);
		}
		table[index] = value >>> 0;
	}

	return table;
}

const CRC32_TABLE = createCrc32Table();

function crc32(buffer) {
	let value = 0xffffffff;

	for (const byte of buffer) {
		value = CRC32_TABLE[(value ^ byte) & 0xff] ^ (value >>> 8);
	}

	return (value ^ 0xffffffff) >>> 0;
}

function releaseDate() {
	const rawEpoch = process.env.SOURCE_DATE_EPOCH;
	if (rawEpoch === undefined || rawEpoch === '') {
		return new Date(Date.UTC(1980, 0, 1, 0, 0, 0));
	}

	if (! /^\d+$/.test(rawEpoch)) {
		throw new Error('SOURCE_DATE_EPOCH must be a non-negative integer.');
	}

	const milliseconds = Number(rawEpoch) * 1000;
	const date = new Date(milliseconds);
	if (
		! Number.isFinite(milliseconds)
		|| Number.isNaN(date.getTime())
		|| milliseconds < Date.UTC(1980, 0, 1, 0, 0, 0)
		|| milliseconds > Date.UTC(2107, 11, 31, 23, 59, 58)
	) {
		throw new Error('SOURCE_DATE_EPOCH is outside the supported ZIP timestamp range.');
	}

	return date;
}

function dosTimestamp(date) {
	return {
		date: ((date.getUTCFullYear() - 1980) << 9)
			| ((date.getUTCMonth() + 1) << 5)
			| date.getUTCDate(),
		time: (date.getUTCHours() << 11)
			| (date.getUTCMinutes() << 5)
			| Math.floor(date.getUTCSeconds() / 2),
	};
}

async function createZip(entries, outputPath) {
	if (entries.length > 0xffff) {
		throw new Error('ZIP64 is intentionally unsupported; the package has too many files.');
	}

	const timestamp = dosTimestamp(releaseDate());
	const localChunks = [];
	const centralChunks = [];
	let localOffset = 0;

	for (const entry of entries) {
		const archivePath = `${PLUGIN_SLUG}/${portable(entry.relativePath)}`;
		const fileName = Buffer.from(archivePath, 'utf8');
		const contents = await readFile(entry.absolutePath);
		const checksum = crc32(contents);
		const flags = 0x0800;
		const compressionMethod = 0;

		// Stored entries make the archive reproducible across operating systems
		// and Node/zlib patch versions while remaining a standard WordPress ZIP.
		if (
			fileName.length > 0xffff
			|| contents.length > 0xffffffff
			|| localOffset > 0xffffffff
		) {
			throw new Error(`ZIP64 would be required for ${archivePath}`);
		}

		const localHeader = Buffer.alloc(30);
		localHeader.writeUInt32LE(0x04034b50, 0);
		localHeader.writeUInt16LE(20, 4);
		localHeader.writeUInt16LE(flags, 6);
		localHeader.writeUInt16LE(compressionMethod, 8);
		localHeader.writeUInt16LE(timestamp.time, 10);
		localHeader.writeUInt16LE(timestamp.date, 12);
		localHeader.writeUInt32LE(checksum, 14);
		localHeader.writeUInt32LE(contents.length, 18);
		localHeader.writeUInt32LE(contents.length, 22);
		localHeader.writeUInt16LE(fileName.length, 26);
		localHeader.writeUInt16LE(0, 28);
		localChunks.push(localHeader, fileName, contents);

		const centralHeader = Buffer.alloc(46);
		centralHeader.writeUInt32LE(0x02014b50, 0);
		centralHeader.writeUInt16LE(0x0314, 4);
		centralHeader.writeUInt16LE(20, 6);
		centralHeader.writeUInt16LE(flags, 8);
		centralHeader.writeUInt16LE(compressionMethod, 10);
		centralHeader.writeUInt16LE(timestamp.time, 12);
		centralHeader.writeUInt16LE(timestamp.date, 14);
		centralHeader.writeUInt32LE(checksum, 16);
		centralHeader.writeUInt32LE(contents.length, 20);
		centralHeader.writeUInt32LE(contents.length, 24);
		centralHeader.writeUInt16LE(fileName.length, 28);
		centralHeader.writeUInt16LE(0, 30);
		centralHeader.writeUInt16LE(0, 32);
		centralHeader.writeUInt16LE(0, 34);
		centralHeader.writeUInt16LE(0, 36);
		centralHeader.writeUInt32LE((0o100644 * 0x10000) >>> 0, 38);
		centralHeader.writeUInt32LE(localOffset, 42);
		centralChunks.push(centralHeader, fileName);

		localOffset += localHeader.length + fileName.length + contents.length;
	}

	const centralDirectory = Buffer.concat(centralChunks);
	if (
		centralDirectory.length > 0xffffffff
		|| localOffset + centralDirectory.length > 0xffffffff
	) {
		throw new Error('ZIP64 is intentionally unsupported; the central directory is too large.');
	}

	const endRecord = Buffer.alloc(22);
	endRecord.writeUInt32LE(0x06054b50, 0);
	endRecord.writeUInt16LE(0, 4);
	endRecord.writeUInt16LE(0, 6);
	endRecord.writeUInt16LE(entries.length, 8);
	endRecord.writeUInt16LE(entries.length, 10);
	endRecord.writeUInt32LE(centralDirectory.length, 12);
	endRecord.writeUInt32LE(localOffset, 16);
	endRecord.writeUInt16LE(0, 20);

	const archive = Buffer.concat([...localChunks, centralDirectory, endRecord]);
	await writeFile(outputPath, archive);
	return archive;
}

function readVersion(pluginSource, readmeSource) {
	const headerVersions = [...pluginSource.matchAll(/^\s*\*\s+Version:\s+([^\s]+)\s*$/gm)];
	const runtimeVersion = pluginSource.match(
		/define\(\s*['"]AOMARK_LISTINGS_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/,
	)?.[1];
	const stableVersion = readmeSource.match(/^Stable tag:\s*([^\s]+)\s*$/m)?.[1];

	if (
		headerVersions.length !== 1
		|| ! runtimeVersion
		|| ! stableVersion
		|| headerVersions[0][1] !== runtimeVersion
		|| headerVersions[0][1] !== stableVersion
		|| ! /^\d+\.\d+\.\d+$/.test(headerVersions[0][1])
	) {
		throw new Error('Plugin header, runtime constant, and readme stable version are not aligned.');
	}

	return headerVersions[0][1];
}

function gitHead() {
	return execFileSync(
		'git',
		[
			'-c',
			`safe.directory=${ROOT_DIRECTORY}`,
			'-C',
			ROOT_DIRECTORY,
			'rev-parse',
			'HEAD',
		],
		{
			encoding: 'utf8',
			windowsHide: true,
		},
	).trim();
}

export async function buildPlugin() {
	const relativePaths = validatePackageDefinition(PACKAGE_FILES);
	for (const relativePath of relativePaths) {
		await assertRegularSourceFile(relativePath);
	}

	const [pluginSource, readmeSource] = await Promise.all([
		readFile(path.join(ROOT_DIRECTORY, 'aomark-listings.php'), 'utf8'),
		readFile(path.join(ROOT_DIRECTORY, 'readme.txt'), 'utf8'),
	]);
	const version = readVersion(pluginSource, readmeSource);

	assertGeneratedPath(BUILD_ROOT);
	assertGeneratedPath(DIST_DIRECTORY);
	await rm(BUILD_ROOT, { recursive: true, force: true });
	await rm(DIST_DIRECTORY, { recursive: true, force: true });
	await mkdir(STAGING_DIRECTORY, { recursive: true });
	await mkdir(DIST_DIRECTORY, { recursive: true });

	const sourceFiles = [];
	for (const relativePath of relativePaths) {
		const sourcePath = path.join(ROOT_DIRECTORY, relativePath);
		const stagedPath = path.join(STAGING_DIRECTORY, relativePath);
		const contents = await readFile(sourcePath);
		await mkdir(path.dirname(stagedPath), { recursive: true });
		await writeFile(stagedPath, contents);
		sourceFiles.push({
			path: relativePath,
			bytes: contents.length,
			sha256: createHash('sha256').update(contents).digest('hex'),
		});
	}

	const entries = relativePaths.map((relativePath) => ({
		absolutePath: path.join(STAGING_DIRECTORY, relativePath),
		relativePath,
	}));
	const archive = await createZip(entries, ZIP_PATH);
	const digest = createHash('sha256').update(archive).digest('hex');
	const manifest = {
		schemaVersion: 1,
		channel: 'wordpress-org',
		slug: PLUGIN_SLUG,
		version,
		sourceCommit: gitHead(),
		package: {
			filename: path.basename(ZIP_PATH),
			files: relativePaths.length,
			bytes: archive.length,
			sha256: digest,
		},
		sourceFiles,
	};

	await writeFile(CHECKSUM_PATH, `${digest}  ${path.basename(ZIP_PATH)}\n`, 'utf8');
	await writeFile(MANIFEST_PATH, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');

	process.stdout.write(
		`Staged ${relativePaths.length} files in ${portable(path.relative(ROOT_DIRECTORY, STAGING_DIRECTORY))}\n`,
	);
	process.stdout.write(
		`Built ${portable(path.relative(ROOT_DIRECTORY, ZIP_PATH))} (${archive.length} bytes)\n`,
	);
	process.stdout.write(`SHA-256 ${digest.toUpperCase()}\n`);

	return {
		archive,
		checksumPath: CHECKSUM_PATH,
		digest,
		fileCount: relativePaths.length,
		manifest,
		manifestPath: MANIFEST_PATH,
		stagingDirectory: STAGING_DIRECTORY,
		zipPath: ZIP_PATH,
	};
}

function isMainModule() {
	if (! process.argv[1]) {
		return false;
	}

	const invokedPath = path.resolve(process.argv[1]);
	return process.platform === 'win32'
		? invokedPath.toLowerCase() === SCRIPT_PATH.toLowerCase()
		: invokedPath === SCRIPT_PATH;
}

if (isMainModule()) {
	await buildPlugin();
}
