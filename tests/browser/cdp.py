#!/usr/bin/env python3
"""A small Chrome DevTools Protocol harness.

Enough of CDP to drive the app in a headless Chrome and look at what came
out: launch the browser on a throwaway profile, open a page authenticated as
somebody, wait for things, evaluate JavaScript and take a screenshot.

Needs `google-chrome` on the PATH and the `websocket-client` package.
"""

import base64
import json
import os
import shutil
import subprocess
import tempfile
import time
import urllib.request

import websocket


DEFAULT_URL = os.environ.get('QN_URL', 'http://localhost:8080')


def credentials():
    """The account the scripts act as. The password never lives in the repo."""
    user = os.environ.get('QN_USER', 'demo')
    password = os.environ.get('QN_PASSWORD')
    if not password:
        raise SystemExit('Set QN_PASSWORD (and QN_USER, if it is not "demo")')
    return user, password


def basic_auth(user, password):
    return 'Basic ' + base64.b64encode(f'{user}:{password}'.encode()).decode()


class Page:
    """One tab, talked to over its websocket."""

    def __init__(self, url):
        self._ws = websocket.create_connection(url, timeout=30)
        self._id = 0

    def send(self, method, **params):
        self._id += 1
        self._ws.send(json.dumps({'id': self._id, 'method': method, 'params': params}))
        while True:
            answer = json.loads(self._ws.recv())
            if answer.get('id') == self._id:
                if 'error' in answer:
                    raise RuntimeError(answer['error'])
                return answer.get('result', {})

    def evaluate(self, expression):
        result = self.send('Runtime.evaluate', expression=expression,
                           awaitPromise=True, returnByValue=True)
        if result.get('exceptionDetails'):
            raise RuntimeError(json.dumps(result['exceptionDetails'])[:400])
        return result['result'].get('value')

    def wait(self, expression, timeout=25):
        """Poll an expression until it is true. Answers whether it ever was."""
        deadline = time.time() + timeout
        while time.time() < deadline:
            if self.evaluate(expression):
                return True
            time.sleep(0.3)
        return False

    def screenshot(self, path):
        data = self.send('Page.captureScreenshot', format='png')['data']
        with open(path, 'wb') as f:
            f.write(base64.b64decode(data))
        return path


class Chrome:
    """A headless Chrome, and the one page these scripts work with.

    Use it as a context manager: it takes the browser and the temporary
    profile down on the way out.
    """

    def __init__(self, width=1366, height=768, scale=1, port=9222):
        self.width, self.height, self.scale = width, height, scale
        self._port = port
        self._profile = tempfile.mkdtemp(prefix='qn-cdp-')
        # --password-store=basic keeps Chrome from asking the keyring for the
        # login it has no business needing here.
        self._chrome = subprocess.Popen([
            'google-chrome', '--headless=new', '--password-store=basic',
            f'--remote-debugging-port={port}', '--remote-allow-origins=*',
            f'--user-data-dir={self._profile}', '--no-first-run',
            '--no-default-browser-check', '--disable-gpu', '--hide-scrollbars',
            f'--window-size={width},{height}', 'about:blank',
        ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.page = self._open_page()

    def _endpoint(self):
        for _ in range(60):
            try:
                with urllib.request.urlopen(f'http://127.0.0.1:{self._port}/json/version') as r:
                    return json.load(r)['webSocketDebuggerUrl']
            except Exception:
                time.sleep(0.5)
        raise SystemExit('Chrome never answered on the debugging port')

    def _open_page(self):
        browser = Page(self._endpoint())
        target = browser.send('Target.createTarget', url='about:blank')['targetId']
        with urllib.request.urlopen(f'http://127.0.0.1:{self._port}/json/list') as r:
            tab = next(t for t in json.load(r) if t['id'] == target)
        page = Page(tab['webSocketDebuggerUrl'])
        page.send('Page.enable')
        page.send('Runtime.enable')
        page.send('Network.enable')
        # A headless page never has the focus, and MediumEditor leans on the
        # focused element to decide whether to show its toolbar at all.
        page.send('Emulation.setFocusEmulationEnabled', enabled=True)
        # The height of --window-size counts the browser chrome, so the picture
        # comes out shorter than asked unless the metrics are set as well.
        page.send('Emulation.setDeviceMetricsOverride', width=self.width,
                  height=self.height, deviceScaleFactor=self.scale, mobile=False)
        return page

    def visit(self, path, user=None, password=None):
        """Open a page of the instance, logged in as somebody."""
        if user:
            self.page.send('Network.setExtraHTTPHeaders',
                           headers={'Authorization': basic_auth(user, password)})
        self.page.send('Page.navigate', url=DEFAULT_URL + path)
        self.page.wait("document.readyState === 'complete'")
        # From here on it rides on the cookies, like a real session.
        self.page.send('Network.setExtraHTTPHeaders', headers={})

    def dismiss_first_run_wizard(self):
        for _ in range(6):
            state = self.page.evaluate("""
                (() => {
                    const wizard = document.querySelector('.modal-mask, #firstrunwizard');
                    if (!wizard) return 'gone';
                    const close = wizard.querySelector('.modal-container__close');
                    if (close) { close.click(); return 'closed'; }
                    wizard.remove();
                    return 'removed';
                })()""")
            time.sleep(0.5)
            if state == 'gone':
                return

    def close(self):
        self._chrome.terminate()
        shutil.rmtree(self._profile, ignore_errors=True)

    def __enter__(self):
        return self

    def __exit__(self, *_):
        self.close()
