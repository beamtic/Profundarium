(() => {
    'use strict';
    const base = '/profundarium/assets/vendor/diagrams/';
    const loaded = new Map();
    const load = (file) => {
        if (!loaded.has(file)) {
            loaded.set(file, new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = base + file;
                script.onload = resolve;
                script.onerror = () => reject(new Error('Could not load diagram renderer.'));
                document.head.append(script);
            }));
        }
        return loaded.get(file);
    };
    let viz;
    let mermaidReady = false;
    const renderers = {
        async mermaid(source, target, id) {
            await load('mermaid.js');
            if (!mermaidReady) {
                window.mermaid.initialize({
                    startOnLoad: false, securityLevel: 'strict', theme: 'default',
                    fontFamily: 'Roboto, Arial, sans-serif',
                    flowchart: { htmlLabels: false, nodeSpacing: 40, rankSpacing: 60 },
                    gantt: {
                        barHeight: 32, barGap: 12, fontSize: 16,
                        sectionFontSize: 16, topPadding: 64, titleTopMargin: 24
                    },
                    sequence: { actorMargin: 60, messageMargin: 45, diagramMarginY: 24 },
                    suppressErrorRendering: true
                });
                mermaidReady = true;
            }
            // Layout must use the article container, not the browser viewport.
            const result = await window.mermaid.render(id, source, target);
            target.innerHTML = result.svg;
        },
        async graphviz(source, target) {
            await load('viz.js');
            viz ??= window.Viz.instance();
            target.append((await viz).renderSVGElement(source));
        },
        async abc(source, target) {
            await load('abcjs.js');
            const tunes = window.ABCJS.renderAbc(target, source, {
                add_classes: true,
                staffwidth: 700, paddingtop: 10, paddingbottom: 10
            });
            if (!tunes.length || !target.querySelector('svg')) throw new Error('Invalid ABC notation.');
            // abcjs reserves the original staff height on its output container.
            // Let the shared SVG styling determine its height after scaling.
            target.style.removeProperty('height');
            target.style.removeProperty('overflow');
        },
        async sequence(source, target) {
            await Promise.all([load('underscore.js'), load('snap.svg.js'), load('webfont.js')]);
            await load('sequence.js');
            await document.fonts.load('16px Roboto');
            const diagram = window.Diagram.parse(source);
            await new Promise((resolve, reject) => {
                const observer = new MutationObserver(() => {
                    if (target.querySelector('svg')) { clearTimeout(timer); observer.disconnect(); resolve(); }
                });
                const timer = setTimeout(() => { observer.disconnect(); reject(new Error('Sequence rendering timed out.')); }, 8000);
                observer.observe(target, { childList: true, subtree: true });
                try { diagram.drawSVG(target.id, { theme: 'simple', 'font-family': 'Roboto' }); }
                catch (error) { clearTimeout(timer); observer.disconnect(); reject(error); }
            });
        },
        async flow(source, target) {
            await load('raphael.js');
            await load('flowchart.js');
            window.flowchart.parse(source).drawSVG(target.id, {
                'font-family': 'Roboto, Arial, sans-serif',
                'font-size': 14, 'line-width': 2, fill: '#f0f5fa'
            });
        }
    };
    const figures = [...document.querySelectorAll('figure[data-diagram]')];
    // Exposed readiness lets browser tests and print automation await all diagrams.
    window.profundariumDiagramsReady = (async () => {
        if (!figures.length) return;
        try { await load('purify.js'); } catch { return; }
        // Sequential rendering avoids global drawing-library state collisions.
        for (const [index, figure] of figures.entries()) {
            const source = figure.querySelector('.diagram-source');
            const type = figure.dataset.diagram;
            if (!source || !renderers[type]) continue;
            const target = document.createElement('div');
            target.id = 'profundarium-diagram-' + index;
            target.className = 'diagram-output';
            figure.append(target);
            try {
                if (source.textContent.length > 100000) throw new Error('Diagram is too large.');
                await renderers[type](source.textContent, target, target.id + '-svg');
                if (!target.querySelector('svg')) throw new Error('No diagram was produced.');
                // Remove active markup from renderer output and external SVG resources.
                const clean = window.DOMPurify.sanitize(target.innerHTML, {
                    USE_PROFILES: { svg: true, svgFilters: true },
                    FORBID_TAGS: ['foreignObject', 'image', 'script', 'a']
                });
                target.innerHTML = clean;
                for (const element of target.querySelectorAll('*')) {
                    for (const attribute of [...element.attributes]) {
                        if (/(?:^|:)href$/i.test(attribute.name) && !attribute.value.startsWith('#')) {
                            element.removeAttribute(attribute.name);
                        }
                    }
                }
                for (const style of target.querySelectorAll('style')) {
                    style.textContent = style.textContent.replace(/@import[^;]*;?/gi, '')
                        .replace(/url\((?![\s'"]*#)[^)]*\)/gi, 'none');
                }
                for (const svg of target.querySelectorAll('svg')) {
                    if (!svg.hasAttribute('viewBox')) {
                        const width = parseFloat(svg.getAttribute('width'));
                        const height = parseFloat(svg.getAttribute('height'));
                        if (width > 0 && height > 0) svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
                    }
                    svg.setAttribute('role', 'img');
                    svg.setAttribute('aria-label', type + ' diagram');
                    const width = svg.viewBox.baseVal.width;
                    if (width > 0) svg.setAttribute('width', width);
                    svg.removeAttribute('height');
                    // abcjs uses an absolute-positioned SVG inside its own wrapper;
                    // the shared output container uses the SVG's natural dimensions.
                    svg.style.position = 'static';
                    svg.style.maxWidth = '100%';
                    svg.style.removeProperty('left');
                    svg.style.removeProperty('top');
                }
                figure.classList.add('diagram-rendered');
                source.hidden = true;
            } catch {
                target.remove();
                const error = document.createElement('figcaption');
                error.className = 'diagram-error';
                error.textContent = 'This diagram could not be rendered. Its source is shown below.';
                figure.prepend(error);
            }
        }
    })();
})();
