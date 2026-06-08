<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Exception\NotFoundException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;

class PhotoService {

	private const BASE_DIR = 'Vinarium/bottles';
	private const TASTINGS_DIR = 'Vinarium/tastings';
	private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
	private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

	public function __construct(
		private readonly IRootFolder $rootFolder,
	) {
	}

	/**
	 * Store an uploaded bottle photo and return its file ID.
	 *
	 * Files are written with a unique name; the authoritative reference is
	 * `vinarium_bottle.photo_file_id`. Lifecycle is decided by the caller
	 * (controller) which compares old vs new file id and calls
	 * {@see deletePhotoFileIfOrphan} when no bottle references the old file
	 * anymore.
	 *
	 * @throws \InvalidArgumentException on invalid file type/size
	 */
	public function saveBottlePhoto(string $userId, int $bottleId, string $content, string $mimeType): int {
		if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
			throw new \InvalidArgumentException('Ungültiger Dateityp. Erlaubt: JPEG, PNG, WebP, GIF.');
		}
		if (strlen($content) > self::MAX_SIZE_BYTES) {
			throw new \InvalidArgumentException('Datei zu groß. Maximum: 10 MB.');
		}

		$ext = $this->extensionForMime($mimeType);
		$dir = $this->getOrCreateDir($userId);
		$filename = 'b' . $bottleId . '_' . time() . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
		$file = $dir->newFile($filename, $content);
		return $file->getId();
	}

	/**
	 * Return the raw file content + mime for a given NC file id, restricted to
	 * files stored inside the user's Vinarium/bottles directory.
	 *
	 * @return array{content: string, mimeType: string}
	 * @throws NotFoundException
	 */
	public function serveBottlePhotoByFileId(string $userId, int $fileId): array {
		$file = $this->getBottlePhotoFile($userId, $fileId);
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
			$file = $this->getBottlePhotoFile($userId, $fileId);
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
	 * Resolve and validate that $fileId points to a file inside the user's bottles folder.
	 *
	 * @throws NotFoundException
	 */
	private function getBottlePhotoFile(string $userId, int $fileId): File {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			throw new NotFoundException('Foto nicht gefunden');
		}
		$node = $nodes[0];
		if (!$node instanceof File) {
			throw new NotFoundException('Foto nicht gefunden');
		}
		$expectedPrefix = $userFolder->getPath() . '/' . self::BASE_DIR . '/';
		if (!str_starts_with($node->getPath() . '/', $expectedPrefix)) {
			throw new NotFoundException('Foto liegt nicht im erwarteten Verzeichnis');
		}
		return $node;
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

	private function getOrCreateDir(string $userId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$path = self::BASE_DIR;
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
