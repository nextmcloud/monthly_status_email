<?php
/**
 * @copyright Copyright (c) 2021 Carl Schwan <carl@carlschwan.eu>
 *
 * @author Carl Schwan <carl@carlschwan.eu>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Tests;

use OCA\MonthlyStatusEmail\Db\NotificationTracker;
use OCA\MonthlyStatusEmail\Service\ClientDetector;
use OCA\MonthlyStatusEmail\Service\MailSender;
use OCA\MonthlyStatusEmail\Service\MessageProvider;
use OCA\MonthlyStatusEmail\Service\NoFileUploadedDetector;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCA\MonthlyStatusEmail\Service\StorageInfoProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class MailSenderTest extends TestCase {
	/**
	 * @var NotificationTrackerService|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $service;
	/**
	 * @var IMailer|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mailer;
	/**
	 * @var MessageProvider|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $provider;
	/**
	 * @var StorageInfoProvider|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $storageInfoProvider;
	/**
	 * @var ClientDetector|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $clientDetector;
	/**
	 * @var NoFileUploadedDetector|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $noFileUploadedDetector;
	/**
	 * @var IManager|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $shareManager;
	/**
	 * @var IUserManager|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $userManager;
	/**
	 * @var MailSender
	 */
	private $mailSender;
	/**
	 * @var NotificationTracker
	 */
	private $trackedNotification;
	/**
	 * @var IUser|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $user;
	/**
	 * @var IEMailTemplate|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $template;

	public function setUp(): void {
		parent::setUp();
		$config = \OC::$server->getConfig();
		$this->mailer = $this->createMock(IMailer::class);
		$this->service = $this->createMock(NotificationTrackerService::class);
		$this->config = $config;
		$this->container = $this->createMock(IServerContainer::class);
		$this->provider = $this->createMock(MessageProvider::class);
		$this->container->expects($this->any())
			->method('get')
			->willReturnCallback(function ($class) {
					return $this->provider;
			});
		$this->userManager = $this->createMock(IUserManager::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->clientDetector = $this->createMock(ClientDetector::class);
		$this->noFileUploadedDetector = $this->createMock(NoFileUploadedDetector::class);
		$this->storageInfoProvider = $this->createMock(StorageInfoProvider::class);

		$this->mailSender = new MailSender(
			$this->service,
			$this->userManager,
			$this->config,
			$this->mailer,
			$this->createMock(IL10N::class),
			$this->shareManager,
			$this->clientDetector,
			$this->createMock(LoggerInterface::class),
			$this->noFileUploadedDetector,
			$this->storageInfoProvider,
			$this->container
		);

		$message = $this->createMock(IMessage::class);
		$this->mailer->expects($this->once())
			->method('createMessage')
			->willReturn($message);

		$this->trackedNotification = new NotificationTracker();
		$this->trackedNotification->setOptedOut(false);
		$this->trackedNotification->setUserId('user1');
		$this->trackedNotification->setSecretToken('rere');
		$this->trackedNotification->setLastSendNotification(time());
		$this->trackedNotification->setFirstTimeSent(false);

		$this->user = $this->createMock(IUser::class);
		$this->user->expects($this->once())
			->method('getEmailAddress')
			->willReturn('user1@corp.corp');

		$this->userManager->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($this->user);

		$this->template = $this->createMock(IEMailTemplate::class);

		$this->mailer->expects($this->once())
			->method('createEmailTemplate')
			->withAnyParameters()
			->willReturn($this->template);

		$message->expects($this->once())
			->method('useTemplate')
			->with($this->template);
	}

	public function testStorageEmpty() {
		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 100,
				'relative' => 100,
			]);

		$this->provider->expects($this->once())
			->method('writeStorageFull')
			->withAnyParameters();
		$this->mailSender->sendMonthlyMailTo($this->trackedNotification);
	}

	public function testStorageWarning() {
		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 95,
				'relative' => 95,
			]);

		$this->provider->expects($this->once())
			->method('writeStorageWarning')
			->withAnyParameters();
		$this->mailSender->sendMonthlyMailTo($this->trackedNotification);
	}

	public function testStorageSafe() {
		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 50,
				'relative' => 50,
			]);

		$this->provider->expects($this->once())
			->method('writeStorageSpaceLeft')
			->withAnyParameters();

		$this->noFileUploadedDetector->expects($this->once())
			->method('hasNotUploadedFiles')
			->with($this->user)
			->willReturn(true);

		$this->provider->expects($this->once())
			->method('writeGenericMessage')
			->with($this->template, $this->user, MessageProvider::NO_FILE_UPLOAD);

			$this->shareManager->expects($this->atLeastOnce())
			->method('getSharesBy')
			->willReturn([0]);

		$this->mailSender->sendMonthlyMailTo($this->trackedNotification);
	}

	public function testShare() {
		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 50,
				'relative' => 50,
			]);

		$this->provider->expects($this->once())
			->method('writeStorageSpaceLeft')
			->withAnyParameters();

		$this->noFileUploadedDetector->expects($this->once())
			->method('hasNotUploadedFiles')
			->with($this->user)
			->willReturn(false);

		$this->shareManager->expects($this->atLeastOnce())
			->method('getSharesBy')
			->willReturn([0]);

		$this->provider->expects($this->once())
			->method('writeShareMessage');

		$this->mailSender->sendMonthlyMailTo($this->trackedNotification);
	}
}
