<?php
/**
 * ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2026
 */

namespace OCA\QuickNotes\Service;

use OCA\QuickNotes\Db\AttachMapper;
use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Db\NoteShareMapper;
use OCA\QuickNotes\Db\NoteStateMapper;
use OCA\QuickNotes\Db\NoteTagMapper;
use OCA\QuickNotes\Exception\ForbiddenException;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The permission model of sharing.
 *
 * Which is to say: the only thing standing between a note and somebody it was
 * never meant for, now that a share can write back and the queries no longer
 * filter everything by owner.
 */
class ShareServiceTest extends TestCase {

	private $shareService;
	private $noteShareMapper;
	private $noteMapper;
	private $noteStateMapper;
	private $noteTagMapper;
	private $attachMapper;
	private $userManager;
	private $groupManager;
	private $config;
	private $collaboratorSearch;
	private $notificationManager;
	private $timeFactory;

	protected function setUp(): void {
		$this->noteShareMapper = $this->createMock(NoteShareMapper::class);
		$this->noteMapper = $this->createMock(NoteMapper::class);
		$this->noteStateMapper = $this->createMock(NoteStateMapper::class);
		$this->noteTagMapper = $this->createMock(NoteTagMapper::class);
		$this->attachMapper = $this->createMock(AttachMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->collaboratorSearch = $this->createMock(ISearch::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$this->timeFactory->method('getTime')->willReturn(1700000000);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('@1700000000'));

		$notification = $this->createMock(INotification::class);
		$notification->method($this->anything())->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);

		$this->shareService = new ShareService(
			$this->noteShareMapper,
			$this->noteMapper,
			$this->noteStateMapper,
			$this->noteTagMapper,
			$this->attachMapper,
			$this->userManager,
			$this->groupManager,
			$this->config,
			$this->collaboratorSearch,
			$this->notificationManager,
			$this->timeFactory,
			$this->createMock(LoggerInterface::class)
		);
	}

	private function makeNote(int $id = 7, string $owner = 'alice'): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId($owner);
		$note->setTitle('A title');
		$note->setContent('A content');
		return $note;
	}

	private function makeShare(int $type, string $with, int $permissions, string $owner = 'alice'): NoteShare {
		$share = new NoteShare();
		$share->setNoteId(7);
		$share->setShareType($type);
		$share->setShareWith($with);
		$share->setPermissions($permissions);
		$share->setUidOwner($owner);
		$share->setUidInitiator($owner);
		return $share;
	}

	/** Nobody in any group, unless a test says otherwise. */
	private function userIsIn(string $userId, array $groupIds): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$user->method('getDisplayName')->willReturn(ucfirst($userId));
		$this->userManager->method('get')->willReturn($user);
		$this->groupManager->method('getUserGroupIds')->willReturn($groupIds);
	}

	// Effective permissions -------------------------------------------------

	public function testTheOwnerCanDoEverything(): void {
		$note = $this->makeNote(7, 'alice');

		$this->noteShareMapper->expects($this->never())->method('findByNoteAndRecipient');

		$this->assertSame(
			NoteShare::PERMISSIONS_ALL,
			$this->shareService->getPermissions('alice', $note)
		);
	}

	public function testAStrangerGetsNothing(): void {
		$this->userIsIn('bob', []);
		$this->noteShareMapper->method('findByNoteAndRecipient')->willReturn([]);

		$this->assertSame(0, $this->shareService->getPermissions('bob', $this->makeNote()));
	}

	public function testAReadOnlyShareIsReadOnly(): void {
		$this->userIsIn('bob', []);
		$this->noteShareMapper->method('findByNoteAndRecipient')->willReturn([
			$this->makeShare(NoteShare::TYPE_USER, 'bob', NoteShare::PERMISSION_READ),
		]);

		$permissions = $this->shareService->getPermissions('bob', $this->makeNote());

		$this->assertSame(NoteShare::PERMISSION_READ, $permissions);
		$this->assertSame(0, $permissions & NoteShare::PERMISSION_UPDATE);
	}

	/**
	 * Two shares can reach the same person — one made with them, one with a
	 * group they are in — and the generous one wins, the way the server
	 * resolves its own overlapping shares.
	 */
	public function testOverlappingSharesAddUp(): void {
		$this->userIsIn('bob', ['team']);

		$shares = [
			$this->makeShare(NoteShare::TYPE_USER, 'bob', NoteShare::PERMISSION_READ),
			$this->makeShare(NoteShare::TYPE_GROUP, 'team', NoteShare::PERMISSION_READ | NoteShare::PERMISSION_UPDATE),
		];

		// Passing the shares in is the path a listing takes: it fetched them
		// in bulk and must not query again per note.
		$this->noteShareMapper->expects($this->never())->method('findByNoteAndRecipient');

		$this->assertSame(
			NoteShare::PERMISSION_READ | NoteShare::PERMISSION_UPDATE,
			$this->shareService->getPermissions('bob', $this->makeNote(), $shares)
		);
	}

	/** A group share only reaches the members of that group. */
	public function testAGroupShareOfAnotherGroupIsNotForYou(): void {
		$this->userIsIn('bob', ['other-team']);

		$shares = [
			$this->makeShare(NoteShare::TYPE_GROUP, 'team', NoteShare::PERMISSIONS_ALL),
		];

		$this->assertSame(0, $this->shareService->getPermissions('bob', $this->makeNote(), $shares));
	}

	public function testSharedNoteIdsAreDeduplicated(): void {
		$this->userIsIn('bob', ['team']);

		$byUser = $this->makeShare(NoteShare::TYPE_USER, 'bob', NoteShare::PERMISSION_READ);
		$byGroup = $this->makeShare(NoteShare::TYPE_GROUP, 'team', NoteShare::PERMISSION_READ);

		$this->noteShareMapper->method('findByRecipient')->willReturn([$byUser, $byGroup]);

		$this->assertSame([7], $this->shareService->getSharedNoteIds('bob'));
	}

	// Sharing ---------------------------------------------------------------

	public function testARecipientWithoutReshareCannotShare(): void {
		$this->userIsIn('bob', []);
		$this->noteShareMapper->method('findByNoteAndRecipient')->willReturn([
			$this->makeShare(NoteShare::TYPE_USER, 'bob', NoteShare::PERMISSION_READ | NoteShare::PERMISSION_UPDATE),
		]);

		$this->expectException(ForbiddenException::class);

		$this->shareService->create('bob', $this->makeNote(), NoteShare::TYPE_USER, 'carol');
	}

	public function testNobodyCanGrantMoreThanTheyHave(): void {
		$this->userIsIn('bob', []);
		$this->noteShareMapper->method('findByNoteAndRecipient')->willReturn([
			$this->makeShare(NoteShare::TYPE_USER, 'bob', NoteShare::PERMISSION_READ | NoteShare::PERMISSION_SHARE),
		]);

		$this->expectException(ForbiddenException::class);

		// Bob may pass the note on, but he cannot let carol write when he
		// cannot write himself.
		$this->shareService->create('bob', $this->makeNote(), NoteShare::TYPE_USER, 'carol',
			NoteShare::PERMISSION_READ | NoteShare::PERMISSION_UPDATE);
	}

	public function testTheOwnerCannotShareTheNoteWithThemselves(): void {
		$this->userIsIn('alice', []);

		$this->expectException(\InvalidArgumentException::class);

		$this->shareService->create('alice', $this->makeNote(7, 'alice'), NoteShare::TYPE_USER, 'alice');
	}

	public function testSharingWithAUserThatDoesNotExistIsRefused(): void {
		$this->userManager->method('userExists')->willReturn(false);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);

		$this->expectException(\InvalidArgumentException::class);

		$this->shareService->create('alice', $this->makeNote(7, 'alice'), NoteShare::TYPE_USER, 'ghost');
	}

	public function testGroupSharingCanBeDisabledByTheAdmin(): void {
		$this->config->method('getAppValue')
			->willReturnCallback(function ($app, $key, $default) {
				return $key === 'shareapi_allow_group_sharing' ? 'no' : $default;
			});

		$this->expectException(\InvalidArgumentException::class);

		$this->shareService->create('alice', $this->makeNote(7, 'alice'), NoteShare::TYPE_GROUP, 'team');
	}

	public function testTheOwnerSharesReadOnlyByDefault(): void {
		$this->userManager->method('userExists')->willReturn(true);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);
		$this->config->method('getAppValue')
			->willReturnCallback(function ($app, $key, $default) { return $default; });

		$this->noteShareMapper->method('existsByNoteAndTarget')->willReturn(false);
		$this->noteShareMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (NoteShare $share) {
				$this->assertSame(NoteShare::PERMISSIONS_DEFAULT, $share->getPermissions());
				$this->assertSame(NoteShare::PERMISSION_READ, $share->getPermissions());
				$this->assertSame('alice', $share->getUidOwner());
				$this->assertSame('alice', $share->getUidInitiator());
				$share->setId(1);
				return $share;
			});

		$share = $this->shareService->create('alice', $this->makeNote(7, 'alice'), NoteShare::TYPE_USER, 'bob');

		$this->assertFalse($share->canEdit());
	}

	public function testANoteIsNotSharedTwiceWithTheSameRecipient(): void {
		$this->userManager->method('userExists')->willReturn(true);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);
		$this->config->method('getAppValue')
			->willReturnCallback(function ($app, $key, $default) { return $default; });
		$this->noteShareMapper->method('existsByNoteAndTarget')->willReturn(true);

		$this->expectException(\InvalidArgumentException::class);

		$this->shareService->create('alice', $this->makeNote(7, 'alice'), NoteShare::TYPE_USER, 'bob');
	}

	// Reshares --------------------------------------------------------------

	/**
	 * A reshare hangs off the access of whoever made it. Taking bob's share
	 * away has to take the one bob made with carol, or she is left holding a
	 * share nobody was entitled to give her.
	 */
	public function testUnsharingDropsTheResharesOfThatPerson(): void {
		$note = $this->makeNote(7, 'alice');
		$bobShare = $this->makeShare(NoteShare::TYPE_USER, 'bob', NoteShare::PERMISSIONS_ALL);
		$bobShare->setId(1);
		$carolShare = $this->makeShare(NoteShare::TYPE_USER, 'carol', NoteShare::PERMISSION_READ);
		$carolShare->setId(2);
		$carolShare->setUidInitiator('bob');

		$this->noteShareMapper->method('find')->with(1)->willReturn($bobShare);
		$this->noteMapper->method('findShared')->willReturn($note);
		// After bob's share is gone, the sweep sees carol's — and bob has no
		// access left to justify it.
		$this->noteShareMapper->method('findByNoteId')
			->willReturnOnConsecutiveCalls([$carolShare], []);
		$this->userIsIn('bob', []);
		$this->noteShareMapper->method('findByNoteAndRecipient')->willReturn([]);

		$deleted = [];
		$this->noteShareMapper->method('delete')->willReturnCallback(function ($share) use (&$deleted) {
			$deleted[] = $share->getShareWith();
			return $share;
		});

		$this->shareService->delete('alice', 1);

		$this->assertSame(['bob', 'carol'], $deleted);
	}

	/** The owner's own shares are never swept: their access is the note. */
	public function testTheOwnersSharesSurviveTheSweep(): void {
		$note = $this->makeNote(7, 'alice');
		$bobShare = $this->makeShare(NoteShare::TYPE_USER, 'bob', NoteShare::PERMISSIONS_ALL);
		$bobShare->setId(1);
		// Made by alice, the owner, so it stands whatever the sweep finds.
		$other = $this->makeShare(NoteShare::TYPE_USER, 'dave', NoteShare::PERMISSION_READ);
		$other->setId(3);

		$this->noteShareMapper->method('find')->with(1)->willReturn($bobShare);
		$this->noteMapper->method('findShared')->willReturn($note);
		$this->noteShareMapper->method('findByNoteId')->willReturn([$other]);
		$this->userIsIn('alice', []);

		$deleted = [];
		$this->noteShareMapper->method('delete')->willReturnCallback(function ($share) use (&$deleted) {
			$deleted[] = $share->getShareWith();
			return $share;
		});

		$this->shareService->delete('alice', 1);

		$this->assertSame(['bob'], $deleted);
	}

	// Leaving ---------------------------------------------------------------

	public function testLeavingAlsoForgetsWhatTheUserMadeOfTheNote(): void {
		$share = $this->makeShare(NoteShare::TYPE_USER, 'bob', NoteShare::PERMISSION_READ);
		$this->noteShareMapper->method('findByNoteAndTarget')->willReturn($share);

		$this->noteShareMapper->expects($this->once())->method('delete')->with($share);
		$this->noteStateMapper->expects($this->once())->method('deleteForUser')->with('bob', 7);
		$this->noteTagMapper->expects($this->once())->method('deleteForUser')->with('bob', 7);
		$this->attachMapper->expects($this->once())->method('deleteForUser')->with('bob', 7);

		$this->assertTrue($this->shareService->leave('bob', 7));
	}

	public function testThereIsNothingToLeaveWhenTheShareIsAGroupOne(): void {
		$this->noteShareMapper->method('findByNoteAndTarget')
			->willThrowException(new DoesNotExistException('nope'));

		$this->noteShareMapper->expects($this->never())->method('delete');

		$this->assertFalse($this->shareService->leave('bob', 7));
	}

}
