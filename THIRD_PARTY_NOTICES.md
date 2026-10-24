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

Pages containing mathematics load MathJax 4.0.0 and its SVG font data from
jsDelivr. MathJax is distributed under the Apache License 2.0; see the
[upstream license](https://github.com/mathjax/MathJax/blob/4.0.0/LICENSE).
MathJax assets are fetched by the browser and are not bundled in this repository.

## PHP dependencies

Laravel and the other Composer dependencies retain their respective licenses.
`composer.lock` records their versions and license identifiers; Composer installs
their license files alongside the packages in `vendor/`.
