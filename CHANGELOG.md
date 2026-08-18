# Changelog
## [Unreleased]
- Add a "Filter notes" entry to the navigation: it looks like the others until
  you click it, and then a field takes its place and narrows the notes on screen
  as you type, matching the title, the body and the names of the tags. Case and
  accents are ignored — "cafe" finds "Café" — and several words all have to
  appear, in any order. It is a filter like the colour and tag ones, so it
  applies to whichever list you are looking at; Escape or the × drops it and puts
  the entry back, and it travels in the url as `?q=`.
- Say "no notes match the filter" instead of leaving a blank area, which is also
  what the colour, tag and reminder filters did when they matched nothing.
- Empty the trash in one go, from a button that shows up in that view when
  there is something in it. Until now a note could only be purged one at a
  time, or left for the weekly cleanup. The API has it as
  `DELETE /notes/trash`, which answers how many notes went.
- Describe the API in `doc/openapi.yml`: every `/api/v1` endpoint, what the
  payloads carry, and which of their fields belong to the note and which to
  whoever is asking.
- Let the reminder, sorting and archive vocabulary be translated at last.
  Twelve strings — "Remind me", "Sort by:", "Move to trash", "Restore" and the
  rest of them — were written inside the handlebars templates, which the
  translation tool does not read, so they never reached Transifex and were in
  English in every language. They go through `templates/fake.php` now, like
  the others already did.
- Stop asking translators to translate the minified vue runtime. The extractor
  was reading the built bundles and pulling strings like
  `key,ref,slot,slot-scope,is` out of them; a `.l10nignore` keeps the build
  output out of it.
- Fix opening the colour palette of a note breaking the rest of the editor: it
  was added with `innerHTML +=`, which re-parses the whole note being edited, so
  every field came back as a new element. The format toolbar stopped appearing
  from then on, and what you typed afterwards no longer counted as a change.
- Close the colour palette when the editor closes. It used to stay open, hanging
  over the next note opened.
- Give "Colors" in the navigation the same icon as the colour button of the
  editor. It was showing a magnifying glass.
- Fix the format toolbar of the editor being dressed by the wrong stylesheet:
  the base styles of MediumEditor were loaded *after* the theme of the app that
  is meant to override them, so the buttons kept a 15px padding that squashed
  their icons — the link-to-a-note one visibly.
- Stop leaving a share behind when the person who made it loses access: taking
  a note back from somebody who had passed it on now takes their reshares with
  it, all the way down a chain.
- Leave archived and trashed notes out of the dashboard widget. They were shown
  there all along, which only became obvious once archiving became personal.
- Remove the `Errors` controller trait, which caught an exception class that
  does not exist in this app. Nothing used it, and the documentation was
  recommending it.
- Sharing a note now shares its attachments. They used to vanish without a
  trace for the people it was shared with — the app asked whether *they* could
  reach the file, and answered by dropping the attachment from the note — with
  a line in the share dialog telling them to go and share the files by hand.
  The app serves them itself now, to anybody who can see the note, reading each
  file with the authority of whoever attached it. Nothing is shared in Files:
  there is no copy, nothing to keep in sync, and access ends when the note
  stops being shared.
- Attachments of a note you cannot reach in your own Files offer a download
  instead of a link into Files, which is where they used to point at nothing.
- Lay the attachments of a note out as a mosaic instead of a row that got
  thinner with every file: one fills the space, two split it, three are one tall
  plus two stacked, four are a 2x2, five are two over three, six are a 3x2.
  Beyond that the note card shows six and counts the rest, and the editor shows
  them all in rows of three.
- Fix the "delete attachment" button of the editor showing no icon at all: it
  asked for a class that never existed — the name of a CSS variable — so it was
  a grey circle with nothing in it.
- Show the icon of the file type for an attachment with no preview — a pdf, a
  zip — instead of an empty tile.
- Attachments open in the Nextcloud viewer, the ones somebody else attached
  included — images, video and audio, which are the types the viewer itself
  handles. Anything else keeps opening the way it did.
- Anybody who can edit a shared note can attach files to it, not just its
  owner: each file is served from the storage of whoever attached it, and only
  they can take it off the note again. An attachment of somebody who is no
  longer part of the note stops being shown and served with it.
- Archiving is personal. Anybody who can see a note can take it out of their
  own grid without touching anybody else's, which is the only way out for a
  note shared with a group: that share is not theirs to leave. The existing
  archived notes are kept, as archived by their owner.
- The "leave" icon of a shared note is now only offered where leaving actually
  works — a share made with you personally — instead of failing with an
  explanation afterwards.
- Deleting a note that is not yours answers "not found" instead of pretending
  it worked.
- Reminders are personal. Everybody who can see a note can set their own date
  on it, including on a note shared read only, and nobody sees anybody else's:
  the reminder moved from the note to the same per-user table as the pin. The
  existing ones are kept, as reminders of the note's owner.
- The reminder is saved the moment it is picked instead of waiting for the note
  to be saved — which is what lets it work on a note you are not allowed to
  edit.
- The read-only editor of a shared note now offers the reminder button, the one
  thing it can still do.
- The virtual calendar shows each user their own reminders, on their notes and
  on the ones shared with them.
- Cancel the reminder of everybody, not just the owner's, when a note is
  trashed or deleted; and drop one silently when the user turns out to have
  lost access to the note in the meantime.
- Fix rescheduling a reminder also dismissing the "shared with you"
  notification of the same note: the two are told apart now.
- Rewrite sharing. A note can now be shared **with permissions** — "can view",
  "can edit" and "can reshare" — and with **groups**, not just with single
  users. Editing a shared note is real collaboration: the recipient writes the
  title and the content of the same note, and the owner keeps the colour, the
  attachments, the reminder, archiving and deleting.
- Shares are applied the moment they are made, instead of travelling inside the
  note and being written on save. Sharing a note no longer depends on
  remembering to save it, and an old browser tab can no longer revoke a share
  made in another one.
- New share dialog: the people and groups a note is shared with, with their
  avatars and a menu to change what each of them may do. The user search runs
  on the server through the collaborator search of Nextcloud, so it finds
  groups and honours the sharing settings of the instance — and the app no
  longer pulls every user of the instance on page load.
- Pinning is now per user: pinning a note somebody shared with you no longer
  pins it for them. Tags on a shared note were already personal and now behave
  like it, showing yours rather than the owner's.
- Refuse to overwrite an edit made elsewhere. Saving sends the state the note
  was read in, and if somebody else saved in the meantime the app asks whether
  to overwrite their version or reload. The grid also refreshes itself when the
  tab comes back to the front.
- Notify people when a note is shared with them, and withdraw the notification
  when it is unshared.
- Search now finds the notes shared with you, which only the dashboard did.
- The v1 API is at 1.4: an attachment carries `download_url`, `link_url`,
  `user_id`, `is_mine`, `basename` and `mime`, its `preview_url` is served by the app, and `redirect_url` /
  `deep_link_url` are null for whoever cannot reach the file in their Files.
- The v1 API is at 1.3: `archivedAt` is now the caller's rather than the
  note's, `POST /notes/{id}/archive` and `/unarchive` answer to anybody who can
  see the note, the payload gained `canLeave`, and `DELETE /notes/{id}` answers
  404 on a note the caller does not own.
- The v1 API is at 1.2: `reminderAt` and `reminderNotifiedAt` are now those of
  whoever asks rather than the note's, and `PUT /notes/{id}/reminder` takes a
  reminder from anybody who can see the note instead of only from its owner.
- The v1 API is at 1.1: the note payload gained `permissions`, `canEdit`,
  `canReshare`, `isOwner`, `owner`, `sharedByMe` and `etag`, `sharedBy` is now
  an object instead of a list of one, and `sharedWith` carries shares rather
  than user ids. `PUT /notes/{id}` takes everything but the title and the
  content as optional, and still accepts `sharedWith` from the owner.
- Ship the sharing icons with the app: the share button of the editor and the
  badge of a shared note rendered blank since Nextcloud 34 stopped serving the
  icon stylesheet.
- Add note reminders: a note can carry a date, and a background job sends a
  Nextcloud notification when it falls due. Sending a note to the trash
  cancels its reminder; archiving does not.
- Add a Reminders entry to the navigation, filtering the grid down to the notes
  that carry one.
- Publish the notes with a reminder as a read-only calendar, so they show up in
  the Calendar app and in CalDAV clients. It is generated from the notes on
  every read, so editing or deleting a note is reflected right away. Off by
  default, there is a checkbox in the app settings.
- Fix the Settings button of the navigation opening nothing: it relied on the
  `data-apps-slide-toggle` jQuery plugin, which Nextcloud 34 removed.
- Fix the format toolbar of the note editor being drawn behind the modal since
  the overlay was raised to the NcModal z-index.
- Ship the calendar icon with the app: Nextcloud 34 no longer serves the
  `icon-*` stylesheet the app used to rely on, so it rendered blank.

## [0.9.0] 2026-07-30
- Add Nextcloud 34 compatibility, and drop the older versions.
- Ship our own jQuery: Nextcloud 34 no longer provides it to the apps.
- Rewrite the tags and shares dialogs with the Nextcloud Vue components,
  replacing the select2 and ocdialog jQuery plugins removed in Nextcloud 34.
- Resolve attachments server side, since the OC.Files javascript client was
  also removed in Nextcloud 34.
- Show errors as toasts: OC.dialogs.alert() no longer works in Nextcloud 34.
- Bring back the navigation on small screens, with our own toggle: Nextcloud 34
  removed snap.js, which used to open and close it.
- Migrate the controllers to route attributes, and the settings to IUserConfig.
- Fix the display name of shared notes being set on an undeclared property,
  deprecated since PHP 8.2.

## [0.8.50] 2026-06-09
- Add Nextcloud 33 compatibility. Thangs to Baki Burak Öğün. PR #124
- Implement Archive and Trash. It only took 5 years. Issue #65
- Open the new notes to edit.
- Many minor usability improvements.

## [0.8.40] 2026-02-12
- Add support to NC32. Thanks Marius Knüppel
- Update deprecated APIs for Nextcloud 32 compatibility. Thanks JanGross

## [0.8.30] 2025-02-27
- Add support to NC30 and NC31
- Fix tags selection.. Issue #112

## [0.8.23] 2024-06-18
- Enable NC29

## [0.8.22] 2024-02-06
- Fix some pending regressions from NC28. Issue #109
- Lots of improvements to dark theme support.

## [0.8.21] 2024-01-30
- Fix attachment selection, broken since NC27 due internal changes. Issue #104

## [0.8.20] 2024-01-30
- Enable and migrate to NC28. Issues #105, #106 and #108

## [0.8.10] 2023-06-14
- Enable Nextcloud 26. Issue #100
- Enable support to NC27 for early testing.
- Implement the option to sort the notes. Issue #85
- Fix some static analisys reports and some css styles.

## [0.8.5] 2022-11-18
- Updates to fix NC25 and enable it.
- New Ukrainian translation thanks to Денис Семенюк
- Update other translations. Thank you very much to all!.

## [0.8.1] 2022-08-02
- Add dashboard widget to show the latest notes. Issue #51
- Integration with Talk. You can save a message as a note to remind yourself.
- Fix unable to forget a shared note. Part of issue #72
- Fix unable to forget a shared note already deleted by the owner. Issue #72
- Update translations, add danish and turkish. Many thanks to all contributors.

## [0.8.0] 2022-05-22
- Just move focus to content when press Return key on title.
- Jump to end of note content when open them. Issue #7
- Fix close modal when select text and mouseup outside note. Issue #27
- Implements automatic saving of notes. Issue #40
- Also save the notes with Crl+Enter key.
- Always save plain text for title on note.
- Prevents closing notes when any part of the note is changed.
- Fix some missing semicolon on colorPicker.
- D'Oh!. Fix use of two translations (Introduced at least two years ago).
- Add 'Title' as placeholder for empty notes.
- Increase the size of un/pin notes and remove icons.
- In the list of notes shows 'Note #' when the title is empty.
- Don´t shrink the size of the note text.
- Translate placeholders of empty notes.
- New Greek translation thanks to Theodoros Bousios.
- Update Spanish translation.
- Fix Shared with 'user' tooltip.
- Improves the tooltip to leaving a shared note.
- Add support info to readme.
- Handle OPTION(CORS) calls to use the API in web apps. Issue #80
- Use display name of users to share dialog and notes. See issue #49
- Don't trim long titles, and show them in more lines.

## [0.7.6] 2022-05-07
- Enable NC24.
- Removes lot use of jQuery, that inexplicably failing in NC24. Issue #84

## [0.7.3] 2021-12-03
- Add where to translate into README. PR #71
- Add lint and static-analysis using github workflows.
- Convert database Mappers to QBMapper.
- Fix impossible to change color of a note. Issue #74
- Fix round of modal buttons..
- New Czech translation thanks to Pavel Borecki
- Enable to Nextcloud 23. Issue #75

## [0.7.2] 2021-08-03
- Initial support for NC22.
- Highlight the note or filter used in the side panel.
- New Chinese (Taiwan) and Czech translations. Many thanks to the contributors.

## [0.7.1] 2021-03-19
- Fix php 7.3 support, as it accidentally used more modern features.
- Introduce initial unified search support, that search by title and content.
- Implements some consistent urls, which allow you to mark a note, tag or color
  as a favorite in the browser to easily access to them.

## [0.7.0] 2021-03-19
- Bump version to move the numbering away from the version nc20 or lower.
- New Macedonian translation thanks to Сашко Тодоров.
- Update Portuguese (Brazil) and Polish translations. Thanks to the translators.

## [0.6.6] 2021-03-19
- Fix on NC20

## [0.6.5]: 2021-03-18
- Emergency release to truly enable NC21. Thanks @nursoda for report it on
  issue #57

## [0.6.4]: 2021-03-18
- Initial Nextcloud 21 support.
- Add new api for uploading attachments. For now only used in the Android
  client.
- Update Portuguese (Brazil) thanks to flaviove.

## [0.6.3]: 2020-10-31
- Fix thumbnail when pretty url is disabled. Issue #48
- Update French translation thanks to Thovi98

## [0.6.2]: 2020-10-27
- Enable Nextcloud 20 support.
- Use the same url from thumbails that Photos.
- Update French translation thanks to Thovi98
- New Portuguese (Brazil) translation thanks to THOMAS COUTO ROCHA

## [0.6.1]: 2020-10-03
- Improve API needed to implement the android client.
- Register nextcloud capabilities to check api versions.
- Fix response when dont have notes. Issue #44.
- Update German translation thanks to Lars Seidler.
- Update German (Germany) translation thanks to Lars Seidler.
- Update Russian translation thanks to Rusalan Kortikov.
- Update Polish translation thanks to Valdnet Valdnet.

## [0.6.0]: 2020-06-17
- Enable sharing of notes as read-only between users. Issue #3, #16 and PR #8. Thanks to Vinzenz Rosenkranz
- Fix many untranslated strings. Issue #31 and #32
- Add a small modal dialog to change the color of the note.
- Add an option to select the default color for new notes.
- First version of an API for third-party applications
- Modernize some code, clean others and try to improve some css styles.
- Update Italian translation thanks to Valerio Pulese.
- Update Spanish translation thanks to Matias De lellis.

## [0.4.0]: 2020-06-14
- Implement attachments to notes.
- Fix the icon that marks the color of the modal note.

## [0.3.0]: 2020-06-08
- Implement pin notes to keep notes always in view.
- Add confirmation dialog to cancel edition. Part of issue #27.
- New links are created to open in another tab.
- Translate many new strings. Part of issue #32.
- Improvements in styles and animations.
- Move part of the code to the new Nextcloud standards.
- Update German (Germany) translation thanks to Lars Seidler

## [0.2.4]: 2020-08-05
- Add French translation thanks to Aymo XXX.
- Update German translation thanks to lhsei.
- Support NC19.

## [0.2.3]: 2020-02-06
- Drop support to NC15 in line with Nextcloud.
- Add Russian translation thanks to Rusalan Kortikov.
- Add Polish translation thanks to Valdnet Valdnet.

## [0.2.2]: 2020-01-08
- Support NC18.
- Fix Italian translation thanks to Valerio Pulese.

## [0.2.1]: 2019-11-14
- Support NC15 again. It's the stable version.
- Update the navigation menu correctly with the new tags.
- Fill the tag dialog with the modal and not replace it with those in the db.

## [0.2.0]: 2019-11-12
- Implement notes tagging support and filter with it.
- Fix calling exponentially to save notes when pressed Alt+Return.
- Update spanish translation

## [0.1.10]: 2019-11-02
### Added
- Add Italian translation. Thanks to @albanobattistella
- Use fullscreen modal on small screen. Issue #23
- Add an small fade animation when hide the modal note.
- Install libs using npm to ensure versions of handlebars.
- Use transifex to translations. Please, help to it.

## [0.1.9]: 2019-08-21
### Added
- Indicate support for NC 17.
- Update app screenshots.

## [0.1.8]: 2019-08-15
### Added
- Add Alt+Return as keyboard shortcut to save note. Issue #21
- Show an animation when show the modal to editing notes.

## [0.1.7]: 2019-04-23
### Added
- Just bump versio to fix NC pattern

## [0.1.6.1]: 2019-04-23
### Added
- Initial Nextcloud 16 release

## [0.1.6]: 2019-02-12
### Added
- Initial Nextcloud 15 release
- Show an spinner while loading notes
- Add a confirmation before deleting a note
- Do happy to NC app:check
- Fix title on new notes.
- Don't collapse color menu when click on empty area

## [0.1.5]: 2018-10-13
### Added
- Initial Nextcloud 14 release.

## [0.1.4]: 2018-08-29
### Added
- Some styles fixed.

## [0.1.3]: 2018-04-26
### Added
- Some styles fixed.

## [0.1.2]: 2018-04-17
### Added
- Initial Nextcloud release.
- Use medium-editor as basic rich editor
- German translation from @v1r0x
- Spanish translation.

## [0.1.0]
### Added
- Implement search on notes.
- Rename 'Add note' item to 'New note' and put first on navigation.
- D'Oh!. Fix animation when append or remove notes.

## [0.0.8]
### Added:
- Fix database schema migration.

## [0.0.6]
### Added
- Several design fixes, thanks to v1r0x.
- Highlight current color on edit mode, thanks to v1r0x.
- Put color on own database with relationship over on notecontroller.
- Highlight current color selection on navigation filter.
- Show all notes when remove one.
- Fix: Redraw content to show first note.

## [0.0.4]
### Added
- Show Animation when add or remove notes without redraw everything.
- Show all when append a new note.
- Add the new notes in the proper position.
- Positioning the modal editor in the position of the original note.
- Hide editor when click outside modal.
- Not refilter anything when cancel edit.
- Add Ocsid and useful data.

## [0.0.2]
### Added
- Initial version:
- Just text notes and filter by color..
- Fix version.
