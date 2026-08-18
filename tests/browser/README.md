# Browser scripts

Two scripts that drive a real, headless Chrome against a running instance
through the DevTools protocol. They are tools, not a test suite: `phpunit`
never sees this folder, and nothing here runs on its own.

What they exist for is the App Store listing. The pictures in
`appinfo/info.xml` are of a board whose every card explains one feature of the
app, and they have to be taken again whenever the interface changes.

## What you need

* The docker instance of `docker/`, up and with the app enabled.
* `google-chrome` on the PATH.
* `pip install websocket-client pillow`.
* Two accounts with the same password and a group, made once:

      occ user:add demo && occ user:add emma
      occ group:add Team && occ group:adduser Team emma
      occ user:setting demo core lang en

  The display names are what the share dialog shows, so give them one through
  the provisioning API — `occ user:setting … displayname` does not write it:

      curl -u admin:… -H 'OCS-APIRequest: true' -X PUT \
           http://localhost:8080/ocs/v2.php/cloud/users/emma \
           -d key=displayname --data-urlencode 'value=Emma Ruiz'

## Taking the pictures

    export QN_PASSWORD=…                       # of both accounts
    python3 tests/browser/seed_demo.py         # builds the board
    python3 tests/browser/screenshots.py ../matiasdelellis.github.io/img/quicknotes

`seed_demo.py` deletes every note the two accounts own before building its
own, so point it at a throwaway instance. `screenshots.py` writes the six
files the listing points at, at the sizes it expects.

The images live in the site repository, not here — they are heavy, and every
new version would leave the old one behind in the history of the app.

## Settings

| Variable | Default | |
| --- | --- | --- |
| `QN_URL` | `http://localhost:8080` | the instance |
| `QN_USER` | `demo` | whose board is photographed |
| `QN_PASSWORD` | — | required |
| `QN_OTHER_USER` | `emma` | shares a note with the first one |
| `QN_GROUP` | `Team` | the group share of the sharing card |
| `QN_REMINDER` | `2026-08-24 12:00:00` | the date on the reminder card |

## cdp.py

The bit worth reusing: launching Chrome on a throwaway profile, opening a page
as somebody, waiting for a condition, evaluating JavaScript and taking a
screenshot. Two things it does that are not obvious, and that anything else
driving this app will want as well:

* it emulates the focus, because a headless page never has it and MediumEditor
  decides whether to show its toolbar by looking at the focused element;
* it sets the device metrics, because the height of `--window-size` counts the
  browser chrome and the picture comes out shorter than asked otherwise.
