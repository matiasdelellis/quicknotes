# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
# @author Bernhard Posselt <dev@bernhard-posselt.com>
# @copyright Bernhard Posselt 2012, 2014

# Generic Makefile for building and packaging an ownCloud app which uses npm and
# Composer.
#
# Dependencies:
# * make
# * which
# * curl: used if phpunit and composer are not installed to fetch them from the web
# * tar: for building the archive
# * npm: for building and testing everything JS
#
# If no composer.json is in the app root directory, the Composer step
# will be skipped. The same goes for the package.json which can be located in
# the app root or the js/ directory.
#
# The npm command by launches the npm build script:
#
#    npm run build
#
# The npm test command launches the npm test script:
#
#    npm run test
#
# The idea behind this is to be completely testing and build tool agnostic. All
# build tools and additional package managers should be installed locally in
# your project, since this won't pollute people's global namespace.
#
# The following npm scripts in your package.json install and update the bower
# and npm dependencies and use gulp as build system (notice how everything is
# run from the node_modules folder):
#
#    "scripts": {
#        "test": "node node_modules/gulp-cli/bin/gulp.js karma",
#        "prebuild": "npm install && node_modules/bower/bin/bower install && node_modules/bower/bin/bower update",
#        "build": "node node_modules/gulp-cli/bin/gulp.js"
#    },

app_name=$(notdir $(CURDIR))
build_tools_directory=$(CURDIR)/build/tools
source_build_directory=$(CURDIR)/build/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

all: build

# Fetches the PHP and JS dependencies and compiles the JS. If no composer.json
# is present, the composer step is skipped, if no package.json or js/package.json
# is present, the npm step is skipped
.PHONY: build
build:
ifneq (,$(wildcard $(CURDIR)/composer.json))
	make composer
endif
ifneq (,$(wildcard $(CURDIR)/package.json))
	make npm
endif
ifneq (,$(wildcard $(CURDIR)/js/package.json))
	make npm
endif

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
	php $(build_tools_directory)/composer.phar update --prefer-dist
else
	composer install --prefer-dist
	composer update --prefer-dist
endif

# Installs npm dependencies
.PHONY: npm
npm:
ifeq (,$(wildcard $(CURDIR)/package.json))
	cd js && $(npm) run build
else
	npm run build
endif

deps:
	mkdir -p vendor
	rm vendor/*

	wget http://builds.handlebarsjs.com.s3.amazonaws.com/handlebars-v4.0.5.js
	mv handlebars-v4.0.5.js vendor/handlebars.js

	wget https://unpkg.com/isotope-layout@3/dist/isotope.pkgd.js
	mv isotope.pkgd.js vendor/

	wget https://github.com/yabwe/medium-editor/archive/master.zip -O medium-editor.zip
	unzip medium-editor.zip
	mv medium-editor-master/dist/js/medium-editor.js vendor/
	mv medium-editor-master/dist/css/medium-editor.css vendor/
	mv medium-editor-master/dist/css/themes/beagle.css vendor/
	rm -R medium-editor-master
	rm medium-editor.zip

	wget https://github.com/varun-raj/medium-editor-autolist/raw/master/dist/autolist.js
	mv autolist.js vendor/

depsmin:
	mkdir -p vendor
	rm vendor/*

	wget http://builds.handlebarsjs.com.s3.amazonaws.com/handlebars-v4.0.5.js
	mv handlebars-v4.0.5.js vendor/handlebars.js

	wget https://unpkg.com/isotope-layout@3/dist/isotope.pkgd.min.js
	mv isotope.pkgd.min.js vendor/isotope.pkgd.js

	wget https://github.com/yabwe/medium-editor/archive/master.zip -O medium-editor.zip
	unzip medium-editor.zip
	mv medium-editor-master/dist/js/medium-editor.min.js vendor/medium-editor.js
	mv medium-editor-master/dist/css/medium-editor.min.css vendor/medium-editor.css
	mv medium-editor-master/dist/css/themes/beagle.min.css vendor/beagle.css
	rm -R medium-editor-master
	rm medium-editor.zip

	wget https://github.com/varun-raj/medium-editor-autolist/raw/master/dist/autolist.min.js
	mv autolist.min.js vendor/autolist.js

# Removes the appstore build
.PHONY: clean
clean:
	rm -rf ./build
	rm -rf vendor/*

# Same as clean but also removes dependencies installed by composer, bower and
# npm
.PHONY: distclean
distclean: clean
	rm -rf vendor
	rm -rf node_modules
	rm -rf js/vendor
	rm -rf js/node_modules

# Builds the source and appstore package
.PHONY: dist
dist:
	make source
	make appstore

# Builds the source package
.PHONY: source
source:
	make build
	make test
	rm -rf $(source_build_directory)
	mkdir -p $(source_build_directory)
	tar cvzf $(source_package_name).tar.gz ../$(app_name) \
	--exclude-vcs \
	--exclude="../$(app_name)/build" \
	--exclude="../$(app_name)/js/node_modules" \
	--exclude="../$(app_name)/*.log" \
	--exclude="../$(app_name)/js/*.log" \

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore:
	make build
	make test
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	tar cvzf $(appstore_package_name).tar.gz ../$(app_name) \
	--exclude-vcs \
	--exclude="../$(app_name)/build" \
	--exclude="../$(app_name)/tests" \
	--exclude="../$(app_name)/Makefile" \
	--exclude="../$(app_name)/*.log" \
	--exclude="../$(app_name)/phpunit*xml" \
	--exclude="../$(app_name)/composer.*" \
	--exclude="../$(app_name)/js/node_modules" \
	--exclude="../$(app_name)/js/tests" \
	--exclude="../$(app_name)/js/test" \
	--exclude="../$(app_name)/js/*.log" \
	--exclude="../$(app_name)/js/package.json" \
	--exclude="../$(app_name)/js/bower.json" \
	--exclude="../$(app_name)/js/karma.*" \
	--exclude="../$(app_name)/js/protractor.*" \
	--exclude="../$(app_name)/package.json" \
	--exclude="../$(app_name)/bower.json" \
	--exclude="../$(app_name)/karma.*" \
	--exclude="../$(app_name)/protractor\.*" \
	--exclude="../$(app_name)/.*" \
	--exclude="../$(app_name)/js/.*" \

# Command for running JS and PHP tests. Works for package.json files in the js/
# and root directory. If phpunit is not installed systemwide, a copy is fetched
# from the internet
.PHONY: test
test:
ifneq (,$(wildcard $(CURDIR)/js/package.json))
	cd js && $(npm) run test
endif
ifneq (,$(wildcard $(CURDIR)/package.json))
	$(npm) run test
endif
ifeq (, $(shell which phpunit 2> /dev/null))
	@echo "No phpunit command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sSL https://phar.phpunit.de/phpunit.phar -o $(build_tools_directory)/phpunit.phar
	php $(build_tools_directory)/phpunit.phar -c phpunit.xml
	php $(build_tools_directory)/phpunit.phar -c phpunit.integration.xml
else
	phpunit -c phpunit.xml
	phpunit -c phpunit.integration.xml
endif