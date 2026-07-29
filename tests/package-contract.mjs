import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

import {
	PACKAGE_FILES,
	PLUGIN_SLUG,
	ROOT_DIRECTORY,
	buildPlugin,
	validatePackageDefinition,
} from '../scripts/build-plugin.mjs';

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

function assertSafeArchiveName(name) {
	assert.ok(! name.includes('\0'), `NUL byte in ZIP path: ${name}`);
	assert.ok(! name.includes('\\'), `backslash in ZIP path: ${name}`);
	assert.ok(! name.startsWith('/'), `absolute ZIP path: ${name}`);
	assert.ok(! /^[A-Za-z]:/.test(name), `drive-qualified ZIP path: ${name}`);

	const segments = name.split('/');
	assert.ok(
		segments.every((segment) => segment && segment !== '.' && segment !== '..'),
		`unsafe ZIP path: ${name}`,
	);
}

function assertUniqueArchiveNames(names) {
	assert.equal(new Set(names).size, names.length, 'duplicate ZIP entry');
	assert.equal(
		new Set(names.map((name) => name.toLowerCase())).size,
		names.length,
		'case-insensitive duplicate ZIP entry',
	);
}

function parseZip(archive) {
	assert.ok(archive.length >= 22, 'ZIP is too short');

	const endOffset = archive.length - 22;
	assert.equal(archive.readUInt32LE(endOffset), 0x06054b50, 'ZIP has no deterministic EOCD');
	assert.equal(archive.readUInt16LE(endOffset + 4), 0, 'multi-disk ZIP is not allowed');
	assert.equal(archive.readUInt16LE(endOffset + 6), 0, 'multi-disk ZIP is not allowed');
	assert.equal(archive.readUInt16LE(endOffset + 20), 0, 'ZIP comments are not allowed');

	const diskEntries = archive.readUInt16LE(endOffset + 8);
	const entryCount = archive.readUInt16LE(endOffset + 10);
	const centralSize = archive.readUInt32LE(endOffset + 12);
	const centralOffset = archive.readUInt32LE(endOffset + 16);
	assert.equal(diskEntries, entryCount, 'central directory spans multiple disks');
	assert.equal(centralOffset + centralSize, endOffset, 'central directory boundaries are invalid');

	const entries = [];
	let offset = centralOffset;
	let previousLocalOffset = -1;

	for (let index = 0; index < entryCount; index += 1) {
		assert.equal(archive.readUInt32LE(offset), 0x02014b50, `invalid central entry ${index}`);

		const flags = archive.readUInt16LE(offset + 8);
		const method = archive.readUInt16LE(offset + 10);
		const modifiedTime = archive.readUInt16LE(offset + 12);
		const modifiedDate = archive.readUInt16LE(offset + 14);
		const checksum = archive.readUInt32LE(offset + 16);
		const compressedSize = archive.readUInt32LE(offset + 20);
		const uncompressedSize = archive.readUInt32LE(offset + 24);
		const nameLength = archive.readUInt16LE(offset + 28);
		const extraLength = archive.readUInt16LE(offset + 30);
		const commentLength = archive.readUInt16LE(offset + 32);
		const localOffset = archive.readUInt32LE(offset + 42);
		const nameStart = offset + 46;
		const name = archive.subarray(nameStart, nameStart + nameLength).toString('utf8');

		assert.equal(flags, 0x0800, `unexpected ZIP flags for ${name}`);
		assert.equal(method, 0, `only deterministic stored entries are allowed: ${name}`);
		assert.equal(compressedSize, uncompressedSize, `stored entry size mismatch: ${name}`);
		assert.equal(extraLength, 0, `central extra data is not allowed: ${name}`);
		assert.equal(commentLength, 0, `entry comments are not allowed: ${name}`);
		assert.ok(localOffset > previousLocalOffset, `local entries are not strictly ordered: ${name}`);
		assertSafeArchiveName(name);

		assert.equal(archive.readUInt32LE(localOffset), 0x04034b50, `missing local entry for ${name}`);
		const localFlags = archive.readUInt16LE(localOffset + 6);
		const localMethod = archive.readUInt16LE(localOffset + 8);
		const localModifiedTime = archive.readUInt16LE(localOffset + 10);
		const localModifiedDate = archive.readUInt16LE(localOffset + 12);
		const localChecksum = archive.readUInt32LE(localOffset + 14);
		const localCompressedSize = archive.readUInt32LE(localOffset + 18);
		const localUncompressedSize = archive.readUInt32LE(localOffset + 22);
		const localNameLength = archive.readUInt16LE(localOffset + 26);
		const localExtraLength = archive.readUInt16LE(localOffset + 28);
		const localNameStart = localOffset + 30;
		const localName = archive
			.subarray(localNameStart, localNameStart + localNameLength)
			.toString('utf8');
		const dataStart = localNameStart + localNameLength + localExtraLength;
		const dataEnd = dataStart + localCompressedSize;
		const contents = archive.subarray(dataStart, dataEnd);

		assert.equal(localName, name, `local/central filename mismatch for ${name}`);
		assert.equal(localFlags, flags, `local/central flags mismatch for ${name}`);
		assert.equal(localMethod, method, `local/central method mismatch for ${name}`);
		assert.equal(localModifiedTime, modifiedTime, `local/central time mismatch for ${name}`);
		assert.equal(localModifiedDate, modifiedDate, `local/central date mismatch for ${name}`);
		assert.equal(localChecksum, checksum, `local/central CRC mismatch for ${name}`);
		assert.equal(localCompressedSize, compressedSize, `local/central compressed size mismatch for ${name}`);
		assert.equal(localUncompressedSize, uncompressedSize, `local/central size mismatch for ${name}`);
		assert.equal(localExtraLength, 0, `local extra data is not allowed: ${name}`);
		assert.ok(dataEnd <= centralOffset, `entry overlaps the central directory: ${name}`);
		assert.equal(crc32(contents), checksum, `content CRC mismatch for ${name}`);

		entries.push({
			contents,
			modifiedDate,
			modifiedTime,
			name,
		});
		previousLocalOffset = localOffset;
		offset += 46 + nameLength + extraLength + commentLength;
	}

	assert.equal(offset, endOffset, 'central directory entry count is inconsistent');
	assertUniqueArchiveNames(entries.map(({ name }) => name));
	return entries;
}

function sorted(values) {
	return [...values].sort((left, right) => (left < right ? -1 : left > right ? 1 : 0));
}

function normalizeLicense(value) {
	return String(value ?? '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

function assertGplV2OrLater(value, location) {
	assert.ok(
		new Set(['gplv2orlater', 'gpl2orlater', 'gpl20orlater']).has(normalizeLicense(value)),
		`${location} must declare GPLv2 or later`,
	);
}

test('production allowlist validation rejects traversal and duplicate paths', () => {
	const invalidPaths = [
		'../escape.php',
		'assets/../../escape.php',
		'/absolute.php',
		'C:/absolute.php',
		'assets\\windows-path.php',
		'.github/workflows/release.yml',
		'dist/release.zip.sha256',
		'Docs/internal.md',
		'THUMBS.DB',
		'docs/internal.md',
		'nested/.env',
		'nested/archive.zip',
	];

	for (const invalidPath of invalidPaths) {
		assert.throws(
			() => validatePackageDefinition([invalidPath]),
			undefined,
			`builder accepted unsafe path ${invalidPath}`,
		);
	}

	assert.throws(
		() => validatePackageDefinition(['readme.txt', 'readme.txt']),
		/Duplicate package path/,
	);
	assert.throws(
		() => validatePackageDefinition(['readme.txt', 'README.TXT']),
		/Case-insensitive duplicate package path/,
	);
});

test('exact WordPress.org ZIP is deterministic, lean, licensed, and updater-free', async () => {
	const packageJson = JSON.parse(
		await readFile(path.join(ROOT_DIRECTORY, 'package.json'), 'utf8'),
	);
	assert.equal(packageJson.private, true, 'tooling package must never be published to npm');
	assert.equal(packageJson.name, PLUGIN_SLUG, 'tooling package name must match the plugin slug');
	assert.deepEqual(packageJson.files, PACKAGE_FILES, 'npm and ZIP allowlists must be identical');

	const firstBuild = await buildPlugin();
	const firstArchive = Buffer.from(firstBuild.archive);
	const secondBuild = await buildPlugin();
	const archive = await readFile(secondBuild.zipPath);

	assert.deepEqual(archive, firstArchive, 'repeated builds must be byte-for-byte identical');
	assert.equal(secondBuild.digest, firstBuild.digest, 'repeated builds must have one SHA-256');

	const entries = parseZip(archive);
	const names = entries.map(({ name }) => name);
	const expectedNames = PACKAGE_FILES.map((name) => `${PLUGIN_SLUG}/${name}`);

	assert.equal(PACKAGE_FILES.length, 32, 'the reviewed lean allowlist must remain exactly 32 files');
	assert.equal(entries.length, PACKAGE_FILES.length);
	assert.deepEqual(sorted(names), sorted(expectedNames));
	assert.deepEqual(names, sorted(expectedNames), 'ZIP entries must use deterministic byte order');
	assert.ok(
		names.every((name) => name.startsWith(`${PLUGIN_SLUG}/`)),
		'every entry must use one plugin wrapper',
	);
	assert.ok(names.every((name) => ! name.endsWith('/')), 'directory-only ZIP entries are not allowed');
	assert.equal(
		new Set(entries.map(({ modifiedDate, modifiedTime }) => `${modifiedDate}:${modifiedTime}`)).size,
		1,
		'every ZIP entry must use one fixed release timestamp',
	);

	const forbiddenPathPattern = /(?:^|\/)(?:\.git|\.github|build|coverage|docs|node_modules|scripts|tests)(?:\/|$)|^aomark-listings\/dist(?:\/|$)|(?:^|\/)(?:README\.md|package(?:-lock)?\.json)$/i;
	const forbiddenFilePattern = /(?:^|\/)\.env(?:\.|$)|\.(?:bak|log|map|sql|sqlite|swp|swo|tar|tmp|wpress|zip)(?:\.gz)?$/i;
	for (const name of names) {
		assert.doesNotMatch(name, forbiddenPathPattern, `development path leaked into ZIP: ${name}`);
		assert.doesNotMatch(name, forbiddenFilePattern, `generated or secret file leaked into ZIP: ${name}`);
	}

	for (const entry of entries) {
		const relativePath = entry.name.slice(`${PLUGIN_SLUG}/`.length);
		const source = await readFile(path.join(ROOT_DIRECTORY, relativePath));
		assert.deepEqual(entry.contents, source, `ZIP bytes differ from source: ${relativePath}`);
	}

	const textExtensions = new Set(['', '.css', '.js', '.php', '.txt']);
	const textEntries = entries.filter(({ name }) => (
		textExtensions.has(path.extname(name).toLowerCase())
	));
	const distributableText = textEntries
		.map(({ contents, name }) => `${name}\n${contents.toString('utf8')}`)
		.join('\n');
	const phpText = textEntries
		.filter(({ name }) => name.endsWith('.php'))
		.map(({ contents }) => contents.toString('utf8'))
		.join('\n');

	for (const pattern of [
		/plugin-update-checker/i,
		/YahnisElsts/i,
		/PucFactory/i,
		/buildUpdateChecker/i,
		/site_transient_update_plugins/i,
		/pre_set_site_transient_update_plugins/i,
		/external_updates-/i,
		/\/api\/plugin\/(?:latest|download)/i,
	]) {
		assert.doesNotMatch(distributableText, pattern);
	}
	assert.doesNotMatch(
		phpText,
		/add_(?:filter|action)\s*\(\s*['"](?:plugins_api|upgrader_source_selection|http_request_args)['"]/i,
		'private update-channel hooks are not allowed in the WordPress.org ZIP',
	);
	assert.doesNotMatch(
		distributableText,
		/veltom|linkbuilding\.rs|bvq\.snh\.mybluehost\.me/i,
		'client-specific or QA-host traces are not allowed in the release ZIP',
	);

	const mainSource = entries
		.find(({ name }) => name === `${PLUGIN_SLUG}/aomark-real-estate.php`)
		?.contents.toString('utf8') ?? '';
	const readmeSource = entries
		.find(({ name }) => name === `${PLUGIN_SLUG}/readme.txt`)
		?.contents.toString('utf8') ?? '';
	const licenseSource = entries
		.find(({ name }) => name === `${PLUGIN_SLUG}/LICENSE`)
		?.contents.toString('utf8') ?? '';
	const leafletLicense = entries
		.find(({ name }) => name === `${PLUGIN_SLUG}/assets/vendor/leaflet/LICENSE`)
		?.contents.toString('utf8') ?? '';

	const headerVersions = [...mainSource.matchAll(/^\s*\*\s+Version:\s+([^\s]+)\s*$/gm)];
	const runtimeVersion = mainSource.match(
		/define\(\s*['"]AOMARK_LISTINGS_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/,
	)?.[1];
	const stableVersion = readmeSource.match(/^Stable tag:\s*([^\s]+)\s*$/m)?.[1];
	const contributors = readmeSource
		.match(/^Contributors:\s*(.+?)\s*$/m)?.[1]
		.split(',')
		.map((contributor) => contributor.trim().toLowerCase()) ?? [];
	assert.equal(headerVersions.length, 1, 'main plugin file must contain one Version header');
	assert.match(headerVersions[0][1], /^\d+\.\d+\.\d+$/, 'release version must be semantic');
	assert.equal(runtimeVersion, headerVersions[0][1], 'runtime version must match plugin header');
	assert.equal(stableVersion, headerVersions[0][1], 'stable tag must match plugin header');
	assert.ok(contributors.includes('alex2703'), 'readme must credit the WordPress.org owner');
	assert.doesNotMatch(mainSource, /^\s*\*\s+Update URI:/mi);
	assert.match(mainSource, /^\s*\*\s+Text Domain:\s+aomark-listings\s*$/m);

	const pluginLicense = mainSource.match(/^\s*\*\s+License:\s*(.+?)\s*$/m)?.[1];
	const readmeLicense = readmeSource.match(/^License:\s*(.+?)\s*$/m)?.[1];
	assertGplV2OrLater(pluginLicense, 'plugin header');
	assertGplV2OrLater(readmeLicense, 'readme.txt');
	assert.match(licenseSource, /GNU GENERAL PUBLIC LICENSE/i);
	assert.match(licenseSource, /Version 2, June 1991/i);
	assert.match(leafletLicense, /BSD 2-Clause License/i);
	assert.match(leafletLicense, /Volodymyr Agafonkin/i);
	assert.match(leafletLicense, /CloudMade/i);
	assert.match(
		readmeSource,
		/https:\/\/unpkg\.com\/leaflet@1\.9\.4\/dist\/leaflet-src\.js/i,
		'readme must identify the human-readable source for bundled Leaflet 1.9.4',
	);
	assert.match(
		readmeSource,
		/https:\/\/github\.com\/Leaflet\/Leaflet\/tree\/v1\.9\.4/i,
		'readme must identify the corresponding Leaflet source repository and tag',
	);

	assert.equal(secondBuild.fileCount, PACKAGE_FILES.length);
	assert.equal(secondBuild.manifest.channel, 'wordpress-org');
	assert.equal(secondBuild.manifest.slug, PLUGIN_SLUG);
	assert.equal(secondBuild.manifest.version, headerVersions[0][1]);
	assert.match(secondBuild.manifest.sourceCommit, /^[a-f0-9]{40}$/);
	assert.equal(secondBuild.manifest.package.filename, `${PLUGIN_SLUG}.zip`);
	assert.equal(secondBuild.manifest.package.files, PACKAGE_FILES.length);
	assert.equal(secondBuild.manifest.package.bytes, archive.length);
	assert.equal(
		secondBuild.manifest.package.sha256,
		createHash('sha256').update(archive).digest('hex'),
	);
	assert.deepEqual(
		secondBuild.manifest.sourceFiles.map(({ path: sourcePath }) => sourcePath),
		sorted(PACKAGE_FILES),
	);

	for (const sourceFile of secondBuild.manifest.sourceFiles) {
		const contents = await readFile(path.join(ROOT_DIRECTORY, sourceFile.path));
		assert.equal(sourceFile.bytes, contents.length);
		assert.equal(
			sourceFile.sha256,
			createHash('sha256').update(contents).digest('hex'),
		);
	}

	const checksumText = await readFile(secondBuild.checksumPath, 'utf8');
	assert.equal(
		checksumText,
		`${secondBuild.digest}  ${PLUGIN_SLUG}.zip\n`,
		'checksum sidecar must identify the exact upload ZIP',
	);
	const persistedManifest = JSON.parse(
		await readFile(secondBuild.manifestPath, 'utf8'),
	);
	assert.deepEqual(persistedManifest, secondBuild.manifest);
	assert.ok(
		! names.includes(`${PLUGIN_SLUG}/${path.basename(secondBuild.manifestPath)}`),
		'release manifest must remain outside the upload ZIP',
	);
});
