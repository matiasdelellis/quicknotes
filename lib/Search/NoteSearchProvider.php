<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Matias De lellis <mati86dl@gmail.com>
 *
 * @author Matias De lellis <mati86dl@gmail.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\QuickNotes\Search;

use OCP\Search\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\NoteStateMapper;
use OCA\QuickNotes\Db\TagMapper;
use OCA\QuickNotes\Service\ShareService;

/**
 * Provide search results from the 'quicknotes' app
 */
class NoteSearchProvider implements IProvider {

	/** @var NoteMapper noteMapper */
	private $noteMapper;

	/** @var NoteStateMapper */
	private $noteStateMapper;

	/** @var TagMapper */
	private $tagMapper;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ShareService */
	private $shareService;

	public function __construct(
		IL10N          $l10n,
		IURLGenerator  $urlGenerator,
		NoteMapper     $noteMapper,
		NoteStateMapper $noteStateMapper,
		TagMapper      $tagMapper,
		ShareService   $shareService
	) {
		$this->l10n            = $l10n;
		$this->urlGenerator    = $urlGenerator;
		$this->noteMapper      = $noteMapper;
		$this->noteStateMapper = $noteStateMapper;
		$this->tagMapper       = $tagMapper;
		$this->shareService    = $shareService;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'quicknotes';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Quick notes');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, 'quicknotes.') === 0) {
			return -1;
		}
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$page = $query->getCursor() ?? 0;
		$limit = $query->getLimit();

		$terms = $this->terms($query->getTerm());
		if (count($terms) === 0) {
			return SearchResult::paginated($this->l10n->t('Quick notes'), [], $page);
		}

		$userId = $user->getUID();

		// Every note this user can reach, minus the trash and minus whatever
		// they archived — both out of their grid, so neither is a result. The
		// matching itself happens below, where case and accents can be ignored
		// the way the grid filter ignores them.
		$notes = $this->noteMapper->findReachable(
			$userId,
			$this->shareService->getSharedNoteIds($userId),
			$this->noteStateMapper->findArchivedNoteIds($userId)
		);

		if (count($notes) === 0) {
			return SearchResult::paginated($this->l10n->t('Quick notes'), [], $page);
		}

		$tagsByNote = $this->tagMapper->getTagNamesForNotes(
			$userId,
			array_map(function (Note $note) { return (int)$note->getId(); }, $notes)
		);

		$matched = [];
		foreach ($notes as $note) {
			if ($this->matches($note, $terms, $tagsByNote)) {
				$matched[] = $note;
			}
		}

		return SearchResult::paginated(
			$this->l10n->t('Quick notes'),
			array_map(function (Note $note) {
				return new SearchResultEntry(
					'',
					strip_tags((string)$note->getTitle()),
					'',
					$this->urlGenerator->linkToRoute('quicknotes.page.index', ['n' => $note->getId()]),
					'icon-edit',
					true,
				);
			}, array_slice($matched, $page * $limit, $limit)),
			$page
		);
	}

	/**
	 * Split a search term into the words that all have to appear, the way the
	 * grid filter does: "pagar luz" finds the note that says both, in any
	 * order. Accents and case are ignored.
	 *
	 * @return string[]
	 */
	private function terms(string $term): array {
		return array_values(array_filter(
			preg_split('/\s+/u', $this->normalize($term)) ?: [],
			function (string $word) { return $word !== ''; }
		));
	}

	/**
	 * Whether a note matches every term, in its title, its body or the names
	 * of the tags this user gave it.
	 *
	 * @param string[] $terms
	 * @param array<int, string[]> $tagsByNote
	 */
	private function matches(Note $note, array $terms, array $tagsByNote): bool {
		$noteId = (int)$note->getId();

		$haystack = $this->normalize(
			strip_tags((string)$note->getTitle())
			. ' '
			. strip_tags((string)$note->getContent())
			. ' '
			. implode(' ', $tagsByNote[$noteId] ?? [])
		);

		foreach ($terms as $term) {
			if (strpos($haystack, $term) === false) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Text as it is compared: lower case and without accents, the same
	 * normalization the grid filter applies (`normalizeForFilter` in
	 * js/script.js), so the unified search finds what the board finds.
	 */
	private function normalize(string $text): string {
		$text = mb_strtolower($text);

		$decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);
		if ($decomposed !== false) {
			$text = $decomposed;
		}

		// preg_replace answers null on an invalid string rather than a match,
		// which would be a worse search result than the accents it meant to
		// strip.
		$stripped = preg_replace('/[\x{0300}-\x{036f}]/u', '', $text);
		return $stripped === null ? $text : $stripped;
	}

}
