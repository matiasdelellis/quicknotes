#
# Nextcloud scaffolder tool
#
# Copyright (C) 2013 Bernhard Posselt, <nukewhale@gmail.com>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

# Makefile for building the project
app_name=quicknotes
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
sign_dir=$(build_dir)/sign
appstore_dir=$(build_dir)/appstore
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates

# building the javascript

all: build

# L10N Rules

l10n-update-pot:
	php translationtool.phar create-pot-files

l10n-transifex-pull:
	tx pull -s -a

l10n-transifex-push:
	tx push -s -t

l10n-transifex-apply:
	php translationtool.phar convert-po-files

l10n-clean:
	rm -rf translationfiles
	rm -f translationtool.phar

l10n-deps:
	@echo "Checking transifex client."
	tx --version
	@echo "Downloading translationtool.phar"
	wget https://github.com/nextcloud/docker-ci/raw/master/translations/translationtool/translationtool.phar -O translationtool.phar


# general
deps:
	mkdir -p vendor
	rm -rf vendor/*
	npm i
	cp node_modules/handlebars/dist/handlebars.js vendor/
	cp node_modules/isotope-layout/dist/isotope.pkgd.js vendor/
	cp node_modules/medium-editor/dist/js/medium-editor.js vendor/
	cp node_modules/medium-editor/dist/css/medium-editor.css vendor/
	cp node_modules/medium-editor-autolist/dist/autolist.js vendor/
	cp node_modules/lozad/dist/lozad.js vendor/

depsmin:
	mkdir -p vendor
	rm -rf vendor/*
	npm i
	cp node_modules/handlebars/dist/handlebars.min.js vendor/handlebars.js
	cp node_modules/isotope-layout/dist/isotope.pkgd.min.js vendor/isotope.pkgd.js
	cp node_modules/medium-editor/dist/js/medium-editor.min.js vendor/medium-editor.js
	cp node_modules/medium-editor/dist/css/medium-editor.min.css vendor/medium-editor.css
	cp node_modules/medium-editor-autolist/dist/autolist.min.js vendor/autolist.js
	cp node_modules/lozad/dist/lozad.min.js vendor/lozad.js


# Build Rules
build-vue:
	npm run build

js-templates:
	node_modules/handlebars/bin/handlebars js/templates -f js/templates.js

build: depsmin js-templates build-vue
	@echo ""
	@echo "Build done. You can enable the application in Nextcloud."

# Clean
clean:
	rm -rf $(build_dir)

distclean: clean
	rm -rf vendor
	rm -rf node_modules
	rm -rf js/vendor
	rm -rf js/node_modules

dist:  appstore

appstore: distclean build
	mkdir -p $(sign_dir)
	rsync -a \
	    --exclude='.*' \
	    --exclude=build \
	    --exclude=CONTRIBUTING.md \
	    --exclude=composer* \
	    --exclude=doc \
	    --exclude=Makefile \
	    --exclude=package*json \
	    --exclude=l10n/no-php \
	    --exclude=phpunit*xml \
	    --exclude=tests \
	    --exclude=vendor/bin \
	    --exclude=node_modules \
	    --exclude=js/templates \
	    --exclude=src \
	    --exclude=templates/fake.php \
	    --exclude=translation* \
	    --exclude=webpack*.js \
	    --exclude=*.js.map \
	    --exclude=psalm.xml \
	$(project_dir) $(sign_dir)
	@echo "Signing…"
	php ../../occ integrity:sign-app \
	    --privateKey=$(cert_dir)/$(app_name).key\
	    --certificate=$(cert_dir)/$(app_name).crt\
	    --path=$(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name).tar.gz \
	    -C $(sign_dir) $(app_name)
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name).tar.gz | openssl base64