<?php declare(strict_types=1);

/**
 * @copyright Copyright 2021 Carl Schwan <carl@carlschwan.eu>
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

namespace OCA\MonthlyStatusEmail\Command;

use OCP\IUserManager;
use OCP\IUser;
use OC\Core\Command\Base;
use OCA\MonthlyStatusEmail\Service\MailSender;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendAllMail extends Base {
	/** @var IUserManager $userManager */
	private $userManager;
	/** @var NotificationTrackerService */
	private $service;
	/** @var MailSender */
	private $mailSender;

	public function __construct(
		NotificationTrackerService $service,
		IUserManager $userManager,
		MailSender $mailSender
	) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->service = $service;
		$this->mailSender = $mailSender;
	}

	protected function configure() {
		$this
						->setName('monthly_status_email:send-all')
						->setDescription('Send the notification mail to all users');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->userManager->callForSeenUsers(function (IUser $user) use ($output) {
			$trackedNotification = $this->service->find($user->getUID());

			$to = $user->getEMailAddress();
			if ($to === null) {
				// We don't have any email address, not sure what to do here.
				$output->writeln('<error>User doesn\'t have an email address</error>');
				return;
			}
			$ret = $this->mailSender->sendMonthlyMailTo($trackedNotification);
			if ($ret) {
				$output->writeln('Email sent to ' . ($user->getDisplayName() ?? $user->getUID()));
			} else {
				$output->writeln('Failure sending email to ' . $user->getDisplayName());
			}
		});
		return 0;
	}
}