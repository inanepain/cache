# inanepain/cache
# version: $Id$
# date: $Date$

#***********************************************
# readme: example
# just readme clean;echo; sleep 2; just readme markdown
#***********************************************

set shell := ["zsh", "-cu"]
set positional-arguments

project := "inane\\cache"

# list recipes
_default:
    @echo "{{project}}:"
    @just --list --list-heading ''

# start
_start task='':
    @echo "{{project}}: {{GREEN}}start{{NORMAL}}: {{task}}"

# done
_done task='':
    @echo "{{project}}: {{GREEN}}done{{NORMAL}} {{task}}"

# git push all
[group: 'GIT']
git-push-all: (_start "Push All") && (_done "Push All")
    #!/usr/bin/env zsh
    git pushall

#*********************************************
#### PHP
##############################################
# generate php part (v2) (all, cache, html)
php-doc clear="all":
	#!/usr/bin/env zsh
	if [ -d .phpdoc ] && [[ "{{clear}}" = "all" || "{{clear}}" = "cache" ]]; then
		echo "\tCleaning: cache..."
		rm -fr .phpdoc
	fi
	if [ -d part/api ] && [[ "{{clear}}" = "all" || "{{clear}}" = "html" ]]; then
		echo "\tCleaning: html..."
		rm -fr part/api
	fi

	mkdir -p part/api
	phpdoc -d src -t part/api --title="{{project}}" --defaultpackagename="Inane"

#*********************************************
#### DOCUMENTATION: README
##############################################
# build: 1 - reduced adoc file
@_readme-reduce:
	echo "\tbuild: reduced"
	asciidoctor-reducer -o README.adoc part/readme/index.adoc

# build: 2 - pandoc xml file
@_readme-pandoc:
	echo "\tbuild: pandoc"
	asciidoctor -b docbook README.adoc

# build: 3 - markdown file
@_readme-markdown:
	echo "\tbuild: markdown"
	pandoc -f docbook -t markdown_strict README.xml -o README.md

# clean: readme files
@_readme-clean:
	echo "\tbuild: clean..."
	rm -vf README.{adoc,xml,md}
	echo "\tbuild: clean: done"

# build README files from part/readme/index.adoc (targets build required files if missing): clean, reduce, pandoc, markdown*
readme target="markdown":
	#!/usr/bin/env zsh
	[[ ! "{{target}}" = *"-v" ]] && echo "building: readme: {{target}}"

	if [[ "{{target}}" = "clean" ]]; then just _readme-clean
	elif [[ "{{target}}" = "reduce"* ]]; then
		if [[ -f doc/readme/index.adoc ]]; then just _readme-reduce; else echo "\tbuild: warn: missing: doc/readme/index.adoc (reduce)"; fi
	elif [[ "{{target}}" = "pandoc"* ]]; then
		if [[ ! -f README.adoc ]]; then
			# echo "\tbuild: warn: missing: README.adoc (pandoc)"
			echo "\twarn: missing: README.adoc\n\t\tadd task: reduce"
			just readme reduce-v
		fi
		just _readme-pandoc
	elif [[ "{{target}}" = "markdown"* ]]; then
		if [[ ! -f README.xml ]]; then
			# echo "\tbuild: warn: missing: README.xml (markdown)"
			echo "\twarn: missing: README.xml\n\t\tadd task: pandoc"
			just readme pandoc-v
		fi
		just _readme-markdown
	fi

	[[ ! "{{target}}" = *"-v" ]] && echo "build: done: {{target}}" || printf ""

#*********************************************
