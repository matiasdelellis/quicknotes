#!/usr/bin/env python3
"""Build the board the screenshots are taken of.

Every card says what one feature of the app does, so the pictures in the App
Store listing double as the tour. They are kept short on purpose: a card that
runs past the fold gets clipped in a 1366x768 shot.

It talks to the REST API as QN_USER, and needs a second account (QN_OTHER_USER,
"emma" by default) and a group (QN_GROUP, "Team") to show sharing from both
ends. Everything those two own is deleted first, so point it at a throwaway
instance — the docker one under docker/ is what it was written for.

    QN_PASSWORD=... python3 tests/browser/seed_demo.py
"""

import base64
import json
import os
import re
import sys
import urllib.parse
import urllib.request

BASE = os.environ.get('QN_URL', 'http://localhost:8080')
USER = os.environ.get('QN_USER', 'demo')
OTHER = os.environ.get('QN_OTHER_USER', 'emma')
GROUP = os.environ.get('QN_GROUP', 'Team')
PASSWORD = os.environ.get('QN_PASSWORD')
API = BASE + '/index.php/apps/quicknotes/api/v1'

# The palette of the colour picker. Only a few of them, reused: a card of every
# colour looks like a swatch book, and the sidebar ends up with two rows.
YELLOW, BLUE, GREEN, PURPLE, PINK = '#F7EB96', '#88B7E3', '#C1ECB0', '#BFA6E9', '#FF96AC'

REMINDER = os.environ.get('QN_REMINDER', '2026-08-24 12:00:00')

NOTES = [
    dict(
        title='Quick notes',
        content='<p>A small card for whatever you would otherwise forget: the '
                'idea, the address, the thing to buy on the way home.</p>',
        color=YELLOW, isPinned=True, tags=['Basics'],
    ),
    dict(
        title='Colours and tags',
        content='<p>Give a note a colour, and as many tags as you want. The '
                'sidebar narrows the board by both.</p>',
        color=BLUE, tags=['Organise'],
    ),
    dict(
        title='Rich text',
        content='<p>Select any text and the toolbar appears: <b>bold</b>, '
                '<i>italic</i>, a heading, a link.</p>'
                '<ul><li>bullet lists</li><li>and numbered ones</li></ul>',
        color=GREEN, tags=['Basics'],
    ),
    dict(
        title='Attachments',
        content='<p>Attach any file of your Nextcloud. Images, video and audio '
                'open in the viewer, and the files stay where they are.</p>',
        color=YELLOW, tags=['Files'], photos=True,
    ),
    dict(
        title='Share with people and groups',
        content='<p>Say who can <b>view</b>, who can <b>edit</b>, and who can '
                'pass the note on to somebody else.</p>',
        color=PINK, tags=['Sharing'],
        shares=[(0, OTHER, 3), (1, GROUP, 1)],
    ),
    dict(
        title='Reminders',
        content='<p>Ask to be reminded and the notification arrives on the day. '
                'Everybody sets their own.</p>',
        color=PURPLE, tags=['Basics'], reminder=REMINDER,
    ),
    dict(
        title='Filter as you type',
        content='<p><b>Filter notes</b> narrows the board while you type. '
                'Accents are ignored, so <i>cafe</i> finds <i>Caf&eacute;</i>.</p>',
        color=BLUE, tags=['Organise'],
    ),
    dict(
        title='Archive and trash',
        content='<p>Archive takes a note off the board without losing it '
                '&mdash; even one that is not yours to delete.</p>',
        color=GREEN, tags=['Organise'],
    ),
    dict(
        title='Everywhere in Nextcloud',
        content='<p>Your notes answer the unified search, sit on the dashboard, '
                'and have a REST API of their own.</p>',
        color=PURPLE, tags=['Basics'],
    ),
]

# The receiving end of a share, so the board shows both sides of it.
FROM_OTHER = dict(
    title='Shared with you',
    content='<p>A note somebody shares with you lands on your own board, with '
            'their name on it. Your pin, your tags and your reminder stay '
            'yours.</p>',
    color=GREEN, tags=['Sharing'],
)

# Pictures every fresh account has, for the attachment mosaic.
PHOTOS = ['Birdie.jpg', 'Frog.jpg', 'Gorilla.jpg', 'Toucan.jpg']


def request(method, url, body=None, user=None):
    headers = {
        'Authorization': 'Basic ' + base64.b64encode(
            f'{user or USER}:{PASSWORD}'.encode()).decode(),
        'OCS-APIRequest': 'true',
    }
    data = None
    if body is not None:
        data = json.dumps(body).encode()
        headers['Content-Type'] = 'application/json'
    try:
        with urllib.request.urlopen(
                urllib.request.Request(url, data=data, method=method, headers=headers)) as answer:
            raw = answer.read().decode()
            return answer.status, (json.loads(raw) if raw.strip().startswith(('{', '[')) else raw)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()


def file_ids(folder):
    """The fileid of everything in a folder of the user's Files, by name."""
    body = ('<?xml version="1.0"?>'
            '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
            '<d:prop><oc:fileid/></d:prop></d:propfind>').encode()
    headers = {
        'Authorization': 'Basic ' + base64.b64encode(f'{USER}:{PASSWORD}'.encode()).decode(),
        'Depth': '1', 'Content-Type': 'application/xml',
    }
    url = f'{BASE}/remote.php/dav/files/{USER}/{folder}/'
    with urllib.request.urlopen(
            urllib.request.Request(url, data=body, method='PROPFIND', headers=headers)) as answer:
        xml = answer.read().decode()
    found = {}
    for match in re.finditer(r'<d:href>([^<]+)</d:href>.*?<oc:fileid>(\d+)</oc:fileid>', xml, re.S):
        name = urllib.parse.unquote(match.group(1)).rstrip('/').split('/')[-1]
        found[name] = int(match.group(2))
    return found


def purge(who):
    status, notes = request('GET', API + '/notes', user=who)
    if not isinstance(notes, list):
        raise SystemExit(f'Could not read the notes of {who}: {status} {notes}')
    theirs = [n for n in notes if n['owner']['uid'] == who]
    for note in theirs:
        request('DELETE', f"{API}/notes/{note['id']}", user=who)
    print(f'purged {len(theirs)} notes of {who}')


def create(spec, user=None, attachments=()):
    status, note = request('POST', API + '/notes', {
        'title': spec['title'],
        'content': spec['content'],
        'color': spec['color'],
        'isPinned': spec.get('isPinned', False),
        'tags': [{'name': tag} for tag in spec.get('tags', [])],
        'attachments': [{'file_id': i} for i in attachments],
    }, user=user)
    if status != 200:
        raise SystemExit(f"could not create {spec['title']}: {status} {note}")
    print(f"  {note['id']:3d}  {spec['title']}  "
          f"tags={[t['name'] for t in note['tags']]} "
          f"attachments={len(note['attachments'])}")
    return note['id']


def main():
    if not PASSWORD:
        raise SystemExit('Set QN_PASSWORD — the same one for both accounts')

    purge(USER)
    purge(OTHER)

    photos = file_ids('Photos')
    missing = [p for p in PHOTOS if p not in photos]
    if missing:
        print('warning: the attachment note will be short of', ', '.join(missing))

    for spec in NOTES:
        attachments = [photos[p] for p in PHOTOS if p in photos] if spec.get('photos') else ()
        note_id = create(spec, attachments=attachments)

        if spec.get('reminder'):
            status, answer = request('PUT', f'{API}/notes/{note_id}/reminder',
                                     {'reminderAt': spec['reminder']})
            print('       reminder ->', status,
                  answer.get('reminderAt') if isinstance(answer, dict) else answer)

        for share_type, share_with, permissions in spec.get('shares', []):
            status, answer = request('POST', f'{API}/notes/{note_id}/shares', {
                'shareType': share_type, 'shareWith': share_with,
                'permissions': permissions,
            })
            print('       share ->', status, answer if status != 200 else
                  f"{answer.get('displayName')} permissions={answer.get('permissions')}")

    note_id = create(FROM_OTHER, user=OTHER)
    request('POST', f'{API}/notes/{note_id}/shares',
            {'shareType': 0, 'shareWith': USER, 'permissions': 3}, user=OTHER)
    # A tag of your own on somebody else's note — what the card claims.
    request('PUT', f'{API}/notes/{note_id}', {
        'id': note_id, 'title': FROM_OTHER['title'], 'content': FROM_OTHER['content'],
        'tags': [{'name': tag} for tag in FROM_OTHER['tags']],
    })
    print(f'       shared by {OTHER} with {USER}')


if __name__ == '__main__':
    sys.exit(main())
