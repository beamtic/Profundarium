# Third-party notices

Original Profundarium code is licensed under GPL-3.0-or-later; see
[LICENSE](LICENSE) and the README's license section. This grant does not
relicense the third-party material listed below.

## HedgeDoc

Parts of `public/profundarium/assets/css/notes.css` adapt the TOC rules from HedgeDoc's
[`extra.css`](https://github.com/Turbulentarius/hedgedoc/blob/9a493dbd38fef038ce9d95ae0009c8d026035547/public/css/extra.css)
and [`site.css`](https://github.com/Turbulentarius/hedgedoc/blob/72337e7f8fa406609c877c2a7f4da00f53a6dd98/public/css/site.css).
The adaptations target Profundarium's note markup and combine those rules with
its application stylesheet. Credit belongs to the HedgeDoc contributors.

The upstream repository supplies the GNU Affero General Public License,
version 3. A copy is included in [licenses/HedgeDoc-AGPL-3.0.txt](licenses/HedgeDoc-AGPL-3.0.txt).
Retain these notices and the applicable license when redistributing the adapted code.

## Roboto

The locally hosted Roboto fonts are distributed under the SIL Open Font License
1.1. Their copyright notice and license are in
[public/profundarium/assets/fonts/roboto/OFL.txt](public/profundarium/assets/fonts/roboto/OFL.txt), with source and format
information in [public/profundarium/assets/fonts/roboto/README.txt](public/profundarium/assets/fonts/roboto/README.txt).

## MathJax

`public/profundarium/assets/vendor/mathjax/` contains MathJax 4.0.0's SVG
bundle, supporting TeX/output/accessibility components, and speech data, plus
the SVG data from `@mathjax/mathjax-newcm-font` 4.0.0 and the matching
`mathjax-mhchem`, `mathjax-bbm`, `mathjax-bboldx`, and `mathjax-dsfont`
font-extension packages (all 4.0.0). These are served locally,
including dynamically loaded font data and TeX extensions.

Sources: the versioned npm distributions
[`mathjax@4.0.0`](https://www.npmjs.com/package/mathjax/v/4.0.0) and
[`@mathjax/mathjax-newcm-font@4.0.0`](https://www.npmjs.com/package/@mathjax/mathjax-newcm-font/v/4.0.0).
The font-extension distributions are also published under the `@mathjax` npm
scope with the `-font-extension` suffix. All these packages declare Apache-2.0 licensing. The MathJax license is included at
[public/profundarium/assets/vendor/mathjax/LICENSE](public/profundarium/assets/vendor/mathjax/LICENSE).

## Emoji mapping

`resources/markdown/emoji.json` is converted from the full mapping in
[markdown-it-emoji 3.0.0](https://github.com/markdown-it/markdown-it-emoji/blob/3.0.0/lib/data/full.mjs).
To reproduce it, remove the leading comment and `export default` from that file
and serialize the remaining object as JSON. The mapping is distributed under
the MIT license, included in [licenses/markdown-it-emoji-MIT.txt](licenses/markdown-it-emoji-MIT.txt).

## Pygments

`public/profundarium/assets/css/pygments.css` is generated with Pygments 2.20.0:
`pygmentize -S default -f html -a '.note .pygments'`. Unscoped line-number
styles are omitted, and print color preservation is added.
Pygments is distributed under the BSD license included in
[licenses/Pygments-BSD.txt](licenses/Pygments-BSD.txt). The container installs
Alpine's `py3-pygments` package for runtime highlighting.

## PHP dependencies

Laravel and the other Composer dependencies retain their respective licenses.
`composer.lock` records their versions and license identifiers; Composer installs
their license files alongside the packages in `vendor/`.

## Diagram rendering

`public/profundarium/assets/vendor/diagrams/` contains pinned browser builds
of Mermaid, Viz.js (including Graphviz and Expat), abcjs, js-sequence-diagrams,
flowchart.js, Underscore, Snap.svg, Raphael, Web Font Loader, and DOMPurify.
`manifest.json` in that directory records versions and upstream sources;
license files and bundled notices are retained alongside the scripts.

These assets and their dependencies are served from Profundarium's own origin.
Web Font Loader is used only to detect the locally hosted Roboto font, without
external font-provider configuration. Diagram source remains visible if a
renderer cannot load or rejects the input.
