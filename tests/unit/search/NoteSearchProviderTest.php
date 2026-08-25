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

namespace OCA\QuickNotes\Search;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\NoteStateMapper;
use OCA\QuickNotes\Db\TagMapper;
use OCA\QuickNotes\Service\ShareService;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;

use PHPUnit\Framework\TestCase;

/**
 * The unified search of the app: what it matches, and what it leaves out.
 */
class NoteSearchProviderTest extends TestCase {

	private $provider;
	private $noteMapper;
	private $noteStateMapper;
	private $tagMapper;
	private $shareService;

	protected function setUp(): void {
		$this->noteMapper = $this->createMock(NoteMapper::class);
		$this->noteStateMapper = $this->createMock(NoteStateMapper::class);
		$this->tagMapper = $this->createMock(TagMapper::class);
		$this->shareService = $this->createMock(ShareService::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturn('Quick notes');

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturn('https://example.com/apps/quicknotes/?n=');

		$this->provider = new NoteSearchProvider(
			$l10n,
			$urlGenerator,
			$this->noteMapper,
			$this->noteStateMapper,
			$this->tagMapper,
			$this->shareService
		);
	}

	private function makeNote(int $id, string $title, string $content = ''): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId('alice');
		$note->setTitle($title);
		$note->setContent($content);
		return $note;
	}

	/** Run a search and hand back the titles of the results. */
	private function search(string $term): array {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn($term);
		$query->method('getLimit')->willReturn(10);
		$query->method('getCursor')->willReturn(null);

		$result = $this->provider->search($user, $query);

		return array_map(function ($entry) {
			return $entry->jsonSerialize()['title'];
		}, $result->jsonSerialize()['entries']);
	}

	public function testIgnoresCaseAndAccents(): void {
		$this->noteMapper->method('findReachable')->willReturn([
			$this->makeNote(1, 'Café au lait', 'the note'),
			$this->makeNote(2, 'Something else', ''),
		]);
		$this->noteStateMapper->method('findArchivedNoteIds')->willReturn([]);
		$this->shareService->method('getSharedNoteIds')->willReturn([]);
		$this->tagMapper->method('getTagNamesForNotes')->willReturn([]);

		$this->assertSame(['Café au lait'], $this->search('cafe'));
		$this->assertSame(['Café au lait'], $this->search('CAFE'));
	}

	public function testMatchesTagsAndBody(): void {
		$this->noteMapper->method('findReachable')->willReturn([
			$this->makeNote(1, 'Grocery list', 'milk and eggs'),
			$this->makeNote(2, 'Other note', ''),
		]);
		$this->noteStateMapper->method('findArchivedNoteIds')->willReturn([]);
		$this->shareService->method('getSharedNoteIds')->willReturn([]);
		$this->tagMapper->method('getTagNamesForNotes')->willReturn([
			1 => ['Urgente'],
			2 => [],
		]);

		// A tag finds the note, and its own tags are what are searched.
		$this->assertSame(['Grocery list'], $this->search('urgente'));
		// The body matches too.
		$this->assertSame(['Grocery list'], $this->search('milk'));
		// A tag of another note does not bleed into this one.
		$this->assertSame([], $this->search('urgentx'));
	}

	public function testEveryTermMustAppear(): void {
		$this->noteMapper->method('findReachable')->willReturn([
			$this->makeNote(1, 'Pagar la luz', ''),
			$this->makeNote(2, 'Pagar el gas', ''),
		]);
		$this->noteStateMapper->method('findArchivedNoteIds')->willReturn([]);
		$this->shareService->method('getSharedNoteIds')->willReturn([]);
		$this->tagMapper->method('getTagNamesForNotes')->willReturn([]);

		$this->assertSame(['Pagar la luz'], $this->search('pagar luz'));
	}

	public function testHandsArchivedAndSharedIdsToTheMapper(): void {
		$this->noteStateMapper->method('findArchivedNoteIds')->willReturn([5]);
		$this->shareService->method('getSharedNoteIds')->willReturn([7]);
		$this->noteMapper->expects($this->once())
			->method('findReachable')
			->with('alice', [7], [5])
			->willReturn([]);

		$this->assertSame([], $this->search('anything'));
	}

	public function testEmptyTermReturnsNothing(): void {
		$this->noteMapper->expects($this->never())->method('findReachable');

		$this->assertSame([], $this->search('   '));
	}

	public function testStripsMarkupFromTheTitle(): void {
		$this->noteMapper->method('findReachable')->willReturn([
			$this->makeNote(1, '<b>Hello</b> world', ''),
		]);
		$this->noteStateMapper->method('findArchivedNoteIds')->willReturn([]);
		$this->shareService->method('getSharedNoteIds')->willReturn([]);
		$this->tagMapper->method('getTagNamesForNotes')->willReturn([]);

		$this->assertSame(['Hello world'], $this->search('hello'));
	}

}
