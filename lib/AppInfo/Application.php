<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCA\Vinarium\Listener\UserDeletedListener;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'vinarium';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Ohne diesen Listener ueberleben Keller- und Weindaten ihren Besitzer:
		// nach `occ user:delete` bleiben sie verwaist in der Datenbank liegen (#212).
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
