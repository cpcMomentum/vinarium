<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Db\Vintage;
use OCA\Vinarium\Exception\NotFoundException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;

class PhotoService {

	private const LABELS_DIR = 'Vinarium/labels';
	/**
	 * Where label photos lived while they hung on the bottle (before #190). Still
	 * accepted when serving and deleting so existing photos keep working; the
	 * migration rewrites the database references but moves no files.
	 */
	private const LEGACY_BOTTLES_DIR = 'Vinarium/bottles';
	private const TASTINGS_DIR = 'Vinarium/tastings';
	private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
	private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

	public function __construct(
		private readonly IRootFolder $rootFolder,
	) {
	}

	/**
	 * Store an uploaded label photo for one side of a vintage and return its file ID.
	 *
	 * Files are written with a unique, self-describing name; the authoritative
	 * reference is `vinarium_vintage.photo_{side}_file_id`. Lifecycle is decided
	 * by the caller, which compares old vs new file id and calls
	 * {@see deletePhotoFile} once no row references the old file anymore.
	 *
	 * @throws \InvalidArgumentException on invalid file type/size/side
	 */
	public function saveVintagePhoto(string $userId, int $vintageId, string $side, string $content, string $mimeType): int {
		if (!in_array($side, Vintage::PHOTO_SIDES, true)) {
			throw new \InvalidArgumentException('Ungültige Etikettenseite.');
		}
		if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
			throw new \InvalidArgumentException('Ungültiger Dateityp. Erlaubt: JPEG, PNG, WebP, GIF.');
		}
		if (strlen($content) > self::MAX_SIZE_BYTES) {
			throw new \InvalidArgumentException('Datei zu groß. Maximum: 10 MB.');
		}

		$ext = $this->extensionForMime($mimeType);
		$dir = $this->getOrCreateLabelsDir($userId);
		$filename = 'v' . $vintageId . '_' . $side . '_' . time() . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
		$file = $dir->newFile($filename, $content);
		return $file->getId();
	}

	/**
	 * Return the raw file content + mime for a given NC file id, restricted to the
	 * user's label directories.
	 *
	 * @return array{content: string, mimeType: string}
	 * @throws NotFoundException
	 */
	public function serveLabelPhotoByFileId(string $userId, int $fileId): array {
		$file = $this->getLabelPhotoFile($userId, $fileId);
		return [
			'content' => $file->getContent(),
			'mimeType' => $file->getMimeType(),
		];
	}

	/**
	 * Delete the physical file iff the caller already cleared all DB references.
	 * Returns true when the file was deleted.
	 */
	public function deletePhotoFile(string $userId, int $fileId): bool {
		try {
			$file = $this->getLabelPhotoFile($userId, $fileId);
		} catch (NotFoundException) {
			return false;
		}
		$file->delete();
		return true;
	}

	// --- Tasting photos (multiple per tasting, stored in Vinarium/tastings/{tastingId}/) ---

	/**
	 * Save a tasting photo and return its NC file ID.
	 *
	 * @throws \InvalidArgumentException on invalid type/size
	 */
	public function saveTastingPhoto(string $userId, int $tastingId, string $content, string $mimeType): int {
		if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
			throw new \InvalidArgumentException('Ungültiger Dateityp. Erlaubt: JPEG, PNG, WebP, GIF.');
		}
		if (strlen($content) > self::MAX_SIZE_BYTES) {
			throw new \InvalidArgumentException('Datei zu groß. Maximum: 10 MB.');
		}

		$ext = $this->extensionForMime($mimeType);
		$dir = $this->getOrCreateTastingDir($userId, $tastingId);
		$filename = time() . '_' . uniqid() . '.' . $ext;
		$file = $dir->newFile($filename, $content);
		return $file->getId();
	}

	/**
	 * Delete all photos for a tasting (the entire tasting subfolder).
	 */
	public function deleteTastingFolder(string $userId, int $tastingId): void {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$path = self::TASTINGS_DIR . '/' . $tastingId;
		try {
			$node = $userFolder->get($path);
			$node->delete();
		} catch (FilesNotFoundException) {
			// nothing to do
		}
	}

	/**
	 * Delete a specific tasting photo by its NC file ID.
	 * Verifies the file belongs to the expected tasting folder.
	 */
	public function deleteTastingPhoto(string $userId, int $tastingId, int $fileId): void {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			return;
		}
		$node = $nodes[0];
		if (!$node instanceof File) {
			throw new \InvalidArgumentException('Expected a file node');
		}
		$expectedPrefix = $userFolder->getPath() . '/' . self::TASTINGS_DIR . '/' . $tastingId . '/';
		if (!str_starts_with($node->getPath() . '/', $expectedPrefix)) {
			throw new \InvalidArgumentException('File does not belong to this tasting');
		}
		$node->delete();
	}

	// --- Helpers ---

	/**
	 * Resolve and validate that $fileId points to a file inside one of the user's
	 * label directories.
	 *
	 * Both the current directory and the pre-#190 bottles directory are accepted:
	 * the migration rewrote the database references but left every file in place,
	 * so photos taken before the move still resolve here.
	 *
	 * @throws NotFoundException
	 */
	private function getLabelPhotoFile(string $userId, int $fileId): File {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			throw new NotFoundException('Foto nicht gefunden');
		}
		$node = $nodes[0];
		if (!$node instanceof File) {
			throw new NotFoundException('Foto nicht gefunden');
		}
		$path = $node->getPath() . '/';
		foreach ([self::LABELS_DIR, self::LEGACY_BOTTLES_DIR] as $dir) {
			if (str_starts_with($path, $userFolder->getPath() . '/' . $dir . '/')) {
				return $node;
			}
		}
		throw new NotFoundException('Foto liegt nicht im erwarteten Verzeichnis');
	}

	private function getOrCreateTastingDir(string $userId, int $tastingId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$path = self::TASTINGS_DIR . '/' . $tastingId;
		try {
			$node = $userFolder->get($path);
			if ($node instanceof Folder) {
				return $node;
			}
		} catch (FilesNotFoundException) {
			// create below
		}
		return $userFolder->newFolder($path);
	}

	private function getOrCreateLabelsDir(string $userId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$path = self::LABELS_DIR;
		try {
			$node = $userFolder->get($path);
			if ($node instanceof Folder) {
				return $node;
			}
		} catch (FilesNotFoundException) {
			// create below
		}
		return $userFolder->newFolder($path);
	}

	private function extensionForMime(string $mime): string {
		return match ($mime) {
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
			default      => 'jpg',
		};
	}
}
