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

/**
 * Provide search results from the 'quicknotes' app
 */
class NoteSearchProvider implements IProvider {

	/** @var NoteMapper noteMapper */
	private $noteMapper;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(
		IL10N         $l10n,
		IURLGenerator $urlGenerator,
		NoteMapper    $noteMapper
	) {
		$this->l10n         = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->noteMapper   = $noteMapper;
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
	public function search(IUser $user, ISearchQuery $query) : SearchResult {
		$page = $query->getCursor() ?? 0;
		$limit = $query->getLimit();
		return SearchResult::paginated(
			$this->l10n->t('Quick notes'),
			array_map(function (Note $result) {
				$noteId = $result->getId();
				$noteTitle = strip_tags($result->getTitle());
				return new SearchResultEntry(
					'',
					$noteTitle,
					'',
					$this->urlGenerator->linkToRoute('quicknotes.page.index', ['n' => $noteId]),
					'icon-edit',
					true,
				);
			},
			$this->noteMapper->findLike($user->getUID(),
				$query->getTerm(),
				$page * $limit,
				$limit)
			),
			$page);
	}

}
