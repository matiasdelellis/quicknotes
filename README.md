# Quick notes

The Quick notes application is a tool to take quick notes (forgive the
redundancy :sweat_smile:) on small cards, organized by colors and tags. It
supports basic rich text formatting such as **bold**, *italic*, and numbered or
bullet lists to enhance your notes. It also allows attaching files, and the
notes can be shared as read-only with other users.

A note can also carry a reminder: Nextcloud sends you a notification when the
date arrives. If you turn it on in the app settings, the notes with a reminder
are published as a read-only calendar too, so they show up in the Calendar app
and in any CalDAV client you use.

![Screenshot of Quick notes](/doc/quicknotes-grid-view.jpeg "App screenshot")

## :heart: Support

If you'd like to support the creation and maintenance of this software, please
consider donating.

[![Donate](https://img.shields.io/badge/Donate-PayPal-blue)](https://github.com/matiasdelellis/quicknotes/wiki/Donate)
[![Donate](https://img.shields.io/badge/Donate-Bitcoin-orange)](https://github.com/matiasdelellis/quicknotes/wiki/Donate)
[![Donate](https://img.shields.io/badge/Donate-Ethereum-blueviolet)](https://github.com/matiasdelellis/quicknotes/wiki/Donate)

## :rocket: Installation

Quick notes is available in the Nextcloud App Store and can be installed
directly from your Nextcloud instance by browsing to the Office category.

Nextcloud will notify you about available updates. Please have a look at
[CHANGELOG.md](CHANGELOG.md) for details about changes.

## :exclamation: Bugs

Before reporting bugs:

* Make sure you are running the latest version of the Quick notes app.
* Consider also installing the [latest development version](https://github.com/matiasdelellis/quicknotes.git).
* [Check whether the issue has already been reported](https://github.com/matiasdelellis/quicknotes/issues).

## Building the app

1. Clone this repository into your Nextcloud `apps` folder: `git clone https://github.com/matiasdelellis/quicknotes.git`.
2. In a terminal, run `make` to install the dependencies and build the application.
3. Enable the app from the Apps management page of your Nextcloud instance.

## Local development with Docker

A self-contained Docker environment is provided under the [`docker/`](docker/)
folder. See [docker/README.md](docker/README.md) for details on how to spin up
a Nextcloud + MariaDB stack with this app mounted as `custom_apps/quicknotes`.

## Translating

Join the project on https://www.transifex.com/matias/quicknotes/
