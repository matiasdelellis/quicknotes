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
build: deps

# general
deps:
	mkdir -p vendor
	rm -rf vendor/*
	npm i
	cp node_modules/handlebars/dist/handlebars.js vendor/
	cp node_modules/isotope-layout/dist/isotope.pkgd.js vendor/
	cp node_modules/medium-editor/dist/js/medium-editor.js vendor/
	cp node_modules/medium-editor/dist/css/medium-editor.css vendor/
	cp node_modules/medium-editor/dist/css/themes/beagle.css vendor/
	cp node_modules/medium-editor-autolist/dist/autolist.js vendor/

depsmin:
	mkdir -p vendor
	rm -rf vendor/*
	npm i
	cp node_modules/handlebars/dist/handlebars.min.js vendor/
	cp node_modules/isotope-layout/dist/isotope.pkgd.min.js vendor/
	cp node_modules/medium-editor/dist/js/medium-editor.min.js vendor/
	cp node_modules/medium-editor/dist/css/medium-editor.min.css vendor/
	cp node_modules/medium-editor/dist/css/themes/beagle.min.css vendor/
	cp node_modules/medium-editor-autolist/dist/autolist.min.js vendor/

js-templates:
	node_modules/handlebars/bin/handlebars js/templates -f js/templates.js

clean:
	rm -rf $(build_dir)

distclean: clean
	rm -rf vendor
	rm -rf node_modules
	rm -rf js/vendor
	rm -rf js/node_modules

dist:  appstore

appstore: distclean depsmin
	mkdir -p $(sign_dir)
	rsync -a \
	    --exclude=.git \
	    --exclude=build \
	    --exclude=.gitignore \
	    --exclude=.travis.yml \
	    --exclude=.scrutinizer.yml \
	    --exclude=CONTRIBUTING.md \
	    --exclude=composer.json \
	    --exclude=composer.lock \
	    --exclude=composer.phar \
	    --exclude=.tx \
	    --exclude=l10n/no-php \
	    --exclude=Makefile \
	    --exclude=nbproject \
	    --exclude=screenshots \
	    --exclude=phpunit*xml \
	    --exclude=tests \
	    --exclude=vendor/bin \
	    --exclude=js/node_modules \
	    --exclude=js/tests \
	    --exclude=js/karma.conf.js \
	    --exclude=js/gulpfile.js \
	    --exclude=js/bower.json \
	    --exclude=js/package.json \
	$(project_dir) $(sign_dir)
	@echo "Signing…"
	php ../../occ integrity:sign-app \
	    --privateKey=$(cert_dir)/$(app_name).key\
	    --certificate=$(cert_dir)/$(app_name).crt\
	    --path=$(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name).tar.gz \
	    -C $(sign_dir) $(app_name)
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name).tar.gz | openssl base64