<?php
/**
 * ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2016
 */

namespace OCA\QuickNotes\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\Color;
use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\ColorMapper;
use OCA\QuickNotes\Db\NoteShareMapper;

class NoteController extends Controller {

	private $notemapper;
	private $colormapper;
	private $notesharemapper;
	private $userId;

	public function __construct($AppName, IRequest $request, NoteMapper $notemapper, NoteShareMapper $notesharemapper, ColorMapper $colormapper, $UserId) {
		parent::__construct($AppName, $request);
		$this->notemapper = $notemapper;
		$this->colormapper = $colormapper;
		$this->notesharemapper = $notesharemapper;
		$this->userId = $UserId;
	}

	/**
	 * @NoAdminRequired
	 */
	 public function index() {
		$notes = $this->notemapper->findAll($this->userId);
		foreach($notes as $note) {
		    $note->setIsShared(false);
		}
		$shareEntries = $this->notesharemapper->findForUser($this->userId);
		$shares = array();
		foreach($shareEntries as $entry) {
		    $share = $this->notemapper->findById($entry->getNoteId());
		    $share->setIsShared(true);
			$shares[] = $share;
			
		}
		$notes = array_merge($notes, $shares);
		// Insert true color to response
		foreach ($notes as $note) {
			$note->setColor($this->colormapper->find($note->getColorId())->getColor());
		}
		return new DataResponse($notes);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function show($id) {
		// TODO: Implement.
		try {
			return new DataResponse($this->notemapper->find($id, $this->userId));
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $title
	 * @param string $content
	 */
	public function create($title, $content, $color = "#F7EB96") {
		// Get color or append it
		if ($this->colormapper->colorExists($color)) {
			$hcolor = $this->colormapper->findByColor($color);
		} else {
			$hcolor = new Color();
			$hcolor->setColor($color);
			$hcolor = $this->colormapper->insert($hcolor);
		}

		// Create note and insert it
		$note = new Note();
		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(time());
		$note->setColorId($hcolor->id);
		$note->setUserId($this->userId);

		// Insert true color to response
		$note->setColor($hcolor->getColor());

		return new DataResponse($this->notemapper->insert($note));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param string $title
	 * @param string $content
	 * @param string $color
	 */
	public function update($id, $title, $content, $color = "#F7EB96") {
		// Get current Note and Color.
		try {
			$note = $this->notemapper->find($id, $this->userId);
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$oldcolorid = $note->getColorId();

		// Get new Color or append it.
		if ($this->colormapper->colorExists($color)) {
			$hcolor = $this->colormapper->findByColor($color);
		} else {
			$hcolor = new Color();
			$hcolor->setColor($color);
			$hcolor = $this->colormapper->insert($hcolor);
		}

		// Set new info on Note
		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(time());
		$note->setColorId($hcolor->id);
		// Insert true color to response
		$note->setColor($hcolor->getColor());

		// Update note.
		$newnote = $this->notemapper->update($note);

		//  Remove old color if necessary
		if (($oldcolorid != $hcolor->getId()) &&
		    (!$this->notemapper->colorIdCount($oldcolorid))) {
			$oldcolor = $this->colormapper->find($oldcolorid);
			$this->colormapper->delete($oldcolor);
		}

		return new DataResponse($newnote);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function destroy($id) {
		// Get Note and Color
		try {
			$note = $this->notemapper->find($id, $this->userId);
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$oldcolorid = $note->getColorId();

		// Delete note.
		$this->notemapper->delete($note);

		// Delete Color if necessary
		if (!$this->notemapper->colorIdCount($oldcolorid)) {
			$oldcolor = $this->colormapper->find($oldcolorid);
			$this->colormapper->delete($oldcolor);
		}

		return new DataResponse($note);
	}
}