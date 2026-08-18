#!/usr/bin/env python3
"""Take the pictures of the App Store listing, on the board of seed_demo.py.

Five shots at 1366x768, the size the listing has always used: the grid, a note
being edited with the palette open, the format toolbar, the attachment mosaic
and the share dialog. The grid is written twice, the second one at 800x450 for
the `small-thumbnail` of appinfo/info.xml.

They are taken wider and at twice the scale and then brought down: at 1366 the
fourth column of notes ends up against the edge, and the supersampling leaves
the text cleaner.

    QN_PASSWORD=... python3 tests/browser/screenshots.py ../matiasdelellis.github.io/img/quicknotes

Needs Pillow, besides what cdp.py needs.
"""

import base64
import json
import os
import sys
import time
import urllib.request

from PIL import Image

import cdp

WIDTH, HEIGHT, SCALE = 1440, 810, 2
FINAL = (1366, 768)
THUMBNAIL = (800, 450)

# What each shot is called in appinfo/info.xml.
SHOTS = {
    'grid': 'quicknotes-grid-view',
    'edit': 'quicknotes-note-edit',
    'richtext': 'quicknotes-rich-text',
    'attachments': 'quicknotes-attachments',
    'share': 'quicknotes-shared-note',
}


def notes(user, password):
    """The board, by title, so the script can open a note by name."""
    request = urllib.request.Request(
        cdp.DEFAULT_URL + '/index.php/apps/quicknotes/api/v1/notes',
        headers={'Authorization': cdp.basic_auth(user, password)})
    with urllib.request.urlopen(request) as answer:
        return {n['title']: n['id'] for n in json.load(answer)}


def main():
    out = sys.argv[1] if len(sys.argv) > 1 else 'screenshots'
    os.makedirs(out, exist_ok=True)
    user, password = cdp.credentials()
    board = notes(user, password)

    raw = {}

    with cdp.Chrome(WIDTH, HEIGHT, SCALE, port=9243) as chrome:
        page = chrome.page
        chrome.visit('/index.php/apps/quicknotes/', user, password)
        chrome.dismiss_first_run_wizard()
        if not page.wait("document.querySelectorAll('.quicknote[data-id]').length >= 10"):
            raise SystemExit('the board never loaded — run seed_demo.py first')
        page.evaluate('window.scrollTo(0, 0)')
        time.sleep(3)  # the previews load lazily, and isotope settles

        def open_note(title):
            page.evaluate(f"""
                document.querySelector('#notes-grid-div .quicknote[data-id="{board[title]}"]')
                    .dispatchEvent(new MouseEvent('click', {{bubbles: true}}))""")
            if not page.wait("!!document.querySelector('#modal-note-div.show-modal-note')"):
                raise SystemExit('the editor did not open on ' + title)
            time.sleep(1.5)

        def close_note():
            page.evaluate("document.querySelector('#cancel-button').click()")
            time.sleep(1.2)

        def shot(name):
            raw[name] = page.screenshot(os.path.join(out, f'.{name}.png'))
            print('  ', SHOTS[name])

        print('the grid')
        shot('grid')

        print('a note being edited, with the palette open')
        open_note('Colours and tags')
        page.evaluate("document.querySelector('#color-button').click()")
        time.sleep(1.0)
        shot('edit')
        close_note()

        print('rich text, with the format toolbar')
        open_note('Rich text')
        # A drag through Input.dispatchMouseEvent selects nothing in headless,
        # so the range is made by hand and MediumEditor told with a mouseup.
        # The editable already has the focus, and MediumEditor only takes note
        # of it on the event, so it has to be let go of and taken again.
        page.evaluate("""
            (() => {
                const el = document.querySelector('#content-editable');
                el.blur();
                el.focus();
                const range = document.createRange();
                // Over the list, not the first paragraph: the toolbar plants
                // itself on top of the selection and would cover the title.
                range.selectNodeContents(el.querySelector('li') || el.querySelector('p'));
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                el.dispatchEvent(new MouseEvent('mouseup', {bubbles: true}));
                document.dispatchEvent(new MouseEvent('mouseup', {bubbles: true}));
            })()""")
        # The toolbar carries no "active" class: it comes and goes by visibility.
        if not page.wait("Array.from(document.querySelectorAll('.medium-editor-toolbar'))"
                         ".some(b => getComputedStyle(b).visibility === 'visible')", 8):
            print('   warning: the format toolbar never showed up')
        time.sleep(0.6)
        shot('richtext')
        close_note()

        print('the attachments')
        open_note('Attachments')
        time.sleep(1.5)
        shot('attachments')
        close_note()

        print('the share dialog, with the permissions menu open')
        open_note('Share with people and groups')
        page.evaluate("document.querySelector('#share-button').click()")
        if not page.wait("!!document.querySelector('.qn-share')", 15):
            raise SystemExit('the share dialog did not open')
        time.sleep(2.0)
        page.evaluate("""
            (() => {
                const row = document.querySelector('.qn-share__entry');
                const menu = row && row.querySelector('.action-item__menutoggle, button');
                if (menu) menu.click();
            })()""")
        time.sleep(1.5)
        shot('share')

    print('writing', out)
    for name, path in raw.items():
        picture = Image.open(path).convert('RGB')
        targets = [(f'{SHOTS[name]}.jpeg', FINAL)]
        if name == 'grid':
            targets.append((f'{SHOTS[name]}-small.jpeg', THUMBNAIL))
        for filename, size in targets:
            picture.resize(size, Image.LANCZOS).save(
                os.path.join(out, filename), 'JPEG', quality=90, optimize=True,
                progressive=True)
            print('  ', filename, '%dx%d' % size)
        os.remove(path)


if __name__ == '__main__':
    sys.exit(main())
