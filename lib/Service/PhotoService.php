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
use OCP\IURLGenerator;

class PhotoService {

	private const BASE_DIR = 'Vinarium/bottles';
	private const TASTINGS_DIR = 'Vinarium/tastings';
	private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
	private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * Store an uploaded bottle photo and return its file ID.
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
		$filename = $bottleId . '.' . $ext;

		// Remove any existing photo for this bottle
		$this->removeExistingPhotos($dir, $bottleId);

		$file = $dir->newFile($filename, $content);
		return $file->getId();
	}

	/**
	 * Delete the photo stored for a bottle (by iterating known extensions).
	 */
	public function deleteBottlePhoto(string $userId, int $bottleId): void {
		try {
			$dir = $this->getDir($userId);
		} catch (NotFoundException) {
			return;
		}
		$this->removeExistingPhotos($dir, $bottleId);
	}

	/**
	 * Return a direct download URL for the bottle photo, or null if none exists.
	 */
	public function getPhotoUrl(string $userId, int $bottleId): ?string {
		try {
			$dir = $this->getDir($userId);
		} catch (NotFoundException) {
			return null;
		}
		$file = $this->findPhoto($dir, $bottleId);
		if ($file === null) {
			return null;
		}
		return $this->urlGenerator->linkToRoute(
			'vinarium.bottle.getPhoto',
			['id' => $bottleId],
		);
	}

	/**
	 * Return the raw file content and MIME type for serving.
	 *
	 * @return array{content: string, mimeType: string}
	 */
	public function serveBottlePhoto(string $userId, int $bottleId): array {
		try {
			$dir = $this->getDir($userId);
		} catch (NotFoundException) {
			throw new NotFoundException('Kein Foto vorhanden');
		}
		$file = $this->findPhoto($dir, $bottleId);
		if ($file === null) {
			throw new NotFoundException('Kein Foto vorhanden');
		}
		return [
			'content' => $file->getContent(),
			'mimeType' => $file->getMimeType(),
		];
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

	private function getDir(string $userId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		try {
			$node = $userFolder->get(self::BASE_DIR);
			if ($node instanceof Folder) {
				return $node;
			}
		} catch (FilesNotFoundException) {
		}
		throw new NotFoundException('Foto-Verzeichnis nicht gefunden');
	}

	private function findPhoto(Folder $dir, int $bottleId): ?File {
		foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
			try {
				$node = $dir->get($bottleId . '.' . $ext);
				if ($node instanceof File) {
					return $node;
				}
			} catch (FilesNotFoundException) {
				// try next
			}
		}
		return null;
	}

	private function removeExistingPhotos(Folder $dir, int $bottleId): void {
		foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
			try {
				$node = $dir->get($bottleId . '.' . $ext);
				$node->delete();
			} catch (FilesNotFoundException) {
				// nothing to remove
			}
		}
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
